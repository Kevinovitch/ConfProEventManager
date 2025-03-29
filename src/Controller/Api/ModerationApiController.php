<?php

namespace App\Controller\Api;

use App\Entity\ModerationRequest;
use App\Entity\User;
use App\Repository\ModerationRequestRepository;
use App\Service\ModerationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * API controller for conference moderation
 */
#[Route('/api/moderation')]
class ModerationApiController extends AbstractController
{
    public function __construct(
        private ModerationService $moderationService,
        private WorkflowInterface $moderationRequestStateMachine
    )
    {}

    /**
     * List pending moderation requests for current moderator
     */
    #[Route('/pending', name: 'api_moderation_pending', methods: ['GET'])]
    public function listPending(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MODERATOR');

        /** @var User $user  */
        $user = $this->getUser();

        $requests = $this->moderationService->getPendingRequestsForModerator($user);

        return $this->json([
            'data' => $requests,
        ], Response::HTTP_OK, [], ['groups' => 'moderation:read']);
    }

    /**
     * Accept a moderation request
     */
    #[Route('/{id}/accept', name: 'api_moderation_accept', methods: ['PUT'])]
    public function accept(ModerationRequest $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MODERATOR');

        // Check if current user is the assigned moderator
        if ($request->getModerator() !== $this->getUser()) {
            return $this->json(['error' => 'You are not the assigned moderator'], Response::HTTP_FORBIDDEN);
        }

        // Check if the transition is possible
        if (!$this->moderationRequestStateMachine->can($request, 'accept')) {
            return $this->json([
                'error' => 'Cannot accept this moderation request. Current state: ' . $request->getStatus(),
                'available_transitions' => array_map(
                    fn($t) => $t->getName(),
                    $this->moderationRequestStateMachine->getEnabledTransitions($request)
                )
            ], Response::HTTP_BAD_REQUEST);
        }

        $success = $this->moderationService->acceptRequest($request);

        if (!$success) {
            return $this->json(['error' => 'Cannot accept this request'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'message' => 'Conference accepted',
            'conference' => [
                'id' => $request->getConference()->getId(),
                'title' => $request->getConference()->getTitle(),
                'status' => $request->getConference()->getStatus()
            ]
        ]);
    }

    /**
     * Reject a moderation request
     */
    #[Route('/{id}/reject', name: 'api_moderation_reject', methods: ['PUT'])]
    public function reject(ModerationRequest $request, Request $httpRequest): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MODERATOR');

        // Check if current user is the assigned moderator
        if ($request->getModerator() !== $this->getUser()) {
            return $this->json(['error' => 'You are not the assigned moderator'], Response::HTTP_FORBIDDEN);
        }

        // Check if the transition is possible
        if (!$this->moderationRequestStateMachine->can($request, 'reject')) {
            return $this->json([
                'error' => 'Cannot reject this moderation request. Current state: ' . $request->getStatus(),
                'available_transitions' => array_map(
                    fn($t) => $t->getName(),
                    $this->moderationRequestStateMachine->getEnabledTransitions($request)
                )
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($httpRequest->getContent(), true);
        $comments = $data['comments'] ?? 'No comments provided';

        $success = $this->moderationService->rejectRequest($request, $comments);

        if (!$success) {
            return $this->json(['error' => 'Cannot reject this request'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'message' => 'Conference rejected',
            'conference' => [
                'id' => $request->getConference()->getId(),
                'title' => $request->getConference()->getTitle(),
                'status' => $request->getConference()->getStatus()
            ]
        ]);
    }


    /**
     * Get moderation request details including workflow information
     */
    #[Route('/{id}', name: 'api_moderation_show', methods: ['GET'])]
    public function show(ModerationRequest $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MODERATOR');

        // Get available transitions
        $transitions = $this->moderationRequestStateMachine->getEnabledTransitions($request);
        $availableTransitions = array_map(function($transition) {
            return $transition->getName();
        }, $transitions);

        return $this->json([
            'data' => $request,
            'workflow' => [
                'current_state' => $request->getStatus(),
                'available_transitions' => $availableTransitions
            ],
            'conference' => [
                'id' => $request->getConference()->getId(),
                'title' => $request->getConference()->getTitle(),
                'status' => $request->getConference()->getStatus()
            ]
        ], Response::HTTP_OK, [], ['groups' => 'moderation:read']);
    }
}