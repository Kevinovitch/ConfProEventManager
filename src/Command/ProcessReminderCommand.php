<?php

namespace App\Command;

use App\Repository\ConferenceRepository;
use App\Service\NotificationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to process and send reminder notifications
 */
#[AsCommand(
    name: 'app:process-reminders',
    description: 'Process and send reminders for upcoming conferences',
)]
class ProcessReminderCommand extends Command
{
    public function __construct(
        private readonly ConferenceRepository $conferenceRepository,
        private readonly NotificationService $notificationService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Processing Conference Reminders');

        // Get conferences scheduled for tomorrow
        $tomorrow = new \DateTimeImmutable('+1 day');
        $startOfTomorrow = $tomorrow->setTime(0, 0, 0);
        $endOfTomorrow = $tomorrow->setTime(23, 59, 59);

        $upcomingConferences = $this->conferenceRepository->findUpcomingConferences($startOfTomorrow, $endOfTomorrow);

        $io->info(sprintf('Found %d conferences scheduled for tomorrow', count($upcomingConferences)));

        $reminderCount = 0;
        $presenterReminderCount = 0;

        foreach ($upcomingConferences as $conference) {
            // Send participant reminders
            $count = $this->notificationService->sendConferenceReminders($conference);
            $reminderCount += $count;

            $io->writeln(sprintf(
                'Sent %d participant reminders for conference: %s',
                $count,
                $conference->getTitle()
            ));

            // Send presenter reminder
            if ($this->notificationService->sendPresenterReminder($conference)) {
                $presenterReminderCount++;
                $io->writeln(sprintf(
                    'Sent presenter reminder for conference: %s',
                    $conference->getTitle()
                ));
            }
        }

        // Find conferences that ended yesterday for feedback requests
        $yesterday = new \DateTimeImmutable('-1 day');
        $startOfYesterday = $yesterday->setTime(0, 0, 0);
        $endOfYesterday = $yesterday->setTime(23, 59, 59);

        $pastConferences = $this->conferenceRepository->findPastConferences($startOfYesterday, $endOfYesterday);

        $io->info(sprintf('Found %d conferences that ended yesterday', count($pastConferences)));

        $feedbackRequestCount = 0;

        foreach ($pastConferences as $conference) {
            $count = $this->notificationService->sendFeedbackRequests($conference);
            $feedbackRequestCount += $count;

            $io->writeln(sprintf(
                'Sent %d feedback requests for conference: %s',
                $count,
                $conference->getTitle()
            ));
        }

        $io->success(sprintf(
            'Successfully sent %d participant reminders, %d presenter reminders, and %d feedback requests',
            $reminderCount,
            $presenterReminderCount,
            $feedbackRequestCount
        ));

        return Command::SUCCESS;
    }
}