<?php

namespace App\Command;

use App\Entity\Conference;
use App\Repository\ConferenceRepository;
use App\Service\NotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to send reminders for upcoming conferences
 */
#[AsCommand(
    name: 'app:send-reminders',
    description: 'Send reminders for conferences scheduled for tomorrow',
)]
class SendRemindersCommand extends Command
{
    public function __construct(
        private ConferenceRepository $conferenceRepository,
        private NotificationService $notificationService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Sending Reminders for Upcoming Conferences');

        // Find conferences scheduled for tomorrow
        $tomorrow = new \DateTimeImmutable('+1 day');
        $startOfDay = $tomorrow->setTime(0, 0, 0);
        $endOfDay = $tomorrow->setTime(23, 59, 59);

        $upcomingConferences = $this->conferenceRepository->createQueryBuilder('c')
            ->andWhere('c.status = :status')
            ->andWhere('c.scheduledAt >= :startOfDay')
            ->andWhere('c.scheduledAt <= :endOfDay')
            ->setParameter('status', Conference::STATUS_SCHEDULED)
            ->setParameter('startOfDay', $startOfDay)
            ->setParameter('endOfDay', $endOfDay)
            ->getQuery()
            ->getResult();

        $conferenceCount = count($upcomingConferences);
        $io->info(sprintf('Found %d conferences scheduled for tomorrow', $conferenceCount));

        if ($conferenceCount === 0) {
            $io->success('No reminders to send');
            return Command::SUCCESS;
        }

        $participantReminderCount = 0;
        $presenterReminderCount = 0;

        foreach ($upcomingConferences as $conference) {
            $io->section('Processing: ' . $conference->getTitle());

            // Send reminders to participants
            $sentCount = $this->notificationService->sendConferenceReminders($conference);
            $participantReminderCount += $sentCount;
            $io->text(sprintf('Sent %d participant reminders', $sentCount));

            // Send reminder to presenter
            $success = $this->notificationService->sendPresenterReminder($conference);
            if ($success) {
                $presenterReminderCount++;
                $io->text('Sent presenter reminder');
            } else {
                $io->warning('Failed to send presenter reminder');
            }
        }

        $io->success(sprintf(
            'Sent %d participant reminders and %d presenter reminders',
            $participantReminderCount,
            $presenterReminderCount
        ));

        return Command::SUCCESS;
    }
}