<?php

namespace App\EntryPoint\Command;

use App\Core\Database\DatabaseHelper;
use App\Core\Utils\Enums\ObjectType;
use App\ActivityLog\Enums\EventType;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FixEventsTableCommand extends Command
{
    public function __construct(private readonly Connection $database)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('fix:events_table')
            ->setDescription('Fixes the Events and EventAssociations tables.')
            ->addArgument(
                'chunk',
                InputArgument::OPTIONAL,
                'chunk size defaults to 1000'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $chunkSize = $input->getArgument('chunk') ?? 1000;
        $output->writeln('Big delete for object_id started');
        DatabaseHelper::efficientBigDelete($this->database, 'EventAssociations', "object_id = ''", $chunkSize, 'event');

        $ifStatement = ' 0 ';
        $ifStatement2 = ' 0 ';
        foreach (ObjectType::cases() as $case) {
            $name = $case->typeName();
            $ifStatement = " IF(object = '$name', '{$case->value}', $ifStatement) ";
            $ifStatement2 = " IF(object_type = '$name', '{$case->value}', $ifStatement2) ";
        }
        $output->writeln('Update for object_type EventAssociations object_type started');
        DatabaseHelper::bigUpdate($this->database, 'EventAssociations', "object_type = $ifStatement", 'object_type = 0', $chunkSize, 'event');
        $output->writeln('Update for object_type Events object_type_id started');
        DatabaseHelper::bigUpdate($this->database, 'Events', "object_type_id = $ifStatement2", 'object_type_id=0', $chunkSize);

        $ifStatement = ' 0 ';
        foreach (EventType::cases() as $case) {
            $id = $case->toInteger();
            $ifStatement = " IF(type = '{$case->value}', $id, $ifStatement) ";
        }
        $output->writeln('Update for Events type_id started');
        DatabaseHelper::bigUpdate($this->database, 'Events', "type_id = $ifStatement ", 'type_id=0', $chunkSize);

        $output->writeln('Big delete for object_type started');
        DatabaseHelper::efficientBigDelete($this->database, 'EventAssociations', 'object_type = 0', $chunkSize, 'event');
        $output->writeln('Big delete for object_type plans');
        DatabaseHelper::efficientBigDelete($this->database, 'EventAssociations', 'object_type = '.ObjectType::Plan->value, $chunkSize, 'event');

        return 0;
    }
}
