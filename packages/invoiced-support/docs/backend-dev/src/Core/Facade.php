<?php

namespace App\Core;

use App\Core\Authentication\Libs\UserContextFacade;
use App\Core\I18n\TranslatorFacade;
use App\Core\Mailer\MailerFacade;
use App\Core\Multitenant\TenantContextFacade;
use App\Core\Queue\QueueFacade;
use App\Core\Search\Libs\SearchFacade;
use App\Core\Statsd\StatsdFacade;
use App\Core\Templating\TwigFacade;
use App\ActivityLog\Libs\EventSpoolFacade;
use App\Integrations\AccountingSync\WriteSync\AccountingWriteSpoolFacade;
use App\Notifications\Libs\NotificationSpoolFacade;
use App\PaymentProcessing\Libs\VaultPaymentInfoFacade;
use App\SalesTax\Libs\TaxCalculatorFactoryFacade;
use App\Sending\Email\Libs\EmailSpoolFacade;
use App\SubscriptionBilling\Libs\CancelSubscriptionFacade;
use Symfony\Component\DependencyInjection\Container;

/**
 * This class is used to provide a global reference to
 * the Symfony service container. It is an anti-pattern
 * and should not be used for new code. The only valid
 * use for this class is to assist migrating off of Infuse
 * framework that also had a global service container instance.
 *
 * @deprecated
 */
abstract class Facade
{
    public static Container $container;

    /**
     * Initializes the facade once the Symfony service container is available.
     */
    public static function init(Container $container): void
    {
        self::$container = $container;
        AccountingWriteSpoolFacade::$instance = null;
        CacheFacade::$instance = null;
        CancelSubscriptionFacade::$instance = null;
        EmailSpoolFacade::$instance = null;
        EnvironmentFacade::$instance = null;
        EventSpoolFacade::$instance = null;
        LockFactoryFacade::$instance = null;
        LoggerFacade::$instance = null;
        MailerFacade::$instance = null;
        NotificationSpoolFacade::$instance = null;
        QueueFacade::$instance = null;
        SearchFacade::$instance = null;
        StatsdFacade::$instance = null;
        TaxCalculatorFactoryFacade::$instance = null;
        TenantContextFacade::$instance = null;
        TranslatorFacade::$instance = null;
        TwigFacade::$instance = null;
        UserContextFacade::$instance = null;
        VaultPaymentInfoFacade::$instance = null;
    }
}
