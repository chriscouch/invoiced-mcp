<?php

namespace App\Form;

use App\Entity\CustomerAdmin\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RegisterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('first_name', TextType::class, ['label' => false, 'attr' => ['autocomplete' => 'off', 'error_bubbling' => true]])
            ->add('last_name', TextType::class, ['label' => false, 'attr' => ['autocomplete' => 'off', 'error_bubbling' => true]])
            ->add('email', EmailType::class, ['label' => false, 'attr' => ['autocomplete' => 'off', 'error_bubbling' => true]])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'validation_groups' => ['registration'],
        ]);
    }
}
