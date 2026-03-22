<?php

namespace App\Tests\Controller;

use App\Entity\Admin;
use App\Entity\Event;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class EventControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private JWTTokenManagerInterface $jwtManager;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();

        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->jwtManager = $container->get(JWTTokenManagerInterface::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);
    }

    public function testListEventsPublic(): void
    {
        $event = $this->createEvent([
            'title' => $this->uniqueTitle('public_list'),
        ]);

        $this->client->request('GET', '/api/events?limit=50');

        $this->assertResponseIsSuccessful();
        $response = $this->getJsonResponse();

        $this->assertArrayHasKey('events', $response);
        $this->assertArrayHasKey('pagination', $response);
        $this->assertContains(
            $event->getId(),
            array_column($response['events'], 'id')
        );
    }

    public function testListEventsWithPagination(): void
    {
        for ($i = 0; $i < 6; ++$i) {
            $this->createEvent([
                'title' => $this->uniqueTitle('page_' . $i),
                'date' => new \DateTime('+' . (20 + $i) . ' days'),
            ]);
        }

        $this->client->request('GET', '/api/events?page=1&limit=5');

        $this->assertResponseIsSuccessful();
        $response = $this->getJsonResponse();

        $this->assertSame(1, $response['pagination']['page']);
        $this->assertSame(5, $response['pagination']['limit']);
        $this->assertLessThanOrEqual(5, count($response['events']));
    }

    public function testListUpcomingEventsFiltersPastEvents(): void
    {
        $futureEvent = $this->createEvent([
            'title' => $this->uniqueTitle('future'),
            'date' => new \DateTime('+3 days'),
        ]);
        $this->createEvent([
            'title' => $this->uniqueTitle('past'),
            'date' => new \DateTime('-3 days'),
        ]);

        $this->client->request('GET', '/api/events?upcoming=true');

        $this->assertResponseIsSuccessful();
        $response = $this->getJsonResponse();
        $eventIds = array_column($response['events'], 'id');

        $this->assertContains($futureEvent->getId(), $eventIds);
        foreach ($response['events'] as $event) {
            $this->assertGreaterThan(new \DateTimeImmutable(), new \DateTimeImmutable($event['date']));
        }
    }

    public function testShowEvent(): void
    {
        $event = $this->createEvent([
            'title' => $this->uniqueTitle('show'),
        ]);

        $this->client->request('GET', '/api/events/' . $event->getId());

        $this->assertResponseIsSuccessful();
        $response = $this->getJsonResponse();

        $this->assertSame($event->getId(), $response['id']);
        $this->assertSame($event->getTitle(), $response['title']);
    }

    public function testShowEventNotFound(): void
    {
        $this->client->request('GET', '/api/events/00000000-0000-0000-0000-000000000000');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $response = $this->getJsonResponse();
        $this->assertSame('Evenement non trouve', $response['error']);
    }

    public function testCreateEventRequiresAuthentication(): void
    {
        $this->jsonRequest('POST', '/api/events', [
            'title' => $this->uniqueTitle('guest_create'),
            'description' => 'Description assez longue pour passer la validation.',
            'date' => (new \DateTimeImmutable('+1 day'))->format('Y-m-d H:i:s'),
            'location' => 'Sousse',
            'seats' => 100,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testCreateEventRejectsRegularUser(): void
    {
        $registerResponse = $this->registerUserAndReturnPayload();
        $token = $registerResponse['token'];

        $this->jsonRequest('POST', '/api/events', [
            'title' => $this->uniqueTitle('user_create'),
            'description' => 'Description assez longue pour passer la validation.',
            'date' => (new \DateTimeImmutable('+2 days'))->format('Y-m-d H:i:s'),
            'location' => 'Tunis',
            'seats' => 50,
        ], $token);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCreateEventAsAdminSucceeds(): void
    {
        $token = $this->createAdminToken();
        $title = $this->uniqueTitle('admin_create');

        $this->jsonRequest('POST', '/api/events', [
            'title' => $title,
            'description' => 'Description assez longue pour passer la validation.',
            'date' => (new \DateTimeImmutable('+4 days'))->format('Y-m-d H:i:s'),
            'location' => 'Monastir',
            'seats' => 120,
        ], $token);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $response = $this->getJsonResponse();

        $this->assertTrue($response['success']);
        $this->assertSame($title, $response['event']['title']);
        $this->assertSame(120, $response['event']['seats']);
    }

    private function jsonRequest(string $method, string $uri, array $payload, ?string $token = null): void
    {
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        if ($token !== null) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        $this->client->request(
            $method,
            $uri,
            [],
            [],
            $server,
            json_encode($payload, JSON_THROW_ON_ERROR)
        );
    }

    private function getJsonResponse(): array
    {
        $content = $this->client->getResponse()->getContent();

        return json_decode($content ?: '{}', true, 512, JSON_THROW_ON_ERROR);
    }

    private function registerUserAndReturnPayload(): array
    {
        $email = sprintf('event_user_%s@test.com', bin2hex(random_bytes(6)));

        $this->jsonRequest('POST', '/api/auth/register', [
            'email' => $email,
            'password' => 'Pass123!',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        return $this->getJsonResponse();
    }

    private function createAdminToken(): string
    {
        $admin = new Admin();
        $admin->setEmail(sprintf('admin_%s@test.com', bin2hex(random_bytes(6))));
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'Admin123!'));

        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        return $this->jwtManager->create($admin);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createEvent(array $overrides = []): Event
    {
        $event = new Event();
        $event->setTitle($overrides['title'] ?? $this->uniqueTitle('event'));
        $event->setDescription($overrides['description'] ?? 'Description assez longue pour passer la validation.');
        $event->setDate($overrides['date'] ?? new \DateTime('+5 days'));
        $event->setLocation($overrides['location'] ?? 'Sousse');
        $event->setSeats($overrides['seats'] ?? 100);

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return $event;
    }

    private function uniqueTitle(string $prefix): string
    {
        return sprintf('%s_%s', $prefix, bin2hex(random_bytes(6)));
    }
}
