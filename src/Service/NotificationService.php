<?php

namespace App\Service;

use App\Entity\Conference;
use App\Entity\Media;
use App\Entity\Registration;
use App\Repository\RegistrationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Psr\Log\LoggerInterface;

/**
 * Service for sending notifications to users
 */
class NotificationService
{
    public function __construct(
        private MailerInterface $mailer,
        private RegistrationRepository $registrationRepository,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Send registration confirmation to a participant
     */
    public function sendRegistrationConfirmation(Registration $registration): bool
    {
        $user = $registration->getUser();
        $conference = $registration->getConference();

        try {
            $email = (new Email())
                ->from('no-reply@confpro.example.com')
                ->to($user->getEmail())
                ->subject('Registration Confirmation: ' . $conference->getTitle())
                ->html($this->renderRegistrationConfirmationTemplate($registration));

            $this->mailer->send($email);

            $this->logger->info('Registration confirmation sent', [
                'user_id' => $user->getId(),
                'conference_id' => $conference->getId(),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to send registration confirmation', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId(),
                'conference_id' => $conference->getId(),
            ]);

            return false;
        }
    }

    /**
     * Send reminder to all participants of a conference
     */
    public function sendConferenceReminders(Conference $conference): int
    {
        // Find all registrations for this conference
        $registrations = $this->registrationRepository->findByConference($conference);

        if (empty($registrations)) {
            return 0;
        }

        $successCount = 0;

        foreach ($registrations as $registration) {
            $user = $registration->getUser();

            try {
                $email = (new Email())
                    ->from('no-reply@confpro.example.com')
                    ->to($user->getEmail())
                    ->subject('Reminder: ' . $conference->getTitle() . ' is tomorrow!')
                    ->html($this->renderConferenceReminderTemplate($registration));

                $this->mailer->send($email);

                $successCount++;

                $this->logger->info('Conference reminder sent', [
                    'user_id' => $user->getId(),
                    'conference_id' => $conference->getId(),
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to send conference reminder', [
                    'error' => $e->getMessage(),
                    'user_id' => $user->getId(),
                    'conference_id' => $conference->getId(),
                ]);
            }
        }

        return $successCount;
    }

    /**
     * Send reminder to the presenter of a conference
     */
    public function sendPresenterReminder(Conference $conference): bool
    {
        $presenter = $conference->getPresenter();

        try {
            $email = (new Email())
                ->from('no-reply@confpro.example.com')
                ->to($presenter->getEmail())
                ->subject('Presenter Reminder: ' . $conference->getTitle() . ' is tomorrow!')
                ->html($this->renderPresenterReminderTemplate($conference));

            $this->mailer->send($email);

            $this->logger->info('Presenter reminder sent', [
                'presenter_id' => $presenter->getId(),
                'conference_id' => $conference->getId(),
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to send presenter reminder', [
                'error' => $e->getMessage(),
                'presenter_id' => $presenter->getId(),
                'conference_id' => $conference->getId(),
            ]);

            return false;
        }
    }

    /**
     * Send feedback request to attendees after a conference
     */
    public function sendFeedbackRequests(Conference $conference): int
    {
        // Find all registrations for this conference where the user attended
        $registrations = $this->registrationRepository->findBy([
            'conference' => $conference,
            'attended' => true,
        ]);

        if (empty($registrations)) {
            return 0;
        }

        $successCount = 0;

        foreach ($registrations as $registration) {
            $user = $registration->getUser();

            try {
                $email = (new Email())
                    ->from('no-reply@confpro.example.com')
                    ->to($user->getEmail())
                    ->subject('Feedback Request: ' . $conference->getTitle())
                    ->html($this->renderFeedbackRequestTemplate($registration));

                $this->mailer->send($email);

                $successCount++;

                $this->logger->info('Feedback request sent', [
                    'user_id' => $user->getId(),
                    'conference_id' => $conference->getId(),
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to send feedback request', [
                    'error' => $e->getMessage(),
                    'user_id' => $user->getId(),
                    'conference_id' => $conference->getId(),
                ]);
            }
        }

        return $successCount;
    }

    /**
     * Render registration confirmation email template
     */
    private function renderRegistrationConfirmationTemplate(Registration $registration): string
    {
        $user = $registration->getUser();
        $conference = $registration->getConference();
        $qrCode = $registration->getQrCode();

        // Get session information if available
        $sessions = $conference->getSessions();
        $sessionInfo = '';

        if (!$sessions->isEmpty()) {
            $sessionInfo = '<h3>Session Details</h3>';
            foreach ($sessions as $session) {
                $sessionInfo .= '<p>';
                $sessionInfo .= '<strong>Date:</strong> ' . $session->getStartTime()->format('F j, Y') . '<br>';
                $sessionInfo .= '<strong>Time:</strong> ' . $session->getStartTime()->format('g:i A') . ' - ' . $session->getEndTime()->format('g:i A') . '<br>';
                $sessionInfo .= '<strong>Room:</strong> ' . $session->getRoom();
                $sessionInfo .= '</p>';
            }
        } else {
            $sessionInfo = '<p>Session details will be provided later.</p>';
        }

        return '
            <html>
            <body>
                <h1>Registration Confirmation</h1>
                <p>Hello ' . htmlspecialchars($user->getName()) . ',</p>
                <p>Thank you for registering for the following conference:</p>
                <h2>' . htmlspecialchars($conference->getTitle()) . '</h2>
                <p>' . nl2br(htmlspecialchars($conference->getDescription())) . '</p>
                
                ' . $sessionInfo . '
                
                <h3>Your QR Code</h3>
                <p><strong>' . $qrCode . '</strong></p>
                <p>Please present this QR code at the event for check-in.</p>
                
                <p>We look forward to seeing you!</p>
                <p>Best regards,<br>ConfPro Event Team</p>
            </body>
            </html>
        ';
    }

    /**
     * Render conference reminder email template
     */
    private function renderConferenceReminderTemplate(Registration $registration): string
    {
        $user = $registration->getUser();
        $conference = $registration->getConference();
        $qrCode = $registration->getQrCode();

        // Get session information
        $sessions = $conference->getSessions();
        $sessionInfo = '';

        if (!$sessions->isEmpty()) {
            $sessionInfo = '<h3>Session Details</h3>';
            foreach ($sessions as $session) {
                $sessionInfo .= '<p>';
                $sessionInfo .= '<strong>Date:</strong> ' . $session->getStartTime()->format('F j, Y') . '<br>';
                $sessionInfo .= '<strong>Time:</strong> ' . $session->getStartTime()->format('g:i A') . ' - ' . $session->getEndTime()->format('g:i A') . '<br>';
                $sessionInfo .= '<strong>Room:</strong> ' . $session->getRoom();
                $sessionInfo .= '</p>';
            }
        }

        return '
            <html>
            <body>
                <h1>Reminder: Conference Tomorrow</h1>
                <p>Hello ' . htmlspecialchars($user->getName()) . ',</p>
                <p>This is a friendly reminder that the following conference is scheduled for tomorrow:</p>
                <h2>' . htmlspecialchars($conference->getTitle()) . '</h2>
                
                ' . $sessionInfo . '
                
                <h3>Your QR Code</h3>
                <p><strong>' . $qrCode . '</strong></p>
                <p>Please present this QR code at the event for check-in.</p>
                
                <p>We look forward to seeing you!</p>
                <p>Best regards,<br>ConfPro Event Team</p>
            </body>
            </html>
        ';
    }

    /**
     * Render presenter reminder email template
     */
    private function renderPresenterReminderTemplate(Conference $conference): string
    {
        $presenter = $conference->getPresenter();

        // Get session information
        $sessions = $conference->getSessions();
        $sessionInfo = '';

        if (!$sessions->isEmpty()) {
            $sessionInfo = '<h3>Session Details</h3>';
            foreach ($sessions as $session) {
                $sessionInfo .= '<p>';
                $sessionInfo .= '<strong>Date:</strong> ' . $session->getStartTime()->format('F j, Y') . '<br>';
                $sessionInfo .= '<strong>Time:</strong> ' . $session->getStartTime()->format('g:i A') . ' - ' . $session->getEndTime()->format('g:i A') . '<br>';
                $sessionInfo .= '<strong>Room:</strong> ' . $session->getRoom();
                $sessionInfo .= '</p>';
            }
        }

        return '
            <html>
            <body>
                <h1>Presenter Reminder: Conference Tomorrow</h1>
                <p>Hello ' . htmlspecialchars($presenter->getName()) . ',</p>
                <p>This is a friendly reminder that you are presenting the following conference tomorrow:</p>
                <h2>' . htmlspecialchars($conference->getTitle()) . '</h2>
                
                ' . $sessionInfo . '
                
                <p>Please arrive at least 30 minutes before your session to set up and test your equipment.</p>
                <p>If you have any questions or need assistance, please contact us immediately.</p>
                
                <p>Best regards,<br>ConfPro Event Team</p>
            </body>
            </html>
        ';
    }

    /**
     * Render feedback request email template
     */
    private function renderFeedbackRequestTemplate(Registration $registration): string
    {
        $user = $registration->getUser();
        $conference = $registration->getConference();

        return '
            <html>
            <body>
                <h1>Feedback Request</h1>
                <p>Hello ' . htmlspecialchars($user->getName()) . ',</p>
                <p>Thank you for attending our conference:</p>
                <h2>' . htmlspecialchars($conference->getTitle()) . '</h2>
                
                <p>We would appreciate your feedback on the session. Please take a few minutes to complete our survey by clicking the link below:</p>
                
                <p><a href="https://example.com/feedback/' . $conference->getId() . '/' . $registration->getId() . '">Complete Feedback Survey</a></p>
                
                <p>Your feedback is valuable to us and helps us improve future conferences.</p>
                
                <p>Best regards,<br>ConfPro Event Team</p>
            </body>
            </html>
        ';
    }

    // Dans src/Service/NotificationService.php, ajoutez:

    /**
     * Notify participants about new media
     */
    public function notifyMediaPublished(Conference $conference, Media $media): int
    {
        // Find all registrations for this conference where the user attended
        $registrations = $this->registrationRepository->findBy([
            'conference' => $conference,
            'attended' => true,
        ]);

        if (empty($registrations)) {
            return 0;
        }

        $successCount = 0;

        foreach ($registrations as $registration) {
            $user = $registration->getUser();

            try {
                $email = (new Email())
                    ->from('no-reply@confpro.example.com')
                    ->to($user->getEmail())
                    ->subject('New ' . ucfirst($media->getType()) . ' Available: ' . $conference->getTitle())
                    ->html($this->renderMediaNotificationTemplate($registration, $media));

                $this->mailer->send($email);
                $successCount++;

                $this->logger->info('Media notification sent', [
                    'user_id' => $user->getId(),
                    'conference_id' => $conference->getId(),
                    'media_id' => $media->getId(),
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to send media notification', [
                    'error' => $e->getMessage(),
                    'user_id' => $user->getId(),
                    'conference_id' => $conference->getId(),
                    'media_id' => $media->getId(),
                ]);
            }
        }

        return $successCount;
    }

    /**
     * Render media notification email template
     */
    private function renderMediaNotificationTemplate(Registration $registration, Media $media): string
    {
        $user = $registration->getUser();
        $conference = $registration->getConference();
        $mediaType = $media->getType() === Media::TYPE_SLIDES ? 'slides' : 'video recording';

        return '
    <html>
    <body>
        <h1>New ' . ucfirst($mediaType) . ' Available</h1>
        <p>Hello ' . htmlspecialchars($user->getName()) . ',</p>
        <p>We are pleased to inform you that new ' . $mediaType . ' is now available for the conference you attended:</p>
        <h2>' . htmlspecialchars($conference->getTitle()) . '</h2>
        <p><strong>' . htmlspecialchars($media->getTitle()) . '</strong></p>
        <p>You can access it using the following link:</p>
        <p><a href="' . $media->getUrl() . '">View ' . ucfirst($mediaType) . '</a></p>
        <p>Thank you for your participation!</p>
        <p>Best regards,<br>ConfPro Event Team</p>
    </body>
    </html>
    ';
    }
}