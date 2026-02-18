<?php

namespace App\Core\Authentication\Libs;

use App\Core\Authentication\Models\User;
use App\Core\Orm\ACLModelRequester;
use App\Tests\AppTestCase;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Test as PHPUnitTest;
use PHPUnit\Framework\TestListener as PHPUnitTestListener;
use PHPUnit\Framework\TestSuite;
use PHPUnit\Framework\Warning;
use Throwable;

/**
 * @codeCoverageIgnore
 */
class TestListener implements PHPUnitTestListener
{
    public static array $userParams = [
        'email' => 'test@example.com',
        'password' => ['TestPassw0rd!', 'TestPassw0rd!'],
        'first_name' => 'Bob',
        'ip' => '127.0.0.1',
    ];

    private User $testUser;

    public function __construct()
    {
        /* Set a requester */

        $userContext = AppTestCase::getService('test.user_context');
        $user = new User();
        $userContext->set($user);

        // use the super user as the requester for model permissions
        ACLModelRequester::set($user);

        /* Set up a test user */

        $user = new User();

        $params = self::$userParams;
        if (property_exists($user, 'testUser')) {
            $params = array_replace($params, $user::$testUser);
        }

        /* Delete any existing test users */

        $existingUser = User::where('email', $params['email'])->oneOrNull();
        if ($existingUser) {
            $existingUser->delete();
        }

        /* Create the test user and set in current user context */

        $user->create($params);
        $user->setIsFullySignedIn();
        $userContext->set($user);
        $this->testUser = $user;
    }

    public function addError(PHPUnitTest $test, Throwable $e, float $time): void
    {
    }

    public function addFailure(PHPUnitTest $test, AssertionFailedError $e, float $time): void
    {
    }

    public function addIncompleteTest(PHPUnitTest $test, Throwable $e, float $time): void
    {
    }

    public function addRiskyTest(PHPUnitTest $test, Throwable $e, float $time): void
    {
    }

    public function addSkippedTest(PHPUnitTest $test, Throwable $e, float $time): void
    {
    }

    public function addWarning(PHPUnitTest $test, Warning $e, float $time): void
    {
    }

    public function startTest(PHPUnitTest $test): void
    {
    }

    public function endTest(PHPUnitTest $test, float $time): void
    {
    }

    public function startTestSuite(TestSuite $suite): void
    {
        AppTestCase::getService('test.user_context')->set($this->testUser);
    }

    public function endTestSuite(TestSuite $suite): void
    {
    }
}
