<?php

namespace App\Entity\Invoiced;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity]
#[ORM\Table(name: 'Products')]
#[UniqueEntity(['name'])]
class Product implements JsonSerializable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    #[ORM\Column(type: 'string')]
    private string $name;
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductFeature::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['feature' => 'ASC'])]
    private Collection $features;

    public function __construct()
    {
        $this->features = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function jsonSerialize(): mixed
    {
        return ['id' => $this->id, 'name' => $this->name];
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getFeatures(): Collection
    {
        return $this->features;
    }

    /**
     * @param Collection<ProductFeature> $features
     */
    public function setFeatures(Collection $features): void
    {
        $features->forAll(function (int|string $key, ProductFeature $value): bool {
            $value->setProduct($this);

            return true;
        });
        $this->features = $features;
    }
}
