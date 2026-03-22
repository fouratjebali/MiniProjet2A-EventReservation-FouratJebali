<?php

namespace App\Controller;

use App\Entity\Admin;
use App\Entity\Event;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/events', name: 'api_events_')]
class EventController extends AbstractController
{
    public function __construct(
        private EventRepository $eventRepository,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(50, max(1, $request->query->getInt('limit', 10)));
        $offset = ($page - 1) * $limit;

        $upcoming = $request->query->getBoolean('upcoming', false);
        $available = $request->query->getBoolean('available', false);

        $qb = $this->eventRepository->createQueryBuilder('e')
            ->orderBy('e.date', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        if ($upcoming || $available) {
            $qb->andWhere('e.date > :now')
                ->setParameter('now', new \DateTime());
        }

        if ($available) {
            $qb->andWhere('e.seats > 0');
        }

        $events = $qb->getQuery()->getResult();
        $total = $this->eventRepository->count([]);

        return $this->json([
            'events' => array_map(static fn (Event $event) => $event->toArray(), $events),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int) ceil($total / $limit),
            ],
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $event = $this->eventRepository->find($id);

        if (!$event instanceof Event) {
            return $this->json([
                'error' => 'Evenement non trouve',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json($event->toArray());
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json([
                'error' => 'Acces refuse. Seuls les administrateurs peuvent creer des evenements.',
            ], Response::HTTP_FORBIDDEN);
        }

        $data = $this->getRequestData($request);
        $missingField = $this->findMissingRequiredField($data, ['title', 'description', 'date', 'location', 'seats']);

        if ($missingField !== null) {
            return $this->json([
                'error' => sprintf("Le champ '%s' est requis", $missingField),
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $event = new Event();
            $this->hydrateEvent($event, $data);

            $imageFile = $request->files->get('imageFile');
            if ($imageFile instanceof UploadedFile) {
                $event->setImageFile($imageFile);
            }

            $user = $this->getUser();
            if ($user instanceof Admin) {
                $event->setCreatedBy($user);
            }

            $errors = $this->validator->validate($event);
            if (count($errors) > 0) {
                return $this->json([
                    'error' => 'Validation echouee',
                    'details' => $this->formatValidationErrors($errors),
                ], Response::HTTP_BAD_REQUEST);
            }

            $this->entityManager->persist($event);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Evenement cree avec succes',
                'event' => $event->toArray(),
            ], Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'Erreur lors de la creation',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'update', methods: ['POST', 'PUT', 'PATCH'])]
    public function update(string $id, Request $request): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json([
                'error' => 'Acces refuse',
            ], Response::HTTP_FORBIDDEN);
        }

        $event = $this->eventRepository->find($id);
        if (!$event instanceof Event) {
            return $this->json([
                'error' => 'Evenement non trouve',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $data = $this->getRequestData($request);
            $this->hydrateEvent($event, $data);

            $imageFile = $request->files->get('imageFile');
            if ($imageFile instanceof UploadedFile) {
                $event->setImageFile($imageFile);
            }

            $errors = $this->validator->validate($event);
            if (count($errors) > 0) {
                return $this->json([
                    'error' => 'Validation echouee',
                    'details' => $this->formatValidationErrors($errors),
                ], Response::HTTP_BAD_REQUEST);
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Evenement mis a jour',
                'event' => $event->toArray(),
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'Erreur lors de la mise a jour',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->json([
                'error' => 'Acces refuse',
            ], Response::HTTP_FORBIDDEN);
        }

        $event = $this->eventRepository->find($id);
        if (!$event instanceof Event) {
            return $this->json([
                'error' => 'Evenement non trouve',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($event->getReservations()->count() > 0) {
            return $this->json([
                'error' => 'Impossible de supprimer un evenement avec des reservations',
                'reservations_count' => $event->getReservations()->count(),
            ], Response::HTTP_CONFLICT);
        }

        try {
            $this->entityManager->remove($event);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Evenement supprime avec succes',
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'Erreur lors de la suppression',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getRequestData(Request $request): array
    {
        $contentType = (string) $request->headers->get('Content-Type', '');

        if (str_starts_with($contentType, 'application/json')) {
            $data = json_decode($request->getContent(), true);

            return is_array($data) ? $data : [];
        }

        return $request->request->all();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hydrateEvent(Event $event, array $data): void
    {
        if (array_key_exists('title', $data)) {
            $event->setTitle((string) $data['title']);
        }

        if (array_key_exists('description', $data)) {
            $event->setDescription((string) $data['description']);
        }

        if (array_key_exists('date', $data) && $data['date'] !== null && $data['date'] !== '') {
            $event->setDate(new \DateTime((string) $data['date']));
        }

        if (array_key_exists('location', $data)) {
            $event->setLocation((string) $data['location']);
        }

        if (array_key_exists('seats', $data)) {
            $event->setSeats((int) $data['seats']);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param string[] $requiredFields
     */
    private function findMissingRequiredField(array $data, array $requiredFields): ?string
    {
        foreach ($requiredFields as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
                return $field;
            }
        }

        return null;
    }

    private function formatValidationErrors(iterable $errors): array
    {
        $errorMessages = [];

        foreach ($errors as $error) {
            $errorMessages[$error->getPropertyPath()] = $error->getMessage();
        }

        return $errorMessages;
    }
}
