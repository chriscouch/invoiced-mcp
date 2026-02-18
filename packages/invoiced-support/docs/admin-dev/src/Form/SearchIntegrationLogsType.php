<?php

namespace App\Form;

use App\Entity\Forms\IntegrationLogSearch;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchIntegrationLogsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->setAttribute('form_attr', 'searchForm');
        $builder->setAttributes(['id' => 'searchForm']);

        $builder
            ->add('tenant', IntegerType::class, [
                'label' => 'Tenant ID',
            ])
            ->add('channel', ChoiceType::class, [
                'required' => true,
                'choices' => [
                    'Avalara' => 'avalara',
                    'Business Central' => 'business_central',
                    'Earth Class Mail' => 'earth_class_mail',
                    'FreshBooks' => 'freshbooks',
                    'Intacct' => 'intacct',
                    'NetSuite' => 'netsuite',
                    'Plaid' => 'plaid',
                    'QuickBooks Desktop' => 'quickbooks_desktop',
                    'QuickBooks Online' => 'quickbooks_online',
                    'SMTP' => 'smtp',
                    'Slack' => 'slack',
                    'Syncserver HTTP' => 'syncserver_http',
                    'Xero' => 'xero',
                ],
            ])
            ->add('start_time', DateTimeType::class, [
                'required' => true,
            ])
            ->add('end_time', DateTimeType::class, [
                'required' => true,
            ])
            ->add('search_term', TextType::class, [
                'required' => false,
            ])
            ->add('correlation_id', TextType::class, [
                'required' => false,
                'label' => 'Correlation ID',
            ])
            ->add('min_level', ChoiceType::class, [
                'required' => false,
                'choices' => [
                    'Any' => null,
                    'Debug' => 'DEBUG',
                    'Info' => 'INFO',
                    'Notice' => 'NOTICE',
                    'Warning' => 'WARNING',
                    'Error' => 'ERROR',
                    'Critical' => 'CRITICAL',
                    'Severe' => 'SEVERE',
                    'Emergency' => 'EMERGENCY',
                ],
                'label' => 'Level',
            ])
            ->add('num_results', ChoiceType::class, [
                'required' => true,
                'choices' => [
                    '10' => 10,
                    '25' => 25,
                    '100' => 100,
                    '500' => 500,
                    '1000' => 1000,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => IntegrationLogSearch::class,
        ]);
    }
}
