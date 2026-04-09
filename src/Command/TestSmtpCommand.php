<?php

namespace App\Command;

use App\Mail\SmtpMailerFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:mail:test',
    description: 'Envoie un email de test SMTP (debug Railway / CLI)',
)]
class TestSmtpCommand extends Command
{
    public function __construct(
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

    protected function configure(): void
    {
        $this->addArgument('to', InputArgument::REQUIRED, 'Adresse email destinataire');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $to = (string) $input->getArgument('to');

        if ($this->mailerPassword === '') {
            $io->error('MAILER_PASSWORD est vide. Définis-le dans .env.local ou sur Railway.');

            return Command::FAILURE;
        }

        $dsn = SmtpMailerFactory::buildDsn(
            $this->mailerHost,
            $this->mailerPort,
            $this->mailerUsername,
            $this->mailerPassword,
            $this->mailerEncryption
        );
        $io->note(sprintf(
            'SMTP %s:%d — user %s — schéma %s',
            $this->mailerHost,
            $this->mailerPort,
            $this->mailerUsername,
            str_starts_with($dsn, 'smtps://') ? 'smtps (TLS implicite)' : 'smtp+STARTTLS'
        ));

        try {
            $mailer = new Mailer(Transport::fromDsn($dsn));
            $mailer->send(
                (new Email())
                    ->from(new Address($this->mailerFromEmail, $this->mailerFromName))
                    ->to($to)
                    ->subject('Test CLI EcoFast SMTP')
                    ->text('Si tu reçois ce message, SMTP fonctionne.')
            );
            $io->success('Email envoyé vers ' . $to);

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            if (method_exists($e, 'getDebug') && $e->getDebug()) {
                $io->block($e->getDebug(), 'DEBUG', 'fg=white;bg=red', ' ', true);
            }

            return Command::FAILURE;
        }
    }
}
