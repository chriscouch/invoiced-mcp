<?php

namespace App\Integrations\NetSuite\Writers;

use App\AccountsReceivable\Models\Customer;

/**
 * @template-extends    AbstractNetSuiteCustomerObjectWriter<Customer>
 *
 * @property Customer $model
 */
class NetSuiteCustomerWriter extends AbstractNetSuiteCustomerObjectWriter
{
    /**
     * Get url deployment id.
     */
    public function getDeploymentId(): string
    {
        return 'customdeploy_invd_customer_model_restlet';
    }

    /**
     * Gets url script id.
     */
    public function getScriptId(): string
    {
        return 'customscript_invd_customer_model_restlet';
    }

    protected function getParentCustomer(): ?Customer
    {
        return $this->model->parentCustomer();
    }

    public function getReverseMapping(): ?string
    {
        return $this->getCustomerMapping($this->model);
    }

    public function shouldUpdate(): bool
    {
        return true;
    }
}
