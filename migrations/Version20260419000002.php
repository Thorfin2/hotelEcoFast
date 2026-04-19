<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260419000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout des champs reset_token pour la réinitialisation de mot de passe';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user`
            ADD reset_token VARCHAR(100) DEFAULT NULL,
            ADD reset_token_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP reset_token, DROP reset_token_expires_at');
    }
}
