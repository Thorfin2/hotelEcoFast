<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260406000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création du schéma initial EcoFast Hotel';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE `user` (
            id INT AUTO_INCREMENT NOT NULL,
            email VARCHAR(180) NOT NULL,
            roles JSON NOT NULL,
            password VARCHAR(255) NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_8D93D649E7927C74 (email),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE hotel (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            name VARCHAR(150) NOT NULL,
            address VARCHAR(255) NOT NULL,
            city VARCHAR(100) NOT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            email VARCHAR(180) DEFAULT NULL,
            stars VARCHAR(5) DEFAULT NULL,
            commission_rate DECIMAL(5, 2) NOT NULL DEFAULT 10.00,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_3535ED9A76ED395 (user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE driver (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            phone VARCHAR(20) NOT NULL,
            email VARCHAR(180) DEFAULT NULL,
            vehicle_model VARCHAR(100) NOT NULL,
            vehicle_type VARCHAR(20) NOT NULL,
            license_plate VARCHAR(20) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'available\',
            license_number VARCHAR(50) DEFAULT NULL,
            license_expiry DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_11667CD9A76ED395 (user_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE ride (
            id INT AUTO_INCREMENT NOT NULL,
            hotel_id INT NOT NULL,
            driver_id INT DEFAULT NULL,
            reference VARCHAR(20) NOT NULL,
            client_name VARCHAR(150) NOT NULL,
            client_phone VARCHAR(20) DEFAULT NULL,
            client_email VARCHAR(180) DEFAULT NULL,
            pickup_address VARCHAR(255) NOT NULL,
            destination_address VARCHAR(255) NOT NULL,
            pickup_datetime DATETIME NOT NULL,
            ride_type VARCHAR(50) DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'pending\',
            price DECIMAL(10, 2) DEFAULT NULL,
            driver_amount DECIMAL(10, 2) DEFAULT NULL,
            hotel_commission DECIMAL(10, 2) DEFAULT NULL,
            notes LONGTEXT DEFAULT NULL,
            passengers INT DEFAULT 1,
            luggage INT DEFAULT 0,
            flight_number VARCHAR(20) DEFAULT NULL,
            assigned_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            started_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            completed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX UNIQ_9B3D7CD0AEA34913 (reference),
            INDEX IDX_9B3D7CD03243BB18 (hotel_id),
            INDEX IDX_9B3D7CD0C3423909 (driver_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE notification (
            id INT AUTO_INCREMENT NOT NULL,
            ride_id INT DEFAULT NULL,
            type VARCHAR(50) NOT NULL,
            channel VARCHAR(20) NOT NULL DEFAULT \'email\',
            recipient_type VARCHAR(20) NOT NULL,
            recipient_email VARCHAR(255) DEFAULT NULL,
            recipient_phone VARCHAR(20) DEFAULT NULL,
            subject VARCHAR(255) NOT NULL,
            content LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'pending\',
            sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            error_message LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_BF5476CA2F639020 (ride_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE hotel ADD CONSTRAINT FK_3535ED9A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE driver ADD CONSTRAINT FK_11667CD9A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE ride ADD CONSTRAINT FK_9B3D7CD03243BB18 FOREIGN KEY (hotel_id) REFERENCES hotel (id)');
        $this->addSql('ALTER TABLE ride ADD CONSTRAINT FK_9B3D7CD0C3423909 FOREIGN KEY (driver_id) REFERENCES driver (id)');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CA2F639020 FOREIGN KEY (ride_id) REFERENCES ride (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CA2F639020');
        $this->addSql('ALTER TABLE ride DROP FOREIGN KEY FK_9B3D7CD03243BB18');
        $this->addSql('ALTER TABLE ride DROP FOREIGN KEY FK_9B3D7CD0C3423909');
        $this->addSql('ALTER TABLE driver DROP FOREIGN KEY FK_11667CD9A76ED395');
        $this->addSql('ALTER TABLE hotel DROP FOREIGN KEY FK_3535ED9A76ED395');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE ride');
        $this->addSql('DROP TABLE driver');
        $this->addSql('DROP TABLE hotel');
        $this->addSql('DROP TABLE `user`');
    }
}
