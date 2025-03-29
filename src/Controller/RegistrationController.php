<?php

namespace App\Controller;

use App\Entity\Conference;
use App\Entity\User;
use App\Service\ConferenceService;
use App\Service\RegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Web controller for participant registrations
 */
#[Route('/registrations')]
class RegistrationController extends AbstractController
{
    public function __construct(
        private RegistrationService $registrationService,
        private ConferenceService $conferenceService,
    )
    {
    }
    /**
     * Display user's registrations
     */
    #[Route('', name: 'app_user_registrations', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user  */
        $user = $this->getUser();

        $registrations = $this->registrationService->getUserRegistrations($user);

        return $this->render('registration/index.html.twig', [
            'registrations' => $registrations,
        ]);
    }

    /**
     * Register for a conference
     */
    #[Route('/conference/{id}/register', name: 'app_register_for_conference', methods: ['POST'])]
    public function register(Conference $conference): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user  */
        $user = $this->getUser();

        $registration = $this->registrationService->registerForConference($user, $conference);

        if (!$registration) {
            $this->addFlash('error', 'Cannot register for this conference. It may not be in scheduled status.');
            return $this->redirectToRoute('app_conferences_show', ['id' => $conference->getId()]);
        }

        $this->addFlash('success', 'Successfully registered for the conference!');
        return $this->redirectToRoute('app_conferences_show', ['id' => $conference->getId()]);
    }

    /**
     * Cancel registration for a conference
     */
    #[Route('/conference/{id}/unregister', name: 'app_unregister_from_conference', methods: ['POST'])]
    public function unregister(Conference $conference): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user  */
        $user = $this->getUser();

        $registration = $this->registrationService->findRegistrationByUserAndConference($user, $conference);

        if (!$registration) {
            $this->addFlash('error', 'You are not registered for this conference.');
            return $this->redirectToRoute('app_conferences_show', ['id' => $conference->getId()]);
        }

        $success = $this->registrationService->cancelRegistration($registration);

        if (!$success) {
            $this->addFlash('error', 'Cannot cancel this registration. You may have already attended.');
            return $this->redirectToRoute('app_conferences_show', ['id' => $conference->getId()]);
        }

        $this->addFlash('success', 'Registration canceled successfully.');
        return $this->redirectToRoute('app_conferences_show', ['id' => $conference->getId()]);
    }

    /**
     * QR code scanner for check-in
     */
    #[Route('/checkin', name: 'app_registration_checkin', methods: ['GET', 'POST'])]
    public function checkin(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $registration = null;
        $error = null;
        $success = null;

        if ($request->isMethod('POST')) {
            $qrCode = $request->request->get('qrCode');

            if (!$qrCode) {
                $error = 'QR code is required.';
            } else {
                $registration = $this->registrationService->findByQrCode($qrCode);

                if (!$registration) {
                    $error = 'Invalid QR code.';
                } else {
                    if ($registration->isAttended()) {
                        $error = 'This participant has already checked in.';
                    } else {
                        $success = $this->registrationService->checkInRegistration($registration);
                        if ($success) {
                            $success = 'Participant checked in successfully!';
                            $registration = null; // Reset for next check-in
                        } else {
                            $error = 'Failed to check in participant.';
                        }
                    }
                }
            }
        }

        return $this->render('registration/checkin.html.twig', [
            'registration' => $registration,
            'error' => $error,
            'success' => $success,
        ]);
    }

    /**
     * Display participants for a conference
     */
    #[Route('/participants/{id}', name: 'app_conference_participants', methods: ['GET'])]
    public function participants(Conference $conference): Response
    {
        // Only admins and the presenter of the conference can view participants
        if (!$this->isGranted('ROLE_ADMIN') && $conference->getPresenter() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You do not have permission to view participants.');
        }

        $registrations = $this->registrationService->getConferenceRegistrations($conference);
        $stats = $this->registrationService->getConferenceStatistics($conference);

        return $this->render('registration/participants.html.twig', [
            'conference' => $conference,
            'registrations' => $registrations,
            'stats' => $stats,
        ]);
    }

    /**
     * Admin dashboard for registrations
     */
    #[Route('/dashboard', name: 'app_registration_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $scheduledConferences = $this->conferenceService->findConferenceByStatus(Conference::STATUS_SCHEDULED);

        return $this->render('registration/dashboard.html.twig', [
            'conferences' => $scheduledConferences,
        ]);
    }
}