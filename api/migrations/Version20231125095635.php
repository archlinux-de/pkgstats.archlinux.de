<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class Version20231125095635 extends AbstractMigration implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    private function skipOnNonProd(): void
    {
        assert($this->container instanceof ContainerInterface);
        $this->skipIf(
            $this->container->getParameter('kernel.environment') !== 'prod',
            'Database cache storage is used in production only'
        );
    }

    public function up(Schema $schema): void
    {
        $this->skipOnNonProd();
        $this->addSql('CREATE TABLE cache_items (item_id VARBINARY(255) NOT NULL, item_data MEDIUMBLOB NOT NULL, item_lifetime INT UNSIGNED DEFAULT NULL, item_time INT UNSIGNED NOT NULL, PRIMARY KEY(item_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->skipOnNonProd();
        $this->addSql('DROP TABLE cache_items');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
