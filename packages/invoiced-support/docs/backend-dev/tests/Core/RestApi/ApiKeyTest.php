<?php

namespace App\Tests\Core\RestApi;

use App\Core\RestApi\Models\ApiKey;
use App\Tests\AppTestCase;

class ApiKeyTest extends AppTestCase
{
    private static ApiKey $apiKey;
    private static ApiKey $apiKey2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::hasCompany();
    }

    public function testCreate(): void
    {
        self::getService('test.tenant')->set(self::$company);

        // create a normal api key
        self::$apiKey = new ApiKey();
        $this->assertTrue(self::$apiKey->create([]));
        // verify secret
        $this->assertEquals(32, strlen(self::$apiKey->secret));
        $this->assertEquals(self::$apiKey->secret, self::$apiKey->secret_enc);
        // verify secret hash
        $this->assertNotEquals(self::$apiKey->secret, self::$apiKey->secret_hash);
        $hashed = hash_hmac('sha512', self::$apiKey->secret, (string) getenv('APP_SALT'));
        $this->assertEquals($hashed, self::$apiKey->secret_hash);
        $this->assertNull(self::$apiKey->expires);

        // create a protected api key
        self::$apiKey2 = new ApiKey();
        self::$apiKey2->protected = true;
        self::$apiKey2->expires = strtotime('+30 minutes');
        $this->assertTrue(self::$apiKey2->save());
        // verify secret
        $this->assertNotEquals(self::$apiKey->secret, self::$apiKey2->secret);
        $this->assertEquals(32, strlen(self::$apiKey2->secret));
        $this->assertEquals(self::$apiKey2->secret, self::$apiKey2->secret_enc);
        // verify secret hash
        $this->assertNotEquals(self::$apiKey2->secret, self::$apiKey2->secret_hash);
        $hashed = hash_hmac('sha512', self::$apiKey2->secret, (string) getenv('APP_SALT'));
        $this->assertEquals($hashed, self::$apiKey2->secret_hash);
    }

    /**
     * @depends testCreate
     */
    public function testSet(): void
    {
        self::$apiKey->description = 'CRM Integration';
        $this->assertTrue(self::$apiKey->save());

        self::$apiKey->tenant_id = -1;
        self::$apiKey->save();
        $this->assertNotEquals(-1, self::$apiKey->tenant_id);

        self::$apiKey->secret = 'not a secret';
        self::$apiKey->save();
        $this->assertNotEquals('not a secret', self::$apiKey->refresh()->secret);
    }

    /**
     * @depends testCreate
     */
    public function testQuery(): void
    {
        // should only be able to see normal api keys
        $keys = ApiKey::all();
        $this->assertCount(1, $keys);
        $this->assertEquals(self::$apiKey->id(), $keys[0]->id());
    }

    /**
     * @depends testCreate
     */
    public function testToArray(): void
    {
        $expected = [
            'id' => self::$apiKey->id(),
            'secret' => self::$apiKey->secret,
            'description' => 'CRM Integration',
            'last_used' => null,
            'created_at' => self::$apiKey->created_at,
            'updated_at' => self::$apiKey->updated_at,
        ];

        $this->assertEquals($expected, self::$apiKey->toArray());
    }

    /**
     * @depends testCreate
     */
    public function testGetFromSecret(): void
    {
        $this->assertNull(ApiKey::getFromSecret('blah'));

        $key = ApiKey::getFromSecret(self::$apiKey->secret);
        $this->assertInstanceOf(ApiKey::class, $key);
        $this->assertEquals(self::$apiKey->id(), $key->id());
    }

    /**
     * @depends testCreate
     */
    public function testGetFromSecretExpired(): void
    {
        self::$apiKey2->expires = strtotime('-30 minutes');
        $this->assertTrue(self::$apiKey2->save());
        $this->assertNull(ApiKey::getFromSecret(self::$apiKey2->secret));
    }

    /**
     * @depends testCreate
     */
    public function testDelete(): void
    {
        $this->assertTrue(self::$apiKey->delete());
        $this->assertFalse(self::$apiKey2->delete());
    }
}
