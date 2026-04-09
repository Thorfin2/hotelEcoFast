<?php

namespace App\Command;

use App\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:send-pending-emails',
    description: 'Send all pending email notifications via Resend API',
)]
class SendPendingEmailsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly string $resendApiKey,
        private readonly string $mailerFromEmail,
        private readonly string $mailerFromName,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (empty($this->resendApiKey) || $this->resendApiKey === 'change_me') {
            $output->writeln('Resend API key not configured, skipping.');
            return Command::SUCCESS;
        }

        $notifications = $this->em->getRepository(Notification::class)
            ->findBy(['status' => Notification::STATUS_PENDING, 'channel' => Notification::CHANNEL_EMAIL], ['id' => 'ASC'], 20);

        if (empty($notifications)) {
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('Sending %d pending emails via Resend...', count($notifications)));

        $sent = 0;
        $failed = 0;

        foreach ($notifications as $notification) {
            $result = $this->sendViaResend(
                $notification->getRecipientEmail(),
                $notification->getSubject(),
                $notification->getContent()
            );

            if ($result === true) {
                $notification->setStatus(Notification::STATUS_SENT);
                $notification->setSentAt(new \DateTimeImmutable());
                $sent++;
                $output->writeln("  OK: {$notification->getSubject()} -> {$notification->getRecipientEmail()}");
            } else {
                $notification->setStatus(Notification::STATUS_FAILED);
                $notification->setErrorMessage($result);
                $failed++;
                $output->writeln("  FAIL: {$notification->getRecipientEmail()}: {$result}");
            }

            $this->em->flush();
        }

        $output->writeln(sprintf('Done: %d sent, %d failed.', $sent, $failed));
        return Command::SUCCESS;
    }

    /**
     * Envoie un email via l'API HTTP de Resend
     * @return true|string true si OK, message d'erreur sinon
     */
    private function sendViaResend(string $to, string $subject, string $html): true|string
    {
        $payload = json_encode([
            'from' => "{$this->mailerFromName} <{$this->mailerFromEmail}>",
            'to' => [$to],
            'subject' => $subject,
            'html' => $html,
        ]);

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->resendApiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return "cURL error: {$error}";
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        }

        $body = json_decode($response, true);
        return "HTTP {$httpCode}: " . ($body['message'] ?? $response);
    }
}
