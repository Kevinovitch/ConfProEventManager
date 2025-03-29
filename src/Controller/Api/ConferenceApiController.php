<?php

namespace App\Controller\Api;

use App\Entity\Conference;
use App\Entity\User;
use App\Repository\ConferenceRepository;
use App\Service\ConferenceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * API controller for conference management
 */
#[Route('/api/conferences')]
class ConferenceApiController extends AbstractController
{
    public function __construct(
        private ConferenceService $conferenceService,
        private WorkflowInterface $conferenceStateMachine,
    ) {
    }

    /**
     * List all conferences
     */
    #[Route('', name: 'api_conferences_list', methods: ['GET'])]
    public function list(ConferenceRepository $repository): JsonResponse
    {
        $conferences = $this->conferenceService->findAllConferences();

        return $this->json([
            'data' => $conferences,
        ], Response::HTTP_OK, [], ['groups' => 'conference:read']);
    }

    /**
     * Get a single conference by ID
     */
    #[Route('/{id}', name: 'api_conferences_show', methods: ['GET'])]
    public function show(Conference $conference): JsonResponse
    {
        // Get available transitions for this conference
        $transitions = $this->conferenceStateMachine->getEnabledTransitions($conference);
        $availableTransitions = array_map(function($transition) {
            return $transition->getName();
        }, $transitions);

        return $this->json([
            'data' => $conference,
            'workflow' => [
                'current_state' => $conference->getStatus(),
                'available_transitions' => $availableTransitions
            ]
        ], Response::HTTP_OK, [], ['groups' => 'conference:read']);
    }

    /**
     * Create a new conference
     */
    #[Route('', name: 'api_conferences_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Get the current user (presenter)
        /** @var User $user  */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'You must be logged in'], Response::HTTP_UNAUTHORIZED);
        }

        $conference = $this->conferenceService->createConference($data, $user);

        if (!$conference) {
            return $this->json(['error' => 'Invalid data'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'data' => $conference,
            'message' => 'Conference created successfully',
            'workflow' => [
                'current_state' => $conference->getStatus(),
                'available_transitions' => ['to_validation']
            ]
        ], Response::HTTP_CREATED, [], ['groups' => 'conference:read']);
    }

    /**
     * Submit a conference for validation
     */
    #[Route('/{id}/submit', name: 'api_conferences_submit', methods: ['PUT'])]
    public function submit(Conference $conference): JsonResponse
    {
        // Check if the current user is the presenter of the conference
        if ($conference->getPresenter() !== $this->getUser()) {
            return $this->json(['error' => 'You are not allowed to submit this conference'], Response::HTTP_FORBIDDEN);
        }

        // Check if the transition is possible
        if (!$this->conferenceStateMachine->can($conference, 'to_validation')) {
            return $this->json([
                'error' => 'Cannot submit this conference for validation. Current state: ' . $conference->getStatus(),
                'available_transitions' => array_map(
                    fn($t) => $t->getName(),
                    $this->conferenceStateMachine->getEnabledTransitions($conference)
                )
            ], Response::HTTP_BAD_REQUEST);
        }


        $success = $this->conferenceService->submitForValidation($conference);

        if (!$success) {
            return $this->json(['error' => 'Unable to submit conference'], Response::HTTP_BAD_REQUEST);
        }


        return $this->json([
            'message' => 'Conference submitted for validation',
            'workflow' => [
                'previous_state' => 'submitted',
                'current_state' => $conference->getStatus(),
                'available_transitions' => array_map(
                    fn($t) => $t->getName(),
                    $this->conferenceStateMachine->getEnabledTransitions($conference)
                )
            ]
        ]);
    }


    /**
     * Schedule a conference
     */
    #[Route('/{id}/schedule', name: 'api_conferences_schedule', methods: ['PUT'])]
    public function schedule(Conference $conference, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $data = json_decode($request->getContent(), true);

        if (!isset($data['scheduled_at'])) {
            return $this->json(['error' => 'Scheduled date is required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $scheduledAt = new \DateTimeImmutable($data['scheduled_at']);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Invalid date format'], Response::HTTP_BAD_REQUEST);
        }

        // Check if the transition is possible
        if (!$this->conferenceStateMachine->can($conference, 'to_scheduled')) {
            return $this->json([
                'error' => 'Cannot schedule this conference. Current state: ' . $conference->getStatus(),
                'available_transitions' => array_map(
                    fn($t) => $t->getName(),
                    $this->conferenceStateMachine->getEnabledTransitions($conference)
                )
            ], Response::HTTP_BAD_REQUEST);
        }

        $success = $this->conferenceService->scheduleConference($conference, $scheduledAt);

        if (!$success) {
            return $this->json(['error' => 'Unable to schedule conference'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'message' => 'Conference scheduled successfully',
            'workflow' => [
                'previous_state' => 'under_validation',
                'current_state' => $conference->getStatus(),
                'available_transitions' => array_map(
                    fn($t) => $t->getName(),
                    $this->conferenceStateMachine->getEnabledTransitions($conference)
                )
            ]
        ]);
    }

    /**
     * Archive a conference
     */
    #[Route('/{id}/archive', name: 'api_conferences_archive', methods: ['PUT'])]
    public function archive(Conference $conference): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Check if the transition is possible
        if (!$this->conferenceStateMachine->can($conference, 'to_archived')) {
            return $this->json([
                'error' => 'Cannot archive this conference. Current state: ' . $conference->getStatus(),
                'available_transitions' => array_map(
                    fn($t) => $t->getName(),
                    $this->conferenceStateMachine->getEnabledTransitions($conference)
                )
            ], Response::HTTP_BAD_REQUEST);
        }

        $success = $this->conferenceService->archiveConference($conference);

        if (!$success) {
            return $this->json(['error' => 'Unable to archive conference'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'message' => 'Conference archived successfully',
            'workflow' => [
                'previous_state' => 'scheduled',
                'current_state' => $conference->getStatus(),
                'available_transitions' => array_map(
                    fn($t) => $t->getName(),
                    $this->conferenceStateMachine->getEnabledTransitions($conference)
                )
            ]
        ]);
    }


}