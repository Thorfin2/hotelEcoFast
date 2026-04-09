<?php

namespace App\Command;

use App\Service\PendingEmailSender;
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
        private readonly PendingEmailSender $pendingEmailSender,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->pendingEmailSender->sendPendingBatch(20);

        if ($result['skipped']) {
            $output->writeln('<comment>MAILER_ENABLED is false; no emails sent.</comment>');

            return Command::SUCCESS;
        }

        $total = $result['sent'] + $result['failed'];
        if (0 === $total) {
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('Processed pending emails: %d sent, %d failed.', $result['sent'], $result['failed']));

        return Command::SUCCESS;
    }
}
