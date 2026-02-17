<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260217120000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Normalize case-variant package names, mirror URLs and OS IDs by merging duplicates.';
    }

    public function up(Schema $schema): void
    {
        // BINARY comparison is needed because the utf8mb4_unicode_ci collation
        // treats 'AltLinux' and 'altlinux' as equal, so without BINARY the
        // WHERE clause would never match any rows.

        // --- packages ---
        // 1. Try to lowercase names in-place. This works if only mixed-case variants exist.
        $this->addSql('UPDATE IGNORE package SET name = LOWER(name) WHERE BINARY name != BINARY LOWER(name)');
        // 2. Merge counts for variants that couldn't be renamed (because a lowercase one already existed).
        $this->addSql(<<<'SQL'
            UPDATE package p
            JOIN (
                SELECT LOWER(name) AS normalized, month, SUM(count) AS total
                FROM package
                WHERE BINARY name != BINARY LOWER(name)
                GROUP BY normalized, month
            ) v ON BINARY p.name = BINARY v.normalized AND p.month = v.month
            SET p.count = p.count + v.total
            SQL);
        // 3. Delete remaining mixed-case rows.
        $this->addSql('DELETE FROM package WHERE BINARY name != BINARY LOWER(name)');

        // --- mirrors ---
        $this->addSql('UPDATE IGNORE mirror SET url = LOWER(url) WHERE BINARY url != BINARY LOWER(url)');
        $this->addSql(<<<'SQL'
            UPDATE mirror m
            JOIN (
                SELECT LOWER(url) AS normalized, month, SUM(count) AS total
                FROM mirror
                WHERE BINARY url != BINARY LOWER(url)
                GROUP BY normalized, month
            ) v ON BINARY m.url = BINARY v.normalized AND m.month = v.month
            SET m.count = m.count + v.total
            SQL);
        $this->addSql('DELETE FROM mirror WHERE BINARY url != BINARY LOWER(url)');

        // --- operating system IDs ---
        $this->addSql('UPDATE IGNORE operating_system_id SET id = LOWER(id) WHERE BINARY id != BINARY LOWER(id)');
        $this->addSql(<<<'SQL'
            UPDATE operating_system_id o
            JOIN (
                SELECT LOWER(id) AS normalized, month, SUM(count) AS total
                FROM operating_system_id
                WHERE BINARY id != BINARY LOWER(id)
                GROUP BY normalized, month
            ) v ON BINARY o.id = BINARY v.normalized AND o.month = v.month
            SET o.count = o.count + v.total
            SQL);
        $this->addSql('DELETE FROM operating_system_id WHERE BINARY id != BINARY LOWER(id)');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        // Data-only migration — original casing cannot be restored,
        // but a no-op down() is safe since no schema was changed.
    }
}
