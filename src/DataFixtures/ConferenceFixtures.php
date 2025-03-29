<?php

namespace App\DataFixtures;

use App\Entity\Conference;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Fixtures for Conference entity with different workflow states
 */
class ConferenceFixtures extends Fixture implements DependentFixtureInterface
{
    // Sample conference titles
    private array $conferenceTitles = [
        'Modern Web Development with Symfony',
        'Mastering API Design with OpenAPI',
        'Workflow Components in PHP Applications',
        'Doctrine ORM Best Practices',
        'Microservices Architecture for Enterprise Applications',
        'Containerization with Docker and Kubernetes',
        'Frontend Performance Optimization Techniques',
        'Advanced Testing Strategies for Web Applications',
        'Real-time Applications with WebSockets',
        'Introduction to Event Sourcing',
        'Securing Modern Web Applications',
    ];

    // Sample conference descriptions
    private array $conferenceDescriptions = [
        'This session will explore the latest features and best practices in %s. Learn how to build secure, scalable and maintainable applications using modern techniques.',
        'Join us for an in-depth look at %s. This presentation covers both fundamental concepts and advanced strategies that you can apply immediately in your projects.',
        'A comprehensive guide to %s. This session is designed for developers looking to enhance their skills and stay up-to-date with current industry trends.',
        'Discover practical approaches to %s that can improve your development workflow. This talk includes live coding demonstrations and real-world examples.',
        'This talk dives deep into %s, exploring both theoretical foundations and practical implementations. Perfect for developers looking to expand their technical expertise.',
    ];

    public function load(ObjectManager $manager): void
    {
        // Get only users whose email starts with "moderator"
        $moderators = [];
        for ($i = 1; $i <= 3; $i++) {
            $moderators[] = $this->getReference("moderator_{$i}", User::class);
        }
        // Add admin as fallback moderator
        $moderators[] = $this->getReference("admin_user", User::class);

        // Get presenters
        $presenters = [];
        for ($i = 1; $i <= 5; $i++) {
            $presenters[] = $this->getReference("presenter_{$i}", User::class);
        }

        // Create conferences in different states to demonstrate workflow
        $statuses = [
            Conference::STATUS_SUBMITTED => 4,      // 4 conferences in submitted state
            Conference::STATUS_UNDER_VALIDATION => 3, // 3 conferences in validation state
            Conference::STATUS_SCHEDULED => 5,      // 5 conferences in scheduled state
            Conference::STATUS_ARCHIVED => 2,       // 2 conferences in archived state
        ];

        $conferenceCount = 0;

        foreach ($statuses as $status => $count) {
            for ($i = 0; $i < $count; $i++) {
                $conference = new Conference();

                // Use a different title for each conference
                $titleIndex = $conferenceCount % count($this->conferenceTitles);
                $title = $this->conferenceTitles[$titleIndex];

                // Add a suffix to ensure uniqueness
                if ($conferenceCount >= count($this->conferenceTitles)) {
                    $title .= ' ' . ceil($conferenceCount / count($this->conferenceTitles));
                }

                $conference->setTitle($title);

                // Generate description
                $descriptionTemplate = $this->conferenceDescriptions[array_rand($this->conferenceDescriptions)];
                $description = sprintf($descriptionTemplate, $title);
                $conference->setDescription($description);

                // Set status
                $conference->setStatus($status);

                // Assign presenter - using explicit bidirectional relation
                $presenter = $presenters[array_rand($presenters)];
                $conference->setPresenter($presenter);
                $presenter->addPresentedConference($conference);

                // Assign moderator - using explicit bidirectional relation
                $moderator = $moderators[array_rand($moderators)];
                $conference->setModerator($moderator);
                $moderator->addModeratedConference($conference);

                // Set scheduled date for scheduled and archived conferences
                if ($status === Conference::STATUS_SCHEDULED) {
                    // Future date
                    $daysInFuture = rand(7, 90); // Between 1 week and 3 months
                    $scheduledAt = new \DateTimeImmutable("+$daysInFuture days");
                    $conference->setScheduledAt($scheduledAt);
                } elseif ($status === Conference::STATUS_ARCHIVED) {
                    // Past date
                    $daysInPast = rand(7, 90); // Between 1 week and 3 months ago
                    $scheduledAt = new \DateTimeImmutable("-$daysInPast days");
                    $conference->setScheduledAt($scheduledAt);
                }

                $manager->persist($conference);
                $conferenceCount++;

                // Create reference for other fixtures to use
                $this->addReference('conference_' . $conferenceCount, $conference);
            }
        }

        $manager->flush();
    }

    /**
     * Get dependencies for this fixture
     */
    public function getDependencies(): array
    {
        return [
            UserFixtures::class
        ];
    }
}