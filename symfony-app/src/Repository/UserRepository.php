<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Uid\Uuid;
use Webauthn\Bundle\Repository\PublicKeyCredentialUserEntityRepositoryInterface;
use Webauthn\PublicKeyCredentialUserEntity;

class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface, PublicKeyCredentialUserEntityRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new \InvalidArgumentException('User must be an instance of App\Entity\User');
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function findOneByUsername(string $username): ?PublicKeyCredentialUserEntity
    {
        $user = $this->findOneBy(['email' => $username]);

        return $user?->toPublicKeyCredentialUserEntity();
    }

    public function findOneByUserHandle(string $userHandle): ?PublicKeyCredentialUserEntity
    {
        $userId = Uuid::fromBinary($userHandle)->toRfc4122();

        $user = $this->createQueryBuilder('u')
            ->where('u.id = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();

        return $user?->toPublicKeyCredentialUserEntity();
    }

    public function createUserEntity(PublicKeyCredentialUserEntity $userEntity): User
    {
        $user = new User();
        $user->setId(Uuid::fromBinary($userEntity->getId())->toRfc4122());
        $user->setEmail($userEntity->getName());
        
        return $user;
    }
}
