<?php

namespace App\Command;

use App\Entity\Conference;
use App\Repository\ConferenceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Command to test workflow transitions
 */
#[AsCommand(
    name: 'app:workflow:transition',
    description: 'Apply a workflow transition to a conference',
)]
class WorkflowTransitionCommand extends Command
{


    public function __construct(
        private ConferenceRepository $conferenceRepository,
        private WorkflowInterface $conferenceStateMachine,
        private EntityManagerInterface $entityManager,
    ) {


        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('conference-id', InputArgument::REQUIRED, 'The UUID of the conference')
            ->addArgument('transition', InputArgument::REQUIRED, 'The transition to apply (to_validation, to_scheduled, to_archived, back_to_submitted)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $conferenceId = $input->getArgument('conference-id');
        $transition = $input->getArgument('transition');

        try {
            $uuid = Uuid::fromString($conferenceId);
            $conference = $this->conferenceRepository->find($uuid);

            if (!$conference) {
                $io->error(sprintf('Conference with ID "%s" not found', $conferenceId));
                return Command::FAILURE;
            }

            // Check if the transition is possible
            if (!$this->conferenceStateMachine->can($conference, $transition)) {
                $io->error(sprintf(
                    'Transition "%s" cannot be applied to conference "%s" (current state: %s)',
                    $transition,
                    $conference->getTitle(),
                    $conference->getStatus()
                ));

                // List available transitions
                $transitions = $this->conferenceStateMachine->getEnabledTransitions($conference);
                $transitionNames = array_map(fn($t) => $t->getName(), $transitions);

                if (count($transitionNames) > 0) {
                    $io->info(sprintf('Available transitions: %s', implode(', ', $transitionNames)));
                } else {
                    $io->info('No transitions available for this conference');
                }

                return Command::FAILURE;
            }

            // Apply the transition
            $this->conferenceStateMachine->apply($conference, $transition);

            // Save changes

            $this->entityManager->flush();

            $io->success(sprintf(
                'Successfully applied transition "%s" to conference "%s" - new state: %s',
                $transition,
                $conference->getTitle(),
                $conference->getStatus()
            ));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }
}