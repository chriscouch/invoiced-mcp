<?php

namespace App\EntryPoint\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

class DbMigrateCommand extends Command
{
    public function __construct(private string $environment, private string $databaseUrl, private string $projectDir, $name = null)
    {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('db:migrate')
            ->setDescription('Run database migrations')
            ->addArgument(
                'args',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'Optional arguments to pass to phinx'
            )
            ->addOption(
                'rollback',
                null,
                InputOption::VALUE_NONE,
                'Rolls back the last ran migration'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $migrateArgs = $input->getArgument('args');
        $rollback = $input->getOption('rollback');
        $result = $this->migrate($rollback, $migrateArgs, $input, $output);

        return $result ? 0 : 1;
    }

    /**
     * Runs all database migrations.
     *
     * @param array $migrateArgs optional arguments to pass to phinx
     */
    private function migrate(bool $rollback, array $migrateArgs, InputInterface $input, OutputInterface $output): bool
    {
        $start = microtime(true);

        $io = new SymfonyStyle($input, $output);

        $envForeground = 'green';
        if ('dev' == $this->environment) {
            $envForeground = 'blue';
        } elseif ('test' == $this->environment) {
            $envForeground = 'yellow';
        }

        $io->comment('Executing database migrations in <fg='.$envForeground.'>'.$this->environment.'</> environment');

        $success = $this->migrateWithPath($this->projectDir.'/assets/migrations/**/**', $rollback, $migrateArgs, $output);

        $time = number_format(microtime(true) - $start, 0);
        $io->comment('Migrations took <options=bold>'.$time.'s</> to complete');

        if ($success) {
            $io->success('All migrations succeeded');
        } else {
            $io->error('Migrations failed');
        }

        return $success;
    }

    private function migrateWithPath(string $path, bool $rollback, array $migrateArgs, OutputInterface $output): bool
    {
        $command = [
            'php',
            $this->projectDir.'/vendor/robmorgan/phinx/bin/phinx',
            $rollback ? 'rollback' : 'migrate',
        ];

        $command = array_merge($command, $migrateArgs, [
            '-c',
            $this->projectDir.'/phinx.php',
        ]);

        $process = new Process($command, null, [
            'PHINX_MIGRATION_PATH' => $path,
            'PHINX_DATABASE_URL' => $this->databaseUrl,
        ]);
        $process->setTimeout(3600);
        $process->run();

        // clean up the output when the command is successful
        if ($process->isSuccessful()) {
            $lines = explode("\n", $process->getOutput());
            foreach ($lines as $line) {
                // when migrating, only output lines starting
                // with ' =='
                if (!empty($line) && ' ==' == substr($line, 0, 3)) {
                    $output->writeln($line);
                }
            }

            return true;
        }

        // output STDOUT and STDERR verbatim
        $output->writeln($process->getOutput());
        if ($error = $process->getErrorOutput()) {
            $output->writeln($error);
        }

        return false;
    }
}
