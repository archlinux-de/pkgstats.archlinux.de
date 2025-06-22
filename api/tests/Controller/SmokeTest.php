<?php

namespace App\Tests\Controller;

use App\DataFixtures\Months;
use App\Entity\Month;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use SymfonyDatabaseTest\DatabaseTestCase;

#[CoversNothing]
class SmokeTest extends DatabaseTestCase
{
    use MatchesSnapshots;

    public static function setUpBeforeClass(): void
    {
        Month::setBaseTimestamp(strtotime('2022-02-02'));
        Months::setNumberOfMonths(3);
    }

    public static function tearDownAfterClass(): void
    {
        Month::resetBaseTimestamp();
        Months::resetNumberOfMonths();
    }

    public function testSitemap(): void
    {
        $client = $this->getClient();

        $client->request('GET', '/sitemap.xml');

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertMatchesXmlSnapshot($client->getResponse()->getContent());
    }

    public function testApiDoc(): void
    {
        $client = $this->getClient();

        $client->request('GET', '/api/doc.json');

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertMatchesJsonSnapshot([
            'request' => $client->getRequest()->getRequestUri(),
            'response' => json_decode($client->getResponse()->getContent())
        ]);
    }

    /**
     * @param array{'startMonth': ?int, 'endMonth': ?int} $parameters
     */
    #[DataProvider('providePackageRequest')]
    public function testPackage(string $name, array $parameters): void
    {
        $client = $this->getClient();

        $client->request('GET', sprintf('/api/packages/%s', $name), $this->createAbsoluteMonths($parameters));

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertMatchesJsonSnapshot([
            'request' => $client->getRequest()->getRequestUri(),
            'response' => json_decode($client->getResponse()->getContent())
        ]);
    }

    /**
     * @param array{'startMonth': ?int, 'endMonth': ?int} $parameters
     * @return array{'startMonth': ?int, 'endMonth': ?int}
     */
    public function createAbsoluteMonths(array $parameters): array
    {
        foreach (['startMonth', 'endMonth'] as $key) {
            if (isset($parameters[$key])) {
                $parameters[$key] = Month::create($parameters[$key])->getYearMonth();
            }
        }

        return $parameters;
    }

    /**
     * @param array{'startMonth': ?int, 'endMonth': ?int} $parameters
     */
    #[DataProvider('providePackageSeriesRequest')]
    public function testPackageSeries(string $name, array $parameters): void
    {
        $client = $this->getClient();

        $client->request('GET', sprintf('/api/packages/%s/series', $name), $this->createAbsoluteMonths($parameters));

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertMatchesJsonSnapshot([
            'request' => $client->getRequest()->getRequestUri(),
            'response' => json_decode($client->getResponse()->getContent())
        ]);
    }

    /**
     * @return list<array<string, array<mixed>>>
     */
    public static function providePackageSeriesRequest(): array
    {
        $requests = [];

        foreach ([null, 1, 10] as $limit) {
            foreach ([null, 0, 1, 2] as $offset) {
                foreach (self::providePackageRequest() as $packageRequest) {
                    self::assertArrayHasKey(1, $packageRequest);
                    self::assertIsArray($packageRequest[1]);
                    if ($limit !== null) {
                        $packageRequest[1]['limit'] = $limit;
                    }
                    if ($offset !== null) {
                        $packageRequest[1]['offset'] = $offset;
                    }

                    $requests[] = $packageRequest;
                }
            }
        }

        return $requests;
    }

    /**
     * @return list<array<string, array<mixed>>>
     */
    public static function providePackageRequest(): array
    {
        $startMonth = -2;
        $endMonth = -1;
        return [ // @phpstan-ignore return.type
            ['pacman', []],
            ['pacman', ['startMonth' => $startMonth]],
            ['pacman', ['endMonth' => $endMonth]],
            ['pacman', ['startMonth' => $startMonth, 'endMonth' => $endMonth]],
        ];
    }

    /**
     * @param array{'startMonth': ?int, 'endMonth': ?int} $parameters
     */
    #[DataProvider('providePackagesRequest')]
    public function testPackages(array $parameters): void
    {
        $client = $this->getClient();

        $client->request('GET', '/api/packages', $this->createAbsoluteMonths($parameters));

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertIsString($client->getResponse()->getContent());
        $this->assertMatchesJsonSnapshot([
            'request' => $client->getRequest()->getRequestUri(),
            'response' => json_decode($client->getResponse()->getContent())
        ]);
    }

    /**
     * @return list<array>
     */
    public static function providePackagesRequest(): array
    {
        Month::setBaseTimestamp(strtotime('2022-02-02'));
        $startMonths = [null, -2];
        $endMonths = [null, -1];
        $limits = [null, 1, 10];
        $offsets = [null, 0, 1, 2];
        $queries = [null, '', 'php', 'pacman'];

        $requests = [];

        foreach ($startMonths as $startMonth) {
            foreach ($endMonths as $endMonth) {
                foreach ($limits as $limit) {
                    foreach ($offsets as $offset) {
                        foreach ($queries as $query) {
                            $request = [];

                            if ($startMonth !== null) {
                                $request['startMonth'] = $startMonth;
                            }
                            if ($endMonth !== null) {
                                $request['endMonth'] = $endMonth;
                            }
                            if ($limit !== null) {
                                $request['limit'] = $limit;
                            }
                            if ($offset !== null) {
                                $request['offset'] = $offset;
                            }
                            if ($query !== null) {
                                $request['query'] = $query;
                            }

                            $requests[] = [$request];
                        }
                    }
                }
            }
        }

        return $requests;
    }

    public function testUnknownUrlFails(): void
    {
        $client = $this->getClient();

        $client->request('GET', '/unknown');

        $this->assertTrue($client->getResponse()->isNotFound());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        self::runCommand(
            new ArrayInput([
                'command' => 'doctrine:fixtures:load',
                '--no-interaction' => true,
                '--quiet' => true
            ])
        );
    }

    private static function runCommand(ArrayInput $input): void
    {
        $application = new Application(static::getClient()->getKernel());
        $application->setAutoExit(false);

        $output = new BufferedOutput();
        $result = $application->run($input, $output);

        $outputResult = $output->fetch();
        static::assertEmpty($outputResult, $outputResult);
        static::assertEquals(0, $result, sprintf('Command %s failed', $input));
    }
}
