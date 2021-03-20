<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20210320110317 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aggregate user table';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->connection->executeQuery(
            'LOCK TABLES country WRITE,'
            . 'mirror WRITE,'
            . 'operating_system_architecture WRITE,'
            . 'system_architecture WRITE,'
            . 'user WRITE'
        );

        $this->connection->executeQuery(
            'INSERT INTO country '
            . 'SELECT countryCode AS code, EXTRACT(YEAR_MONTH FROM FROM_UNIXTIME(time)) AS month, COUNT(*) AS count '
            . 'FROM user WHERE countryCode IS NOT NULL GROUP BY countryCode, month'
        );
        $this->connection->executeQuery(
            'INSERT INTO mirror '
            . 'SELECT mirror AS url, EXTRACT(YEAR_MONTH FROM FROM_UNIXTIME(time)) AS month, COUNT(*) AS count '
            . 'FROM user WHERE mirror IS NOT NULL GROUP BY mirror, month'
        );
        $this->connection->executeQuery(
            'INSERT INTO operating_system_architecture '
            . 'SELECT arch AS name, EXTRACT(YEAR_MONTH FROM FROM_UNIXTIME(time)) AS month, COUNT(*) AS count '
            . 'FROM user WHERE arch IS NOT NULL GROUP BY arch, month'
        );
        $this->connection->executeQuery(
            'INSERT INTO system_architecture '
            . 'SELECT cpuarch AS name, EXTRACT(YEAR_MONTH FROM FROM_UNIXTIME(time)) AS month, COUNT(*) AS count '
            . 'FROM user WHERE cpuarch IS NOT NULL GROUP BY cpuarch, month'
        );

        $this->connection->executeQuery('UNLOCK TABLES');
    }

    public function down(Schema $schema): void
    {
        $this->warnIf(true, 'Aggregation of user table cannot be reverted');
    }
}
