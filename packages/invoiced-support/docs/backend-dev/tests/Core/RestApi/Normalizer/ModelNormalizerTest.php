<?php

namespace App\Tests\Core\RestApi\Normalizer;

use App\CashApplication\Models\Payment;
use App\CashApplication\Models\Transaction;
use App\Companies\Models\Company;
use App\Core\RestApi\Normalizers\ModelApiNormalizer;
use App\PaymentProcessing\Models\Charge;
use App\Tests\AppTestCase;
use App\Tests\Core\RestApi\Address;
use App\Tests\Core\RestApi\Person;
use App\Tests\Core\RestApi\Post;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ModelNormalizerTest extends AppTestCase
{
    private function getNormalizer(Request $request): ModelApiNormalizer
    {
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new ModelApiNormalizer($requestStack);
    }

    public function testConstruct(): void
    {
        $req = new Request(['exclude' => 'exclude,these', 'include' => 'include,these', 'expand' => 'expand_this']);
        $normalizer = $this->getNormalizer($req);

        $this->assertEquals(['exclude', 'these'], $normalizer->getExclude());
        $this->assertEquals(['include', 'these'], $normalizer->getInclude());
        $this->assertEquals(['expand_this'], $normalizer->getExpand());
    }

    public function testGettersAndSetters(): void
    {
        $normalizer = $this->getNormalizer(new Request());

        $this->assertEquals($normalizer, $normalizer->setExclude(['exclude']));
        $this->assertEquals(['exclude'], $normalizer->getExclude());

        $this->assertEquals($normalizer, $normalizer->setInclude(['include']));
        $this->assertEquals(['include'], $normalizer->getInclude());

        $this->assertEquals($normalizer, $normalizer->setExpand(['expand']));
        $this->assertEquals(['expand'], $normalizer->getExpand());
    }

    public function testNormalize(): void
    {
        $normalizer = $this->getNormalizer(new Request());

        $model = new Post(['id' => 5]);
        $model->author = 6;
        $model->body = 'text';

        $expected = [
            'id' => 5,
            'author' => 6,
            'body' => 'text',
            'appended' => null,
            'hook' => true,
        ];

        $this->assertEquals($expected, $normalizer->normalize($model));
        $this->assertTrue($model::$without);
    }

    public function testNormalizeInvalidInput(): void
    {
        $normalizer = $this->getNormalizer(new Request());

        $this->assertNull($normalizer->normalize('blah'));
        $this->assertNull($normalizer->normalize(['blah']));
    }

    public function testNormalizeExcluded(): void
    {
        $model = new Post(['id' => 5, 'author' => 100]);

        $normalizer = $this->getNormalizer(new Request());
        $normalizer->setExclude(['id', 'body', 'appended', 'hook']);

        $expected = [
            'author' => 100,
        ];

        $this->assertEquals($expected, $normalizer->normalize($model));
    }

    public function testNormalizeIncluded(): void
    {
        $model = new Post(['id' => 5]);
        $model->body = 'text';
        $model->date = 'Dec 5, 2015';
        $author = new Person(['id' => 100]);
        $author->name = 'Bob';
        $author->email = 'bob@example.com';
        $author->active = false;
        $author->balance = 150;
        $author->created_at = 1;
        $author->updated_at = 2;
        $address = new Address(['id' => 200]);
        $address->street = '1234 Main St';
        $address->city = 'Austin';
        $address->state = 'TX';
        $address->created_at = 12345;
        $address->updated_at = 12345;
        $author->setRelation('address', $address);
        $model->setRelation('author', $author);

        $normalizer = $this->getNormalizer(new Request());
        $normalizer->setInclude(['date', 'person']);

        $expected = [
            'id' => 5,
            'author' => 100,
            'person' => [
                'id' => 100,
                'name' => 'Bob',
                'email' => 'bob@example.com',
                'address' => 200,
                'active' => false,
                'created_at' => 1,
                'updated_at' => 2,
            ],
            'body' => 'text',
            'date' => 'Dec 5, 2015',
            'appended' => null,
            'hook' => true,
        ];

        $this->assertEquals($expected, $normalizer->normalize($model));
    }

    public function testNormalizeExpandLegacy(): void
    {
        self::getService('test.tenant')->set(new Company());

        // Tests on Post object's author and Person's address objects
        // which use legacy relationships.
        $model = new Post(['id' => 10]);
        $model->body = 'text';
        $model->date = 3;
        $model->appended = '...'; /* @phpstan-ignore-line */
        $author = new Person(['id' => 100]);
        $author->name = 'Bob';
        $author->email = 'bob@example.com';
        $author->active = false;
        $author->balance = 150;
        $author->created_at = 1;
        $author->updated_at = 2;
        $address = new Address(['id' => 200]);
        $address->street = '1234 Main St';
        $address->city = 'Austin';
        $address->state = 'TX';
        $address->created_at = 12345;
        $address->updated_at = 12345;
        $author->setRelation('address', $address);
        $model->setRelation('author', $author);

        $normalizer = $this->getNormalizer(new Request());
        $normalizer->setExclude(['author.address.created_at'])
            ->setInclude(['author.balance', 'author.address.updated_at'])
            ->setExpand(['author.address', 'author.does_not_exist', 'author.id']);

        $result = $normalizer->normalize($model);

        $expected = [
            'id' => 10,
            'body' => 'text',
            'appended' => '...',
            'hook' => true,
            'author' => [
                'id' => 100,
                'name' => 'Bob',
                'email' => 'bob@example.com',
                'address' => [
                    'id' => 200,
                    'street' => '1234 Main St',
                    'city' => 'Austin',
                    'state' => 'TX',
                    'updated_at' => 12345,
                ],
                'balance' => 150,
                'active' => false,
                'created_at' => 1,
                'updated_at' => 2,
            ],
        ];

        $this->assertEquals($expected, $result);
    }

    public function testNormalizeExpand(): void
    {
        // Tests on Charge object which uses new 'belongs_to'
        // relationship on 'payment'.
        $payment = new Payment();
        $payment->amount = 200;
        $payment->currency = 'usd';

        // The local_key (payment_id) is set here to ensure ModelNormalizer::expand() is called.
        // The local_key value does not matter and does not have an affect on the test case.
        //
        // NOTE: `payment_id` needs to be set after `payment` in order to not reset the payment_id
        // value to null when `payment` is set. An alternative to this would be to set it directly
        // on the mock payment object above.
        $charge = new Charge();
        $charge->refunds = [];
        $charge->payment = $payment;
        $charge->payment_id = 1;

        $normalizer = $this->getNormalizer(new Request());
        $normalizer->setExclude([])
            ->setExpand(['payment']);
        $result = $normalizer->normalize($charge);

        $expected = [
            'ach_sender_id' => null,
            'amount' => 200,
            'balance' => null,
            'bank_feed_transaction' => null,
            'bank_feed_transaction_id' => null,
            'charge' => null,
            'created_at' => null,
            'currency' => 'usd',
            'customer' => null,
            'date' => 'now',
            'id' => null,
            'matched' => null,
            'metadata' => new \stdClass(),
            'method' => 'other',
            'notes' => null,
            'object' => 'payment',
            'pdf_url' => null,
            'reference' => null,
            'source' => 'keyed',
            'updated_at' => null,
            'voided' => null,
            'surcharge_percentage' => 0.0,
        ];

        $this->assertIsArray($result);
        $this->assertIsArray($result['payment']);
        $this->assertEquals($expected, $result['payment']);
    }

    public function testNormalizeMultiple(): void
    {
        $normalizer = $this->getNormalizer(new Request());
        $normalizer->setExclude(['address'])
            ->setInclude(['include'])
            ->setExpand(['expand']);

        $models = [];
        $expected = [];
        for ($i = 1; $i <= 5; ++$i) {
            $obj = new Person([
                'id' => $i,
                'body' => 'text',
                'author' => 100,
                'date' => 3,
                'appended' => '...',
            ]);
            $models[] = $obj;

            $expected[] = [
                'id' => $i,
                'email' => null,
                'name' => null,
                'active' => false,
                'include' => null,
                'created_at' => null,
                'updated_at' => null,
            ];
        }

        $result = $normalizer->normalize($models);

        $this->assertEquals($expected, $result);
    }

    public function testNormalizeWithRelationshipIds(): void
    {
        $normalizer = $this->getNormalizer(new Request());

        // Test that renaming of relationship foreign ID property is working
        $obj = new Transaction(['payment_id' => 1234, 'tenant_id' => -1]);
        $obj->setRelation('tenant_id', new Company());

        /** @var array $result */
        $result = $normalizer->normalize($obj);
        // TODO: We want to remove the original property name (i.e. payment_id)
        // This is not done yet for BC purposes until all usages
        // in the dashboard have been updated.
//        $this->assertTrue(!isset($result['payment_id']));
        $this->assertEquals(1234, $result['payment_id']);
        $this->assertEquals(1234, $result['payment']);
    }
}
