<?php

namespace App\DataFixtures;

use App\Entity\Conference;
use App\Entity\ModerationRequest;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ModerationRequestFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // Get all conferences that are in "under_validation" status
        $conferenceRepository = $manager->getRepository(Conference::class);
        $conferences = $conferenceRepository->findByStatus(Conference::STATUS_UNDER_VALIDATION);

        foreach ($conferences as $conference) {
            // Check if a moderation request already exists for this conference
            $moderationRequestRepository = $manager->getRepository(ModerationRequest::class);
            $existingRequest = $moderationRequestRepository->findOneBy(['conference' => $conference]);

            // Only create a new request if none exists
            if (!$existingRequest) {
                $moderator = $conference->getModerator();

                if ($moderator) {
                    $moderationRequest = new ModerationRequest();

                    // Set conference - explicit bidirectional relation
                    $moderationRequest->setConference($conference);
                    $conference->addModerationRequest($moderationRequest);

                    // Set moderator - explicit bidirectional relation
                    $moderationRequest->setModerator($moderator);
                    $moderator->addModerationRequest($moderationRequest);

                    $moderationRequest->setStatus(ModerationRequest::STATUS_PENDING);

                    $manager->persist($moderationRequest);
                }
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            ConferenceFixtures::class,
            UserFixtures::class,
        ];
    }
}