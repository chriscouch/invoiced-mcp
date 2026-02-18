<?php

namespace App\EntryPoint\Controller;

use App\Core\Statsd\Interfaces\StatsdAwareInterface;
use App\Core\Statsd\StatsdAwareTrait;
use App\Integrations\Adyen\AdyenClient;
use App\Tokenization\Libs\TokenizationFactory;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    name: 'tokenization_',
    host: 'tknz.%app.domain%',
    schemes: '%app.protocol%',
)]
/**
 * Tokenization endpoint, we may want to move it to separate microservice
 * at some point
 */
class TokenizationController extends AbstractController implements StatsdAwareInterface
{
    use statsdAwareTrait;

    #[Route(path: '/', name: 'tokenize', methods: ['POST'])]
    public function tokenize(Request $request, AdyenClient $client, TokenizationFactory $factory): JsonResponse
    {
        $data = $request->request->all();

        try {
            $tokenizer = $factory->get($data);
        } catch (InvalidArgumentException) {
            $this->statsd->increment('payments.failed_tokenization.v2');
            return new JsonResponse(['message' => 'Invalid payment method'], 400);
        }

        $parameters = $tokenizer->getParameters($data);
        $resp = $client->createPayment($parameters);

        if ('Authorised' !== ($resp['resultCode'] ?? '')) {
            $this->statsd->increment('payments.failed_tokenization.v2');
            return new JsonResponse([
                'message' => 'Tokenization failed: '. ($resp['resultCode'] ?? 'Unknown error'),
            ], 400);
        }

        $application = $tokenizer->makeApplication($resp, $data);

        if (!$application->save()) {
            $this->statsd->increment('payments.failed_tokenization.v2');
            return new JsonResponse([
                'message' => 'Failed to save tokenization application',
            ], 400);
        }

        $this->statsd->increment('payments.successful_tokenization.v2');

        return new JsonResponse([
            'invoiced_token' => $application->identifier,
        ]);
    }
}

