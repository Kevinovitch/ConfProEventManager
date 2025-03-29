<?php

namespace App\Controller\Api;

use App\Entity\Conference;
use App\Repository\RegistrationRepository;
use App\Service\FeedbackService;
use App\Service\RegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;


/**
 * API controller for feedback management
 */
#[Route('/api/feedback')]
class FeedbackApiController extends AbstractController
{
    public function __construct(
        private RegistrationService $registrationService,
        private FeedbackService $feedbackService,
    ) {
    }
    /**
     * Submit feedback for a conference
     */
    #[Route('/submit', name: 'api_feedback_submit', methods: ['POST'])]
    public function submit(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $data = json_decode($request->getContent(), true);

        // Check required fields
        if (!isset($data['registration_id']) || !isset($data['rating'])) {
            return $this->json(['error' => 'Registration ID and rating are required'], Response::HTTP_BAD_REQUEST);
        }

        // Get registration
        $registration = $this->registrationService->getRegistration(Uuid::fromString($data['registration_id']));

        if (!$registration) {
            return $this->json(['error' => 'Registration not found'], Response::HTTP_NOT_FOUND);
        }

        // Check if user is the owner of the registration
        if ($registration->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'You can only submit feedback for your own registrations'], Response::HTTP_FORBIDDEN);
        }

        // Submit feedback
        $feedback = $this->feedbackService->submitFeedback($registration, $data);

        if (!$feedback) {
            return $this->json(['error' => 'Failed to submit feedback. You may have already submitted feedback or not attended the conference.'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'message' => 'Feedback submitted successfully',
            'feedback' => [
                'id' => $feedback->getId(),
                'rating' => $feedback->getRating(),
                'submitted_at' => $feedback->getSubmittedAt()->format('c')
            ]
        ], Response::HTTP_CREATED);
    }

    /**
     * Get feedback statistics for a conference
     */
    #[Route('/stats/{id}', name: 'api_feedback_stats', methods: ['GET'])]
    public function getStats(Conference $conference): JsonResponse
    {
        // Anyone can view the stats, but we could restrict this if needed
        $stats = $this->feedbackService->getConferenceStats($conference);

        return $this->json([
            'stats' => $stats,
            'conference' => [
                'id' => $conference->getId(),
                'title' => $conference->getTitle()
            ]
        ]);
    }

    /**
     * Get latest comments for a conference
     */
    #[Route('/comments/{id}', name: 'api_feedback_comments', methods: ['GET'])]
    public function getComments(Conference $conference, Request $request): JsonResponse
    {
        $limit = $request->query->getInt('limit', 5);
        $comments = $this->feedbackService->getLatestComments($conference, $limit);

        return $this->json([
            'comments' => $comments,
            'conference' => [
                'id' => $conference->getId(),
                'title' => $conference->getTitle()
            ]
        ]);
    }
}