<?php

namespace App\Service;

use App\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;
use PHPMailer\PHPMailer\PHPMailer;
use Psr\Log\LoggerInterface;

/**
 * Envoie les notifications email en attente via SMTP (PHPMailer).
 * Utilisé par la commande console et directement après les actions HTTP
 * (évite exec/php en arrière-plan, souvent indisponible sous PHP-FPM).
 */
class PendingEmailSender
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly string $mailerHost,
        private readonly int $mailerPort,
        private readonly string $mailerUsername,
        private readonly string $mailerPassword,
        private readonly string $mailerFromEmail,
        private readonly string $mailerFromName,
        private readonly string $mailerEncryption,
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

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $this->mailerHost;
            $mail->SMTPAuth = true;
            $mail->Username = $this->mailerUsername;
            $mail->Password = $this->mailerPassword;
            $mail->SMTPSecure = $this->mailerEncryption === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $this->mailerPort;
            $mail->CharSet = 'UTF-8';
            $mail->Timeout = 30;
            $mail->SMTPKeepAlive = true;
            $mail->setFrom($this->mailerFromEmail, $this->mailerFromName);
        } catch (\Throwable $e) {
            $this->logger->error('SMTP configuration failed: {message}', ['message' => $e->getMessage()]);

            return ['sent' => 0, 'failed' => \count($notifications), 'skipped' => false];
        }

        $sent = 0;
        $failed = 0;

        foreach ($notifications as $notification) {
            try {
                $mail->clearAddresses();
                $mail->addAddress($notification->getRecipientEmail());
                $mail->isHTML(true);
                $mail->Subject = $notification->getSubject();
                $mail->Body = $notification->getContent();
                $mail->AltBody = strip_tags($notification->getContent());
                $mail->send();

                $notification->setStatus(Notification::STATUS_SENT);
                $notification->setSentAt(new \DateTimeImmutable());
                ++$sent;
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

        $mail->smtpClose();

        return ['sent' => $sent, 'failed' => $failed, 'skipped' => false];
    }
}
