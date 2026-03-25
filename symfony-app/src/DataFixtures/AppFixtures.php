<?php

namespace App\DataFixtures;

use App\Entity\Admin;
use App\Entity\Event;
use App\Entity\Reservation;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = new Admin();
        $admin->setEmail('admin@event.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);

        $users = [];
        for ($i = 1; $i <= 5; ++$i) {
            $user = new User();
            $user->setEmail("user{$i}@test.com");
            $user->setRoles(['ROLE_USER']);
            $user->setPassword($this->passwordHasher->hashPassword($user, 'user123'));
            $user->setIsVerified(true);
            $manager->persist($user);
            $users[] = $user;
        }

        $eventData = [
            [
                'title' => 'Conference Tech 2026',
                'description' => 'Grande conference annuelle sur les nouvelles technologies et l\'innovation. Intervenants de renommee internationale, ateliers pratiques et networking.',
                'date' => new \DateTime('+10 days'),
                'location' => 'Palais des Congres, Sousse',
                'seats' => 150,
                'image' => 'conference-tech-2026.jpg',
            ],
            [
                'title' => 'Concert Jazz Under The Stars',
                'description' => 'Soiree jazz en plein air avec des artistes locaux et internationaux. Ambiance conviviale et decontractee sous les etoiles.',
                'date' => new \DateTime('+15 days'),
                'location' => 'Theatre de Verdure, Tunis',
                'seats' => 200,
                'image' => 'concert-jazz-stars.jpg',
            ],
            [
                'title' => 'Workshop Developpement Mobile',
                'description' => 'Atelier pratique de 2 jours sur le developpement d\'applications mobiles avec Flutter. Niveau intermediaire requis.',
                'date' => new \DateTime('+7 days'),
                'location' => 'ISSAT Sousse',
                'seats' => 30,
                'image' => 'workshop-mobile.jpg',
            ],
            [
                'title' => 'Salon de l\'Entrepreneuriat',
                'description' => 'Rencontrez des startups innovantes, participez a des pitchs et decouvrez les opportunites d\'investissement.',
                'date' => new \DateTime('+20 days'),
                'location' => 'Centre d\'Affaires, Sfax',
                'seats' => 300,
                'image' => 'salon-entrepreneuriat.jpg',
            ],
            [
                'title' => 'Festival de Cinema Documentaire',
                'description' => 'Projection de documentaires primes suivie de discussions avec les realisateurs. 3 jours d\'immersion cinematographique.',
                'date' => new \DateTime('+30 days'),
                'location' => 'Cinema Le Colisee, Tunis',
                'seats' => 120,
                'image' => 'festival-documentaire.jpg',
            ],
            [
                'title' => 'Marathon de Programmation 24h',
                'description' => 'Hackathon intense sur 24h. Formez votre equipe et developpez un projet innovant pour gagner des prix.',
                'date' => new \DateTime('+5 days'),
                'location' => 'TechHub Sousse',
                'seats' => 80,
                'image' => 'marathon-programmation.jpg',
            ],
            [
                'title' => 'Soiree Gastronomique Mediterraneenne',
                'description' => 'Decouvrez les saveurs de la Mediterranee avec un menu degustation prepare par des chefs renommes.',
                'date' => new \DateTime('+12 days'),
                'location' => 'Restaurant La Medina, Hammamet',
                'seats' => 50,
                'image' => 'soiree-gastronomique.jpg',
            ],
            [
                'title' => 'Evenement Passe - Archive',
                'description' => 'Cet evenement est deja passe et sert de test pour les filtres.',
                'date' => new \DateTime('-10 days'),
                'location' => 'Quelque part',
                'seats' => 100,
                'image' => 'evenement-archive.jpg',
            ],
        ];

        $events = [];
        foreach ($eventData as $data) {
            $event = new Event();
            $event->setTitle($data['title']);
            $event->setDescription($data['description']);
            $event->setDate($data['date']);
            $event->setLocation($data['location']);
            $event->setSeats($data['seats']);
            $event->setImage($data['image'] ?? null);
            $event->setCreatedBy($admin);

            $manager->persist($event);
            $events[] = $event;
        }

        for ($i = 0; $i < 15; ++$i) {
            $reservation = new Reservation();
            $reservation->setEvent($events[array_rand($events)]);
            $reservation->setUser($users[array_rand($users)]);
            $reservation->setName('Utilisateur Test ' . ($i + 1));
            $reservation->setEmail("reservation{$i}@test.com");
            $reservation->setPhone('+216' . random_int(20000000, 99999999));

            if (random_int(1, 10) === 1) {
                $reservation->cancel();
            }

            $manager->persist($reservation);
        }

        $manager->flush();
    }
}
