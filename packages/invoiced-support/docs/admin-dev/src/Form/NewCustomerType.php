<?php

namespace App\Form;

use App\Entity\Forms\NewCustomer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NewCustomerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Customer Name',
                'required' => true,
            ])
            ->add('address1', TextType::class, [
                'label' => 'Address Line 1',
                'required' => true,
            ])
            ->add('address2', TextType::class, [
                'label' => 'Address Line 2',
                'required' => false,
                'attr' => [
                    'class' => 'optional',
                ],
            ])
            ->add('city', TextType::class, [
                'label' => 'City',
                'required' => true,
            ])
            ->add('state', TextType::class, [
                'label' => 'State',
                'required' => true,
            ])
            ->add('postalCode', TextType::class, [
                'label' => 'Postal Code',
                'required' => true,
            ])
            ->add('country', CountryType::class, [
                'label' => 'Country',
                'required' => true,
                'preferred_choices' => ['US'],
            ])
            ->add('billingEmail', EmailType::class, [
                'label' => 'Accounts Payable Email',
                'required' => true,
            ])
            ->add('billingPhone', TextType::class, [
                'label' => 'Accounts Payable Phone #',
                'required' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => NewCustomer::class,
        ]);
    }
}
