<?php

namespace App\Service;

use App\Entity\Conference;
use App\Entity\ModerationRequest;
use App\Entity\User;
use App\Repository\ModerationRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Service for managing conference moderation
 */
class ModerationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ModerationRequestRepository $moderationRepository,
        private WorkflowInterface $moderationRequestStateMachine,
        private WorkflowInterface $conferenceStateMachine
    ) {
    }

    /**
     * Create a moderation request for a conference
     */
    public function createModerationRequest(Conference $conference, User $moderator): ?ModerationRequest
    {
        // Check if a pending request already exists
        $existingRequest = $this->moderationRepository->findOneBy([
            'conference' => $conference,
            'status' => ModerationRequest::STATUS_PENDING
        ]);

        if ($existingRequest) {
            return $existingRequest;
        }

        // Create new request
        $request = new ModerationRequest();
        $request->setConference($conference);
        $request->setModerator($moderator);
        $request->setStatus(ModerationRequest::STATUS_PENDING);

        $this->entityManager->persist($request);
        $this->entityManager->flush();

        return $request;
    }

    /**
     * Accept a conference moderation request
     */
    public function acceptRequest(ModerationRequest $request): bool
    {
        // Check if the transition is possible
        if (!$this->moderationRequestStateMachine->can($request, 'accept')) {
            return false;
        }

        // Apply the transition to the moderation request
        $this->moderationRequestStateMachine->apply($request, 'accept');

        // Update conference status using the workflow
        $conference = $request->getConference();
        if ($this->conferenceStateMachine->can($conference, 'to_scheduled')) {
            $this->conferenceStateMachine->apply($conference, 'to_scheduled');
        }

        // We delete the request
        $this->entityManager->remove($request);

        $this->entityManager->flush();

        return true;
    }

    /**
     * Reject a conference moderation request with comments
     */
    public function rejectRequest(ModerationRequest $request, string $comments): bool
    {
        // Check if the transition is possible
        if (!$this->moderationRequestStateMachine->can($request, 'reject')) {
            return false;
        }

        // Apply the transition to the moderation request
        $this->moderationRequestStateMachine->apply($request, 'reject');
        $request->setComments($comments);

        // Update conference status using the workflow
        $conference = $request->getConference();
        if ($this->conferenceStateMachine->can($conference, 'back_to_submitted')) {
            $this->conferenceStateMachine->apply($conference, 'back_to_submitted');
        }

        // We delete the request
        $this->entityManager->remove($request);

        $this->entityManager->flush();

        return true;
    }

    /**
     * Get pending moderation requests for a moderator
     */
    public function getPendingRequestsForModerator(User $moderator): array
    {
        return $this->moderationRepository->findPendingByModerator($moderator);
    }

    /**
     * Get pending moderation requests for a moderator
     */
    public function getModerationRequestByConferenceAndByUser(Conference $conference, User $user, string $status): ModerationRequest|null
    {
        return $this->moderationRepository->findOneBy([
            'conference' => $conference,
            'moderator' => $user,
            'status' => $status
        ]);
    }
}