<?php

namespace App\PaymentProcessing\Models;

use App\AccountsReceivable\Models\Customer;
use App\Core\Multitenant\Models\MultitenantModel;
use App\Integrations\OAuth\Interfaces\OAuthAccountInterface;
use App\Integrations\OAuth\OAuthAccessToken;
use App\Integrations\OAuth\Traits\OAuthAccountTrait;
use App\PaymentProcessing\ValueObjects\PaymentGatewayConfiguration;
use Carbon\CarbonImmutable;
use App\Core\Orm\Event\ModelDeleting;
use App\Core\Orm\Exception\ListenerException;
use App\Core\Orm\Property;
use App\Core\Orm\Traits\AutoTimestamps;
use App\Core\Orm\Traits\SoftDelete;
use App\Core\Orm\Type;
use stdClass;

/**
 * This model represents a merchant's payment gateway account.
 * WARNING: Never store credentials or secrets un-encrypted.
 *
 * @property int         $id
 * @property string      $gateway
 * @property string      $gateway_id
 * @property string|null $name
 * @property integer     $top_up_threshold_num_of_days
 * @property object      $credentials
 * @property object      $credentials_enc
 * @property object      $settings
 */
class MerchantAccount extends MultitenantModel implements OAuthAccountInterface
{
    use AutoTimestamps;
    use SoftDelete;
    use OAuthAccountTrait;

    protected static function getProperties(): array
    {
        return [
            'gateway' => new Property(
                required: true,
            ),
            'gateway_id' => new Property(
                required: true,
            ),
            'name' => new Property(
                null: true,
            ),
            'credentials_enc' => new Property(
                type: Type::OBJECT,
                required: true,
                encrypted: true,
                in_array: false,
            ),
            'top_up_threshold_num_of_days' => new Property(
                null: true,
            ),
            'settings' => new Property(
                type: Type::OBJECT,
                default: [],
            ),
        ];
    }

    public function initialize(): void
    {
        parent::initialize();
        self::deleting([self::class, 'deletingEnsureNotActive']);
    }

    public static function deletingEnsureNotActive(ModelDeleting $event): void
    {
        /** @var self $merchantAccount */
        $merchantAccount = $event->getModel();
        $accountId = $merchantAccount->id();

        // check if the account is associated w/ any customers
        $customers = Customer::where("ach_gateway_id = $accountId OR cc_gateway_id = $accountId")->count();
        if ($customers) {
            throw new ListenerException('This gateway configuration cannot be deleted because it has '.$customers.' customer(s) associated with it. Please update the customers\' gateways settings before deleting.');
        }

        // check if the account is used on any stored cards or bank accounts
        $activeCards = Card::where('merchant_account_id', $accountId)
            ->where('chargeable', true)
            ->count();
        if ($activeCards > 0) {
            throw new ListenerException('This gateway configuration cannot be deleted because it has '.$activeCards.' card(s) associated. Please remove these first before deleting.');
        }

        $activeBankAccounts = BankAccount::where('merchant_account_id', $accountId)
            ->where('chargeable', true)
            ->count();
        if ($activeBankAccounts > 0) {
            throw new ListenerException('This gateway configuration cannot be deleted because it has '.$activeBankAccounts.' bank account(s) associated. Please remove these first before deleting.');
        }

        // check if the account is used on any payment methods
        $activePaymentMethods = PaymentMethod::where('merchant_account_id', $accountId)
            ->count();
        if ($activePaymentMethods > 0) {
            throw new ListenerException('This gateway configuration cannot be deleted because it is assigned to '.$activePaymentMethods.' payment method(s). Please remove from the payment method first before deleting.');
        }
    }

    /**
     * Sets the `credentials` property by encrypting it
     * and storing it on `credentials_enc`.
     *
     * @param object $credentials
     *
     * @return mixed token
     */
    protected function setCredentialsValue($credentials)
    {
        $this->credentials_enc = $credentials;

        return $credentials;
    }

    /**
     * Gets the decrypted `credentials` property value.
     *
     * @param mixed $credentials current value
     *
     * @return mixed decrypted token
     */
    protected function getCredentialsValue($credentials)
    {
        if ($credentials) {
            return $credentials;
        }

        return $this->credentials_enc;
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        unset($result['deleted']);
        unset($result['deleted_at']);

        return $result;
    }

    public function getToken(): OAuthAccessToken
    {
        $credentials = $this->credentials;

        // This is a fallback for backwards compatibility for
        // records created before the new OAuth library was used.
        $accessToken = $credentials->access_token ?? null;
        if (!$accessToken && 'stripe' == $this->gateway) {
            $accessToken = $credentials->key;
        } elseif (!$accessToken && 'lawpay' == $this->gateway) {
            $accessToken = $credentials->secret_key;
        }

        return new OAuthAccessToken(
            $accessToken,
            new CarbonImmutable($credentials->access_token_expiration),
            $credentials->refresh_token ?? null,
            $credentials->refresh_token_expiration ? new CarbonImmutable($credentials->refresh_token_expiration) : null,
        );
    }

    public function setToken(OAuthAccessToken $token): void
    {
        if (isset($this->credentials)) {
            $credentials = $this->credentials;
        } else {
            $credentials = new stdClass();
        }

        $credentials->access_token = $token->accessToken;
        $credentials->access_token_expiration = $token->accessTokenExpiration->toIso8601String();
        $credentials->refresh_token = $token->refreshToken;
        $credentials->refresh_token_expiration = $token->refreshTokenExpiration?->toIso8601String();
        $this->credentials = $credentials;
    }

    public function persistOAuth(): void
    {
        // Special case for LawPay should not persist the account
        if ('lawpay' == $this->gateway) {
            return;
        }

        $this->saveOrFail();
    }

    public function toGatewayConfiguration(): PaymentGatewayConfiguration
    {
        return new PaymentGatewayConfiguration(
            $this->gateway,
            $this->credentials ?: new stdClass(), /* @phpstan-ignore-line */
        );
    }
}
