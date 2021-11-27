<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20191020113255 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX month_name ON package (month, name)');
        $this->addSql('CREATE INDEX month ON package (month)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX month_name ON package');
        $this->addSql('DROP INDEX month ON package');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
