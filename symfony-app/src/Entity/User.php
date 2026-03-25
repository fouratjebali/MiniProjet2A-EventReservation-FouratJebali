<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Uid\Uuid;
use Webauthn\PublicKeyCredentialUserEntity;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID, unique: true)]
    private string $id;

    #[ORM\Column(length: 180, unique: true)]
    private string $email;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private string $password;

    #[ORM\Column(options: ['default' => false])]
    private bool $isVerified = false;

    #[ORM\OneToMany(
        mappedBy: 'user',
        targetEntity: WebauthnCredential::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    private Collection $webauthnCredentials;

    public function __construct()
    {
        $this->id = self::generateUuid();
        $this->webauthnCredentials = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    /**
     * @return Collection<int, WebauthnCredential>
     */
    public function getWebauthnCredentials(): Collection
    {
        return $this->webauthnCredentials;
    }

    public function addWebauthnCredential(WebauthnCredential $credential): static
    {
        if (!$this->webauthnCredentials->contains($credential)) {
            $this->webauthnCredentials->add($credential);
            $credential->setUser($this);
        }

        return $this;
    }

    public function removeWebauthnCredential(WebauthnCredential $credential): static
    {
        $this->webauthnCredentials->removeElement($credential);

        return $this;
    }

    public function toPublicKeyCredentialUserEntity(): PublicKeyCredentialUserEntity
    {
        return new PublicKeyCredentialUserEntity(
            $this->email,
            Uuid::fromString($this->id)->toBinary(),
            $this->email
        );
    }

    private static function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
