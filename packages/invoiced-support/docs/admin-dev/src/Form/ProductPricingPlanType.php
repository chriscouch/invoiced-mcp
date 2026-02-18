<?php

namespace App\Form;

use App\Entity\Invoiced\Product;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

class ProductPricingPlanType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('product', EntityType::class, [
                'label' => 'Product',
                'class' => Product::class,
                'choice_label' => 'name',
                'query_builder' => function (EntityRepository $er): QueryBuilder {
                    return $er->createQueryBuilder('p')
                        ->orderBy('p.name', 'ASC');
                },
                'expanded' => false,
                'multiple' => false,
                'required' => true,
            ])
            ->add('price', NumberType::class, [
                'label' => 'Price ($)',
                'required' => true,
                'constraints' => [
                    new PositiveOrZero(),
                ],
            ])
            ->add('annual', ChoiceType::class, [
                'label' => 'Interval',
                'required' => true,
                'choices' => [
                    'Per Month' => false,
                    'Per Year' => true,
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
