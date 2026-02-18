<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

class SqlConsoleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('sql', TextareaType::class, [
                'label_attr' => ['class' => 'd-none'],
                'required' => true,
                'attr' => [
                    'placeholder' => 'New SQL Query',
                    'col' => '50',
                    'row' => '10',
                ],
            ]);
    }
}
