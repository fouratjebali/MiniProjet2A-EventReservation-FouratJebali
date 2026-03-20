<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Webauthn\PublicKeyCredentialSource;

#[ORM\Entity(repositoryClass: 'App\Repository\WebauthnCredentialRepository')]
#[ORM\Table(name: 'webauthn_credentials')]
class WebauthnCredential
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'webauthnCredentials')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'text')]
    private string $credentialData;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $lastUsedAt;

    #[ORM\Column(length: 255, unique: true)]
    private string $credentialId;

    public function __construct()
    {
        $this->id = self::generateUuid();
        $this->createdAt = new \DateTimeImmutable();
        $this->lastUsedAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getCredentialSource(): PublicKeyCredentialSource
    {
        $data = json_decode($this->credentialData, true);
        return PublicKeyCredentialSource::createFromArray($data);
    }

    public function setCredentialSource(PublicKeyCredentialSource $source): self
    {
        $this->credentialData = json_encode($source);
        $this->credentialId = base64_encode($source->getPublicKeyCredentialId());
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastUsedAt(): \DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function touch(): self
    {
        $this->lastUsedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getCredentialId(): string
    {
        return $this->credentialId;
    }

    private static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
