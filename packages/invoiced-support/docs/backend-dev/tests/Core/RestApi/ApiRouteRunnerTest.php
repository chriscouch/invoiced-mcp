<?php

namespace App\Tests\Core\RestApi;

use App\Core\RestApi\Libs\ApiRouteRunner;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\QueryParameter;
use App\Core\RestApi\ValueObjects\RequestParameter;
use App\Tests\AppTestCase;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ApiRouteRunnerTest extends AppTestCase
{
    private function getRunner(): ApiRouteRunner
    {
        return self::getService('test.api_runner');
    }

    public function testRequestResolver(): void
    {
        $runner = $this->getRunner();
        $definition = new ApiRouteDefinition(
            queryParameters: null,
            requestParameters: [
                'vendor' => new RequestParameter(
                    required: true,
                    types: ['int'],
                ),
                'amount' => new RequestParameter(
                    required: true,
                    types: ['float', 'integer'],
                ),
                'currency' => new RequestParameter(
                    required: true,
                    types: ['string'],
                    allowedValues: ['usd', 'gbp', 'eur'],
                ),
                'date' => new RequestParameter(
                    types: ['string'],
                    default: '2023-04-04',
                ),
                'payment_method' => new RequestParameter(
                    types: ['string'],
                ),
                'reference' => new RequestParameter(
                    types: ['string', 'null'],
                ),
                'notes' => new RequestParameter(
                    types: ['string', 'null'],
                ),
                'applied_to' => new RequestParameter(
                    types: ['array'],
                ),
            ],
            requiredPermissions: [],
        );

        $resolver = $runner->getRequestOptionsResolver($definition);

        $this->assertInstanceOf(OptionsResolver::class, $resolver);
        $this->assertEquals([
            'vendor',
            'amount',
            'currency',
            'date',
            'payment_method',
            'reference',
            'notes',
            'applied_to',
        ], $resolver->getDefinedOptions());

        $this->assertEquals([
            'vendor',
            'amount',
            'currency',
        ], $resolver->getRequiredOptions());
    }

    public function testQueryResolver(): void
    {
        $runner = $this->getRunner();
        $definition = new ApiRouteDefinition(
            queryParameters: [
                'query' => new QueryParameter(
                    required: true,
                ),
                'per_page' => new QueryParameter(
                    default: 5,
                ),
                'type' => new QueryParameter(
                    allowedValues: ['customer', 'invoice', 'payment'],
                    default: null,
                ),
                '_' => new QueryParameter(
                    default: null,
                ),
            ],
            requestParameters: null,
            requiredPermissions: [],
        );

        $resolver = $runner->getQueryOptionsResolver($definition);

        $this->assertInstanceOf(OptionsResolver::class, $resolver);
        $this->assertEquals([
            'query',
            'per_page',
            'type',
            '_',
        ], $resolver->getDefinedOptions());
        $this->assertEquals([
            'query',
        ], $resolver->getRequiredOptions());
    }
}
