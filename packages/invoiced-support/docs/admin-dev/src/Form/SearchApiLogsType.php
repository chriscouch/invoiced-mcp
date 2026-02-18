<?php

namespace App\Form;

use App\Entity\Forms\ApiLogSearch;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchApiLogsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->setAttribute('form_attr', 'searchForm');
        $builder->setAttributes(['id' => 'searchForm']);

        $builder
            ->add('tenant', IntegerType::class, [
                'label' => 'Tenant ID',
            ])
            ->add('requestId', TextType::class, [
                'label' => 'Request ID',
                'required' => false,
            ])
            ->add('correlationId', TextType::class, [
                'label' => 'Correlation ID',
                'required' => false,
            ])
            ->add('method', ChoiceType::class, [
                'required' => false,
                'choices' => [
                    'GET' => 'GET',
                    'POST' => 'POST',
                    'PATCH' => 'PATCH',
                    'PUT' => 'PUT',
                    'DELETE' => 'DELETE',
                ],
            ])
            ->add('route_name', TextType::class, [
                'required' => false,
                'help' => 'Examples: <code>list_customers</code>, <code>retrieve_invoice</code>, <code>list_payments</code>',
            ])
            ->add('endpoint', TextType::class, [
                'required' => false,
                'help' => 'Examples: <code>/customers</code>, <code>/invoices/1234</code>, <code>/payments</code>',
            ])
            ->add('status_code', ChoiceType::class, [
                'required' => false,
                'choices' => [
                    '200 OK' => 200,
                    '201 Created' => 201,
                    '204 No Content' => 204,
                    '400 Invalid Request' => 400,
                    '403 Forbidden' => 403,
                    '404 Not Found' => 404,
                    '500 Internal Server Error' => 500,
                ],
            ])
            ->add('user_agent', TextType::class, [
                'required' => false,
                'help' => 'Examples: <code>Invoiced/1.0</code>, <code>Invoiced PHP/6.5</code>, <code>NetSuite/2020.2 (SuiteScript)</code>, <code>SFDC-Callout/50.0</code>',
            ])
            ->add('start_time', DateTimeType::class, [
                'required' => false,
            ])
            ->add('end_time', DateTimeType::class, [
                'required' => false,
            ])
            ->add('num_results', ChoiceType::class, [
                'required' => true,
                'choices' => [
                    '10' => 10,
                    '25' => 25,
                    '100' => 100,
                    '500' => 500,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ApiLogSearch::class,
        ]);
    }
}
