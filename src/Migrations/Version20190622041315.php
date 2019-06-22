<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20190622041315 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM package WHERE pkgname NOT REGEXP \'^[a-zA-Z0-9][a-zA-Z0-9@:\.+_-]*$\'');
    }

    public function down(Schema $schema): void
    {
        $this->warnIf(true, 'Filtering of package names cannot be reverted');
    }
}
