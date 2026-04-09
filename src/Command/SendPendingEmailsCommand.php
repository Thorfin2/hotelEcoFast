<?php

namespace App\Command;

use App\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;
use PHPMailer\PHPMailer\PHPMailer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:send-pending-emails',
    description: 'Send all pending email notifications via SMTP',
)]
class SendPendingEmailsCommand extends Command
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
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $notifications = $this->em->getRepository(Notification::class)
            ->findBy(['status' => Notification::STATUS_PENDING, 'channel' => Notification::CHANNEL_EMAIL], ['id' => 'ASC'], 20);

        if (empty($notifications)) {
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('Sending %d pending emails...', count($notifications)));

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = $this->mailerHost;
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->mailerUsername;
            $mail->Password   = $this->mailerPassword;
            $mail->SMTPSecure = $this->mailerEncryption === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $this->mailerPort;
            $mail->CharSet    = 'UTF-8';
            $mail->Timeout    = 30;
            $mail->SMTPKeepAlive = true;
            $mail->setFrom($this->mailerFromEmail, $this->mailerFromName);
        } catch (\Throwable $e) {
            $output->writeln('<error>SMTP failed: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }

        $sent = 0;
        $failed = 0;

        foreach ($notifications as $notification) {
            try {
                $mail->clearAddresses();
                $mail->addAddress($notification->getRecipientEmail());
                $mail->isHTML(true);
                $mail->Subject = $notification->getSubject();
                $mail->Body    = $notification->getContent();
                $mail->AltBody = strip_tags($notification->getContent());
                $mail->send();

                $notification->setStatus(Notification::STATUS_SENT);
                $notification->setSentAt(new \DateTimeImmutable());
                $sent++;
                $output->writeln("  OK -> {$notification->getRecipientEmail()}");
            } catch (\Throwable $e) {
                $notification->setStatus(Notification::STATUS_FAILED);
                $notification->setErrorMessage($e->getMessage());
                $failed++;
                $output->writeln("  FAIL -> {$notification->getRecipientEmail()}: {$e->getMessage()}");
            }

            $this->em->flush();
        }

        $mail->smtpClose();
        $output->writeln(sprintf('Done: %d sent, %d failed.', $sent, $failed));
        return Command::SUCCESS;
    }
}
