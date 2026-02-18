<?php

namespace App\Tests\Imports\Importers;

use App\Core\Orm\Model;
use App\Imports\Interfaces\ImporterInterface;
use App\Imports\Models\Import;
use App\Tests\AppTestCase;
use DateTimeInterface;

abstract class ImporterTestBase extends AppTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::hasCompany();
    }

    abstract public function testRunCreate(): void;

    abstract protected function getLines(): array;

    abstract protected function getMapping(): array;

    abstract protected function getExpectedAfterBuild(): array;

    abstract protected function getImport(): Import;

    abstract protected function getImporter(): ImporterInterface;

    public function testBuild(): void
    {
        $mapping = $this->getMapping();
        $lines = $this->getLines();
        $options = [];
        $import = $this->getImport();

        $expected = $this->getExpectedAfterBuild();

        $result = $this->getImporter()->build($mapping, $lines, $options, $import);
        $result = $this->transformExpectedResult($result);

        $this->assertEquals($expected, $result);
    }

    protected function transformExpectedResult(array $result): array
    {
        return $this->convertExpected($result);
    }

    private function convertExpected(array $result): array
    {
        foreach ($result as &$row) {
            if (!is_array($row)) {
                continue;
            }

            foreach ($row as &$value) {
                if ($value instanceof Model) {
                    $value = $value->id();
                } elseif ($value instanceof DateTimeInterface) {
                    $value = $value->format('Y-m-d');
                } elseif (is_array($value)) {
                    $value = $this->convertExpected($value);
                }
            }
        }

        return $result;
    }
}
