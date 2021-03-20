<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20210320084043 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration can only be executed safely on \'mysql\'.'
        );

        // phpcs:disable
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
        $this->addSql('DROP INDEX month_name ON package');
        $this->addSql('CREATE INDEX package_month_name ON package (month, name)');
        $this->addSql('DROP INDEX month ON package');
        $this->addSql('CREATE INDEX package_month ON package (month)');
        $this->addSql('DROP INDEX mirror ON user');
        $this->addSql('CREATE INDEX user_mirror ON user (mirror)');
        $this->addSql('DROP INDEX countrycode ON user');
        $this->addSql('CREATE INDEX user_countryCode ON user (countryCode)');
        // phpcs:enable
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql('DROP TABLE country');
        $this->addSql('DROP TABLE mirror');
        $this->addSql('DROP TABLE operating_system_architecture');
        $this->addSql('DROP TABLE system_architecture');
        $this->addSql('DROP INDEX package_month ON package');
        $this->addSql('CREATE INDEX month ON package (month)');
        $this->addSql('DROP INDEX package_month_name ON package');
        $this->addSql('CREATE INDEX month_name ON package (month, name)');
        $this->addSql('DROP INDEX user_mirror ON user');
        $this->addSql('CREATE INDEX mirror ON user (mirror)');
        $this->addSql('DROP INDEX user_countrycode ON user');
        $this->addSql('CREATE INDEX countryCode ON user (countryCode)');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
