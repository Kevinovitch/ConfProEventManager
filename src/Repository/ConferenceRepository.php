<?php

namespace App\Repository;

use App\Entity\Conference;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conference>
 */
class ConferenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conference::class);
    }

    /**
     * Find conferences by status
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.status = :status')
            ->setParameter('status', $status)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find upcoming conferences
     *
     * @param int|null $int Number of results to limit, null for no limit
     */
    public function findUpcoming(?int $int = null): array
    {
        $now = new \DateTimeImmutable();

        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.status = :status')
            ->andWhere('c.scheduledAt > :now')
            ->setParameter('status', Conference::STATUS_SCHEDULED)
            ->setParameter('now', $now)
            ->orderBy('c.scheduledAt', 'ASC');

        if ($int !== null) {
            $qb->setMaxResults($int);
        }

        return $qb
            ->getQuery()
            ->getResult();
    }



    /**
     * Find conferences scheduled within a given date range
     */
    public function findUpcomingConferences(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.sessions', 's')
            ->where('c.status = :status')
            ->andWhere('s.startTime >= :startDate')
            ->andWhere('s.startTime <= :endDate')
            ->setParameter('status', Conference::STATUS_SCHEDULED)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->groupBy('c.id')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find conferences whose last session ended within a given date range
     */
    public function findPastConferences(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.sessions', 's')
            ->where('c.status = :status')
            ->andWhere('s.endTime >= :startDate')
            ->andWhere('s.endTime <= :endDate')
            ->setParameter('status', Conference::STATUS_SCHEDULED)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->groupBy('c.id')
            ->getQuery()
            ->getResult();
    }
}