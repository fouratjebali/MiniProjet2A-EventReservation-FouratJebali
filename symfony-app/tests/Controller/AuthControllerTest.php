<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class AuthControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testRegisterSuccess(): void
    {
        $email = $this->uniqueEmail('register');

        $this->jsonRequest('POST', '/api/auth/register', [
            'email' => $email,
            'password' => 'SecurePass123!',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $response = $this->getJsonResponse();

        $this->assertTrue($response['success']);
        $this->assertTrue($response['verification_sent']);
        $this->assertArrayHasKey('user', $response);
        $this->assertSame($email, $response['user']['email']);
        $this->assertArrayHasKey('id', $response['user']);
        $this->assertFalse($response['user']['is_verified']);
        $this->assertArrayHasKey('verification_url', $response);
        $this->assertStringContainsString('/api/auth/verify/email?', (string) $response['verification_url']);
    }

    public function testRegisterDuplicateEmail(): void
    {
        $email = $this->uniqueEmail('duplicate');
        $payload = [
            'email' => $email,
            'password' => 'Pass123!',
        ];

        $this->jsonRequest('POST', '/api/auth/register', $payload);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->jsonRequest('POST', '/api/auth/register', $payload);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $response = $this->getJsonResponse();
        $this->assertSame('Cet email est deja utilise', $response['error']);
    }

    public function testLoginRequiresVerifiedEmail(): void
    {
        $email = $this->uniqueEmail('login');
        $password = 'Pass123!';

        $this->jsonRequest('POST', '/api/auth/register', [
            'email' => $email,
            'password' => $password,
        ]);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->jsonRequest('POST', '/api/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $response = $this->getJsonResponse();
        $this->assertSame('Veuillez verifier votre adresse email avant de vous connecter', $response['error']);
        $this->assertFalse($response['is_verified']);
    }

    public function testLoginSuccessAfterEmailVerification(): void
    {
        $email = $this->uniqueEmail('verified_login');
        $password = 'Pass123!';

        $this->jsonRequest('POST', '/api/auth/register', [
            'email' => $email,
            'password' => $password,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $registerResponse = $this->getJsonResponse();

        $this->client->request('GET', $this->extractPathFromUrl($registerResponse['verification_url']));
        $this->assertResponseRedirects('/?verified=success');

        $this->jsonRequest('POST', '/api/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);

        $this->assertResponseIsSuccessful();
        $response = $this->getJsonResponse();

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('token', $response);
        $this->assertArrayHasKey('refresh_token', $response);
        $this->assertSame($email, $response['user']['email']);
        $this->assertTrue($response['user']['is_verified']);
    }

    public function testLoginInvalidCredentials(): void
    {
        $this->jsonRequest('POST', '/api/auth/login', [
            'email' => $this->uniqueEmail('missing'),
            'password' => 'WrongPass',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $response = $this->getJsonResponse();
        $this->assertSame('Identifiants invalides', $response['error']);
    }

    public function testMeEndpointRequiresAuth(): void
    {
        $this->client->request('GET', '/api/auth/me');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testMeEndpointWithValidToken(): void
    {
        $email = $this->uniqueEmail('me');
        $password = 'Pass123!';

        $this->jsonRequest('POST', '/api/auth/register', [
            'email' => $email,
            'password' => $password,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $registerResponse = $this->getJsonResponse();

        $user = $this->findUserByEmail($email);
        $user->setIsVerified(true);
        $this->entityManager->flush();

        $this->jsonRequest('POST', '/api/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);
        $token = $this->getJsonResponse()['token'];

        $this->client->request('GET', '/api/auth/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        $meResponse = $this->getJsonResponse();
        $this->assertSame($email, $meResponse['email']);
        $this->assertArrayHasKey('passkeys_count', $meResponse);
    }

    public function testVerifyEmailRouteMarksUserAsVerified(): void
    {
        $email = $this->uniqueEmail('verify_route');

        $this->jsonRequest('POST', '/api/auth/register', [
            'email' => $email,
            'password' => 'Pass123!',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $response = $this->getJsonResponse();

        $this->client->request('GET', $this->extractPathFromUrl($response['verification_url']));
        $this->assertResponseRedirects('/?verified=success');

        $this->entityManager->clear();
        $user = $this->findUserByEmail($email);
        $this->assertTrue($user->isVerified());
    }

    public function testPasskeyRegisterOptionsRequiresEmail(): void
    {
        $this->jsonRequest('POST', '/api/auth/passkey/register/options', []);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $response = $this->getJsonResponse();
        $this->assertSame('Email requis', $response['error']);
    }

    public function testPasskeyRegisterOptionsSuccess(): void
    {
        $email = $this->uniqueEmail('passkey');

        $this->jsonRequest('POST', '/api/auth/passkey/register/options', [
            'email' => $email,
        ]);

        $this->assertResponseIsSuccessful();
        $response = $this->getJsonResponse();

        $this->assertArrayHasKey('challenge', $response);
        $this->assertArrayHasKey('rp', $response);
        $this->assertArrayHasKey('user', $response);
        $this->assertArrayHasKey('pubKeyCredParams', $response);
        $this->assertSame($email, $response['user']['name']);
        $this->assertIsArray($response['excludeCredentials']);
    }

    private function jsonRequest(string $method, string $uri, array $payload): void
    {
        $this->client->request(
            $method,
            $uri,
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            json_encode($payload, JSON_THROW_ON_ERROR)
        );
    }

    private function getJsonResponse(): array
    {
        $content = $this->client->getResponse()->getContent();

        return json_decode($content ?: '{}', true, 512, JSON_THROW_ON_ERROR);
    }

    private function uniqueEmail(string $prefix): string
    {
        return sprintf('%s_%s@test.com', $prefix, bin2hex(random_bytes(6)));
    }

    private function extractPathFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $query = parse_url($url, PHP_URL_QUERY);

        return $query ? $path . '?' . $query : (string) $path;
    }

    private function findUserByEmail(string $email): User
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        self::assertInstanceOf(User::class, $user);

        return $user;
    }
}
