<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240327185625 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE country CHANGE month month INT UNSIGNED NOT NULL, CHANGE count count INT UNSIGNED NOT NULL');
        $this->addSql('ALTER TABLE mirror CHANGE month month INT UNSIGNED NOT NULL, CHANGE count count INT UNSIGNED NOT NULL');
        $this->addSql('ALTER TABLE operating_system_architecture CHANGE month month INT UNSIGNED NOT NULL, CHANGE count count INT UNSIGNED NOT NULL');
        $this->addSql('ALTER TABLE package CHANGE month month INT UNSIGNED NOT NULL, CHANGE count count INT UNSIGNED NOT NULL');
        $this->addSql('ALTER TABLE system_architecture CHANGE month month INT UNSIGNED NOT NULL, CHANGE count count INT UNSIGNED NOT NULL');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE system_architecture CHANGE month month INT NOT NULL, CHANGE count count INT NOT NULL');
        $this->addSql('ALTER TABLE mirror CHANGE month month INT NOT NULL, CHANGE count count INT NOT NULL');
        $this->addSql('ALTER TABLE operating_system_architecture CHANGE month month INT NOT NULL, CHANGE count count INT NOT NULL');
        $this->addSql('ALTER TABLE country CHANGE month month INT NOT NULL, CHANGE count count INT NOT NULL');
        $this->addSql('ALTER TABLE package CHANGE month month INT NOT NULL, CHANGE count count INT NOT NULL');
    }

    #[\Override]
    public function isTransactional(): bool
    {
        return false;
    }
}
