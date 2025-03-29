<?php

namespace App\DataFixtures;

use App\Entity\Conference;
use App\Entity\Registration;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Fixtures for conference registrations
 */
class RegistrationFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // Get all scheduled and archived conferences
        $conferences = $manager->getRepository(Conference::class)->findBy([
            'status' => [Conference::STATUS_SCHEDULED, Conference::STATUS_ARCHIVED]
        ]);

        // Get all participants
        $participants = $manager->getRepository(User::class)->findBy([
            'roles' => 'ROLE_PARTICIPANT'
        ]);

        // If no participants found, use any user
        if (empty($participants)) {
            $participants = $manager->getRepository(User::class)->findAll();
        }

        // For each conference, register random participants
        foreach ($conferences as $conference) {
            // Determine how many people to register (between 40% and 80% of available participants)
            $numberOfParticipants = rand(
                (int)(count($participants) * 0.4),
                (int)(count($participants) * 0.8)
            );

            // Shuffle participants to randomize
            shuffle($participants);
            $selectedParticipants = array_slice($participants, 0, $numberOfParticipants);

            foreach ($selectedParticipants as $participant) {
                $registration = new Registration();
                $registration->setUser($participant);
                $registration->setConference($conference);
                $registration->generateQrCode();

                // For archived conferences, mark some as attended (between 70% and 90%)
                if ($conference->getStatus() === Conference::STATUS_ARCHIVED) {
                    if (rand(1, 100) <= rand(70, 90)) {
                        $registration->setAttended(true);
                    }
                }
                // For scheduled conferences that are in the past, mark some as attended
                elseif ($conference->getScheduledAt() !== null && $conference->getScheduledAt() < new \DateTimeImmutable()) {
                    if (rand(1, 100) <= rand(70, 90)) {
                        $registration->setAttended(true);
                    }
                }

                $manager->persist($registration);
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
            UserFixtures::class,
            ConferenceFixtures::class,
        ];
    }
}