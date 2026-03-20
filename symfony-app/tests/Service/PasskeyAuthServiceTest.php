<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\WebauthnCredentialRepository;
use App\Service\PasskeyAuthService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class PasskeyAuthServiceTest extends KernelTestCase
{
    private PasskeyAuthService $passkeyService;
    private WebauthnCredentialRepository $credentialRepository;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $requestStack = $container->get(RequestStack::class);

        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack->push($request);

        $this->credentialRepository = $this->createStub(WebauthnCredentialRepository::class);
        $this->credentialRepository
            ->method('findAllForUser')
            ->willReturn([]);

        $this->passkeyService = new PasskeyAuthService(
            $requestStack,
            $this->credentialRepository,
            $_ENV['WEBAUTHN_RP_NAME'] ?? 'Event Reservation App',
            $_ENV['APP_DOMAIN'] ?? 'localhost:8080'
        );
    }

    protected function tearDown(): void
    {
        $requestStack = static::getContainer()->get(RequestStack::class);
        $requestStack->pop();

        parent::tearDown();
    }

    public function testGetRegistrationOptions(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');

        $options = $this->passkeyService->getRegistrationOptions($user);

        $this->assertSame('test@example.com', $options->user->name);
        $this->assertNotEmpty($options->challenge);
        $this->assertNotNull($this->passkeyService->getStoredRegistrationChallenge());
    }

    public function testGetLoginOptions(): void
    {
        $options = $this->passkeyService->getLoginOptions();

        $this->assertNotEmpty($options->challenge);
        $this->assertNotNull($this->passkeyService->getStoredLoginChallenge());
    }
}
