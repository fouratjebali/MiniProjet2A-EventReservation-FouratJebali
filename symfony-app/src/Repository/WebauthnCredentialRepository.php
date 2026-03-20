<?php

namespace App\Repository;

use App\Entity\WebauthnCredential;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;
use Webauthn\PublicKeyCredentialSource;

class WebauthnCredentialRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebauthnCredential::class);
    }

    public function saveCredential(User $user, PublicKeyCredentialSource $source, string $name = 'Default Key'): WebauthnCredential
    {
        $credential = new WebauthnCredential();
        $credential->setCredentialSource($source);
        $credential->setName($name);
        $user->addWebauthnCredential($credential);

        $this->getEntityManager()->persist($credential);
        $this->getEntityManager()->flush();

        return $credential;
    }

    public function findByCredentialId(string $credentialId): ?WebauthnCredential
    {
        return $this->findOneBy(['credentialId' => $credentialId]);
    }

    public function findAllForUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['createdAt' => 'DESC']);
    }

    public function findOneByPublicKeyCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource
    {
        $credential = $this->findByCredentialId(base64_encode($publicKeyCredentialId));
        
        return $credential ? $credential->getCredentialSource() : null;
    }

    public function findAllByUserHandle(string $userHandle): array
    {
        $userId = Uuid::fromBinary($userHandle)->toRfc4122();

        $qb = $this->createQueryBuilder('wc')
            ->join('wc.user', 'u')
            ->where('u.id = :userId')
            ->setParameter('userId', $userId);

        $credentials = $qb->getQuery()->getResult();

        return array_map(
            fn(WebauthnCredential $credential) => $credential->getCredentialSource(),
            $credentials
        );
    }

    public function saveCredentialSource(PublicKeyCredentialSource $source): void
    {
        $credential = $this->findByCredentialId(
            base64_encode($source->getPublicKeyCredentialId())
        );

        if ($credential) {
            $credential->setCredentialSource($source);
            $credential->touch();
            $this->getEntityManager()->flush();
        }
    }
}
