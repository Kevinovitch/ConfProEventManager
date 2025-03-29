<?php

namespace App\Service;

use App\Entity\Conference;
use App\Entity\Registration;
use App\Entity\User;
use App\Repository\RegistrationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service for managing conference registrations
 */
class RegistrationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RegistrationRepository $registrationRepository
    ) {
    }

    /**
     * Register a user for a conference
     */
    public function registerForConference(User $user, Conference $conference): ?Registration
    {
        // Check if conference is in a valid status
        if ($conference->getStatus() !== Conference::STATUS_SCHEDULED) {
            return null;
        }

        // Check if user is already registered
        $existingRegistration = $this->registrationRepository->findByUserAndConference($user, $conference);
        if ($existingRegistration) {
            return $existingRegistration;
        }

        // Create new registration
        $registration = new Registration();
        $registration->setUser($user);
        $registration->setConference($conference);
        $registration->generateQrCode();

        $this->entityManager->persist($registration);
        $this->entityManager->flush();

        return $registration;
    }

    /**
     * Cancel a registration
     */
    public function cancelRegistration(Registration $registration): bool
    {

        // Check if user has already attended
        if ($registration->isAttended()) {
            return false;
        }

        $this->entityManager->remove($registration);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Mark a registration as attended (check-in)
     */
    public function checkInRegistration(Registration $registration, ?string $qrCode = null): bool
    {

        // Verify QR code if provided
        if ($qrCode !== null && $registration->getQrCode() !== $qrCode) {
            return false;
        }

        // Mark as attended
        $registration->setAttended(true);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Get registrations for a user
     */
    public function getUserRegistrations(User $user): array
    {
        return $this->registrationRepository->findByUser($user);
    }

    /**
     * Get registrations for a conference
     */
    public function getConferenceRegistrations(Conference $conference): array
    {
        return $this->registrationRepository->findByConference($conference);
    }

    /**
     * Find registration by QR code
     */
    public function findByQrCode(string $qrCode): ?Registration
    {
        return $this->registrationRepository->findByQrCode($qrCode);
    }

    /**
     * Get statistics for a conference
     */
    public function getConferenceStatistics(Conference $conference): array
    {
        $totalRegistrations = $this->registrationRepository->countByConference($conference);
        $totalAttendees = $this->registrationRepository->countAttendeesByConference($conference);

        $attendanceRate = $totalRegistrations > 0
            ? round(($totalAttendees / $totalRegistrations) * 100, 2)
            : 0;

        return [
            'total_registrations' => $totalRegistrations,
            'total_attendees' => $totalAttendees,
            'attendance_rate' => $attendanceRate
        ];
    }

    /**
     * Find registration by user and conference
     */
    public function findRegistrationByUserAndConference(User $user, Conference $conference)
    {
        return $this->registrationRepository->findByUserAndConference($user, $conference);
    }

    /**
     * Find the total of participants
     */
    public function findTotalOfParticipants()
    {
        return $this->registrationRepository->count([]);
    }

    /**
     * Find a registration by its id
     */
    public function getRegistration($registrationId)
    {
        return $this->registrationRepository->find($registrationId);
    }
}