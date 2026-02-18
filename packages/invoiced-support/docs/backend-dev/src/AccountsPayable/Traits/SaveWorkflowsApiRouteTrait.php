<?php

namespace App\AccountsPayable\Traits;

use App\AccountsPayable\Api\EditWorkflowApiRoute;
use App\AccountsPayable\Models\ApprovalWorkflow;
use App\AccountsPayable\Operations\SaveApprovalWorkflow;
use App\Core\RestApi\Exception\InvalidRequest;
use App\Core\RestApi\ValueObjects\ApiCallContext;
use App\Core\RestApi\ValueObjects\ApiRouteDefinition;
use App\Core\RestApi\ValueObjects\RequestParameter;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\OptionsResolver\Exception\ExceptionInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

trait SaveWorkflowsApiRouteTrait
{
    public function __construct(private readonly SaveApprovalWorkflow $saveApprovalWorkflow)
    {
    }

    public function getDefinition(): ApiRouteDefinition
    {
        return new ApiRouteDefinition(
            queryParameters: $this->getBaseQueryParameters(),
            requestParameters: [
                'paths' => new RequestParameter(
                    required: true,
                    types: ['array'],
                ),
                'name' => new RequestParameter(
                    required: true,
                ),
                'default' => new RequestParameter(
                    types: ['bool'],
                    default: false
                ),
                'enabled' => new RequestParameter(
                    types: ['bool'],
                    default: true
                ),
            ],
            requiredPermissions: [],
            modelClass: ApprovalWorkflow::class,
            features: ['accounts_payable'],
        );
    }

    public function buildResponse(ApiCallContext $context): ApprovalWorkflow
    {
        /** @var ApprovalWorkflow $model */
        $model = parent::buildResponse($context);
        $isUpdate = EditWorkflowApiRoute::class === static::class; /* @phpstan-ignore-line */

        // Validate paths
        $expressionLanguage = new ExpressionLanguage();
        $subResolver = new OptionsResolver();
        $subResolver->setDefined(['rules', 'id'])
            ->setDefaults([
                'rules' => '',
            ])
            ->setRequired('steps')
            ->setAllowedTypes('steps', 'array[]')
            ->setAllowedValues('steps', function (array &$steps): bool {
                if (0 === count($steps)) {
                    return false;
                }
                $subResolver = new OptionsResolver();
                $subResolver->setDefaults([
                        'members' => [],
                        'roles' => [],
                    ])
                    ->setRequired('minimum_approvers')
                    ->setAllowedTypes('members', 'array')
                    ->setAllowedTypes('roles', 'array')
                    ->setDefined('id');
                $steps = array_map([$subResolver, 'resolve'], $steps);

                return true;
            })
            ->setAllowedTypes('rules', 'string')
            ->setAllowedValues('rules', function (string $rules) use ($expressionLanguage) {
                try {
                    return '' === $rules || $expressionLanguage->compile($rules, ['document']);
                } catch (SyntaxError $e) {
                    throw new InvalidOptionsException('Invalid formula: '.$e->getMessage());
                }
            });

        try {
            $paths = array_map([$subResolver, 'resolve'], $context->requestParameters['paths']);
        } catch (ExceptionInterface $e) {
            // Replace double quotation marks with single for more clean output.
            $message = str_replace('"', "'", $e->getMessage());

            throw new InvalidRequest($message, 400, 'paths');
        }

        $this->saveApprovalWorkflow->savePaths($model, $paths, $isUpdate);

        return $model->refresh();
    }
}
