<?php

namespace App\Controller;

use App\Entity\Conference;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller for manual notification management
 */
#[Route('/admin/notifications')]
class NotificationController extends AbstractController
{
    /**
     * Notification dashboard
     */
    #[Route('', name: 'app_notifications_index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('notification/index.html.twig');
    }

    /**
     * Send reminders manually for a specific conference
     */
    #[Route('/send-reminders/{id}', name: 'app_notifications_send_reminders', methods: ['POST'])]
    public function sendReminders(Conference $conference, NotificationService $notificationService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Send reminders to participants
        $sentParticipants = $notificationService->sendConferenceReminders($conference);

        // Send reminder to presenter
        $sentPresenter = $notificationService->sendPresenterReminder($conference);

        $this->addFlash(
            'success',
            sprintf(
                'Sent %d participant reminders %s',
                $sentParticipants,
                $sentPresenter ? 'and a presenter reminder' : 'but failed to send presenter reminder'
            )
        );

        return $this->redirectToRoute('app_conferences_show', ['id' => $conference->getId()]);
    }

    /**
     * Send feedback requests manually for a specific conference
     */
    #[Route('/send-feedback-requests/{id}', name: 'app_notifications_send_feedback_requests', methods: ['POST'])]
    public function sendFeedbackRequests(Conference $conference, NotificationService $notificationService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Send feedback requests to attendees
        $sentCount = $notificationService->sendFeedbackRequests($conference);

        $this->addFlash(
            'success',
            sprintf('Sent %d feedback requests', $sentCount)
        );

        return $this->redirectToRoute('app_conferences_show', ['id' => $conference->getId()]);
    }
}