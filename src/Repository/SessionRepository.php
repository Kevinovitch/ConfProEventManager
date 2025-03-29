<?php

namespace App\Repository;

use App\Entity\Conference;
use App\Entity\Session;
use App\Service\UuidDatabaseService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Session>
 */
class SessionRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private UuidDatabaseService $uuidDatabaseService
    )
    {
        parent::__construct($registry, Session::class);
    }

    /**
     * Find sessions for a specific conference
     */
    public function findByConference(Conference $conference): array
    {
        return $this->uuidDatabaseService->findEntitiesByUuidField(
            'session',
            'conference_id',
            $conference->getId(),
            Session::class,
            ['start_time' => 'ASC']
        );
    }

    /**
     * Find sessions by date
     */
    public function findByDate(\DateTimeImmutable $date): array
    {
        $startOfDay = $date->setTime(0, 0, 0);
        $endOfDay = $date->setTime(23, 59, 59);

        return $this->createQueryBuilder('s')
            ->andWhere('s.startTime >= :startOfDay')
            ->andWhere('s.startTime <= :endOfDay')
            ->setParameter('startOfDay', $startOfDay)
            ->setParameter('endOfDay', $endOfDay)
            ->orderBy('s.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find conflicting sessions for a given time range and room
     */
    public function findConflicting(\DateTimeImmutable $startTime, \DateTimeImmutable $endTime, string $room, ?Uuid $excludeId = null): array
    {
        // Construire le tableau des conditions
        $conditions = [
            'room' => [
                'value' => $room,
                'operator' => '='
            ],
            'start_time' => [
                'value' => $endTime->format('Y-m-d H:i:s'),
                'operator' => '<'
            ],
            'end_time' => [
                'value' => $startTime->format('Y-m-d H:i:s'),
                'operator' => '>'
            ]
        ];

        // Ajouter la condition d'exclusion si un ID est fourni
        if ($excludeId) {
            $conditions['id'] = [
                'value' => $excludeId,
                'operator' => '!=',
                'type' => 'uuid'
            ];
        }

        // Utiliser la nouvelle méthode du service
        return $this->uuidDatabaseService->findEntitiesWithCustomConditions(
            'session',
            $conditions,
            Session::class,
            ['start_time' => 'ASC']
        );
    }
    /**
     * Get available rooms for a given time range
     */
    public function getAvailableRooms(\DateTimeImmutable $startTime, \DateTimeImmutable $endTime, array $allRooms): array
    {
        $occupiedRooms = $this->createQueryBuilder('s')
            ->select('s.room')
            ->andWhere('
                (s.startTime < :endTime AND s.endTime > :startTime)
            ')
            ->setParameter('startTime', $startTime)
            ->setParameter('endTime', $endTime)
            ->getQuery()
            ->getSingleColumnResult();

        return array_diff($allRooms, $occupiedRooms);
    }
}