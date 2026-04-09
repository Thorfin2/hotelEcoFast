<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:mail:test',
    description: 'Envoie un email de test via Resend API',
)]
class TestSmtpCommand extends Command
{
    public function __construct(
        private readonly string $resendApiKey,
        private readonly string $mailerFromEmail,
        private readonly string $mailerFromName,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('to', InputArgument::REQUIRED, 'Adresse email destinataire');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $to = (string) $input->getArgument('to');

        if (empty($this->resendApiKey) || $this->resendApiKey === 'change_me') {
            $io->error('RESEND_API_KEY non configuree.');
            return Command::FAILURE;
        }

        $io->note("Envoi via Resend API -> {$to}");

        $fromEmail = $this->mailerFromEmail;
        $result = $this->sendViaResend($to, $fromEmail, $io);

        // If domain not verified, retry with Resend default sender
        if ($result === 403) {
            $io->warning("Domaine non verifie, envoi avec onboarding@resend.dev...");
            $fromEmail = 'onboarding@resend.dev';
            $result = $this->sendViaResend($to, $fromEmail, $io);
        }

        return $result === true ? Command::SUCCESS : Command::FAILURE;
    }

    private function sendViaResend(string $to, string $fromEmail, SymfonyStyle $io): true|int
    {
        $payload = json_encode([
            'from' => "{$this->mailerFromName} <{$fromEmail}>",
            'to' => [$to],
            'subject' => 'Test EcoFast - Email fonctionne !',
            'html' => '<div style="font-family:Arial;max-width:500px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.1)"><div style="background:linear-gradient(135deg,#0d9488,#0f766e);padding:32px;text-align:center"><h1 style="color:#fff;margin:0">EcoFast VTC</h1></div><div style="padding:32px;text-align:center"><h2 style="color:#1e293b">Ca marche !</h2><p style="color:#64748b">La configuration email est correcte.</p></div></div>',
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
            $io->error("cURL: {$error}");
            return 0;
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            $io->success("Email envoye vers {$to} (from: {$fromEmail})");
            return true;
        }

        $body = json_decode($response, true);
        $io->error("HTTP {$httpCode}: " . ($body['message'] ?? $response));
        return $httpCode;
    }
}
