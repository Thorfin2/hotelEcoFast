<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:init-admin',
    description: 'Crée ou met à jour le compte administrateur principal',
)]
class InitAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $adminEmail  = 'ecofastvtc@gmail.com';
        $envPassword = $_ENV['ADMIN_PASSWORD'] ?? null;

        // Cherche un compte avec l'email cible
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $adminEmail]);

        // Sinon cherche n'importe quel ROLE_ADMIN
        if (!$user) {
            $all = $this->em->getRepository(User::class)->findAll();
            foreach ($all as $u) {
                if (in_array('ROLE_ADMIN', $u->getRoles())) {
                    $user = $u;
                    break;
                }
            }
        }

        $isNew = false;
        if (!$user) {
            $user = new User();
            $user->setFirstName('Admin')
                 ->setLastName('Cabsolu')
                 ->setRoles(['ROLE_ADMIN'])
                 ->setIsActive(true);
            $this->em->persist($user);
            $isNew = true;
        }

        // Toujours forcer l'email admin
        $user->setEmail($adminEmail);

        // Mettre à jour le mot de passe si ADMIN_PASSWORD est défini
        if ($envPassword) {
            $user->setPassword($this->hasher->hashPassword($user, $envPassword));
            $io->success("✅ Admin mis à jour : $adminEmail (mot de passe mis à jour depuis ADMIN_PASSWORD)");
        } elseif ($isNew) {
            // Nouveau compte sans mot de passe défini : mot de passe temporaire
            $tmp = 'EcoFast2024!';
            $user->setPassword($this->hasher->hashPassword($user, $tmp));
            $io->warning("⚠️  Nouveau compte admin créé : $adminEmail — mot de passe temporaire : $tmp");
            $io->note("Définissez ADMIN_PASSWORD dans vos variables d'environnement Railway pour sécuriser ce compte.");
        } else {
            $io->success("✅ Compte admin existant conservé : $adminEmail (mot de passe inchangé)");
        }

        $this->em->flush();
        return Command::SUCCESS;
    }
}
