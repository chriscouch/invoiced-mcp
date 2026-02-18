<?php

namespace App\Tests\Core\Cron;

use App\Core\Cron\ValueObjects\Run;
use App\Tests\AppTestCase;
use Mockery;

class RunTest extends AppTestCase
{
    public function testWriteOutput(): void
    {
        $run = new Run();
        $this->assertEquals('', $run->getOutput());
        $run->writeOutput('');
        $run->writeOutput('');
        $run->writeOutput('1');
        $run->writeOutput('2');
        $run->writeOutput('3');
        $this->assertEquals("1\n2\n3", $run->getOutput());
    }

    public function testWriteOutputWithConsole(): void
    {
        $console = Mockery::mock('Symfony\Component\Console\Output\OutputInterface');
        $console->shouldReceive('writeln')
            ->times(3);

        $run = new Run();
        $run->setConsoleOutput($console);
        $run->writeOutput('');
        $run->writeOutput('');
        $run->writeOutput('1');
        $run->writeOutput('2');
        $run->writeOutput('3');
        $this->assertEquals("1\n2\n3", $run->getOutput());
    }

    public function testGetResult(): void
    {
        $run = new Run();
        $run->setResult('1');
        $this->assertEquals('1', $run->getResult());
    }

    public function testSucceeded(): void
    {
        $run = new Run();
        $this->assertFalse($run->succeeded());
        $run->setResult(Run::RESULT_SUCCEEDED);
        $this->assertTrue($run->succeeded());
    }

    public function testFailed(): void
    {
        $run = new Run();
        $this->assertFalse($run->failed());
        $run->setResult(Run::RESULT_FAILED);
        $this->assertTrue($run->failed());
    }
}
