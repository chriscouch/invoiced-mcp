<?php

namespace App\EntryPoint\Controller\CustomerPortal;

use App\AccountsReceivable\Models\Customer;
use App\CustomerPortal\Command\SignInCustomer;
use App\Statements\ClientView\StatementClientViewVariables;
use App\Statements\Enums\StatementType;
use App\Statements\Libs\AbstractStatement;
use App\Statements\Libs\StatementBuilder;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    name: 'customer_portal_',
    requirements: ['subdomain' => '^(?!api|tknz).*$'],
    host: '{subdomain}.%app.domain%',
    schemes: '%app.protocol%',
)]
class StatementCustomerPortalController extends AbstractCustomerPortalController
{
    #[Route(path: '/statements/{id}', name: 'statement', methods: ['GET'])]
    public function viewStatement(Request $request, StatementBuilder $builder, SignInCustomer $signIn, StatementClientViewVariables $viewVariables, string $id): Response
    {
        $portal = $this->customerPortalContext->getOrFail();

        $customer = Customer::findClientId($id);
        if (!$customer) {
            throw new NotFoundHttpException();
        }

        // Check if the viewer has permission when "Require Authentication" is enabled
        if ($response = $this->mustLogin($customer, $request)) {
            return $response;
        }

        // sign the customer into the customer portal temporarily
        $response = new Response();
        $response = $signIn->signIn($customer, $response, true);

        try {
            $statement = $this->makeStatement($request, $builder, $customer);
        } catch (InvalidArgumentException $e) {
            return new Response($e->getMessage());
        }

        return $this->render('customerPortal/statements/view.twig', [
            'dateFormat' => $statement->getSendCompany()->date_format,
            'statement' => $viewVariables->make($statement, $portal, $request),
        ], $response);
    }

    private function makeStatement(Request $request, StatementBuilder $builder, Customer $customer): AbstractStatement
    {
        $type = StatementType::tryFrom((string) $request->query->get('type')) ?? StatementType::OpenItem;
        $currency = (string) $request->query->get('currency');
        $start = $request->query->getInt('start') ?: null;
        $end = $request->query->getInt('end') ?: null;
        $pastDueOnly = 'past_due' == $request->query->get('items');

        // Month selector override. Parses 2021-01
        if ($monthStr = (string) $request->query->get('month')) {
            $month = (int) substr($monthStr, 5, 2);
            $year = (int) substr($monthStr, 0, 4);
            $start = (int) mktime(0, 0, 0, $month, 1, $year);
            $end = min(time(), (int) mktime(23, 59, 59, $month, (int) date('t', $start), $year));
        }

        if (StatementType::OpenItem == $type) {
            $start = null;
        }

        return $builder->build($customer, $type, $currency, $start, $end, $pastDueOnly);
    }
}
