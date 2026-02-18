<?php

namespace App\Form;

use App\Entity\CustomerAdmin\Order;
use App\Enums\OrderType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Vich\UploaderBundle\Form\Type\VichFileType;

class NewOrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    '- Select One' => '',
                    'New Company' => OrderType::NewAccount->value,
                    'Statement of Work' => OrderType::StatementOfWork->value,
                    'Add Product' => OrderType::AddProduct->value,
                    'Remove Product' => OrderType::RemoveProduct->value,
                    'Change User Count' => OrderType::ChangeUserCount->value,
                    'Change Usage Pricing Plan' => OrderType::ChangeUsagePricingPlan->value,
                    'Change Billing Interval' => OrderType::ChangeBillingInterval->value,
                    'Cancel Company' => OrderType::CancelEntity->value,
                ],
                'required' => true,
                'attr' => [
                    'class' => 'order-type',
                ],
            ])
            ->add('startDate', DateType::class, [
                'label' => 'Start Date',
                'required' => true,
                'attr' => [
                    'class' => 'start-date',
                ],
            ])
            ->add('sowAmount', NumberType::class, [
                'label' => 'Statement of Work Total ($)',
                'required' => true,
                'attr' => [
                    'class' => 'order-sow-amount',
                ],
            ])
            ->add('attachment_file', VichFileType::class, [
                'label' => 'Order Form',
                'required' => true,
            ])
            ->add('billingProfileId', HiddenType::class, [
                'label' => 'Billing Profile ID',
                'constraints' => [
                    new NotBlank(),
                ],
            ])
            ->add('customer', HiddenType::class, [
                'label' => 'Billing Profile',
                'required' => true,
                'disabled' => true,
            ])
            ->add('productPricingPlans', CollectionType::class, [
                'label' => 'Products',
                'entry_type' => ProductPricingPlanType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'row_attr' => [
                    'class' => 'field-collection',
                ],
            ])
            ->add('usagePricingPlans', CollectionType::class, [
                'label' => 'Usage',
                'entry_type' => UsagePricingPlanType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'row_attr' => [
                    'class' => 'field-collection',
                ],
            ])
            ->add('newUserCount', IntegerType::class, [
                'label' => 'New User Count',
            ])
            ->add('tenantId', IntegerType::class, [
                'label' => 'Tenant ID',
            ])
            ->add('billingInterval', ChoiceType::class, [
                'label' => 'New Billing Interval',
                'choices' => [
                    'Monthly' => 1,
                    'Yearly' => 2,
                    'Quarterly' => 3,
                    'Semiannually' => 4,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Order::class,
        ]);
    }
}
