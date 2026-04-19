<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:reset-admin',
    description: 'Réinitialise le mot de passe d\'un admin ou liste les admins',
)]
class ResetAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::OPTIONAL, 'Email du compte admin');
        $this->addArgument('password', InputArgument::OPTIONAL, 'Nouveau mot de passe');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Liste tous les users
        $users = $this->em->getRepository(User::class)->findAll();

        $io->title('Comptes existants');
        $rows = [];
        foreach ($users as $user) {
            $rows[] = [
                $user->getId(),
                $user->getEmail(),
                $user->getFirstName() . ' ' . $user->getLastName(),
                implode(', ', $user->getRoles()),
            ];
        }
        $io->table(['ID', 'Email', 'Nom', 'Rôles'], $rows);

        $email = $input->getArgument('email');
        $password = $input->getArgument('password');

        if (!$email || !$password) {
            $io->note('Usage : php bin/console app:reset-admin <email> <nouveau_mot_de_passe>');
            return Command::SUCCESS;
        }

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            $io->error("Aucun utilisateur trouvé avec l'email : {$email}");
            return Command::FAILURE;
        }

        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->flush();

        $io->success("Mot de passe réinitialisé pour : {$email}");
        return Command::SUCCESS;
    }
}
