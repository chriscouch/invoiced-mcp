<?php

namespace App\Entity\Invoiced;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'InvoiceVolumes')]
class InvoiceVolume extends AbstractUsageVolume
{
    #[ORM\ManyToOne(targetEntity: '\App\Entity\Invoiced\Company', inversedBy: 'invoiceVolumes')]
    #[ORM\JoinColumn(nullable: false)]
    private Company $tenant;

    public function getTenant(): Company
    {
        return $this->tenant;
    }

    public function setTenant(Company $tenant): void
    {
        $this->tenant = $tenant;
    }
}
