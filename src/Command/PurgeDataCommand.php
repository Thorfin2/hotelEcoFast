<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:purge-data',
    description: 'Supprime tous les utilisateurs, hôtels, chauffeurs, courses et notifications. Recrée ensuite le compte admin.',
)]
class PurgeDataCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Confirme la suppression sans prompt interactif');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🗑️  Purge de la base de données Cabsolu');

        if (!$input->getOption('force')) {
            $io->warning([
                'Cette opération supprimera DÉFINITIVEMENT :',
                '  • Toutes les notifications',
                '  • Toutes les courses (rides)',
                '  • Tous les utilisateurs',
                '  • Tous les hôtels',
                '  • Tous les chauffeurs',
                '',
                'Le compte admin sera recréé automatiquement après la purge.',
            ]);

            if (!$io->confirm('Confirmer la purge complète ?', false)) {
                $io->comment('Opération annulée.');
                return Command::SUCCESS;
            }
        }

        $io->section('Suppression des données...');

        try {
            // Désactiver les contraintes FK pour MySQL
            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');

            $tables = [
                'notification' => 'Notifications',
                'ride'         => 'Courses',
                'user'         => 'Utilisateurs',
                'hotel'        => 'Hôtels',
                'driver'       => 'Chauffeurs',
            ];

            foreach ($tables as $table => $label) {
                $count = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM `{$table}`");
                $this->connection->executeStatement("DELETE FROM `{$table}`");
                $io->writeln("  ✅ {$label} : {$count} supprimé(s)");
            }

            // Réinitialiser les auto-increments
            foreach (array_keys($tables) as $table) {
                $this->connection->executeStatement("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
            }

            $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        } catch (\Throwable $e) {
            // S'assurer que FK checks est réactivé même en cas d'erreur
            try { $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS=1'); } catch (\Throwable) {}
            $io->error('Erreur lors de la purge : ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->success('Base de données purgée avec succès.');

        // Recréer le compte admin
        $io->section('Recréation du compte admin...');
        try {
            $initAdmin = $this->getApplication()->find('app:init-admin');
            $initAdmin->run(new ArrayInput([]), $output);
        } catch (\Throwable $e) {
            $io->warning('Le compte admin n\'a pas pu être recréé automatiquement : ' . $e->getMessage());
            $io->note('Lancez manuellement : php bin/console app:init-admin');
        }

        $io->section('✅ Opération terminée');
        $io->listing([
            'Toutes les données ont été supprimées',
            'Le compte admin a été recréé',
            'Vous pouvez maintenant recréer vos hôtels et utilisateurs via l\'interface admin',
        ]);

        return Command::SUCCESS;
    }
}
