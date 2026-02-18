<?php

namespace App\Tests\Controller;

use App\Core\Authentication\Exception\AuthException;
use App\Core\Authentication\Libs\SAMLAvailableCompanies;
use App\Core\Authentication\Models\CompanySamlSettings;
use App\Core\Authentication\Models\User;
use App\Core\Authentication\Saml\SamlAuthFactory;
use App\Core\Mailer\Mailer;
use App\EntryPoint\Controller\AuthController;
use App\Tests\AppTestCase;
use Mockery;
use OneLogin\Saml2\Auth;
use Symfony\Component\HttpFoundation\Request;

class AuthControllerTest extends AppTestCase
{
    public function testSso(): void
    {
        self::hasCompany();

        $controller = new AuthController();
        $url = 'https://example.com';
        $factory = Mockery::mock(SamlAuthFactory::class);
        $auth = Mockery::mock(Auth::class);
        $factory->shouldReceive('get')->andReturn($auth);
        $mailer = Mockery::mock(Mailer::class);
        $companies = Mockery::mock(SAMLAvailableCompanies::class);

        $request = new Request();
        $response = $controller->sso($request, $url, $factory, $mailer, $companies);
        $this->assertEquals('https://example.com/login/sso?error=For+security+reasons+we+must+perform+additional+verification+on+your+account.&email=&is_warning=1', $response->getTargetUrl());

        $request->query->set('email', 'random');
        $response = $controller->sso($request, $url, $factory, $mailer, $companies);
        $this->assertEquals('https://example.com/login/sso?error=The+requested+user+can+not+be+found.&email=random&is_warning=0', $response->getTargetUrl());

        /** @var User $user */
        $user = User::oneOrNull();
        $request->query->set('email', $user->email);
        $companies->shouldReceive('get')->andReturn([])->once();
        $response = $controller->sso($request, $url, $factory, $mailer, $companies);
        $this->assertEquals('https://example.com/login/sso?error=The+requested+user+can+not+be+found.&email='.rawurlencode($user->email).'&is_warning=0', $response->getTargetUrl());

        $samlSettings1 = new CompanySamlSettings();
        $samlSettings1->cert = 'test1';
        $samlSettings1->company_id = self::$company->id;
        $samlSettings2 = new CompanySamlSettings();
        $samlSettings2->cert = 'test2';
        $samlSettings2->company_id = self::$company->id;

        $companies->shouldReceive('get')->andReturn([
            $samlSettings1,
            $samlSettings2,
        ])->times(3);

        $mailer->shouldReceive('sendToUser')->withArgs(function (User $user, array $email, string $template, array $data) {
            $this->assertEquals([
                'subject' => 'Please select the company to log in',
            ], $email);
            $this->assertEquals('sso-select-company', $template);
            $this->assertEquals(self::$company->id, $data['companies'][0]['id']);

            return true;
        })->once();
        $response = $controller->sso($request, $url, $factory, $mailer, $companies);
        $this->assertEquals('https://example.com/login/sso?error=You+have+multiply+companies+we+have+sent+you+email+to+select+the+company+you+for+log+in.&email='.rawurlencode($user->email).'&is_warning=1', $response->getTargetUrl());

        $samlSettings2->cert = 'test1';

        $auth->shouldReceive('login')->andThrow(new AuthException('test'))->once();
        $response = $controller->sso($request, $url, $factory, $mailer, $companies);
        $this->assertEquals('https://example.com/login/sso?error=test&email='.rawurlencode($user->email).'&is_warning=0', $response->getTargetUrl());

        $auth->shouldReceive('login')->once();
        $response = $controller->sso($request, $url, $factory, $mailer, $companies);
        $this->assertEquals('https://example.com/login/sso?error=&email='.rawurlencode($user->email).'&is_warning=0', $response->getTargetUrl());
    }
}
