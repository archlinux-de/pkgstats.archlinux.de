<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20210320110604 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration can only be executed safely on \'mysql\'.'
        );
        $this->addSql('DROP TABLE user');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration can only be executed safely on \'mysql\'.'
        );
        // phpcs:disable
        $this->addSql(
            'CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, time INT NOT NULL, arch VARCHAR(10) CHARACTER SET utf8 NOT NULL COLLATE `utf8_unicode_ci`, cpuarch VARCHAR(10) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, countryCode VARCHAR(2) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, mirror VARCHAR(255) CHARACTER SET utf8 DEFAULT NULL COLLATE `utf8_unicode_ci`, INDEX user_mirror (mirror), INDEX user_countryCode (countryCode), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' '
        );
        // phpcs:enable
    }
}
