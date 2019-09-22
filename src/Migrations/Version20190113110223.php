<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial schema
 */
final class Version20190113110223 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration can only be executed safely on \'mysql\'.'
        );

        // phpcs:disable
        $this->addSql('CREATE TABLE module (name VARCHAR(50) NOT NULL, month INT NOT NULL, count INT NOT NULL, PRIMARY KEY(name, month)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE package (pkgname VARCHAR(191) NOT NULL, month INT NOT NULL, count INT NOT NULL, PRIMARY KEY(pkgname, month)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, ip VARCHAR(40) NOT NULL, time INT NOT NULL, arch VARCHAR(10) NOT NULL, cpuarch VARCHAR(10) DEFAULT NULL, countryCode VARCHAR(2) DEFAULT NULL, mirror VARCHAR(255) DEFAULT NULL, packages SMALLINT NOT NULL, modules SMALLINT DEFAULT NULL, INDEX mirror (mirror), INDEX ip (ip, time), INDEX countryCode (countryCode), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        // phpcs:enable
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql('DROP TABLE module');
        $this->addSql('DROP TABLE package');
        $this->addSql('DROP TABLE user');
    }
}
