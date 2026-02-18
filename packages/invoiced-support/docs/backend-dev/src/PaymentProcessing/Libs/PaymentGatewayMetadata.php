<?php

namespace App\PaymentProcessing\Libs;

use App\Core\I18n\ValueObjects\Money;
use InvalidArgumentException;

/**
 * Payment gateway factory and metadata repository.
 */
class PaymentGatewayMetadata
{
    const PAYPAL = 'paypal';

    public static self $instance;
    private array $metadata = [];

    public static function get(): self
    {
        if (!isset(self::$instance)) {
            self::$instance = new self(dirname(__DIR__, 3));
        }

        return self::$instance;
    }

    public function __construct(private string $projectDir)
    {
    }

    /**
     * Gets a list of supported currencies given a payment method.
     * If there are no restrictions on currencies then gateways
     * may return '*' instead of enumerating every currency. It can
     * be assumed that this method will only be called with
     * payment methods supported by this gateway.
     *
     * @param string $id     gateway ID
     * @param string $method payment method
     *
     * @throws InvalidArgumentException if the gateway does support the given payment method
     *
     * @return array|string
     */
    public function getSupportedCurrencies(string $id, string $method)
    {
        $metadata = $this->getMetadata($id);

        if (!isset($metadata['methods'][$method])) {
            throw new InvalidArgumentException('Payment gateway "'.$id.'" does not support '.$method.' payment method');
        }

        if (is_array($metadata['methods'][$method]['currencies'])) {
            sort($metadata['methods'][$method]['currencies']);
        }

        return $metadata['methods'][$method]['currencies'];
    }

    /**
     * Gets the minimum payment amount allowed for a given payment gateway.
     *
     * @param string $id gateway ID
     *
     * @throws InvalidArgumentException if the gateway does specify a minimum payment amount
     */
    public function getMinPaymentAmount(string $id, string $currency): Money
    {
        $metadata = $this->getMetadata($id);

        if (isset($metadata['minPayment'][$currency])) {
            $amount = $metadata['minPayment'][$currency];

            return new Money($currency, $amount);
        }

        if (isset($metadata['minPayment']['*'])) {
            $amount = $metadata['minPayment']['*'];

            return new Money($currency, $amount);
        }

        throw new InvalidArgumentException('No minimum payment amount specified for "'.$id.'" payment gateway and '.$currency.' currency');
    }

    /**
     * Gets metadata for a payment gateway.
     *
     * @throws InvalidArgumentException if the gateway does not exist
     */
    private function getMetadata(string $id): array
    {
        if (!isset($this->metadata[$id])) {
            $file = $this->projectDir.'/config/paymentGateways/'.$id.'.json';
            if (!file_exists($file)) {
                throw new InvalidArgumentException('Payment gateway metadata for "'.$id.'" does not exist.');
            }

            $json = (string) file_get_contents($file);
            $this->metadata[$id] = json_decode($json, true);
        }

        return $this->metadata[$id];
    }
}
