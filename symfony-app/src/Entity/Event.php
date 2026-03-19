<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Entity]
#[ORM\Table(name: 'events')]
class Event
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    private string $id;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $date;

    #[ORM\Column(length: 255)]
    private string $location;

    #[ORM\Column(type: 'integer')]
    private int $seats;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\OneToMany(mappedBy: 'event', targetEntity: Reservation::class, cascade: ['remove'])]
    private Collection $reservations;

    public function __construct()
    {
        $this->id = self::generateUuid();
        $this->reservations = new ArrayCollection();
    }

    private static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
