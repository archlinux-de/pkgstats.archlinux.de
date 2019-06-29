<?php

namespace App\Tests\Util;

use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class DatabaseTestCase extends WebTestCase
{
    /** @var KernelBrowser */
    protected static $client;

    /**
     * @param string $className
     * @return ObjectRepository
     */
    protected static function getRepository(string $className): ObjectRepository
    {
        return static::getEntityManager()->getRepository($className);
    }

    /**
     * @return EntityManagerInterface
     */
    protected static function getEntityManager(): EntityManagerInterface
    {
        $container = static::getClient()->getContainer();
        static::assertNotNull($container);
        return $container->get('doctrine.orm.entity_manager');
    }

    /**
     * @return KernelBrowser
     */
    protected static function getClient(): KernelBrowser
    {
        return static::$client;
    }

    protected function setUp(): void
    {
        parent::setUp();
        static::$client = static::createClient();

        if (static::isPersistentDatabase()) {
            static::dropDatabase();
            static::createDatabase();
        }
        static::createDatabaseSchema();
    }

    /**
     * @return bool
     */
    protected static function isPersistentDatabase(): bool
    {
        $params = static::getEntityManager()->getConnection()->getParams();
        return !empty($params['path']) || !empty($params['dbname']);
    }

    protected static function dropDatabase(): void
    {
        static::runCommand(new ArrayInput([
            'command' => 'doctrine:database:drop',
            '--force' => true,
            '--if-exists' => true,
            '--quiet' => true
        ]));
    }

    /**
     * @param ArrayInput $input
     */
    protected static function runCommand(ArrayInput $input): void
    {
        $application = new Application(static::getClient()->getKernel());
        $application->setAutoExit(false);

        $output = new BufferedOutput();
        $result = $application->run($input, $output);

        $outputResult = $output->fetch();
        static::assertEmpty($outputResult, $outputResult);
        static::assertEquals(0, $result, sprintf('Command %s failed', $input));
    }

    protected static function createDatabase(): void
    {
        static::runCommand(new ArrayInput([
            'command' => 'doctrine:database:create'
        ]));
    }

    protected static function createDatabaseSchema(): void
    {
        static::runCommand(new ArrayInput([
            'command' => 'doctrine:schema:create',
            '--quiet' => true
        ]));
    }

    protected function tearDown(): void
    {
        if (static::isPersistentDatabase()) {
            static::dropDatabase();
        }
        parent::tearDown();
    }
}
