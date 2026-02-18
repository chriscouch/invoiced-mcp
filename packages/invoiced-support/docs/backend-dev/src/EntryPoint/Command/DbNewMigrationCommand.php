<?php

namespace App\EntryPoint\Command;

use Phinx\Console\PhinxApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DbNewMigrationCommand extends Command
{
    public function __construct(private string $databaseUrl, private string $projectDir, $name = null)
    {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('db:new-migration')
            ->setDescription('Run database migrations')
            ->addArgument(
                'name',
                InputArgument::REQUIRED,
                'Name of migration to create'
            )
            ->addArgument(
                'args',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'Optional arguments to pass to phinx'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');
        $args = $input->getArgument('args');

        $phinx = new PhinxApplication();
        $command = $phinx->find('create');
        putenv('PHINX_DATABASE_URL='.$this->databaseUrl);
        $path = $this->getMigrationsDir(date('Y/m'));
        putenv('PHINX_MIGRATION_PATH='.$path);

        $arguments = [
            'command' => 'create',
            'name' => $name,
            '--configuration' => $this->projectDir.'/phinx.php',
        ];
        $arguments = array_merge($arguments, $args);

        $input = new ArrayInput($arguments);

        return $command->run($input, $output);
    }

    /**
     * Gets the migrations directory for a path.
     */
    private function getMigrationsDir(string $path): string
    {
        $dir = $this->projectDir.'/assets/migrations/';
        $parts = explode('/', $path);
        foreach ($parts as $part) {
            $dir .= '/'.$part;
            if (!is_dir($dir)) {
                mkdir($dir);
            }
        }

        return $dir;
    }
}
