<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Service\MirrorUrlFilter;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20190621141307 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $mirrorUrlFilter = new MirrorUrlFilter();

        foreach (
            $this->connection->fetchAllAssociative(
                'SELECT DISTINCT mirror FROM `user` WHERE mirror IS NOT NULL '
            ) as $row
        ) {
            $mirror = $mirrorUrlFilter->filter($row['mirror']);
            if ($mirror != $row['mirror']) {
                $this->connection->update(
                    'user',
                    ['mirror' => $mirror],
                    ['mirror' => $row['mirror']]
                );
                if ($mirror === null) {
                    $this->write(sprintf('Removing mirror "%s"', $row['mirror']));
                } else {
                    $this->write(sprintf('Updating mirror "%s" to "%s"', $row['mirror'], $mirror));
                }
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->warnIf(true, 'Filtering of mirror urls cannot be reverted');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
