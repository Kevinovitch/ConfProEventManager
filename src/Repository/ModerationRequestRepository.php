<?php

namespace App\Repository;

use App\Entity\ModerationRequest;
use App\Entity\User;
use App\Service\UuidDatabaseService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ModerationRequest>
 */
class ModerationRequestRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private UuidDatabaseService $uuidDatabaseService
    )
    {
        parent::__construct($registry, ModerationRequest::class);
    }


    /**
     * Find pending moderation requests for a moderator
     */
    public function findPendingByModerator(User $moderator): array
    {
        $allRequests = $this->uuidDatabaseService->findEntitiesByUuidField(
            'moderation_request',
            'moderator_id',
            $moderator->getId(),
            ModerationRequest::class,
            ['created_at' => 'DESC']
        );

        // Filter to keep only requests with PENDING status
        return array_filter($allRequests, function($request) {
            return $request->getStatus() === ModerationRequest::STATUS_PENDING;
        });
    }
}