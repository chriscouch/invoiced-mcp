<?php

namespace App\Form;

use App\Entity\Forms\EmailLogSearch;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchEmailLogsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'required' => true,
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
            'data_class' => EmailLogSearch::class,
        ]);
    }
}
