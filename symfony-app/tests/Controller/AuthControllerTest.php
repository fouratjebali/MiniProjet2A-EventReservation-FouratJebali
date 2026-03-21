<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class AuthControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
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
        $this->assertArrayHasKey('token', $response);
        $this->assertArrayHasKey('refresh_token', $response);
        $this->assertArrayHasKey('user', $response);
        $this->assertSame($email, $response['user']['email']);
        $this->assertArrayHasKey('id', $response['user']);
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

    public function testLoginSuccess(): void
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

        $this->assertResponseIsSuccessful();
        $response = $this->getJsonResponse();

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('token', $response);
        $this->assertArrayHasKey('refresh_token', $response);
        $this->assertSame($email, $response['user']['email']);
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
        $token = $registerResponse['token'];

        $this->client->request('GET', '/api/auth/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();
        $meResponse = $this->getJsonResponse();
        $this->assertSame($email, $meResponse['email']);
        $this->assertArrayHasKey('passkeys_count', $meResponse);
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
}
