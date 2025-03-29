<?php

namespace App\Controller;

use App\Entity\ModerationRequest;
use App\Entity\User;
use App\Service\ModerationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Web controller for moderation management
 */
#[Route('/moderation')]
class ModerationController extends AbstractController
{
    public function __construct(
        private ModerationService $moderationService,
    )
    {}
    /**
     * List pending moderation requests
     */
    #[Route('', name: 'app_moderation_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MODERATOR');

        /** @var User $user  */
        $user = $this->getUser();

        $requests = $this->moderationService->getPendingRequestsForModerator($user);

        return $this->render('moderation/index.html.twig', [
            'moderation_requests' => $requests,
        ]);
    }

    /**
     * Show moderation request details
     */
    #[Route('/{id}', name: 'app_moderation_show', methods: ['GET'])]
    public function show(ModerationRequest $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MODERATOR');

        // Check if current user is the assigned moderator
        if ($request->getModerator() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('You are not the assigned moderator');
        }

        return $this->render('moderation/show.html.twig', [
            'moderation_request' => $request,
        ]);
    }

    /**
     * Accept a moderation request
     */
    #[Route('/{id}/accept', name: 'app_moderation_accept', methods: ['POST'])]
    public function accept(ModerationRequest $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MODERATOR');

        // Check if current user is the assigned moderator
        if ($request->getModerator() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('You are not the assigned moderator');
        }

        $success = $this->moderationService->acceptRequest($request);

        if ($success) {
            $this->addFlash('success', 'Conference accepted successfully');
        } else {
            $this->addFlash('error', 'Cannot accept this conference');
        }

        return $this->redirectToRoute('app_moderation_index');
    }

    /**
     * Reject a moderation request
     */
    #[Route('/{id}/reject', name: 'app_moderation_reject', methods: ['POST'])]
    public function reject(ModerationRequest $request, Request $httpRequest): Response
    {
        $this->denyAccessUnlessGranted('ROLE_MODERATOR');

        // Check if current user is the assigned moderator
        if ($request->getModerator() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('You are not the assigned moderator');
        }

        $comments = $httpRequest->request->get('comments', 'No comments provided');

        $success = $this->moderationService->rejectRequest($request, $comments);

        if ($success) {
            $this->addFlash('success', 'Conference rejected with feedback');
        } else {
            $this->addFlash('error', 'Cannot reject this conference');
        }

        return $this->redirectToRoute('app_moderation_index');
    }
}