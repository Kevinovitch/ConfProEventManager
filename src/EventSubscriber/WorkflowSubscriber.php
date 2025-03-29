<?php

namespace App\EventSubscriber;


use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\Event;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\TransitionBlocker;

/**
 * Event subscriber for workflow transitions
 */
class WorkflowSubscriber implements EventSubscriberInterface
{

    public static function getSubscribedEvents(): array
    {
        return [
            // Conference transitions
            'workflow.conference.completed.to_validation' => 'onConferenceToValidation',
            'workflow.conference.completed.to_scheduled' => 'onConferenceToScheduled',
            'workflow.conference.completed.to_archived' => 'onConferenceToArchived',
            'workflow.conference.completed.back_to_submitted' => 'onConferenceBackToSubmitted',

            // Guard events for conference transitions
            'workflow.conference.guard.to_scheduled' => 'guardConferenceToScheduled',

            // ModerationRequest transitions
            'workflow.moderation_request.completed.accept' => 'onModerationRequestAccepted',
            'workflow.moderation_request.completed.reject' => 'onModerationRequestRejected',
        ];
    }

    /**
     * Conference transitions to under_validation
     */
    public function onConferenceToValidation(Event $event): void
    {
        $conference = $event->getSubject();

        // Log transition
        error_log(sprintf(
            'Conference "%s" transitioned to under_validation state',
            $conference->getTitle()
        ));

        // This could trigger a notification to moderators
        // Typically would be done through NotificationService
    }

    /**
     * Conference transitions to scheduled
     */
    public function onConferenceToScheduled(Event $event): void
    {
        $conference = $event->getSubject();

        // Log transition
        error_log(sprintf(
            'Conference "%s" has been scheduled',
            $conference->getTitle()
        ));

        // This could trigger a notification to the presenter
        // And potentially to all registered participants
    }

    /**
     * Conference transitions to archived
     */
    public function onConferenceToArchived(Event $event): void
    {
        $conference = $event->getSubject();

        // Log transition
        error_log(sprintf(
            'Conference "%s" has been archived',
            $conference->getTitle()
        ));

        // Could trigger feedback requests to attendees
    }

    /**
     * Conference transitions back to submitted
     */
    public function onConferenceBackToSubmitted(Event $event): void
    {
        $conference = $event->getSubject();

        // Log transition
        error_log(sprintf(
            'Conference "%s" has been sent back to submitted state',
            $conference->getTitle()
        ));

        // Could notify the presenter that changes are needed
    }

    /**
     * Guard for conference transition to scheduled
     * Prevents transition if no scheduledAt date is set
     */
    public function guardConferenceToScheduled(GuardEvent $event): void
    {
        $conference = $event->getSubject();

        if (!$conference->getScheduledAt()) {
            $event->addTransitionBlocker(
                new TransitionBlocker(
                    'Cannot schedule conference without a date',
                    'no_date_set'
                )
            );
        }
    }

    /**
     * ModerationRequest accepted
     */
    public function onModerationRequestAccepted(Event $event): void
    {
        $request = $event->getSubject();

        // Log transition
        error_log(sprintf(
            'Moderation request for conference "%s" has been accepted',
            $request->getConference()->getTitle()
        ));

        // Automatically update conference state
        $conference = $request->getConference();

        // This would be done in the service now using the workflow
    }

    /**
     * ModerationRequest rejected
     */
    public function onModerationRequestRejected(Event $event): void
    {
        $request = $event->getSubject();

        // Log transition
        error_log(sprintf(
            'Moderation request for conference "%s" has been rejected',
            $request->getConference()->getTitle()
        ));

        // Automatically update conference state
        $conference = $request->getConference();

        // This would be done in the service now using the workflow
    }
}