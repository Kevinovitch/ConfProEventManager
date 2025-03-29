<?php

namespace App\Controller;

use App\Entity\Conference;
use App\Entity\Session;
use App\Form\SessionType;
use App\Service\ConferenceService;
use App\Service\SessionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Web controller for session management
 */
#[Route('/sessions')]
class SessionController extends AbstractController
{

    public function __construct(
        private SessionService $sessionService,
        private ConferenceService $conferenceService,
    )
    {
    }

    /**
     * Display session planning
     */
    #[Route('', name: 'app_sessions_index', methods: ['GET'])]
    public function index(): Response
    {
        $sessionsByDate = $this->sessionService->getSessionsByDate();

        return $this->render('session/index.html.twig', [
            'sessions_by_date' => $sessionsByDate,
        ]);
    }

    /**
     * Display sessions for a specific conference
     */
    #[Route('/conference/{id}', name: 'app_sessions_conference', methods: ['GET'])]
    public function conferenceSchedule(Conference $conference): Response
    {
        $sessions = $this->sessionService->findSessionsByConference($conference);

        return $this->render('session/conference.html.twig', [
            'conference' => $conference,
            'sessions' => $sessions,
        ]);
    }

    /**
     * Create a new session
     */
    #[Route('/new', name: 'app_sessions_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Get conference ID from query parameter
        $conferenceId = $request->query->get('conference');
        $conference = null;

        if ($conferenceId) {
            $conference = $this->conferenceService->findConferenceById($conferenceId);
        }

        $session = new Session();
        if ($conference) {
            $session->setConference($conference);
        }

        $form = $this->createForm(SessionType::class, $session, [
            'available_rooms' => $this->sessionService->getAllRooms(),
            'scheduled_conferences' => $this->conferenceService->findConferenceByStatus(Conference::STATUS_SCHEDULED),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $success = $this->sessionService->createSession($session->getConference(), [
                'room' => $session->getRoom(),
                'startTime' => $session->getStartTime()->format('Y-m-d H:i:s'),
                'endTime' => $session->getEndTime()->format('Y-m-d H:i:s'),
            ]);

            if ($success) {
                $this->addFlash('success', 'Session created successfully');

                if ($conference) {
                    return $this->redirectToRoute('app_sessions_conference', ['id' => $conference->getId()]);
                }

                return $this->redirectToRoute('app_sessions_index');
            }

            $this->addFlash('error', 'Cannot create session. Check for conflicts or invalid data');
        }

        return $this->render('session/new.html.twig', [
            'form' => $form,
            'conference' => $conference,
        ]);
    }

    /**
     * Edit a session
     */
    #[Route('/{id}/edit', name: 'app_sessions_edit', methods: ['GET', 'POST'])]
    public function edit(Session $session, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(SessionType::class, $session, [
            'available_rooms' => $this->sessionService->getAllRooms(),
            'scheduled_conferences' => [$session->getConference()],
            'is_edit' => true,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $success = $this->sessionService->updateSession($session, [
                'room' => $session->getRoom(),
                'startTime' => $session->getStartTime()->format('Y-m-d H:i:s'),
                'endTime' => $session->getEndTime()->format('Y-m-d H:i:s'),
            ]);

            if ($success) {
                $this->addFlash('success', 'Session updated successfully');
                return $this->redirectToRoute('app_sessions_conference', ['id' => $session->getConference()->getId()]);
            }

            $this->addFlash('error', 'Cannot update session. Check for conflicts or invalid data');
        }

        return $this->render('session/edit.html.twig', [
            'form' => $form,
            'session' => $session,
        ]);
    }

    /**
     * Delete a session
     */
    #[Route('/{id}/delete', name: 'app_sessions_delete', methods: ['POST'])]
    public function delete(Session $session, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('delete'.$session->getId(), $request->request->get('_token'))) {
            $conferenceId = $session->getConference()->getId();

            $success = $this->sessionService->deleteSession($session);

            if ($success) {
                $this->addFlash('success', 'Session deleted successfully');
            } else {
                $this->addFlash('error', 'Cannot delete session');
            }

            return $this->redirectToRoute('app_sessions_conference', ['id' => $conferenceId]);
        }

        $this->addFlash('error', 'Invalid CSRF token');
        return $this->redirectToRoute('app_sessions_index');
    }
}