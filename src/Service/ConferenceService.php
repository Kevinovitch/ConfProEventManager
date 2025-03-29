<?php

namespace App\Service;

use App\Entity\Conference;
use App\Entity\ModerationRequest;
use App\Entity\User;
use App\Repository\ConferenceRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Service for conference management operations
 */
class ConferenceService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private UserRepository $userRepository,
        private ConferenceRepository $conferenceRepository,
        private WorkflowInterface $conferenceStateMachine
    ) {
    }

    /**
     * Create a new conference
     */
    public function createConference(array $data, User $presenter): ?Conference
    {
        // Create a new conference
        $conference = new Conference();
        $conference->setTitle($data['title']);
        $conference->setDescription($data['description']);

        // Set presenter - explicit bidirectional relation
        $conference->setPresenter($presenter);
        $presenter->addPresentedConference($conference);

        // Find a moderator to assign
        $moderator = $this->findAvailableModerator();
        if ($moderator) {
            // Set moderator - explicit bidirectional relation
            $conference->setModerator($moderator);
            $moderator->addModeratedConference($conference);
        }

        // Validate the conference
        $errors = $this->validator->validate($conference);
        if (count($errors) > 0) {
            return null;
        }

        // Persist to database
        $this->entityManager->persist($conference);
        $this->entityManager->flush();

        return $conference;
    }

    /**
     * Submit conference for validation
     */
    public function submitForValidation(Conference $conference): bool
    {
        // Check if the transition is possible
        if (!$this->conferenceStateMachine->can($conference, 'to_validation')) {
            return false;
        }

        // Ensure a moderator is assigned
        if (!$conference->getModerator()) {
            $moderator = $this->findAvailableModerator();
            if (!$moderator) {
                return false;
            }

            // Set moderator - explicit bidirectional relation
            $conference->setModerator($moderator);
            $moderator->addModeratedConference($conference);
        }

        // Create moderation request with explicit bidirectional relations
        $moderationRequest = new ModerationRequest();

        // Set conference relation
        $moderationRequest->setConference($conference);
        $conference->addModerationRequest($moderationRequest);

        // Set moderator relation
        $moderator = $conference->getModerator();
        $moderationRequest->setModerator($moderator);
        $moderator->addModerationRequest($moderationRequest);

        $moderationRequest->setStatus(ModerationRequest::STATUS_PENDING);

        // Apply the transition
        $this->conferenceStateMachine->apply($conference, 'to_validation');

        // Persist the moderation request
        $this->entityManager->persist($moderationRequest);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Schedule a conference
     */
    public function scheduleConference(Conference $conference, \DateTimeImmutable $scheduledAt): bool
    {
        // Set scheduled date
        $conference->setScheduledAt($scheduledAt);

        // Check if transition is possible
        if (!$this->conferenceStateMachine->can($conference, 'to_scheduled')) {
            return false;
        }

        // Apply transition
        $this->conferenceStateMachine->apply($conference, 'to_scheduled');

        // We delete all the moderation Requests linked to this conference
        $moderationRequests = $this->entityManager->getRepository(ModerationRequest::class)
            ->findBy(['conference' => $conference]);

        foreach ($moderationRequests as $request) {
            $this->entityManager->remove($request);
        }

        $this->entityManager->flush();

        return true;
    }

    /**
     * Archive a conference
     */
    public function archiveConference(Conference $conference): bool
    {
        // Check if transition is possible
        if (!$this->conferenceStateMachine->can($conference, 'to_archived')) {
            return false;
        }

        // Apply transition
        $this->conferenceStateMachine->apply($conference, 'to_archived');
        $this->entityManager->flush();

        return true;
    }

    /**
     * Return a conference to submitted state (rejected by moderator)
     */
    public function returnToSubmitted(Conference $conference): bool
    {
        // Check if transition is possible
        if (!$this->conferenceStateMachine->can($conference, 'back_to_submitted')) {
            return false;
        }

        // Apply transition
        $this->conferenceStateMachine->apply($conference, 'back_to_submitted');

        // We delete all the moderation Requests linked to this conference
        $moderationRequests = $this->entityManager->getRepository(ModerationRequest::class)
            ->findBy(['conference' => $conference]);

        foreach ($moderationRequests as $request) {
            $this->entityManager->remove($request);
        }

        $this->entityManager->flush();

        return true;
    }

    /**
     * Find an available moderator based on workload
     */
    private function findAvailableModerator(): ?User
    {
        // Find moderators ordered by number of assigned conferences
        return $this->userRepository->findModeratorsOrderedByWorkload();
    }

    /**
     * Find all the conferences in the database
     */
    public function findAllConferences(): array
    {
        return $this->conferenceRepository->findAll();
    }

    /**
     * Find a conference in the database
     */
    public function findConferenceById($conferenceId): Conference|null
    {
        return $this->conferenceRepository->find($conferenceId);
    }

    /**
     * Find a conference by status in the database
     */
    public function findConferenceByStatus(string $status): array
    {
        return $this->conferenceRepository->findByStatus($status);
    }

    /**
     * Find upcoming conferences in the database
     */
    public function getUpcomingConferences(int $count): array
    {
        return $this->conferenceRepository->findUpcoming($count);
    }

    /**
     * Find the number of conferences in the database
     */
    public function getConferencesTotal(): int
    {
        return $this->conferenceRepository->count([]);
    }

}