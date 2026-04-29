<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260419000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout du champ commission_enabled sur hotel';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hotel ADD commission_enabled TINYINT(1) NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hotel DROP commission_enabled');
    }
}
