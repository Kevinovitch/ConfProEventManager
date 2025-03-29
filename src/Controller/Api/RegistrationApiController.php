<?php

namespace App\Controller\Api;

use App\Entity\Conference;
use App\Entity\Registration;
use App\Entity\User;
use App\Repository\RegistrationRepository;
use App\Service\ConferenceService;
use App\Service\RegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

/**
 * API controller for conference registrations
 */
#[Route('/api')]
class RegistrationApiController extends AbstractController
{

    public function __construct(
        private RegistrationService $registrationService,
    )
    {
    }
    /**
     * Register for a conference
     */
    #[Route('/conferences/{id}/register', name: 'api_conference_register', methods: ['POST'])]
    public function register(Conference $conference): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user  */
        $user = $this->getUser();

        $registration = $this->registrationService->registerForConference($user, $conference);

        if (!$registration) {
            return $this->json(['error' => 'Cannot register for this conference'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'message' => 'Successfully registered for the conference',
            'registration_id' => $registration->getId(),
            'qr_code' => $registration->getQrCode()
        ], Response::HTTP_CREATED);
    }

    /**
     * Cancel registration for a conference
     */
    #[Route('/conferences/{id}/unregister', name: 'api_conference_unregister', methods: ['DELETE'])]
    public function unregister(Conference $conference): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user  */
        $user = $this->getUser();
        ;
        $registration = $this->registrationService->findRegistrationByUserAndConference($user, $conference);

        if (!$registration) {
            return $this->json(['error' => 'You are not registered for this conference'], Response::HTTP_NOT_FOUND);
        }

        $success = $this->registrationService->cancelRegistration($registration);

        if (!$success) {
            return $this->json(['error' => 'Cannot cancel this registration'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['message' => 'Registration canceled successfully']);
    }

    /**
     * Check in a participant at the conference
     */
    #[Route('/registrations/{id}/checkin', name: 'api_registration_checkin', methods: ['PUT'])]
    public function checkin(Registration $registration, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);
        $qrCode = $data['qrCode'] ?? null;

        $success = $this->registrationService->checkInRegistration($registration, $qrCode);

        if (!$success) {
            return $this->json(['error' => 'Cannot check in this registration. QR code may be invalid'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['message' => 'Check-in successful']);
    }

    /**
     * Get list of participants for a conference
     */
    #[Route('/conferences/{id}/participants', name: 'api_conference_participants', methods: ['GET'])]
    public function participants(Conference $conference): JsonResponse
    {
        // Only admins and the presenter of the conference can view participants
        if (!$this->isGranted('ROLE_ADMIN') && $conference->getPresenter() !== $this->getUser()) {
            return $this->json(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $registrations = $this->registrationService->getConferenceRegistrations($conference);

        $participants = [];
        foreach ($registrations as $registration) {
            $user = $registration->getUser();
            $participants[] = [
                'id' => $registration->getId(),
                'user_id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'registered_at' => $registration->getRegisteredAt()->format('c'),
                'attended' => $registration->isAttended()
            ];
        }

        $stats = $this->registrationService->getConferenceStatistics($conference);

        return $this->json([
            'participants' => $participants,
            'stats' => $stats
        ]);
    }

    /**
     * Get user's registrations
     */
    #[Route('/users/me/registrations', name: 'api_user_registrations', methods: ['GET'])]
    public function userRegistrations(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user  */
        $user = $this->getUser();

        $registrations = $this->registrationService->getUserRegistrations($user);

        $result = [];
        foreach ($registrations as $registration) {
            $conference = $registration->getConference();
            $result[] = [
                'id' => $registration->getId(),
                'conference' => [
                    'id' => $conference->getId(),
                    'title' => $conference->getTitle(),
                    'scheduled_at' => $conference->getScheduledAt() ? $conference->getScheduledAt()->format('c') : null
                ],
                'registered_at' => $registration->getRegisteredAt()->format('c'),
                'qr_code' => $registration->getQrCode(),
                'attended' => $registration->isAttended()
            ];
        }

        return $this->json(['registrations' => $result]);
    }

    /**
     * Check registration by QR code
     */
    #[Route('/registrations/check', name: 'api_registration_check_qr', methods: ['POST'])]
    public function checkByQrCode(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);
        $qrCode = $data['qrCode'] ?? null;

        if (!$qrCode) {
            return $this->json(['error' => 'QR code is required'], Response::HTTP_BAD_REQUEST);
        }

        $registration = $this->registrationService->findByQrCode($qrCode);

        if (!$registration) {
            return $this->json(['error' => 'Invalid QR code'], Response::HTTP_NOT_FOUND);
        }

        $user = $registration->getUser();
        $conference = $registration->getConference();

        return $this->json([
            'registration' => [
                'id' => $registration->getId(),
                'user' => [
                    'id' => $user->getId(),
                    'name' => $user->getName(),
                    'email' => $user->getEmail()
                ],
                'conference' => [
                    'id' => $conference->getId(),
                    'title' => $conference->getTitle()
                ],
                'registered_at' => $registration->getRegisteredAt()->format('c'),
                'attended' => $registration->isAttended()
            ]
        ]);
    }
}