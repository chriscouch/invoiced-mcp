<?php

namespace App\EntryPoint\Command;

use App\Automations\AutomationConfiguration;
use App\Core\Database\DatabaseHelper;
use App\Core\Entitlements\FeatureData;
use App\Core\Entitlements\ProductData;
use App\Core\I18n\Currencies;
use App\Core\Ledger\Enums\DocumentType;
use App\Core\Ledger\Repository\CurrencyRepository;
use App\Core\Ledger\Repository\DocumentTypeRepository;
use App\Core\Utils\ObjectConfiguration;
use App\Imports\Libs\ImportConfiguration;
use App\Integrations\AccountingSync\IntegrationConfiguration;
use App\Reports\ReportBuilder\ReportConfiguration;
use App\Sending\Email\Models\EmailTemplate;
use App\Themes\Models\Theme;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Money\Currencies\ISOCurrencies;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class WriteConfig extends Command
{
    public function __construct(
        private string $environment,
        private string $projectDir,
        private DocumentTypeRepository $documentTypeRepository,
        private CurrencyRepository $currencyRepository,
        private Connection $database,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('config:build')
            ->setDescription('Writes the auto-generated configuration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $assetsDir = $this->projectDir.'/assets';
        $publicDir = $this->projectDir.'/public';
        $viewsDir = $this->projectDir.'/templates';

        /* .env.local */

        $dotenvFile = $this->projectDir.'/.env.local';
        if ('dev' === $this->environment && !file_exists($dotenvFile)) {
            $output->write('Writing sample .env.local..');
            if (copy("$dotenvFile.dist", $dotenvFile)) {
                $output->writeln('ok');
            } else {
                $output->writeln('not ok');
            }
        }

        /* Countries.php */

        $countries = json_decode((string) file_get_contents($this->projectDir.'/config/countries.json'), true);

        usort($countries, fn ($a, $b) => strcasecmp($a['country'], $b['country']));

        $output->write('Writing countries.php..');

        $php = "<?php\n// THIS FILE IS AUTO-GENERATED\nreturn ".var_export($countries, true).';';
        if (file_put_contents($assetsDir.'/countries.php', $php)) {
            $output->writeln('ok');
        } else {
            $output->writeln('not ok');
        }

        /* Features.php */

        $features = FeatureData::get(false)->getData();

        $output->write('Writing features.php..');

        $php = "<?php\n// THIS FILE IS AUTO-GENERATED\nreturn ".var_export($features, true).';';
        if (file_put_contents($assetsDir.'/features.php', $php)) {
            $output->writeln('ok');
        } else {
            $output->writeln('not ok');
        }

        /* Products.php */

        $productData = ProductData::get(false)->getData();

        $output->write('Writing products.php..');

        $php = "<?php\n// THIS FILE IS AUTO-GENERATED\nreturn ".var_export($productData, true).';';
        if (file_put_contents($assetsDir.'/products.php', $php)) {
            $output->writeln('ok');
        } else {
            $output->writeln('not ok');
        }

        /* Object_fields.php */

        $fields = ObjectConfiguration::get(false)->all();

        $output->write('Writing object_fields.php..');

        $php = "<?php\n// THIS FILE IS AUTO-GENERATED\nreturn ".var_export($fields, true).';';
        if (file_put_contents($assetsDir.'/object_fields.php', $php)) {
            $output->writeln('ok');
        } else {
            $output->writeln('not ok');
        }

        /* Automation_fields.php */

        $fields = AutomationConfiguration::get(false)->all();

        $output->write('Writing automation_fields.php..');

        $php = "<?php\n// THIS FILE IS AUTO-GENERATED\nreturn ".var_export($fields, true).';';
        if (file_put_contents($assetsDir.'/automation_fields.php', $php)) {
            $output->writeln('ok');
        } else {
            $output->writeln('not ok');
        }

        /* Import_fields.php */

        $fields = ImportConfiguration::get(false)->all();

        $output->write('Writing import_fields.php..');

        $php = "<?php\n// THIS FILE IS AUTO-GENERATED\nreturn ".var_export($fields, true).';';
        if (file_put_contents($assetsDir.'/import_fields.php', $php)) {
            $output->writeln('ok');
        } else {
            $output->writeln('not ok');
        }

        /* Report_fields.php */

        $fields = ReportConfiguration::get(false)->all();

        $output->write('Writing report_fields.php..');

        $php = "<?php\n// THIS FILE IS AUTO-GENERATED\nreturn ".var_export($fields, true).';';
        if (file_put_contents($assetsDir.'/report_fields.php', $php)) {
            $output->writeln('ok');
        } else {
            $output->writeln('not ok');
        }

        /* Integrations.php */

        $fields = IntegrationConfiguration::get(false)->all();

        $output->write('Writing integrations.php..');

        $php = "<?php\n// THIS FILE IS AUTO-GENERATED\nreturn ".var_export($fields, true).';';
        if (file_put_contents($assetsDir.'/integrations.php', $php)) {
            $output->writeln('ok');
        } else {
            $output->writeln('not ok');
        }

        /* Version.php */

        $version = trim((string) shell_exec('git rev-parse --short --verify HEAD'));

        $output->write('Writing version.php..');

        $php = "<?php\n// THIS FILE IS AUTO-GENERATED\ndefine('INVOICED_VERSION', '$version');";
        if (file_put_contents($assetsDir.'/version.php', $php)) {
            $output->writeln('ok');
        } else {
            $output->writeln('not ok');
        }

        /* Config.js */

        $config = [
            'environment' => $this->environment,
            'currencies' => Currencies::all(),
            'countries' => $countries,
        ];

        $js = 'var InvoicedConfig = '.json_encode($config).';';

        $output->write('Writing config.js..');

        if (file_put_contents("$publicDir/js/config.js", $js)) {
            $output->writeln('ok');
        } else {
            $output->writeln('not ok');
        }

        /* Locale.js */

        $currencies = [];
        foreach (Currencies::all() as $code => $value) {
            $currencies[strtolower($code)] = $value;
        }

        $js = 'InvoicedConfig.currencies = '.json_encode($currencies).";\n";
        $js .= 'InvoicedConfig.timezones = '.json_encode(DateTimeZone::listIdentifiers()).';';

        $output->write('Writing locale.js..');

        if (file_put_contents("$publicDir/js/locale.js", $js)) {
            $output->writeln('ok');
        } else {
            $output->writeln('not ok');
        }

        /* DefaultTemplates.js */
        try {
            // get the default templates and
            // compile their default variables
            $eNames = [
                // invoices
                EmailTemplate::NEW_INVOICE,
                EmailTemplate::UNPAID_INVOICE,
                EmailTemplate::LATE_PAYMENT_REMINDER,
                EmailTemplate::PAID_INVOICE,
                EmailTemplate::PAYMENT_PLAN,
                EmailTemplate::AUTOPAY_FAILED,
                // estimates
                EmailTemplate::ESTIMATE,
                // credit notes
                EmailTemplate::CREDIT_NOTE,
                // receipts
                EmailTemplate::PAYMENT_RECEIPT,
                EmailTemplate::REFUND,
                // statements
                EmailTemplate::STATEMENT,
                // subscriptions
                EmailTemplate::SUBSCRIPTION_CONFIRMATION,
                EmailTemplate::SUBSCRIPTION_CANCELED,
                EmailTemplate::SUBSCRIPTION_BILLED_SOON,
            ];

            $eTypes = [
                EmailTemplate::TYPE_INVOICE,
                EmailTemplate::TYPE_CREDIT_NOTE,
                EmailTemplate::TYPE_PAYMENT_PLAN,
                EmailTemplate::TYPE_ESTIMATE,
                EmailTemplate::TYPE_SUBSCRIPTION,
                EmailTemplate::TYPE_TRANSACTION,
                EmailTemplate::TYPE_STATEMENT,
                EmailTemplate::TYPE_CHASING,
            ];

            $emailTemplates = [];
            $emailVariablesById = [];
            $emailVariablesByType = [];
            foreach ($eNames as $email) {
                $template = EmailTemplate::make(-1, $email);
                $template->subject = '';
                $template->type = EmailTemplate::$types[$email];
                $template->name = EmailTemplate::$names[$email];

                $emailTemplates[$email] = [
                    'id' => $email,
                    'name' => $template->name,
                    'type' => $template->type,
                    'subject' => $template->subject,
                    'body' => file_get_contents($viewsDir.'/emailContent/'.$email.'.twig'),
                    'options' => new \stdClass(),
                ];

                $emailVariablesById[$email] = $template->getAvailableVariables();
            }

            foreach ($eTypes as $type) {
                $template = EmailTemplate::make(-1, '');
                $template->subject = '';
                $template->type = $type;

                $emailVariablesByType[$type] = $template->getAvailableVariables();
            }

            $templates = [
                'pdf' => [
                    'css' => [
                        'credit_note' => file_get_contents($viewsDir.'/pdf/classic/credit_note.css'),
                        'estimate' => file_get_contents($viewsDir.'/pdf/classic/estimate.css'),
                        'invoice' => file_get_contents($viewsDir.'/pdf/classic/invoice.css'),
                        'receipt' => file_get_contents($viewsDir.'/pdf/classic/receipt.css'),
                        'statement' => file_get_contents($viewsDir.'/pdf/classic/statement.css'),
                    ],
                    'twig' => [
                        'credit_note' => file_get_contents($viewsDir.'/pdf/classic/credit_note.twig'),
                        'estimate' => file_get_contents($viewsDir.'/pdf/classic/estimate.twig'),
                        'invoice' => file_get_contents($viewsDir.'/pdf/classic/invoice.twig'),
                        'receipt' => file_get_contents($viewsDir.'/pdf/classic/receipt.twig'),
                        'statement' => file_get_contents($viewsDir.'/pdf/classic/statement.twig'),
                    ],
                ],
                'email' => $emailTemplates,
                'emailVariables' => $emailVariablesById,
                'emailVariablesByType' => $emailVariablesByType,
            ];

            $js = 'InvoicedConfig.templates = '.json_encode($templates).";\n";

            $theme = [];
            foreach (Theme::definition()->all() as $property) {
                $k = $property->name;
                if (in_array($k, ['id', 'company', 'created_at', 'updated_at'])) {
                    continue;
                }

                $theme[$k] = $property->default;
            }
            $themes = ['default' => $theme];
            $js .= 'InvoicedConfig.themes = '.json_encode($themes).';';

            $output->write('Writing defaultTemplates.js..');

            if (file_put_contents($publicDir.'/js/defaultTemplates.js', $js)) {
                $output->writeln('ok');
            } else {
                $output->writeln('not ok');
            }
        } catch (Throwable $e) {
            $output->writeln($e->getMessage());
        }

        $this->populateLists($output);

        return 0;
    }

    private function populateLists(OutputInterface $output): void
    {
        try {
            $this->database->executeQuery('SELECT 1');
        } catch (Throwable) {
            $output->writeln('Not populating lists because database is not connected');

            return;
        }

        try {
            $output->write('Populating currencies table..');
            $currencies = new ISOCurrencies();
            foreach ($currencies as $currency) {
                $numericCode = $currencies->numericCodeFor($currency);
                $minorUnit = $currencies->subunitFor($currency);
                $this->currencyRepository->create($currency, $numericCode, $minorUnit);
            }
            $output->writeln('ok');
        } catch (Throwable $e) {
            $output->writeln($e->getMessage());
        }

        try {
            $output->write('Populating document types table..');
            foreach (DocumentType::cases() as $documentType) {
                $this->documentTypeRepository->create($documentType);
            }
            $output->writeln('ok');
        } catch (Throwable $e) {
            $output->writeln($e->getMessage());
        }

        try {
            $hasCalendar = $this->database->fetchOne('SELECT 1 FROM Calendar');
            if (!$hasCalendar) {
                $output->write('Populating calendar table..');
                $date = (new CarbonImmutable('2000-01-01'))->setTime(0, 0);
                $until = (new CarbonImmutable('2101-01-01'))->setTime(0, 0);
                $rows = [];
                while ($date->isBefore($until)) {
                    $rows[] = $date->toDateString();
                    $date = $date->addDay();
                }
                DatabaseHelper::bulkInsert($this->database, 'Calendar', ['date'], $rows);
                $output->writeln('ok');
            }
        } catch (Throwable $e) {
            $output->writeln($e->getMessage());
        }

        try {
            $output->write('Populating product tables..');
            foreach (ProductData::get()->getData()['products'] as $product) {
                $this->database->beginTransaction();

                // Create or locate the product
                $id = $this->database->fetchOne('SELECT id FROM Products WHERE name=?', [$product['name']]);
                if (!$id) {
                    $this->database->executeStatement('INSERT INTO Products (name) VALUES (?)', [$product['name']]);
                    $id = $this->database->lastInsertId();
                }

                // Delete features
                $this->database->executeStatement('DELETE FROM ProductFeatures WHERE product_id='.$id);

                // Create features
                foreach ($product['features'] as $feature) {
                    $this->database->executeStatement('INSERT INTO ProductFeatures (product_id, feature) VALUES ('.$id.',"'.$feature.'")');
                }

                $this->database->commit();
            }
            $output->writeln('ok');
        } catch (Throwable $e) {
            if ($this->database->isTransactionActive()) {
                $this->database->rollBack();
            }

            $output->writeln($e->getMessage());
        }
    }
}
