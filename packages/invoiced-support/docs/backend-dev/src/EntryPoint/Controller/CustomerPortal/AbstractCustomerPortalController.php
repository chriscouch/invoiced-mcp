<?php

namespace App\EntryPoint\Controller\CustomerPortal;

use App\AccountsReceivable\Libs\DocumentViewTracker;
use App\AccountsReceivable\Models\Customer;
use App\AccountsReceivable\Models\Invoice;
use App\AccountsReceivable\Models\ReceivableDocument;
use App\AccountsReceivable\Traits\CustomerPortalViewVariablesTrait;
use App\Core\Authentication\Libs\UserContext;
use App\Core\Files\Exception\UploadException;
use App\Core\Files\Libs\AttachmentUploader;
use App\Core\Files\Models\Attachment;
use App\Core\Orm\Query;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\InfuseUtility as Utility;
use App\CustomerPortal\Enums\CustomerPortalEvent;
use App\CustomerPortal\Libs\CustomerPortalContext;
use App\CustomerPortal\Libs\CustomerPortalEvents;
use App\CustomerPortal\Libs\CustomerPortalSecurityChecker;
use App\Sending\Email\Interfaces\EmailBodyStorageInterface;
use App\Sending\Email\Libs\CommentEmailWriter;
use App\Sending\Email\Models\InboxEmail;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Connection;
use EmailReplyParser\EmailReplyParser;
use Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

abstract class AbstractCustomerPortalController extends AbstractController implements LoggerAwareInterface, StatsdAwareInterface
{
    use LoggerAwareTrait;
    use StatsdAwareTrait;
    use CustomerPortalViewVariablesTrait;

    public function __construct(
        protected CustomerPortalContext $customerPortalContext,
        private UrlGeneratorInterface $urlGenerator,
        protected CustomerPortalSecurityChecker $securityChecker,
        private string $appProtocol,
    ) {
    }

    /**
     * Generates a URL (path only) to a given customer portal route.
     */
    protected function generatePortalContextUrl(string $route, array $parameters = []): string
    {
        $portal = $this->customerPortalContext->getOrFail();

        return $this->generatePortalUrl($portal, $route, $parameters);
    }

    /**
     * Redirects to the login page.
     */
    protected function redirectToLogin(Request $request): RedirectResponse
    {
        $response = new RedirectResponse($this->generatePortalContextUrl('customer_portal_login_form'));

        // Redirect to the same URL after login
        $uri = $request->getUri();
        $cookie = $this->makeCookie('redirect_after_login', $uri, 0);
        $response->headers->setCookie($cookie);

        return $response;
    }

    /**
     * Creates a cookie for use in a customer portal response.
     */
    protected function makeCookie(string $name, string $value, int $expire): Cookie
    {
        $secure = 'https' == $this->appProtocol;

        return new Cookie($name, $value, $expire, '/', '', $secure, true, false, $secure ? Cookie::SAMESITE_NONE : Cookie::SAMESITE_LAX);
    }

    /**
     * Creates a cookie that will delete an existing cookie.
     */
    protected function clearCookie(string $name): Cookie
    {
        return $this->makeCookie($name, '', 1);
    }

    /**
     * Checks if the viewer has permission when the
     * "Require Authentication" setting is enabled.
     */
    protected function mustLogin(Customer $customer, Request $request): RedirectResponse|null
    {
        $portal = $this->customerPortalContext->getOrFail();
        if ($portal->requiresAuthentication() && !$this->securityChecker->canAccessCustomer($customer)) {
            return $this->redirectToLogin($request);
        }

        return null;
    }

    /**
     * @throws UploadException
     */
    protected function sendMessage(ReceivableDocument $document, string $emailAddress, string $text, ?UploadedFile $file, AttachmentUploader $uploader, CommentEmailWriter $emailWriter, CustomerPortalEvents $events, EmailBodyStorageInterface $storage): ?array
    {
        // process the file
        $files = [];
        if ($file) {
            $error = $file->getError();
            $size = $file->getSize();
            $upldTmpName = $file->getPathname();

            if (0 !== $error || $size <= 0 || !$upldTmpName) {
                throw new Exception('We had trouble parsing your file upload.');
            }

            $temp = $uploader->moveUploadedFile($upldTmpName);
            $files[] = $uploader->upload($temp, $file->getClientOriginalName());
        }

        // post it to the A/R inbox as an email
        $email = $emailWriter->write($document, true, $emailAddress, $text, $files);

        // track the event
        $events->track($document->customer(), CustomerPortalEvent::SendMessage);
        $this->statsd->increment('billing_portal.send_message');

        // mark invoices as needs attention
        if ($document instanceof Invoice && !$document->needs_attention) {
            $document->needs_attention = true;
            $document->save();
        }

        return $email ? $this->expandInboxEmail($email, $document, $text, $storage) : null;
    }

