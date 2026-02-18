<?php

namespace App\Sending\Email\InboundParse\Handlers;

use App\AccountsReceivable\Models\Invoice;
use App\Companies\Models\Company;
use App\Core\Authentication\Libs\UserContext;
use App\Core\Authentication\Models\User;
use App\Core\Files\Exception\UploadException;
use App\Core\Files\Libs\DocumentPdfUploader;
use App\Core\Multitenant\TenantContext;
use App\Core\Orm\ACLModelRequester;
use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Core\Utils\InfuseUtility as Utility;
use App\Sending\Email\Exceptions\InboundParseException;
use App\Sending\Email\InboundParse\Router;
use Symfony\Component\HttpFoundation\Request;

/**
 * This handler takes an inbound email message
 * and attaches the invoice PDF to an existing
 * invoice in the system.
 *
 * The email must have at least one PDF attachment
 * with the filename of the attachment set to:
 *   {invoice_number}.pdf
 */
class ImportInvoicePdfHandler implements HandlerInterface, StatsdAwareInterface
{
    use StatsdAwareTrait;
    private Company $company;

    public function __construct(
        private UserContext $userContext,
        private TenantContext $tenant,
        private DocumentPdfUploader $pdfUploader,
        private string $projectDir,
        private string $inboundEmailDomain,
    ) {
    }

    public function supports(string $to): bool
    {
        // This handles invoice imports in the format:
        // invimport+{company_username}@$this->inboundEmailDomain
        if (str_starts_with($to, 'invimport')) {
            $parsed = $this->parsePossibleInvoicePdfAddress($to);

            $username = $parsed[1];
            $company = Company::where('username', $username)->oneOrNull();
            if (!$company) {
                throw new InboundParseException('No such company: '.$username);
            }

            $this->setCompany($company);

            return true;
        }

        return false;
    }

    public function setCompany(Company $company): void
    {
        $this->company = $company;
    }

    public function processEmail(Request $request): void
    {
        // IMPORTANT: set the current tenant to enable multitenant operations
        $this->tenant->set($this->company);
        ACLModelRequester::set($this->company);

        // try to find the user this is from
        $from = Router::getAddressRfc822((string) $request->request->get('from'));
        $user = User::where('email', $from)->oneOrNull();
        if (!$user) {
            $user = new User(['id' => User::INVOICED_USER]);
        }
        $this->userContext->set($user);

        // process the attachments
        if ($request->request->has('attachment-info')) {
            $tempDir = $this->projectDir.'/var/uploads';
            if (!is_dir($tempDir)) {
                @mkdir($tempDir, 0774);
            }
            $attachmentInfo = json_decode((string) $request->request->get('attachment-info'));

            foreach ($attachmentInfo as $key => $entry) {
                $file = $request->files->get($key);
                if (!$file) {
                    continue;
                }

                $filename = $file->getClientOriginalName();

                // look up the invoice based on the filename which
                // should be: {invoice_number}.pdf
                $invoiceNumber = $this->parseInvoiceNumber($filename);
                $invoice = Invoice::where('number', $invoiceNumber)->oneOrNull();
                if (!$invoice) {
                    continue;
                }

                // move the file to a temporary location
                $tmpName = Utility::guid(false);
                $newFile = $file->move($tempDir, $tmpName);

                // upload it and create a file object
                try {
                    $fileObject = $this->pdfUploader->upload($newFile->getPathname(), $filename);
                } catch (UploadException $e) {
                    throw new InboundParseException($e->getMessage(), $e->getCode(), $e);
                }

                $this->pdfUploader->attachToDocument($invoice, $fileObject);

                // issue the invoice, if needed
                if ($invoice->draft) {
                    $invoice->draft = false;
                    $invoice->saveOrFail();
                }

                $this->statsd->increment('inbound_parse.pdf_import');
            }
        }
    }

    public function parseInvoiceNumber(string $filename): ?string
    {
        if (preg_match('/^(Invoice\s|Invoice_)?(.*)\.pdf$/i', $filename, $matches)) {
            return $matches[2];
        }

        return null;
    }

    /**
     * @throws InboundParseException when the email address is not recognized
     */
    private function parsePossibleInvoicePdfAddress(string $to): array
    {
        // Sometimes the '+' gets treated as whitespace because
        // it is URL encoded
        $to = str_replace('invimport ', 'invimport+', $to);

        // Figure out which company the message is associated with...
        // The format is: "companyUsername"
        if (!preg_match('/^invimport\+([A-Za-z0-9]+)\@'.str_replace('.', '\\.', $this->inboundEmailDomain).'$/', $to, $matches)) {
            throw new InboundParseException('Invalid address: '.$to);
        }

        return $matches;
    }
}
