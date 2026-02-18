<?php

namespace App\Command;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BuildEcsTaskDefinitionCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('deploy:ecs-task-definition')
            ->setDescription('Generates an ECS task definition file')
            ->addArgument(
                'task-type',
                InputArgument::REQUIRED,
                'Type of the task definition (cron, http, console, etc)'
            )
            ->addArgument(
                'environment',
                InputArgument::REQUIRED,
                'Name of the environment (staging, sandbox, production)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $environment = $input->getArgument('environment');
        if (!in_array($environment, ['production', 'sandbox', 'staging', 'custom'])) {
            throw new Exception('Invalid environment');
        }

        $type = $input->getArgument('task-type');
        $ecsDir = dirname(dirname(__DIR__)).'/ecs/';
        $dir = $ecsDir.$type;
        if (!file_exists($dir)) {
            throw new Exception('Invalid type');
        }

        $file = $dir.'/task-definition.'.$environment.'.json';
        if (!file_exists($file)) {
            throw new Exception('Task definition file missing: '.$file);
        }

        $contents = (string) file_get_contents($file);
        $taskDefinition = json_decode($contents);

        // Add in the secrets
        $secrets = (string) file_get_contents($ecsDir.'/secrets.json');
        $secretsJson = json_decode($secrets);
        foreach ($taskDefinition->containerDefinitions as &$containerDefinition) {
            if (isset($containerDefinition->secrets) && '%%secrets%%' == $containerDefinition->secrets) {
                $containerDefinition->secrets = $secretsJson;
            }
        }

        $output->writeln((string) json_encode($taskDefinition, JSON_PRETTY_PRINT));

        return 0;
    }
}