    private function expandInboxEmail(InboxEmail $email, ReceivableDocument $document, ?string $text, EmailBodyStorageInterface $storage): array
    {
        $result = $email->toArray();
        if (!$text) {
            $text = $storage->retrieve($email, EmailBodyStorageInterface::TYPE_PLAIN_TEXT);
            $text = EmailReplyParser::parseReply((string) $text);
        }
        $result['text'] = $text;

        if ($email->incoming) {
            $result['name'] = $document->customer()->name;
            $result['from_customer'] = true;
        } else {
            $result['from_customer'] = false;
            $result['name'] = $email->tenant()->name;
        }

        $createdAt = $result['created_at'] - 1;
        $result['when'] = Utility::timeAgo($createdAt);

        $attachments = Attachment::allForObject($email);

        $result['attachments'] = [];
        foreach ($attachments as $attachment) {
            $attachment = $attachment->toArray();
            $size = $attachment['file']['size'];
            $attachment['size'] = Utility::numberAbbreviate($size).'B';
            $result['attachments'][] = $attachment;
        }

        return $result;
    }

    protected function addSearchToQuery(Query $query, Request $request): void
    {
        if ($search = $request->query->get('search')) {
            /** @var Connection $database */
            $database = Invoice::getDriver()->getConnection(null);
            $quotedQuery = $database->quote('%'.$search.'%');
            $query->where('(number LIKE '.$quotedQuery.' OR purchase_order LIKE '.$quotedQuery.')');
        }
    }

    protected function addDateRangeToQuery(Query $query, Request $request): void
    {
        // Dates will be in ISO-8601 date-only format
        if ($start = $request->query->get('start_date')) {
            $startDate = (new CarbonImmutable($start))->startOfDay();
            $query->where('date', $startDate->getTimestamp(), '>=');
        }

        if ($end = $request->query->get('end_date')) {
            $endDate = (new CarbonImmutable($end))->endOfDay();
            $query->where('date', $endDate->getTimestamp(), '<=');
        }
    }

    protected function parseFilterParams(Request $request, string $base, int $total, string $defaultSort): array
    {
        $perPage = $request->query->getInt('per_page', 10);
        $numPages = ceil($total / $perPage);

        $page = max(1, (int) $request->query->get('page'));
        $sort = (string) $request->query->get('sort');
        $sort = $this->parseSort($sort) ?? $defaultSort;

        $params = $request->query->all();
        $prevPage = null;
        if ($page > 1) {
            $params['page'] = $page - 1;
            $prevPage = $base.'?'.http_build_query($params);
        }

        $nextPage = null;
        if ($page < $numPages) {
            $params['page'] = $page + 1;
            $nextPage = $base.'?'.http_build_query($params);
        }

        $params = $request->query->all();
        unset($params['sort']);
        unset($params['page']);
        $sortBase = $base.'?'.http_build_query($params);

        $params = $request->query->all();
        unset($params['per_page']);
        unset($params['page']);
        $perPageBase = $base.'?'.http_build_query($params);

        return [
            'page' => $page,
            'perPage' => $perPage,
            'prevPage' => $prevPage,
            'nextPage' => $nextPage,
            'numPages' => $numPages,
            'sort' => $sort,
            'sortBase' => $sortBase,
            'perPageBase' => $perPageBase,
        ];
    }

    private function parseSort(string $sort): ?string
    {
        if (preg_match('/^\\w+ (ASC|DESC)$/', $sort)) {
            return $sort;
        }

        return null;
    }

    protected function trackDocumentView(ReceivableDocument $document, Request $request, UserContext $userContext, DocumentViewTracker $documentViewTracker): void
    {
        if ($request->query->get('noview')) {
            return;
        }

        $company = $document->tenant();
        $user = $userContext->get();
        if (!$user || !$company->isMember($user)) {
            $documentViewTracker->addView($document, $request->headers->get('User-Agent'), $request->getClientIp());
        }
    }
}
