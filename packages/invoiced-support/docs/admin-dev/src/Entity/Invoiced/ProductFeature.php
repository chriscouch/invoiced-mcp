<?php

namespace App\Entity\Invoiced;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity]
#[ORM\Table(name: 'ProductFeatures')]
#[UniqueEntity(['product_id', 'feature'])]
class ProductFeature
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    #[ORM\Column(type: 'string')]
    private string $feature;
    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'features')]
    #[ORM\JoinColumn(nullable: false)]
    private Product $product;

    public function __toString(): string
    {
        return $this->feature;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getFeature(): string
    {
        return $this->feature;
    }

    public function setFeature(string $feature): void
    {
        $this->feature = $feature;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): void
    {
        $this->product = $product;
    }
}
