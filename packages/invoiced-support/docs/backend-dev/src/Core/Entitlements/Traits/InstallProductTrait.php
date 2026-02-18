<?php

namespace App\Core\Entitlements\Traits;

use App\Companies\Models\AutoNumberSequence;
use App\Companies\Models\Company;
use App\Companies\Models\Role;
use App\Core\Entitlements\Exception\InstallProductException;

trait InstallProductTrait
{
    /**
     * Creates roles for the company.
     */
    private function createRoles(Company $company, array $roles): void
    {
        foreach ($roles as $row) {
            // skip if role already exists
            $existing = $this->database->fetchOne('SELECT COUNT(*) FROM Roles WHERE tenant_id=:tenantId AND id=:id', [
                'tenantId' => $company->id,
                'id' => $row['id'],
            ]);
            if ($existing > 0) {
                continue;
            }

            $role = new Role();
            $role->id = $row['id'];
            $role->tenant_id = (int) $company->id();
            $role->name = $row['name'];

            foreach ($row['permissions'] as $k) {
                $role->$k = true;
            }

            if (!$role->save()) {
                throw new InstallProductException('Could not create role: '.$role->getErrors());
            }
        }
    }

    /**
     * Creates auto number sequences for the company.
     */
    private function createAutoNumberSequences(Company $company, array $sequences): void
    {
        foreach ($sequences as $type => $template) {
            // skip if number sequence already exists
            $existing = $this->database->fetchOne('SELECT COUNT(*) FROM AutoNumberSequences WHERE tenant_id=:tenantId AND type=:type', [
                'tenantId' => $company->id,
                'type' => $type,
            ]);
            if ($existing > 0) {
                continue;
            }

            $sequence = new AutoNumberSequence();
            $sequence->tenant_id = (int) $company->id();
            $sequence->type = $type;
            $sequence->template = $template;
            if (!$sequence->save()) {
                throw new InstallProductException('Could not create auto numbering sequences: '.$sequence->getErrors());
            }
        }
    }
}
