<?php

namespace App\Controller;

use App\Entity\Conference;
use App\Entity\User;
use App\Form\ConferenceType;
use App\Repository\ConferenceRepository;
use App\Service\ConferenceService;
use App\Service\ModerationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Web controller for conference management
 */
#[Route('/conferences')]
class ConferenceController extends AbstractController
{

    public function __construct(
        private ConferenceService $conferenceService,
        private WorkflowInterface $conferenceStateMachine,
        private ModerationService $moderationService,
    ){}
    /**
     * List all conferences
     */
    #[Route('', name: 'app_conferences_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('conference/index.html.twig', [
            'conferences' => $this->conferenceService->findAllConferences(),
        ]);
    }

    /**
     * Create a new conference
     */
    #[Route('/new', name: 'app_conferences_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        // Check if user is a presenter
        if (!$this->isGranted('ROLE_PRESENTER')) {
            $this->addFlash('error', 'You must be a presenter to create a conference');
            return $this->redirectToRoute('app_conferences_index');
        }

        /** @var User $user  */
        $user = $this->getUser();

        $form = $this->createForm(ConferenceType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $conference = $this->conferenceService->createConference([
                'title' => $data->getTitle(),
                'description' => $data->getDescription(),
            ], $user);

            if ($conference) {
                $this->addFlash('success', 'Conference created successfully');
                return $this->redirectToRoute('app_conferences_index');
            } else {
                $this->addFlash('error', 'Failed to create conference');
            }
        }

        return $this->render('conference/new.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * Show a single conference
     */
    #[Route('/{id}', name: 'app_conferences_show', methods: ['GET'])]
    public function show(Conference $conference): Response
    {
        /** @var User $user  */
        $user = $this->getUser();

        // Get moderation request if user is moderator
        $moderationRequest = null;
        if ($this->isGranted('ROLE_MODERATOR')) {
            $moderationRequest = $this->moderationService->getModerationRequestByConferenceAndByUser($conference, $user, 'pending');
        }

        return $this->render('conference/show.html.twig', [
            'conference' => $conference,
            'moderation_request' => $moderationRequest,
            'transitions' => $this->conferenceStateMachine->getEnabledTransitions($conference)
        ]);
    }

    /**
     * Submit a conference for validation
     */
    #[Route('/{id}/submit', name: 'app_conferences_submit', methods: ['POST'])]
    public function submit(Conference $conference): Response
    {
        // Check if user is the presenter of the conference
        if ($conference->getPresenter() !== $this->getUser()) {
            $this->addFlash('error', 'You are not allowed to submit this conference');
            return $this->redirectToRoute('app_conferences_show', ['id' => $conference->getId()]);
        }

        // Check if the transition is possible
        if (!$this->conferenceStateMachine->can($conference, 'to_validation')) {
            $this->addFlash('error', 'Cannot submit this conference for validation');
            return $this->redirectToRoute('app_conferences_show', ['id' => $conference->getId()]);
        }

        $success = $this->conferenceService->submitForValidation($conference);

        if ($success) {
            // Get the moderator information
            $moderator = $conference->getModerator();
            $moderatorName = $moderator ? $moderator->getName() : 'an administrator';
            $moderatorEmail = $moderator ? $moderator->getEmail() : '';

            // Add success message with moderator information
            $this->addFlash(
                'success',
                sprintf(
                    'Conference submitted for validation. It will be reviewed by %s%s.',
                    $moderatorName,
                    $moderatorEmail ? ' (' . $moderatorEmail . ')' : ''
                )
            );
        } else {
            $this->addFlash('error', 'Unable to submit conference');
        }

        return $this->redirectToRoute('app_conferences_show', ['id' => $conference->getId()]);
    }

    /**
     * Schedule a conference
     */
    #[Route('/{id}/schedule', name: 'app_conferences_schedule', methods: ['POST'])]
    public function schedule(Conference $conference, Request $request): Response
    {
        // If the user is neither an admin nor the moderator of this conference, we deny access to this action
        if (!($this->isGranted('ROLE_ADMIN') || $conference->getModerator() === $this->getUser())) {
            throw $this->createAccessDeniedException('You are not authorized to schedule this conference');
        }

        $scheduledAt = $request->request->get('scheduledAt');
        if (!$scheduledAt) {
            $this->addFlash('error', 'Scheduled date is required');
            return $this->redirectToRoute('app_conferences_show', ['id' => $conference->getId()]);
        }


        try {
            $scheduledDate = new \DateTimeImmutable($scheduledAt);

            $conference->setScheduledAt($scheduledDate);

            // Check if the transition is possible
            if (!$this->conferenceStateMachine->can($conference, 'to_scheduled')) {
                $this->addFlash('error', 'Cannot schedule this conference in its current state');
                return $this->redirectToRoute('app_conferences_show', ['id' => $conference->getId()]);
            }

            $success = $this->conferenceService->scheduleConference($conference, $scheduledDate);

            if ($success) {
                $this->addFlash('success', 'Conference scheduled successfully');
            } else {
                $this->addFlash('error', 'Unable to schedule conference');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Invalid date format');
            return $this->redirectToRoute('app_conferences_show', ['id' => $conference->getId()]);
        }



        return $this->redirectToRoute('app_conferences_show', ['id' => $conference->getId()]);
    }

    /**
     * Archive a conference
     */
    #[Route('/{id}/archive', name: 'app_conferences_archive', methods: ['POST'])]
    public function archive(Conference $conference): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Check if the transition is possible
        if (!$this->conferenceStateMachine->can($conference, 'to_archived')) {
            $this->addFlash('error', 'Cannot archive this conference in its current state');
            return $this->redirectToRoute('app_conferences_show', ['id' => $conference->getId()]);
        }

        $success = $this->conferenceService->archiveConference($conference);

        if ($success) {
            $this->addFlash('success', 'Conference archived successfully');
        } else {
            $this->addFlash('error', 'Unable to archive conference');
        }

        return $this->redirectToRoute('app_conferences_show', ['id' => $conference->getId()]);
    }

}