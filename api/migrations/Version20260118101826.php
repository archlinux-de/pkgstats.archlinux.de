<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260118101826 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE operating_system_id (id VARCHAR(50) NOT NULL, month INT UNSIGNED NOT NULL, count INT UNSIGNED NOT NULL, INDEX operating_sytem_id_month_id (month, id), INDEX operating_sytem_id_month_count (month, count), PRIMARY KEY (id, month)) DEFAULT CHARACTER SET utf8mb4');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE operating_system_id');
    }

    #[\Override]
    public function isTransactional(): bool
    {
        return false;
    }
}
