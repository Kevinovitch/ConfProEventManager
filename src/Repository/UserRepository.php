<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Find moderators ordered by the number of conferences they moderate
     */
    public function findModeratorsOrderedByWorkload(): ?User
    {
        $result = $this->createQueryBuilder('u')
            ->leftJoin('u.moderatedConferences', 'c')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%"ROLE_MODERATOR"%')
            ->groupBy('u.id')
            ->orderBy('COUNT(c.id)', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    /**
     * Find the total of presenters
     */
    public function countPresenters(): int
    {
        return $this->createQueryBuilder('u')
            ->select('COUNT(DISTINCT u.id)')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%"ROLE_PRESENTER"%')
            ->getQuery()
            ->getSingleScalarResult();
    }

}