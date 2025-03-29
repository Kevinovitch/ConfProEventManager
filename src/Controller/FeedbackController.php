<?php

namespace App\Controller;

use App\Entity\Conference;
use App\Entity\Registration;
use App\Form\FeedbackType;
use App\Repository\RegistrationRepository;
use App\Service\FeedbackService;
use App\Service\RegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Web controller for feedback management
 */
#[Route('/feedback')]
class FeedbackController extends AbstractController
{
    public function __construct(
        private RegistrationService $registrationService,
        private FeedbackService $feedbackService,
    ){}
    /**
     * Show feedback form for a conference
     */
    #[Route('/submit/{id}', name: 'app_feedback_submit', methods: ['GET', 'POST'])]
    public function submit(string $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        try {
            $registration = $this->registrationService->getRegistration(Uuid::fromString($id));
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Registration not found');
        }

        if (!$registration) {
            throw $this->createNotFoundException('Registration not found');
        }

        // Check if user is the owner of the registration
        if ($registration->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('You can only submit feedback for your own registrations');
        }

        // Check if user attended the conference
        if (!$registration->isAttended()) {
            $this->addFlash('error', 'You can only submit feedback for conferences you attended');
            return $this->redirectToRoute('app_user_registrations');
        }

        $form = $this->createForm(FeedbackType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $feedback = $this->feedbackService->submitFeedback($registration, [
                'rating' => $data['rating'],
                'comment' => $data['comment'],
                'aspectRated' => $data['aspectRated'] ?? null
            ]);

            if ($feedback) {
                $this->addFlash('success', 'Thank you for your feedback!');
                return $this->redirectToRoute('app_user_registrations');
            } else {
                $this->addFlash('error', 'You have already submitted feedback for this conference.');
            }
        }

        return $this->render('feedback/submit.html.twig', [
            'form' => $form->createView(),
            'registration' => $registration,
            'conference' => $registration->getConference()
        ]);
    }

    /**
     * Show feedback statistics for a conference
     */
    #[Route('/stats/{id}', name: 'app_feedback_stats', methods: ['GET'])]
    public function stats(Conference $conference): Response
    {
        // Check if user is the presenter of the conference or an admin
        $isOwner = $this->isGranted('ROLE_ADMIN') ||
            ($this->isGranted('ROLE_PRESENTER') && $conference->getPresenter() === $this->getUser());

        $stats = $this->feedbackService->getConferenceStats($conference);
        $comments = [];

        // Only show comments to the presenter or admin
        if ($isOwner) {
            $comments = $this->feedbackService->getLatestComments($conference, 10);
        }

        return $this->render('feedback/stats.html.twig', [
            'conference' => $conference,
            'stats' => $stats,
            'comments' => $comments,
            'is_owner' => $isOwner
        ]);
    }
}