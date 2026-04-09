<?php

namespace App\Service;

use App\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Envoie les notifications email en attente via Symfony Mailer (SMTP).
 */
class PendingEmailSender
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly MailerInterface $mailer,
        private readonly string $mailerFromEmail,
        private readonly string $mailerFromName,
        private readonly bool $mailerEnabled,
    ) {
    }

    /**
     * @return array{sent: int, failed: int, skipped: bool}
     */
    public function sendPendingBatch(int $limit = 20): array
    {
        if (!$this->mailerEnabled) {
            $this->logger->debug('MAILER_ENABLED=false, pending emails not sent.');

            return ['sent' => 0, 'failed' => 0, 'skipped' => true];
        }

        $notifications = $this->em->getRepository(Notification::class)
            ->findBy(['status' => Notification::STATUS_PENDING, 'channel' => Notification::CHANNEL_EMAIL], ['id' => 'ASC'], $limit);

        if ($notifications === []) {
            return ['sent' => 0, 'failed' => 0, 'skipped' => false];
        }

        $sent = 0;
        $failed = 0;
        $from = new Address($this->mailerFromEmail, $this->mailerFromName);

        foreach ($notifications as $notification) {
            try {
                $html = $notification->getContent();
                $email = (new Email())
                    ->from($from)
                    ->to($notification->getRecipientEmail())
                    ->subject($notification->getSubject())
                    ->html($html)
                    ->text(trim(strip_tags($html)));

                $this->mailer->send($email);

                $notification->setStatus(Notification::STATUS_SENT);
                $notification->setSentAt(new \DateTimeImmutable());
                ++$sent;
            } catch (TransportExceptionInterface $e) {
                $notification->setStatus(Notification::STATUS_FAILED);
                $notification->setErrorMessage($e->getMessage());
                ++$failed;
                $this->logger->warning('Email send failed for {email}: {message}', [
                    'email' => $notification->getRecipientEmail(),
                    'message' => $e->getMessage(),
                ]);
            } catch (\Throwable $e) {
                $notification->setStatus(Notification::STATUS_FAILED);
                $notification->setErrorMessage($e->getMessage());
                ++$failed;
                $this->logger->warning('Email send failed for {email}: {message}', [
                    'email' => $notification->getRecipientEmail(),
                    'message' => $e->getMessage(),
                ]);
            }

            $this->em->flush();
        }

        return ['sent' => $sent, 'failed' => $failed, 'skipped' => false];
    }
}
