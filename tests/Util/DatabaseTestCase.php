<?php

namespace App\Tests\Util;

use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class DatabaseTestCase extends KernelTestCase
{
    public function setUp()
    {
        parent::setUp();
        static::bootKernel();
        if ($this->isPersistentDatabase()) {
            $this->dropDatabase();
            $this->createDatabase();
        }
        $this->createDatabaseSchema();
    }

    /**
     * @return bool
     */
    private function isPersistentDatabase(): bool
    {
        $params = $this->getEntityManager()->getConnection()->getParams();
        return (isset($params['path']) && $params['path']) || (isset($params['dbname']) && $params['dbname']);
    }

    /**
     * @return EntityManagerInterface
     */
    protected function getEntityManager(): EntityManagerInterface
    {
        return static::$container->get('doctrine.orm.entity_manager');
    }

    private function dropDatabase()
    {
        $this->runCommand(new ArrayInput([
            'command' => 'doctrine:database:drop',
            '--force' => true,
            '--if-exists' => true
        ]));
    }

    /**
     * @param ArrayInput $input
     */
    private function runCommand(ArrayInput $input)
    {
        $application = new Application(static::$kernel);
        $application->setAutoExit(false);

        $output = new BufferedOutput();
        $result = $application->run($input, $output);

        $outputResult = $output->fetch();
        $this->assertEmpty($outputResult, $outputResult);
        $this->assertEquals(0, $result);
    }

    private function createDatabase(): void
    {
        $this->runCommand(new ArrayInput([
            'command' => 'doctrine:database:create'
        ]));
    }

    private function createDatabaseSchema()
    {
        $this->runCommand(new ArrayInput([
            'command' => 'doctrine:schema:create'
        ]));
    }

    public function tearDown()
    {
        if ($this->isPersistentDatabase()) {
            $this->dropDatabase();
        }
        parent::tearDown();
    }

    /**
     * @param string $className
     * @return ObjectRepository
     */
    protected function getRepository(string $className): ObjectRepository
    {
        return $this->getEntityManager()->getRepository($className);
    }

    /**
     * @return Client
     */
    protected function getClient(): Client
    {
        return static::$container->get('test.client');
    }

    /**
     * @return Application
     */
    protected function createApplication(): Application
    {
        return new Application(static::$kernel);
    }
}
