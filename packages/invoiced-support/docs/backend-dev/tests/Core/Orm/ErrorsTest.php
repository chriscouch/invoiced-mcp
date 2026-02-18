<?php

/**
 * @author Jared King <j@jaredtking.com>
 *
 * @see http://jaredtking.com
 *
 * @copyright 2015 Jared King
 * @license MIT
 */

namespace App\Tests\Core\Orm;

use Exception;
use OutOfBoundsException;
use App\Core\Orm\Error;
use App\Core\Orm\Errors;
use App\Core\Orm\Translator;

class ErrorsTest extends ModelTestCase
{
    private function getErrorStack(): Errors
    {
        return new Errors();
    }

    public function testSetGlobalLocale(): void
    {
        $translator = new Translator();
        Errors::setTranslator($translator);
        $this->assertEquals($translator, (new Errors())->getTranslator());
    }

    public function testGetTranslator(): void
    {
        Errors::clearTranslator();
        $errorStack = new Errors();
        $this->assertInstanceOf(Translator::class, $errorStack->getTranslator());
        $translator = new Translator();
        $errorStack->setTranslator($translator);
        $this->assertEquals($translator, $errorStack->getTranslator());
    }

    public function testToString(): void
    {
        $errorStack = $this->getErrorStack();
        $errorStack->add('Error message 1');
        $errorStack->add('Error message 2');

        $this->assertEquals("Error message 1\nError message 2", $errorStack);
    }

    public function testAll(): void
    {
        $errorStack = $this->getErrorStack();

        // push some errors
        $this->assertEquals($errorStack, $errorStack->add('some_error'));
        $this->assertEquals($errorStack, $errorStack->add('pulsar.validation.failed', ['field_name' => 'Username']));
        $this->assertEquals($errorStack, $errorStack->add('some_error'));

        // check the result
        $expected = [
            'some_error',
            'Username is invalid',
            'some_error',
        ];

        $messages = $errorStack->all();
        $this->assertEquals(3, count($messages));
        $this->assertEquals($expected, $messages);
    }

    public function testAllWithoutLocale(): void
    {
        $errorStack = new Errors();
        $errorStack->add('pulsar.validation.failed', ['field_name' => 'test']);
        $this->assertEquals(['test is invalid'], $errorStack->all());
    }

    public function testAllFallback(): void
    {
        $errorStack = $this->getErrorStack();
        $errorStack->add('pulsar.validation.alpha', ['field_name' => 'Name']);
        $this->assertEquals(['Name only allows letters'], $errorStack->all());
    }

    public function testFind(): void
    {
        $errorStack = $this->getErrorStack();

        // push some errors
        $this->assertEquals($errorStack, $errorStack->add('some_error'));
        $this->assertEquals($errorStack, $errorStack->add('pulsar.validation.failed', ['field_name' => 'Username', 'field' => 'username']));
        $this->assertEquals($errorStack, $errorStack->add('some_error'));

        // check the result
        $error = $errorStack->find('username');
        $this->assertInstanceOf(Error::class, $error);
        $this->assertEquals('pulsar.validation.failed', $error->getError());
        $this->assertEquals('Username is invalid', $error->getMessage());
        $this->assertEquals([
            'field_name' => 'Username',
            'field' => 'username',
        ], $error->getContext());

        $error = $errorStack->find('Username', 'field_name');
        $this->assertInstanceOf(Error::class, $error);
        $this->assertEquals('Username is invalid', $error);

        $this->assertNull($errorStack->find('non-existent'));
    }

    public function testHas(): void
    {
        $errorStack = $this->getErrorStack();

        // push some errors
        $this->assertEquals($errorStack, $errorStack->add('some_error'));
        $this->assertEquals($errorStack, $errorStack->add('username_invalid', ['field' => 'username']));
        $this->assertEquals($errorStack, $errorStack->add('some_error'));

        // check the result
        $this->assertTrue($errorStack->has('username'));
        $this->assertTrue($errorStack->has('username', 'field'));

        $this->assertFalse($errorStack->has('non-existent'));
        $this->assertFalse($errorStack->has('username', 'something'));
    }

    public function testClear(): void
    {
        $errorStack = $this->getErrorStack();
        $this->assertEquals($errorStack, $errorStack->clear());
        $this->assertCount(0, $errorStack->all());
    }

    public function testIterator(): void
    {
        $errorStack = $this->getErrorStack();

        for ($i = 1; $i <= 5; ++$i) {
            $errorStack->add("$i");
        }

        $result = [];
        foreach ($errorStack as $k => $v) {
            $result[$k] = $v['error'];
        }

        $this->assertEquals(['1', '2', '3', '4', '5'], $result);
    }

    public function testCount(): void
    {
        $errorStack = $this->getErrorStack();

        $errorStack->add('Test');
        $this->assertCount(1, $errorStack);
    }

    public function testArrayAccess(): void
    {
        $errorStack = $this->getErrorStack();

        $errorStack[0] = 'test';
        $this->assertTrue(isset($errorStack[0]));
        $this->assertFalse(isset($errorStack[6]));

        $this->assertEquals('test', $errorStack[0]['error']);
        unset($errorStack[0]);
    }

    public function testArrayGetFail(): void
    {
        $this->expectException(OutOfBoundsException::class);

        $errorStack = $this->getErrorStack();

        echo $errorStack['invalid'];
    }

    public function testArraySetFail(): void
    {
        $this->expectException(Exception::class);

        $errorStack = $this->getErrorStack();

        $errorStack['invalid'] = 'test';
    }
}
