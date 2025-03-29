<?php

namespace App\Repository;

use App\Entity\Conference;
use App\Entity\Registration;
use App\Entity\User;
use App\Service\UuidDatabaseService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Registration>
 */
class RegistrationRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private UuidDatabaseService $uuidDatabaseService
    )
    {
        parent::__construct($registry, Registration::class);
    }


    /**
     * Find registration by user and conference
     */
    public function findByUserAndConference(User $user, Conference $conference): ?Registration
    {
        return $this->uuidDatabaseService->findOneEntityByUuidFields(
            'registration',
            [
                'user_id' => $user->getId(),
                'conference_id' => $conference->getId()
            ],
            Registration::class
        );
    }


    /**
     * Find registrations for a user
     */
    public function findByUser(User $user): array
    {
        return $this->uuidDatabaseService->findEntitiesByUuidField(
            'registration',
            'user_id',
            $user->getId(),
            Registration::class,
            ['registered_at' => 'DESC']
        );
    }

    /**
     * Find registrations for a conference
     */
    public function findByConference(Conference $conference): array
    {
        return $this->uuidDatabaseService->findEntitiesByUuidField(
            'registration',
            'conference_id',
            $conference->getId(),
            Registration::class,
            ['registered_at' => 'DESC']
        );
    }

    /**
     * Count registrations for a conference
     */
    public function countByConference(Conference $conference): int
    {
        return $this->uuidDatabaseService->countEntitiesByUuidField(
            'registration',
            'conference_id',
            $conference->getId()
        );
    }

    /**
     * Count attendees for a conference
     */
    public function countAttendeesByConference(Conference $conference): int
    {
        return $this->uuidDatabaseService->countEntitiesByUuidField(
            'registration',
            'conference_id',
            $conference->getId(),
            ['attended' => 1]
        );
    }
    /**
     * Find registration by QR code
     */
    public function findByQrCode(string $qrCode): ?Registration
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.qrCode = :qrCode')
            ->setParameter('qrCode', $qrCode)
            ->getQuery()
            ->getOneOrNullResult();
    }
}