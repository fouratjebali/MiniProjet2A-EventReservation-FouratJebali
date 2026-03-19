<?php
namespace App\DataFixtures;

use App\Entity\Event;
use App\Entity\Admin;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        // Admin par défaut
        $admin = new Admin();
        $admin->setEmail('admin@event.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);

        // Événements de test
        for ($i = 1; $i <= 5; $i++) {
            $event = new Event();
            $event->setTitle("Événement Test $i");
            $event->setDescription("Description de l'événement test numéro $i");
            $event->setDate(new \DateTime("+$i days"));
            $event->setLocation("Lieu $i, Sousse");
            $event->setSeats(100);
            $manager->persist($event);
        }

        $manager->flush();
    }
}