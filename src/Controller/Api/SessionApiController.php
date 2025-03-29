<?php

namespace App\Controller\Api;

use App\Entity\Conference;
use App\Entity\Session;
use App\Service\ConferenceService;
use App\Service\SessionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

/**
 * API controller for session management
 */
#[Route('/api/sessions')]
class SessionApiController extends AbstractController
{

    public function __construct(
        private SessionService $sessionService,
        private ConferenceService $conferenceService,
    )
    {
    }

    /**
     * Get sessions for a conference
     */
    #[Route('/conference/{id}', name: 'api_sessions_by_conference', methods: ['GET'])]
    public function getByConference(Conference $conference): JsonResponse
    {
        $sessions = $this->sessionService->findSessionsByConference($conference);

        return $this->json([
            'data' => $sessions,
        ], Response::HTTP_OK, [], ['groups' => 'session:read']);
    }

    /**
     * Create a new session
     */
    #[Route('', name: 'api_sessions_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);

        // Get the conference
        $conferenceId = $data['conference_id'] ?? null;
        if (!$conferenceId) {
            return $this->json(['error' => 'Conference ID is required'], Response::HTTP_BAD_REQUEST);
        }

        $conference = $this->conferenceService->findConferenceById($conferenceId);
        if (!$conference) {
            return $this->json(['error' => 'Conference not found'], Response::HTTP_NOT_FOUND);
        }

        // Create session
        $session = $this->sessionService->createSession($conference, $data);

        if (!$session) {
            return $this->json(['error' => 'Cannot create session. Check for conflicts or invalid data'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'data' => $session,
            'message' => 'Session created successfully'
        ], Response::HTTP_CREATED, [], ['groups' => 'session:read']);
    }

    /**
     * Update a session
     */
    #[Route('/{id}', name: 'api_sessions_update', methods: ['PUT'])]
    public function update(Session $session, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);

        $success = $this->sessionService->updateSession($session, $data);

        if (!$success) {
            return $this->json(['error' => 'Cannot update session. Check for conflicts or invalid data'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'data' => $session,
            'message' => 'Session updated successfully'
        ], Response::HTTP_OK, [], ['groups' => 'session:read']);
    }

    /**
     * Delete a session
     */
    #[Route('/{id}', name: 'api_sessions_delete', methods: ['DELETE'])]
    public function delete(Session $session): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $success = $this->sessionService->deleteSession($session);

        if (!$success) {
            return $this->json(['error' => 'Cannot delete session'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'message' => 'Session deleted successfully'
        ]);
    }

    /**
     * Check availability of rooms for a given time range
     */
    #[Route('/check-availability', name: 'api_sessions_check_availability', methods: ['POST'])]
    public function checkAvailability(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['startTime']) || !isset($data['endTime'])) {
            return $this->json(['error' => 'Start time and end time are required'], Response::HTTP_BAD_REQUEST);
        }

        $startTime = new \DateTimeImmutable($data['startTime']);
        $endTime = new \DateTimeImmutable($data['endTime']);

        $availableRooms = $this->sessionService->getAvailableRooms($startTime, $endTime);
        $allRooms = $this->sessionService->getAllRooms();
        $occupiedRooms = array_diff($allRooms, $availableRooms);

        return $this->json([
            'available_rooms' => $availableRooms,
            'occupied_rooms' => $occupiedRooms
        ]);
    }
}