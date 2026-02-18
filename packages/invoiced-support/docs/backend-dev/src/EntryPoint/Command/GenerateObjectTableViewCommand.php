<?php

namespace App\EntryPoint\Command;

use App\Core\Utils\ObjectConfiguration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateObjectTableViewCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('object:table-view')
            ->setDescription('Generates the TableView configuration for an object type')
            ->addArgument(
                'object',
                InputArgument::REQUIRED,
                'Object type to make the configuration for (i.e. customer or invoice)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $object = $input->getArgument('object');

        $fields = ObjectConfiguration::get(false)->getFields($object);
        $result = [];
        foreach ($fields as $id => $field) {
            // Skip certain protected fields
            if (in_array($id, ['tenant_id', 'company'])) {
                continue;
            }

            $entry = [
                'id' => $id,
                'name' => $field['name'],
                'type' => $field['type'],
            ];

            if (!$field['filterable']) {
                $entry['filterable'] = false;
            }

            if ('enum' == $field['type']) {
                $entry['values'] = [];
                foreach ($field['values'] as $key => $value) {
                    $entry['values'][] = ['value' => $key, 'text' => $value];
                }
            }

            if ('join' == $field['type']) {
                $entry['type'] = 'enum';

                $joinObject = $field['join_object'] ?? $id;
                if ('customer' == $joinObject) {
                    $entry['type'] = 'customer';
                }
                if ('member' == $joinObject) {
                    $entry['type'] = 'user';
                }
            }

            if ('currency' == $id) {
                $entry['type'] = 'currency';
            }

            if ('country' == $id) {
                $entry['type'] = 'country';
            }

            if ('integer' == $field['type'] || 'float' == $field['type']) {
                $entry['type'] = 'number';
            }

            if ('date_unix' == $field['type']) {
                $entry['type'] = 'datetime';
            }

            $result[] = $entry;
        }

        usort($result, fn ($a, $b) => $a['id'] <=> $b['id']);

        $output->writeln('Table view configuration:');
        $output->writeln((string) json_encode($result, JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return 0;
    }
}
