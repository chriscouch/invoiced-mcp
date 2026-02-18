<?php

namespace App\EntryPoint\Command;

use App\Core\I18n\Countries;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LoadPPPData extends Command
{
    private const DATA_URL = 'http://api.worldbank.org/v2/country/{country}/indicator/PA.NUS.PPP?format=json';

    public function __construct(
        private Connection $database,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('billing:load-ppp-data')
            ->setDescription('Loads purchase power parity data into the database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Loading data from World Bank');
        $countries = new Countries();
        $numData = 0;
        $numCountries = 0;
        foreach ($countries->all() as $country) {
            $url = str_replace('{country}', $country['code'], self::DATA_URL);
            $data = (string) file_get_contents($url);
            $result = json_decode($data);

            if (!is_array($result) || !isset($result[1])) {
                continue;
            }

            ++$numCountries;

            foreach ($result[1] as $row) {
                $year = (int) $row->date;
                if ($year < 2020) {
                    continue;
                }

                try {
                    $this->database->insert('PurchaseParityConversionRates', [
                        'year' => $year,
                        'country' => $country['code'],
                        'conversion_rate' => (float) $row->value,
                    ]);

                    ++$numData;
                } catch (UniqueConstraintViolationException) {
                    // skip if already exists
                }
            }
        }

        $output->writeln('Loaded '.$numData.' data points for '.$numCountries.' countries');

        return 0;
    }
}
