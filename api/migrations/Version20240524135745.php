<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240524135745 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX country_month ON country');
        $this->addSql('CREATE INDEX country_month_count ON country (month, count)');
        $this->addSql('DROP INDEX mirror_month ON mirror');
        $this->addSql('CREATE INDEX mirror_month_count ON mirror (month, count)');
        $this->addSql('DROP INDEX operating_sytem_architecture_month ON operating_system_architecture');
        $this->addSql('CREATE INDEX operating_sytem_architecture_month_count ON operating_system_architecture (month, count)');
        $this->addSql('DROP INDEX package_month ON package');
        $this->addSql('CREATE INDEX package_month_count ON package (month, count)');
        $this->addSql('DROP INDEX sytem_architecture_month ON system_architecture');
        $this->addSql('CREATE INDEX sytem_architecture_month_count ON system_architecture (month, count)');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX country_month_count ON country');
        $this->addSql('CREATE INDEX country_month ON country (month)');
        $this->addSql('DROP INDEX mirror_month_count ON mirror');
        $this->addSql('CREATE INDEX mirror_month ON mirror (month)');
        $this->addSql('DROP INDEX operating_sytem_architecture_month_count ON operating_system_architecture');
        $this->addSql('CREATE INDEX operating_sytem_architecture_month ON operating_system_architecture (month)');
        $this->addSql('DROP INDEX package_month_count ON package');
        $this->addSql('CREATE INDEX package_month ON package (month)');
        $this->addSql('DROP INDEX sytem_architecture_month_count ON system_architecture');
        $this->addSql('CREATE INDEX sytem_architecture_month ON system_architecture (month)');
    }

    #[\Override]
    public function isTransactional(): bool
    {
        return false;
    }
}
