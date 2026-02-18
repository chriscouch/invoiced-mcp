<?php

namespace App\Form;

use App\Entity\CustomerAdmin\NewAccount;
use App\Entity\Invoiced\Product;
use Doctrine\Persistence\ManagerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NewAccountType extends AbstractType
{
    public function __construct(private ManagerRegistry $registry)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $productChoices = [];
        $products = $this->registry->getManager('Invoiced_ORM')
            ->getRepository(Product::class)
            ->createQueryBuilder('p')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
        foreach ($products as $product) {
            $productChoices[$product->getName()] = $product->getId();
        }

        $builder
            ->add('billingProfileId', HiddenType::class, [
                'label' => 'Billing Profile ID',
            ])
            ->add('email', EmailType::class, [
                'label' => 'Invitee Email Address',
                'required' => true,
            ])
            ->add('country', CountryType::class, [
                'label' => 'Country',
                'required' => true,
                'preferred_choices' => ['US'],
            ])
            ->add('first_name', TextType::class, [
                'label' => 'Invitee First Name',
                'required' => true,
            ])
            ->add('last_name', TextType::class, [
                'label' => 'Invitee Last Name',
                'required' => true,
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
            ])
            ->addEventListener(FormEvents::POST_SUBMIT, [$this, 'postSubmit']);

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
            'data_class' => NewAccount::class,
        ]);
    }

    public function postSubmit(FormEvent $event): void
    {
        /** @var NewAccount $newAccount */
        $newAccount = $event->getData();

        // Product pricing plans
        if (0 == count($newAccount->getProductPricingPlans())) {
            $event->getForm()->addError(new FormError('You must add at least one product'));
            $event->stopPropagation();

            return;
        }
        $productIds = [];
        $productPrices = [];
        foreach ($newAccount->getProductPricingPlans() as $row) {
            $productId = $row['product']->getId();
            $productIds[] = $productId;
            $productPrices[$productId] = [
                'price' => round($row['price'] * 100),
                'annual' => $row['annual'],
                'custom_pricing' => false,
            ];
        }

        // Usage pricing plans
        $usagePricing = [];
        $quota = [];
        foreach ($newAccount->getUsagePricingPlans() as $row) {
            $usageType = $row['usageType'];
            $usagePricing[$usageType] = [
                'threshold' => $row['threshold'],
                'unit_price' => round($row['unit_price'] * 100),
            ];
        }

        // Set user quota
        if (isset($usagePricing['user'])) {
            $quota['users'] = $usagePricing['user']['threshold'];
        }

        // Set the changeset on the new account
        $changeset = [
            'features' => [],
            'products' => $productIds,
            'productPrices' => $productPrices,
            'quota' => $quota,
            'usagePricing' => $usagePricing,
            'billingInterval' => null,
        ];
        $newAccount->setChangeset((string) json_encode($changeset));
    }
}
