<?php

namespace App\Service;

use App\Entity\Feedback;
use App\Entity\Registration;
use App\Repository\FeedbackRepository;
use App\Repository\RegistrationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Service for managing conference feedback
 */
class FeedbackService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FeedbackRepository $feedbackRepository,
        private ValidatorInterface $validator
    ) {
    }

    /**
     * Submit feedback for a conference
     */
    public function submitFeedback(Registration $registration, array $data): ?Feedback
    {
        // Check if user attended the conference
        if (!$registration->isAttended()) {
            return null;
        }

        // Check if feedback already exists
        $existingFeedback = $this->feedbackRepository->findOneBy(['registration' => $registration]);
        if ($existingFeedback) {
            return null;
        }

        // Create new feedback
        $feedback = new Feedback();
        $feedback->setRegistration($registration);
        $feedback->setRating($data['rating']);

        if (isset($data['comment'])) {
            $feedback->setComment($data['comment']);
        }

        if (isset($data['aspectRated'])) {
            $feedback->setAspectRated($data['aspectRated']);
        }

        // Validate feedback
        $errors = $this->validator->validate($feedback);
        if (count($errors) > 0) {
            return null;
        }

        // Save to database
        $this->entityManager->persist($feedback);
        $this->entityManager->flush();

        return $feedback;
    }

    /**
     * Get feedback statistics for a conference
     */
    public function getConferenceStats($conference): array
    {
        return $this->feedbackRepository->getConferenceStats($conference);
    }

    /**
     * Get latest feedback comments for a conference
     */
    public function getLatestComments($conference, int $limit = 5): array
    {
        return $this->feedbackRepository->getLatestComments($conference, $limit);
    }

    /**
     * Get average rating of feebacks for a conference
     */
    public function getAverageRating(): ?float
    {
        return $this->feedbackRepository->getAverageRating();
    }
}