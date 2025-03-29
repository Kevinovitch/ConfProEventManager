<?php

namespace App\DataFixtures;

use App\Entity\Conference;
use App\Entity\Feedback;
use App\Entity\Registration;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Fixtures for conference feedback
 */
class FeedbackFixtures extends Fixture implements DependentFixtureInterface
{
    // Sample feedback comments by rating
    private array $feedbackComments = [
        5 => [
            'Absolutely outstanding! The presenter was engaging and the content was perfectly tailored to the audience.',
            'One of the best conferences I\'ve attended. Very informative and well-structured.',
            'Excellent content and delivery. I learned so much that I can apply immediately.',
            'The speaker was exceptional and the materials were top notch.',
        ],
        4 => [
            'Very good presentation with valuable insights. Just a bit rushed toward the end.',
            'Great content overall, although a few more real-world examples would have been nice.',
            'The speaker was knowledgeable and engaging. Some technical issues with slides though.',
            'I enjoyed it and learned a lot. Some Q&A time would have made it perfect.',
        ],
        3 => [
            'Decent content but the presentation could have been more engaging.',
            'The information was useful, but the delivery was somewhat dry.',
            'Good topic, average presentation. Still worth attending.',
            'Some interesting points, but felt like the material could have been condensed.',
        ],
        2 => [
            'The topic was interesting but the presentation was difficult to follow.',
            'Too basic for the advertised audience level. Disappointed.',
            'The content seemed outdated and the speaker wasn\'t well-prepared for questions.',
            'Technical issues and poor organization made this less valuable than it could have been.',
        ],
        1 => [
            'Unfortunately, this didn\'t meet my expectations at all.',
            'Very disorganized and hard to follow. Wouldn\'t recommend.',
            'The content didn\'t match the description and was too basic.',
            'Too many technical problems and the speaker seemed unprepared.',
        ],
    ];

    // Sample aspect ratings
    private array $aspects = [
        'content', 'presenter', 'organization', 'technical', 'relevance'
    ];

    public function load(ObjectManager $manager): void
    {
        // Get all registered participants who attended
        $registrations = $manager->getRepository(Registration::class)->findBy([
            'attended' => true
        ]);

        if (empty($registrations)) {
            // If no attended registrations found, modify some to be attended
            $allRegistrations = $manager->getRepository(Registration::class)->findAll();
            $registrationsToModify = array_slice($allRegistrations, 0, min(count($allRegistrations), 15));

            foreach ($registrationsToModify as $registration) {
                $registration->setAttended(true);
                $manager->persist($registration);
            }

            $manager->flush();
            $registrations = $registrationsToModify;
        }

        // Create feedback for each attended registration
        foreach ($registrations as $index => $registration) {
            // For realism, not everyone submits feedback
            if (mt_rand(0, 10) < 3) {
                continue; // Skip some registrations to simulate partial feedback submission
            }

            // Generate a rating weighted toward more positive reviews (typical in feedback systems)
            $ratingDistribution = [1 => 5, 2 => 10, 3 => 20, 4 => 30, 5 => 35]; // Percentages
            $randPercent = mt_rand(1, 100);
            $cumulativePercent = 0;
            $rating = 3; // Default if something goes wrong

            foreach ($ratingDistribution as $r => $percent) {
                $cumulativePercent += $percent;
                if ($randPercent <= $cumulativePercent) {
                    $rating = $r;
                    break;
                }
            }

            $feedback = new Feedback();
            $feedback->setRegistration($registration);
            $feedback->setRating($rating);

            // Add comments for about 70% of feedback
            if (mt_rand(0, 10) < 7) {
                $comments = $this->feedbackComments[$rating];
                $feedback->setComment($comments[array_rand($comments)]);
            }

            // Add aspect rated for about 50% of feedback
            if (mt_rand(0, 10) < 5) {
                $feedback->setAspectRated($this->aspects[array_rand($this->aspects)]);
            }

            $manager->persist($feedback);
        }

        $manager->flush();
    }

    /**
     * Get dependencies for this fixture
     */
    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            RegistrationFixtures::class,
        ];
    }
}