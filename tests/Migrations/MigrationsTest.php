<?php

namespace App\Tests\Migrations;

use App\Tests\Util\DatabaseTestCase;
use Symfony\Component\Console\Input\ArrayInput;

class MigrationsTest extends DatabaseTestCase
{
    public function setUp()
    {
        static::bootKernel();
        if ($this->isPersistentDatabase()) {
            $this->dropDatabase();
            $this->createDatabase();
        }
    }

    public function testAllMigrationsUp()
    {
        $this->migrateDatabase('latest');
        $this->validateDatabase();
    }

    /**
     * @param string $version
     */
    private function migrateDatabase(string $version)
    {
        $this->runCommand(new ArrayInput([
            'command' => 'doctrine:migrations:migrate',
            'version' => $version,
            '--no-interaction' => true
        ]));
    }

    private function validateDatabase()
    {
        $this->runCommand(new ArrayInput([
            'command' => 'doctrine:schema:validate'
        ]));
    }

    public function testAllMigrationsDown()
    {
        $this->createDatabaseSchema();
        $this->validateDatabase();
        $this->addAllMigrationVersions();
        $this->migrateDatabase('first');
        $this->assertEquals(
            ['migration_versions'],
            $this->getEntityManager()->getConnection()->getSchemaManager()->listTableNames()
        );
    }

    private function addAllMigrationVersions()
    {
        $this->runCommand(new ArrayInput([
            'command' => 'doctrine:migrations:version',
            '--add' => true,
            '--all' => true,
            '--no-interaction' => true
        ]));
    }

    /**
     * @param string $version
     * @dataProvider provideAvailableVersions
     */
    public function testMigration(string $version)
    {
        $this->migrateDatabase($version);
        static::bootKernel();
        $this->migrateDatabase('prev');
        static::bootKernel();
        $this->migrateDatabase('next');
    }

    /**
     * @return array
     */
    public function provideAvailableVersions(): array
    {
        $files = glob(__DIR__ . '/../../src/Migrations/Version*.php');
        $this->assertGreaterThanOrEqual(3, $files);
        asort($files);
        $versions = [];

        foreach ($files as $file) {
            $versions[] = [preg_replace('/^.*Version(\d+)\.php$/', '$1', $file)];
        }

        return $versions;
    }
}
