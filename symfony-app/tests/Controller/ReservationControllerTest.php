<?php

namespace App\Tests\Controller;

use App\Entity\Admin;
use App\Entity\Event;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ReservationControllerTest extends WebTestCase
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

    public function testCreateReservationRequiresAuth(): void
    {
        $this->jsonRequest('POST', '/api/reservations', [
            'event_id' => '00000000-0000-0000-0000-000000000000',
            'name' => 'Test User',
            'email' => 'test@test.com',
            'phone' => '+21612345678',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testCreateReservationEventNotFound(): void
    {
        $user = $this->registerUser();

        $this->jsonRequest('POST', '/api/reservations', [
            'event_id' => '00000000-0000-0000-0000-000000000000',
            'name' => 'Test User',
            'email' => $user['email'],
            'phone' => '+21612345678',
        ], $user['token']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $response = $this->getJsonResponse();
        $this->assertSame('Evenement non trouve', $response['error']);
    }

    public function testCreateReservationSuccess(): void
    {
        $event = $this->createEvent();
        $user = $this->registerUser();

        $this->jsonRequest('POST', '/api/reservations', [
            'event_id' => $event->getId(),
            'name' => 'Test User',
            'email' => $user['email'],
            'phone' => '+21612345678',
        ], $user['token']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $response = $this->getJsonResponse();

        $this->assertTrue($response['success']);
        $this->assertSame('Reservation creee avec succes', $response['message']);
        $this->assertSame($event->getId(), $response['reservation']['event']['id']);
        $this->assertSame($user['email'], $response['reservation']['email']);
    }

    public function testCreateReservationRejectsDuplicateReservationForSameUser(): void
    {
        $event = $this->createEvent();
        $user = $this->registerUser();
        $payload = [
            'event_id' => $event->getId(),
            'name' => 'Duplicate User',
            'email' => $user['email'],
            'phone' => '+21612345678',
        ];

        $this->jsonRequest('POST', '/api/reservations', $payload, $user['token']);
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->jsonRequest('POST', '/api/reservations', $payload, $user['token']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $response = $this->getJsonResponse();
        $this->assertSame('Vous avez deja une reservation pour cet evenement', $response['error']);
    }

    public function testMyReservationsRequiresAuth(): void
    {
        $this->client->request('GET', '/api/reservations/my-reservations');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testMyReservationsWithAuthReturnsOnlyCurrentUserReservations(): void
    {
        $event = $this->createEvent();
        $firstUser = $this->registerUser('first');
        $secondUser = $this->registerUser('second');

        $firstEntity = $this->findUserByEmail($firstUser['email']);
        $secondEntity = $this->findUserByEmail($secondUser['email']);

        $firstReservation = $this->createReservation($event, $firstEntity, $firstUser['email'], 'First User');
        $this->createReservation($event, $secondEntity, $secondUser['email'], 'Second User');

        $this->client->request('GET', '/api/reservations/my-reservations', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $firstUser['token'],
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();
        $response = $this->getJsonResponse();

        $this->assertArrayHasKey('reservations', $response);
        $this->assertCount(1, $response['reservations']);
        $this->assertSame($firstReservation->getId(), $response['reservations'][0]['id']);
    }

    public function testShowReservationRejectsAnotherUser(): void
    {
        $event = $this->createEvent();
        $owner = $this->registerUser('owner');
        $otherUser = $this->registerUser('other');

        $ownerEntity = $this->findUserByEmail($owner['email']);
        $reservation = $this->createReservation($event, $ownerEntity, $owner['email'], 'Owner User');

        $this->client->request('GET', '/api/reservations/' . $reservation->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $otherUser['token'],
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $response = $this->getJsonResponse();
        $this->assertSame('Acces refuse', $response['error']);
    }

    public function testCancelReservationSuccess(): void
    {
        $event = $this->createEvent();
        $user = $this->registerUser('cancel');
        $userEntity = $this->findUserByEmail($user['email']);
        $reservation = $this->createReservation($event, $userEntity, $user['email'], 'Cancel User');

        $this->client->request('POST', '/api/reservations/' . $reservation->getId() . '/cancel', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $user['token'],
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();
        $response = $this->getJsonResponse();

        $this->assertTrue($response['success']);
        $this->assertSame('Reservation annulee avec succes', $response['message']);
        $this->assertSame(Reservation::STATUS_CANCELLED, $response['reservation']['status']);
    }

    public function testByEventRequiresAdmin(): void
    {
        $event = $this->createEvent();

        $this->client->request('GET', '/api/reservations/event/' . $event->getId());

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        $adminToken = $this->createAdminToken();
        $this->client->request('GET', '/api/reservations/event/' . $event->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();
        $response = $this->getJsonResponse();
        $this->assertArrayHasKey('event', $response);
        $this->assertArrayHasKey('stats', $response);
        $this->assertSame($event->getId(), $response['event']['id']);
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

    /**
     * @return array{email:string, token:string}
     */
    private function registerUser(string $prefix = 'reservation_user'): array
    {
        $email = sprintf('%s_%s@test.com', $prefix, bin2hex(random_bytes(6)));

        $this->jsonRequest('POST', '/api/auth/register', [
            'email' => $email,
            'password' => 'Pass123!',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $user = $this->findUserByEmail($email);
        $user->setIsVerified(true);
        $this->entityManager->flush();

        return [
            'email' => $email,
            'token' => $this->jwtManager->create($user),
        ];
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

    private function createEvent(): Event
    {
        $event = new Event();
        $event->setTitle(sprintf('event_%s', bin2hex(random_bytes(6))));
        $event->setDescription('Description assez longue pour passer la validation.');
        $event->setDate(new \DateTime('+5 days'));
        $event->setLocation('Sousse');
        $event->setSeats(100);

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return $event;
    }

    private function findUserByEmail(string $email): User
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function createReservation(Event $event, User $user, string $email, string $name): Reservation
    {
        $managedEvent = $this->entityManager->getRepository(Event::class)->find($event->getId());
        $managedUser = $this->entityManager->getRepository(User::class)->find($user->getId());

        self::assertInstanceOf(Event::class, $managedEvent);
        self::assertInstanceOf(User::class, $managedUser);

        $reservation = new Reservation();
        $reservation->setEvent($managedEvent);
        $reservation->setUser($managedUser);
        $reservation->setName($name);
        $reservation->setEmail($email);
        $reservation->setPhone('+21612345678');

        $this->entityManager->persist($reservation);
        $this->entityManager->flush();

        return $reservation;
    }
}
