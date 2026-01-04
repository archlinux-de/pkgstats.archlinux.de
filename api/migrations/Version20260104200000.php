<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260104200000 extends AbstractMigration
{
    private const int AFFECTED_MONTH = 202512;
    private const int AFFECTED_COUNT = 25200;
    private const array PACKAGE_DIFFS = [
        'pkgstats' => self::AFFECTED_COUNT,
        'gzip' => self::AFFECTED_COUNT,
        'curl' => self::AFFECTED_COUNT,
        'python' => self::AFFECTED_COUNT,
        'discord' => self::AFFECTED_COUNT,
        'dash' => self::AFFECTED_COUNT,
        'equibop-bin' => self::AFFECTED_COUNT,
    ];

    private const array COUNTRY_DIFFS = [
        'US' => 8569,
        'SE' => 2013,
        'DE' => 1798,
        'GB' => 1426,
        'IN' => 1354,
        'CA' => 1076,
        'CH' => 956,
        'FR' => 779,
        'NL' => 718,
        'AU' => 662,
        'JP' => 464,
        'IT' => 363,
        'FI' => 357,
        'AT' => 268,
        'PL' => 267,
        'NO' => 261,
        'SG' => 256,
        'ES' => 210,
        'HU' => 208,
        'HK' => 208,
        'CZ' => 206,
        'DK' => 203,
        'PT' => 202,
        'MX' => 202,
        'EE' => 152,
        'BE' => 152,
        'IL' => 150,
        'BR' => 120,
        'UA' => 106,
        'RS' => 105,
        'RO' => 105,
        'ID' => 104,
        'MY' => 103,
        'GR' => 103,
        'CL' => 103,
        'ZA' => 102,
        'HR' => 102,
        'CO' => 102,
        'BG' => 102,
        'TR' => 101,
        'PH' => 101,
        'NZ' => 101,
        'CY' => 101,
        'TH' => 100,
        'SK' => 100,
        'PE' => 100,
        'NG' => 100,
        'IE' => 100,
        'AR' => 100,
        'AL' => 100,
        'CN' => 24,
        'RU' => 19,
        'IR' => 3,
        'TN' => 2,
        'NP' => 2,
        'LV' => 2,
        'KW' => 2,
        'BD' => 2,
        'AM' => 2,
        'TW' => 1,
        'SA' => 1,
        'RE' => 1,
        'MA' => 1,
        'KR' => 1,
        'KG' => 1,
        'KE' => 1,
        'JO' => 1,
        'IS' => 1,
        'EC' => 1,
        'DZ' => 1,
        'AE' => 1,
    ];

    private const array MIRROR_DIFFS = [
        'https://arch.mirror.constant.com/' => self::AFFECTED_COUNT,
    ];

    private const array OS_ARCH_DIFFS = [
        'x86_64' => self::AFFECTED_COUNT,
    ];

    private const array SYSTEM_ARCH_DIFFS = [
        'x86_64_v4' => self::AFFECTED_COUNT,
    ];

    #[\Override]
    public function getDescription(): string
    {
        return 'Remove malicious submissions from December ~18th.';
    }

    public function up(Schema $schema): void
    {
        foreach (self::PACKAGE_DIFFS as $name => $count) {
            $this->addSql(
                "UPDATE package SET count = count - ? WHERE name = ? AND month = ?",
                [$count, $name, self::AFFECTED_MONTH]
            );
        }

        foreach (self::COUNTRY_DIFFS as $code => $count) {
            $this->addSql(
                "UPDATE country SET count = count - ? WHERE code = ? AND month = ?",
                [$count, $code, self::AFFECTED_MONTH]
            );
        }

        foreach (self::MIRROR_DIFFS as $url => $count) {
            $this->addSql(
                "UPDATE mirror SET count = count - ? WHERE url = ? AND month = ?",
                [$count, $url, self::AFFECTED_MONTH]
            );
        }

        foreach (self::OS_ARCH_DIFFS as $name => $count) {
            $this->addSql(
                "UPDATE operating_system_architecture SET count = count - ? WHERE name = ? AND month = ?",
                [$count, $name, self::AFFECTED_MONTH]
            );
        }

        foreach (self::SYSTEM_ARCH_DIFFS as $name => $count) {
            $this->addSql(
                "UPDATE system_architecture SET count = count - ? WHERE name = ? AND month = ?",
                [$count, $name, self::AFFECTED_MONTH]
            );
        }
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        foreach (self::PACKAGE_DIFFS as $name => $count) {
            $this->addSql(
                "UPDATE package SET count = count + ? WHERE name = ? AND month = ?",
                [$count, $name, self::AFFECTED_MONTH]
            );
        }

        foreach (self::COUNTRY_DIFFS as $code => $count) {
            $this->addSql(
                "UPDATE country SET count = count + ? WHERE code = ? AND month = ?",
                [$count, $code, self::AFFECTED_MONTH]
            );
        }

        foreach (self::MIRROR_DIFFS as $url => $count) {
            $this->addSql(
                "UPDATE mirror SET count = count + ? WHERE url = ? AND month = ?",
                [$count, $url, self::AFFECTED_MONTH]
            );
        }

        foreach (self::OS_ARCH_DIFFS as $name => $count) {
            $this->addSql(
                "UPDATE operating_system_architecture SET count = count + ? WHERE name = ? AND month = ?",
                [$count, $name, self::AFFECTED_MONTH]
            );
        }

        foreach (self::SYSTEM_ARCH_DIFFS as $name => $count) {
            $this->addSql(
                "UPDATE system_architecture SET count = count + ? WHERE name = ? AND month = ?",
                [$count, $name, self::AFFECTED_MONTH]
            );
        }
    }
}
