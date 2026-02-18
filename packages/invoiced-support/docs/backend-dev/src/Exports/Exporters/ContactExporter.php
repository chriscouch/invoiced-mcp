<?php

namespace App\Exports\Exporters;

use App\AccountsReceivable\Models\Contact;
use App\AccountsReceivable\Models\ContactRole;
use App\AccountsReceivable\Models\Customer;
use App\Core\Orm\Model;
use App\Core\Orm\Query;

class ContactExporter extends CustomerExporter
{
    /** @var string[] */
    private array $roles = [];

    protected function getQuery(array $options): Query
    {
        return parent::getQuery($options)
            ->where('EXISTS (SELECT 1 FROM Contacts WHERE customer_id=Customers.id)');
    }

    protected function getColumns(): array
    {
        return [
            'customer.name',
            'customer.number',
            'name',
            'title',
            'department',
            'email',
            'address1',
            'address2',
            'city',
            'state',
            'postal_code',
            'country',
            'phone',
            'contact_role',
            'created_at',
        ];
    }

    protected function getCsvModelItems(Model $model): ?array
    {
        return Contact::where('customer_id', $model)
            ->limit(1000)
            ->all()
            ->toArray();
    }

    protected function isLineItemColumn(string $column): bool
    {
        return !str_starts_with($column, 'customer.');
    }

    protected function getCsvColumnValue(Model $model, string $field): string
    {
        if (str_starts_with($field, 'customer.') && $model instanceof Customer) {
            $field = str_replace('customer.', '', $field);
        }

        if ('contact_role' == $field && $model instanceof Contact) {
            return $this->getContactRoleValue($model);
        }

        return parent::getCsvColumnValue($model, $field);
    }

    public function getContactRoleValue(Contact $contact): string
    {
        $roleId = $contact->role_id;
        if (!$roleId) {
            return '';
        }

        if (!isset($this->roles[$roleId])) {
            $this->roles[$roleId] = ContactRole::findOrFail($roleId)->name;
        }

        return $this->roles[$roleId];
    }

    public static function getId(): string
    {
        return 'contact_csv';
    }
}
