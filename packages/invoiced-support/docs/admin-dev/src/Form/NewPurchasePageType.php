<?php

namespace App\Form;

use App\Controller\Admin\PurchasePageCrudController;
use App\Entity\Forms\NewPurchasePage;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

class NewPurchasePageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('billingInterval', ChoiceType::class, [
                'label' => 'Billing Interval',
                'required' => true,
                'choices' => [
                    'Monthly' => 1,
                    'Yearly' => 2,
                    'Quarterly' => 3,
                    'Semiannually' => 4,
                ],
            ])
            ->add('expirationDate', DateType::class, [
                'label' => 'Expiration Date',
                'required' => true,
                'constraints' => [
                    new GreaterThan('today'),
                ],
            ])
            ->add('paymentTerms', ChoiceType::class, [
                'label' => 'Payment Terms',
                'required' => true,
                'choices' => PurchasePageCrudController::PAYMENT_TERMS,
            ])
            ->add('activationFee', NumberType::class, [
                'label' => 'Activation Fee ($)',
                'required' => true,
                'constraints' => [
                    new PositiveOrZero(),
                ],
            ])
            ->add('note', TextareaType::class, [
                'label' => 'Note to Buyer',
                'required' => false,
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
                'required' => false,
                'entry_type' => UsagePricingPlanType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'row_attr' => [
                    'class' => 'field-collection',
                ],
            ]);

        if (!$options['existing_tenant']) {
            $builder
                ->add('country', CountryType::class, [
                    'label' => 'Country',
                    'required' => true,
                    'preferred_choices' => ['US'],
                ]);
        }

        $productPricingPlansField = CollectionField::new('productPricingPlans')
            ->renderExpanded()
            ->setEntryIsComplex()
            ->setColumns('col-12')
            ->getAsDto();
        $builder->get('productPricingPlans')->setAttribute('ea_field', $productPricingPlansField);

        $usagePricingPlansField = CollectionField::new('usagePricingPlans')
            ->renderExpanded()
            ->setEntryIsComplex()
            ->setColumns('col-12')
            ->getAsDto();
        $builder->get('usagePricingPlans')->setAttribute('ea_field', $usagePricingPlansField);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => NewPurchasePage::class,
            'existing_tenant' => false,
        ]);
    }
}
