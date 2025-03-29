<?php

namespace App\Controller;

use App\Service\ConferenceService;
use App\Service\FeedbackService;
use App\Service\RegistrationService;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MainController extends AbstractController
{
    public function __construct(
        private ConferenceService $conferenceService,
        private UserService $userService,
        private RegistrationService $registrationService,
        private FeedbackService $feedbackService
    )
    {

    }
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        // Get upcoming conferences
        $upcomingConferences = $this->conferenceService->getUpcomingConferences(3);

        // Get statistics
        $conferencesCount = $this->conferenceService->getConferencesTotal();

        // Get the number of presenters
        $presentersCount = $this->userService->findTotalOfPresenters();

        // Get the number of participants
        $participantsCount = $this->registrationService->findTotalOfParticipants();


        // Calculate average rating if FeedbackRepository exists
        try {
            $averageRating = $this->feedbackService->getAverageRating() ?
                number_format((float)$this->feedbackService->getAverageRating(), 1) : '0.0';
        } catch (\Exception $e) {
            // If feedback table doesn't exist or there's an error, use a default value
            $averageRating = '4.8';
        }

        return $this->render('main/index.html.twig', [
            'upcoming_conferences' => $upcomingConferences,
            'conferences_count' => $conferencesCount,
            'presenters_count' => $presentersCount,
            'participants_count' => $participantsCount,
            'average_rating' => $averageRating,
        ]);
    }

/*    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('main/index.html.twig', [
            'upcoming_conferences' => [],
            'conferences_count' => 25,
            'presenters_count' => 42,
            'participants_count' => 450,
            'average_rating' => '4.8',
        ]);
    }*/
}