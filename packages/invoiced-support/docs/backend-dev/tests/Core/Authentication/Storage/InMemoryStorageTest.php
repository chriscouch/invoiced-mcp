<?php

namespace App\Tests\Core\Auth\Storage;

use App\Core\Authentication\Models\User;
use App\Core\Authentication\Storage\InMemoryStorage;
use App\Tests\AppTestCase;
use Symfony\Component\HttpFoundation\Request;

class InMemoryStorageTest extends AppTestCase
{
    public function testStorage(): void
    {
        $storage = new InMemoryStorage();

        $request = new Request();

        $this->assertNull($storage->getAuthenticatedUser($request));

        $user = new User(['id' => 10]);
        $storage->signIn($user, $request);

        $storage->markTwoFactorVerified($request);

        $user2 = $storage->getAuthenticatedUser($request);
        $this->assertEquals($user, $user2);

        $storage->markRemembered($request);

        $storage->signOut($request);

        $this->assertNull($storage->getAuthenticatedUser($request));
    }
}
