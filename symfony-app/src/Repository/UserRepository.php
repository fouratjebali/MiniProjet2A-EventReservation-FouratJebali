<?php
namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Uid\Uuid;
use Webauthn\PublicKeyCredentialUserEntity;

class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
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

    public function findOneByUserHandle(string $userHandle): ?User
    {
        $userId = Uuid::fromBinary($userHandle)->toRfc4122();

        return $this->createQueryBuilder('u')
            ->where('u.id = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function createUserEntity(PublicKeyCredentialUserEntity $userEntity): User
    {
        $user = new User();
        $user->setId(Uuid::fromBinary($userEntity->getId())->toRfc4122());
        $user->setEmail($userEntity->getName());
        
        return $user;
    }
}
