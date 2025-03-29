<?php

namespace App\Service;

use App\Entity\Conference;
use App\Entity\Session;
use App\Repository\SessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Service for managing conference sessions
 */
class SessionService
{
    // List of available rooms
    private const AVAILABLE_ROOMS = [
        'Room A',
        'Room B',
        'Room C',
        'Room D',
        'Room E',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private SessionRepository $sessionRepository
    ) {
    }

    /**
     * Create a new session
     * @throws \Exception
     */
    public function createSession(Conference $conference, array $data): ?Session
    {
        // Check if conference is in scheduled status
        if ($conference->getStatus() !== Conference::STATUS_SCHEDULED) {
            return null;
        }

        // Create session
        $session = new Session();
        $session->setConference($conference);
        $session->setRoom($data['room']);
        $session->setStartTime(new \DateTimeImmutable($data['startTime']));
        $session->setEndTime(new \DateTimeImmutable($data['endTime']));

        // Check for conflicts
        $conflicts = $this->sessionRepository->findConflicting(
            $session->getStartTime(),
            $session->getEndTime(),
            $session->getRoom()
        );

        if (!empty($conflicts)) {
            return null;
        }

        // Validate session
        $errors = $this->validator->validate($session);
        if (count($errors) > 0) {
            return null;
        }

        // Update conference scheduled date if not set
        if (!$conference->getScheduledAt()) {
            $conference->setScheduledAt($session->getStartTime());
        }

        // Persist
        $this->entityManager->persist($session);
        $this->entityManager->flush();

        return $session;
    }

    /**
     * Update an existing session
     */
    public function updateSession(Session $session, array $data): bool
    {
        // Update session data
        if (isset($data['room'])) {
            $session->setRoom($data['room']);
        }

        if (isset($data['startTime'])) {
            $session->setStartTime(new \DateTimeImmutable($data['startTime']));
        }

        if (isset($data['endTime'])) {
            $session->setEndTime(new \DateTimeImmutable($data['endTime']));
        }

        // Check for conflicts
        $conflicts = $this->sessionRepository->findConflicting(
            $session->getStartTime(),
            $session->getEndTime(),
            $session->getRoom(),
            $session->getId()
        );

        if (!empty($conflicts)) {
            return false;
        }

        // Validate session
        $errors = $this->validator->validate($session);
        if (count($errors) > 0) {
            return false;
        }

        // Persist
        $this->entityManager->flush();

        return true;
    }

    /**
     * Delete a session
     */
    public function deleteSession(Session $session): bool
    {
        $this->entityManager->remove($session);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Get all available rooms
     */
    public function getAllRooms(): array
    {
        return self::AVAILABLE_ROOMS;
    }

    /**
     * Get available rooms for a given time range
     */
    public function getAvailableRooms(\DateTimeImmutable $startTime, \DateTimeImmutable $endTime): array
    {
        return $this->sessionRepository->getAvailableRooms($startTime, $endTime, self::AVAILABLE_ROOMS);
    }

    /**
     * Get sessions grouped by date
     */
    public function getSessionsByDate(): array
    {
        $sessions = $this->sessionRepository->findBy([], ['startTime' => 'ASC']);
        $grouped = [];

        foreach ($sessions as $session) {
            $date = $session->getStartTime()->format('Y-m-d');
            if (!isset($grouped[$date])) {
                $grouped[$date] = [];
            }
            $grouped[$date][] = $session;
        }

        return $grouped;
    }

    /**
     * Get sessions by conference
     */
    public function findSessionsByConference(Conference $conference): array
    {
        return $this->sessionRepository->findByConference($conference);
    }
}