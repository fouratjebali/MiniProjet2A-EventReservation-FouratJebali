<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Entity\User;
use App\Repository\EventRepository;
use App\Repository\ReservationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/reservations', name: 'api_reservations_')]
class ReservationController extends AbstractController
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private EventRepository $eventRepository,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if (!$user instanceof User) {
            return $this->json([
                'error' => 'Authentification requise',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json([
                'error' => 'Le corps de la requete doit etre un JSON valide',
            ], Response::HTTP_BAD_REQUEST);
        }

        $missingField = $this->findMissingRequiredField($data, ['event_id', 'name', 'email', 'phone']);
        if ($missingField !== null) {
            return $this->json([
                'error' => "Le champ '{$missingField}' est requis",
            ], Response::HTTP_BAD_REQUEST);
        }

        $event = $this->eventRepository->find((string) $data['event_id']);
        if ($event === null) {
            return $this->json([
                'error' => 'Evenement non trouve',
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$event->isAvailable()) {
            return $this->json([
                'error' => 'Cet evenement n\'est plus disponible a la reservation',
                'reason' => $event->getAvailableSeats() <= 0 ? 'Complet' : 'Evenement passe',
            ], Response::HTTP_CONFLICT);
        }

        $existingReservation = $this->reservationRepository->findOneBy([
            'event' => $event,
            'user' => $user,
            'status' => Reservation::STATUS_CONFIRMED,
        ]);

        if ($existingReservation === null) {
            $existingReservation = $this->reservationRepository->findOneBy([
                'event' => $event,
                'email' => (string) $data['email'],
                'status' => Reservation::STATUS_CONFIRMED,
            ]);
        }

        if ($existingReservation !== null) {
            return $this->json([
                'error' => 'Vous avez deja une reservation pour cet evenement',
                'reservation' => $existingReservation->toArray(),
            ], Response::HTTP_CONFLICT);
        }

        try {
            $reservation = new Reservation();
            $reservation->setEvent($event);
            $reservation->setUser($user);
            $reservation->setName((string) $data['name']);
            $reservation->setEmail((string) $data['email']);
            $reservation->setPhone((string) $data['phone']);

            $errors = $this->validator->validate($reservation);
            if (count($errors) > 0) {
                return $this->json([
                    'error' => 'Validation echouee',
                    'details' => $this->formatValidationErrors($errors),
                ], Response::HTTP_BAD_REQUEST);
            }

            $this->entityManager->persist($reservation);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Reservation creee avec succes',
                'reservation' => $reservation->toArray(),
            ], Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'Erreur lors de la creation de la reservation',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/my-reservations', name: 'my_list', methods: ['GET'])]
    public function myReservations(): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if (!$user instanceof User) {
            return $this->json([
                'error' => 'Authentification requise',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $reservations = $this->reservationRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        return $this->json([
            'reservations' => array_map(
                static fn (Reservation $reservation) => $reservation->toArray(),
                $reservations
            ),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if (!$user instanceof User) {
            return $this->json([
                'error' => 'Authentification requise',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $reservation = $this->reservationRepository->find($id);
        if ($reservation === null) {
            return $this->json([
                'error' => 'Reservation non trouvee',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($reservation->getUser()?->getId() !== $user->getId()) {
            return $this->json([
                'error' => 'Acces refuse',
            ], Response::HTTP_FORBIDDEN);
        }

        return $this->json($reservation->toArray());
    }

    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST', 'PATCH'])]
    public function cancel(string $id): JsonResponse
    {
        $user = $this->getAuthenticatedUser();
        if (!$user instanceof User) {
            return $this->json([
                'error' => 'Authentification requise',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $reservation = $this->reservationRepository->find($id);
        if ($reservation === null) {
            return $this->json([
                'error' => 'Reservation non trouvee',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($reservation->getUser()?->getId() !== $user->getId()) {
            return $this->json([
                'error' => 'Acces refuse',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($reservation->getStatus() === Reservation::STATUS_CANCELLED) {
            return $this->json([
                'error' => 'Cette reservation est deja annulee',
            ], Response::HTTP_CONFLICT);
        }

        try {
            $reservation->cancel();
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Reservation annulee avec succes',
                'reservation' => $reservation->toArray(),
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'Erreur lors de l\'annulation',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/event/{eventId}', name: 'by_event', methods: ['GET'])]
    public function byEvent(string $eventId): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json([
                'error' => 'Acces refuse',
            ], Response::HTTP_FORBIDDEN);
        }

        $event = $this->eventRepository->find($eventId);
        if ($event === null) {
            return $this->json([
                'error' => 'Evenement non trouve',
            ], Response::HTTP_NOT_FOUND);
        }

        $reservations = $this->reservationRepository->findBy(
            ['event' => $event],
            ['createdAt' => 'DESC']
        );

        return $this->json([
            'event' => [
                'id' => $event->getId(),
                'title' => $event->getTitle(),
            ],
            'reservations' => array_map(
                static fn (Reservation $reservation) => $reservation->toArray(),
                $reservations
            ),
            'stats' => [
                'total' => count($reservations),
                'confirmed' => count(array_filter(
                    $reservations,
                    static fn (Reservation $reservation) => $reservation->getStatus() === Reservation::STATUS_CONFIRMED
                )),
                'cancelled' => count(array_filter(
                    $reservations,
                    static fn (Reservation $reservation) => $reservation->getStatus() === Reservation::STATUS_CANCELLED
                )),
            ],
        ]);
    }

    private function getAuthenticatedUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }

    private function findMissingRequiredField(array $data, array $requiredFields): ?string
    {
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $data) || trim((string) $data[$field]) === '') {
                return $field;
            }
        }

        return null;
    }

    private function formatValidationErrors(iterable $errors): array
    {
        $messages = [];

        foreach ($errors as $error) {
            $messages[$error->getPropertyPath()] = $error->getMessage();
        }

        return $messages;
    }
}
