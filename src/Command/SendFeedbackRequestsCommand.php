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
 * Command to send feedback requests after conferences
 */
#[AsCommand(
    name: 'app:send-feedback-requests',
    description: 'Send feedback requests for conferences that ended yesterday',
)]
class SendFeedbackRequestsCommand extends Command
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
        $io->title('Sending Feedback Requests for Recently Ended Conferences');

        // Find conferences that ended yesterday
        $yesterday = new \DateTimeImmutable('-1 day');
        $startOfDay = $yesterday->setTime(0, 0, 0);
        $endOfDay = $yesterday->setTime(23, 59, 59);

        $recentConferences = $this->conferenceRepository->createQueryBuilder('c')
            ->andWhere('c.status = :status')
            ->andWhere('c.scheduledAt >= :startOfDay')
            ->andWhere('c.scheduledAt <= :endOfDay')
            ->setParameter('status', Conference::STATUS_SCHEDULED)
            ->setParameter('startOfDay', $startOfDay)
            ->setParameter('endOfDay', $endOfDay)
            ->getQuery()
            ->getResult();

        $conferenceCount = count($recentConferences);
        $io->info(sprintf('Found %d conferences that ended yesterday', $conferenceCount));

        if ($conferenceCount === 0) {
            $io->success('No feedback requests to send');
            return Command::SUCCESS;
        }

        $feedbackRequestCount = 0;

        foreach ($recentConferences as $conference) {
            $io->section('Processing: ' . $conference->getTitle());

            // Send feedback requests to attendees
            $sentCount = $this->notificationService->sendFeedbackRequests($conference);
            $feedbackRequestCount += $sentCount;

            $io->text(sprintf('Sent %d feedback requests', $sentCount));
        }

        $io->success(sprintf('Sent %d feedback requests in total', $feedbackRequestCount));

        return Command::SUCCESS;
    }
}