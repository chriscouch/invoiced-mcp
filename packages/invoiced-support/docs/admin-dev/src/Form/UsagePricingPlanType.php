<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

class UsagePricingPlanType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('usageType', ChoiceType::class, [
                'label' => 'Usage Type',
                'choices' => [
                    'Invoices/Month' => 'invoice',
                    'Customers/Month' => 'customer',
                    'Users' => 'user',
                    'Money Billed/Month' => 'money_billed',
                ],
                'expanded' => false,
                'multiple' => false,
                'required' => true,
            ])
            ->add('threshold', IntegerType::class, [
                'label' => 'Included',
                'required' => true,
                'constraints' => [
                    new PositiveOrZero(),
                ],
            ])
            ->add('unit_price', NumberType::class, [
                'label' => 'Unit Price ($/Month)',
                'required' => true,
                'constraints' => [
                    new Positive(),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'by_reference' => false,
        ]);
    }
}
