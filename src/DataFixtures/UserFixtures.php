<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Fixtures for User entity with various roles
 */
class UserFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        // Create admin user
        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setName('Admin User');
        $admin->setRoles([User::ROLE_ADMIN, User::ROLE_MODERATOR]); // Admin can also moderate
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'password'));
        $manager->persist($admin);
        $this->addReference('admin_user', $admin);

        // Create moderators - make sure emails start with "moderator"
        for ($i = 1; $i <= 3; $i++) {
            $moderator = new User();
            $moderator->setEmail("moderator{$i}@example.com");
            $moderator->setName("Moderator {$i}");
            $moderator->setRoles([User::ROLE_MODERATOR]);
            $moderator->setPassword($this->passwordHasher->hashPassword($moderator, 'password'));
            $manager->persist($moderator);
            $this->addReference("moderator_{$i}", $moderator);
        }

        // Create presenters
        for ($i = 1; $i <= 5; $i++) {
            $presenter = new User();
            $presenter->setEmail("presenter{$i}@example.com");
            $presenter->setName("Presenter {$i}");
            $presenter->setRoles([User::ROLE_PRESENTER]);
            $presenter->setPassword($this->passwordHasher->hashPassword($presenter, 'password'));
            $manager->persist($presenter);
            $this->addReference("presenter_{$i}", $presenter);
        }

        // Create participants
        for ($i = 1; $i <= 20; $i++) {
            $participant = new User();
            $participant->setEmail("participant{$i}@example.com");
            $participant->setName("Participant {$i}");
            $participant->setRoles([User::ROLE_PARTICIPANT]);
            $participant->setPassword($this->passwordHasher->hashPassword($participant, 'password'));
            $manager->persist($participant);
            $this->addReference("participant_{$i}", $participant);
        }

        $manager->flush();
    }
}