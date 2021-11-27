<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20190922101653 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE package DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE package CHANGE pkgname name VARCHAR(191) NOT NULL');
        $this->addSql('ALTER TABLE package ADD PRIMARY KEY (name, month)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE package DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE package CHANGE name pkgname VARCHAR(191) NOT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE package ADD PRIMARY KEY (pkgname, month)');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
