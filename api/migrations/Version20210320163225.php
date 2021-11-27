<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20210320163225 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE country (code VARCHAR(2) NOT NULL, month INT NOT NULL, count INT NOT NULL, INDEX country_month_code (month, code), INDEX country_month (month), PRIMARY KEY(code, month)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE mirror (url VARCHAR(191) NOT NULL, month INT NOT NULL, count INT NOT NULL, INDEX mirror_month_url (month, url), INDEX mirror_month (month), PRIMARY KEY(url, month)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE operating_system_architecture (name VARCHAR(10) NOT NULL, month INT NOT NULL, count INT NOT NULL, INDEX operating_sytem_architecture_month_name (month, name), INDEX operating_sytem_architecture_month (month), PRIMARY KEY(name, month)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE system_architecture (name VARCHAR(10) NOT NULL, month INT NOT NULL, count INT NOT NULL, INDEX sytem_architecture_month_name (month, name), INDEX sytem_architecture_month (month), PRIMARY KEY(name, month)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB'
        );

        $this->migrateUserData();

        $this->addSql('DROP TABLE user');
        $this->addSql('DROP INDEX month_name ON package');
        $this->addSql('CREATE INDEX package_month_name ON package (month, name)');
        $this->addSql('DROP INDEX month ON package');
        $this->addSql('CREATE INDEX package_month ON package (month)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, ip VARCHAR(40) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, time INT NOT NULL, arch VARCHAR(10) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, cpuarch VARCHAR(10) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, countryCode VARCHAR(2) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, mirror VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, packages SMALLINT NOT NULL, INDEX countryCode (countryCode), INDEX mirror (mirror), INDEX ip (ip, time), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' '
        );
        $this->addSql('DROP TABLE country');
        $this->addSql('DROP TABLE mirror');
        $this->addSql('DROP TABLE operating_system_architecture');
        $this->addSql('DROP TABLE system_architecture');
        $this->addSql('DROP INDEX package_month_name ON package');
        $this->addSql('CREATE INDEX month_name ON package (month, name)');
        $this->addSql('DROP INDEX package_month ON package');
        $this->addSql('CREATE INDEX month ON package (month)');
    }

    public function isTransactional(): bool
    {
        return false;
    }

    private function migrateUserData(): void
    {
        $this->addSql(
            'LOCK TABLES country WRITE,'
            . 'mirror WRITE,'
            . 'operating_system_architecture WRITE,'
            . 'system_architecture WRITE,'
            . 'user WRITE'
        );

        $this->addSql(
            'INSERT INTO country '
            . 'SELECT countryCode AS code, EXTRACT(YEAR_MONTH FROM FROM_UNIXTIME(time)) AS month, COUNT(*) AS count '
            . 'FROM user WHERE countryCode IS NOT NULL GROUP BY countryCode, month'
        );
        $this->addSql(
            'INSERT INTO mirror '
            . 'SELECT mirror AS url, EXTRACT(YEAR_MONTH FROM FROM_UNIXTIME(time)) AS month, COUNT(*) AS count '
            . 'FROM user WHERE mirror IS NOT NULL GROUP BY mirror, month'
        );
        $this->addSql(
            'INSERT INTO operating_system_architecture '
            . 'SELECT arch AS name, EXTRACT(YEAR_MONTH FROM FROM_UNIXTIME(time)) AS month, COUNT(*) AS count '
            . 'FROM user WHERE arch IS NOT NULL GROUP BY arch, month'
        );
        $this->addSql(
            'INSERT INTO system_architecture '
            . 'SELECT cpuarch AS name, EXTRACT(YEAR_MONTH FROM FROM_UNIXTIME(time)) AS month, COUNT(*) AS count '
            . 'FROM user WHERE cpuarch IS NOT NULL GROUP BY cpuarch, month'
        );

        $this->addSql('UNLOCK TABLES');
    }
}
