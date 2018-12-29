<?php

namespace DatatablesApiBundle\Tests;

use DatatablesApiBundle\DatatablesColumnConfiguration;
use DatatablesApiBundle\DatatablesQuery;
use DatatablesApiBundle\DatatablesRequest;
use DatatablesApiBundle\Request\Column;
use DatatablesApiBundle\Request\Order;
use DatatablesApiBundle\Request\Search;
use DatatablesApiBundle\Tests\Entity\TestEntity;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\Setup;
use PHPUnit\Framework\TestCase;

/**
 * @covers \DatatablesApiBundle\DatatablesQuery
 */
class DatatablesQueryTest extends TestCase
{
    /** @var EntityManagerInterface */
    private $entityManager;

    public function setUp()
    {
        $this->entityManager = EntityManager::create(
            ['driver' => 'pdo_sqlite', 'memory' => true],
            Setup::createAnnotationMetadataConfiguration(
                [__DIR__ . '/Entity'],
                true,
                null,
                null,
                false
            )
        );

        (new SchemaTool($this->entityManager))
            ->createSchema(
                $this->entityManager->getMetadataFactory()->getAllMetadata()
            );
    }

    public function tearDown()
    {
        (new SchemaTool($this->entityManager))
            ->dropDatabase();
    }

    public function testGetResult()
    {
        $request = new DatatablesRequest(1, 0, 1);
        $configuration = new DatatablesColumnConfiguration();

        $testEntity = new TestEntity('foo');
        $this->entityManager->persist($testEntity);
        $this->entityManager->flush();

        /** @var EntityRepository $repository */
        $repository = $this->entityManager->getRepository(TestEntity::class);
        $queryBuilder = $repository->createQueryBuilder('test');

        $query = new DatatablesQuery();
        $response = $query->getResult($request, $configuration, $queryBuilder, 1);
        /** @var TestEntity[] $data */
        $data = $response->getData();

        $this->assertCount(1, $data);
        $this->assertEquals('foo', $data[0]->getValue());
    }

    /**
     * @param array $entities
     * @param string $direction
     * @param array $expected
     * @dataProvider providerOrderedEntities
     */
    public function testOrder(array $entities, string $direction, array $expected)
    {
        $request = new DatatablesRequest(1, 0, count($expected));
        $request->addOrder(
            new Order(
                new Column(
                    1,
                    'value',
                    'value',
                    false,
                    true,
                    new Search('', false)
                ),
                $direction
            )
        );
        $configuration = new DatatablesColumnConfiguration();
        $configuration->addOrderableColumn('value', 'test.value');

        foreach ($entities as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();

        /** @var EntityRepository $repository */
        $repository = $this->entityManager->getRepository(TestEntity::class);
        $queryBuilder = $repository->createQueryBuilder('test');

        $query = new DatatablesQuery();
        $response = $query->getResult($request, $configuration, $queryBuilder, count($expected));
        /** @var TestEntity[] $data */
        $data = $response->getData();

        $this->assertCount(count($entities), $data);
        for ($i = 0; $i < count($entities); $i++) {
            $this->assertEquals($expected[$i], $data[$i]->getValue());
        }
    }

    /**
     * @return array
     */
    public function providerOrderedEntities(): array
    {
        return [
            [[new TestEntity('a'), new TestEntity('b')], 'asc', ['a', 'b']],
            [[new TestEntity('a'), new TestEntity('b')], 'desc', ['b', 'a']],
        ];
    }

    /**
     * @param array $entities
     * @param string $search
     * @param array $expected
     * @dataProvider providerSearchedEntities
     */
    public function testSearch(array $entities, string $search, array $expected)
    {
        $request = new DatatablesRequest(1, 0, count($expected));
        $request->setSearch(new Search($search, false));
        $configuration = new DatatablesColumnConfiguration();
        $configuration->addTextSearchableColumn('value', 'test.value');

        foreach ($entities as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();

        /** @var EntityRepository $repository */
        $repository = $this->entityManager->getRepository(TestEntity::class);
        $queryBuilder = $repository->createQueryBuilder('test');

        $query = new DatatablesQuery();
        $response = $query->getResult($request, $configuration, $queryBuilder, count($expected));
        /** @var TestEntity[] $data */
        $data = $response->getData();

        $this->assertCount(count($expected), $data);
        for ($i = 0; $i < count($expected); $i++) {
            $this->assertEquals($expected[$i], $data[$i]->getValue());
        }
    }

    /**
     * @return array
     */
    public function providerSearchedEntities(): array
    {
        return [
            [[new TestEntity('foo'), new TestEntity('bar')], 'f', ['foo']],
            [[new TestEntity('foo'), new TestEntity('bar')], 'a', ['bar']],
        ];
    }


    /**
     * @param array $entities
     * @param string $search
     * @param array $expected
     * @dataProvider providerSearchedEntities
     */
    public function testSearchColumn(array $entities, string $search, array $expected)
    {
        $request = new DatatablesRequest(1, 0, count($expected));
        $request->addColumn(
            new Column(
                1,
                'value',
                'value',
                true,
                false,
                new Search($search, false)
            )
        );
        $configuration = new DatatablesColumnConfiguration();
        $configuration->addTextSearchableColumn('value', 'test.value');

        foreach ($entities as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();

        /** @var EntityRepository $repository */
        $repository = $this->entityManager->getRepository(TestEntity::class);
        $queryBuilder = $repository->createQueryBuilder('test');

        $query = new DatatablesQuery();
        $response = $query->getResult($request, $configuration, $queryBuilder, count($expected));
        /** @var TestEntity[] $data */
        $data = $response->getData();

        $this->assertCount(count($expected), $data);
        for ($i = 0; $i < count($expected); $i++) {
            $this->assertEquals($expected[$i], $data[$i]->getValue());
        }
    }
}
