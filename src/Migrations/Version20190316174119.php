<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190316174119 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql('DROP TABLE module');
        $this->addSql('ALTER TABLE user DROP modules');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration can only be executed safely on \'mysql\'.'
        );

        // phpcs:disable
        $this->addSql('CREATE TABLE module (name VARCHAR(50) NOT NULL COLLATE utf8mb4_unicode_ci, month INT NOT NULL, count INT NOT NULL, PRIMARY KEY(name, month)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE user ADD modules SMALLINT DEFAULT NULL');
        // phpcs:enable
    }
}
