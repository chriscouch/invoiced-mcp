<?php

namespace App\Tests\Core\Auth\Libs;

use App\Core\Authentication\Saml\SamlResponseSimplified;
use App\Core\Authentication\Saml\SamlResponseSimplifiedFactory;
use App\Tests\AppTestCase;
use OneLogin\Saml2\ValidationError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class SamlResponseSimplifiedFactoryTest extends AppTestCase
{
    public function testMain(): void
    {
        $requestStack = \Mockery::mock(RequestStack::class);
        $requestStack->shouldReceive('getCurrentRequest')
            ->andReturn(null)
            ->once();

        $factory = new SamlResponseSimplifiedFactory($requestStack);

        try {
            $factory->getInstance();
            $this->assertTrue('No exception thrown');
        } catch (ValidationError $e) {
            $this->assertEquals('No Request specified', $e->getMessage());
        }

        $request = new Request();
        $request->request->set('SAMLResponse', base64_encode('<test></test>'));

        $requestStack->shouldReceive('getCurrentRequest')
            ->andReturn($request)
            ->once();

        $factory = new SamlResponseSimplifiedFactory($requestStack);
        $this->assertInstanceOf(SamlResponseSimplified::class, $factory->getInstance());
    }
}
