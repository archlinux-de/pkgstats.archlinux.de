<?php

namespace App\Tests\Controller;

use App\Entity\Package;
use App\Entity\User;
use SymfonyDatabaseTest\DatabaseTestCase;

/**
 * @coversNothing
 */
class SmokeTest extends DatabaseTestCase
{
    /**
     * @param string $url
     * @dataProvider provideUrls
     */
    public function testRequestIsSuccessful(string $url)
    {
        $entityManager = $this->getEntityManager();
        $package = (new Package())
            ->setName('pacman')
            ->setMonth(201812);
        $user = (new User())
            ->setPackages(1)
            ->setMirror('https://mirror.archlinux.de')
            ->setCountrycode('DE')
            ->setCpuarch('x86_64')
            ->setArch('x86_64')
            ->setTime((new \DateTime('2018-12-01'))->getTimestamp())
            ->setIp('localhost');
        $entityManager->persist($package);
        $entityManager->persist($user);
        $entityManager->flush();

        $client = $this->getClient();

        $client->request('GET', $url);

        $this->assertTrue($client->getResponse()->isSuccessful());
    }

    public function testUnknownUrlFails()
    {
        $client = $this->getClient();

        $client->request('GET', '/unknown');

        $this->assertTrue($client->getResponse()->isNotFound());
    }

    /**
     * @param string $legacyUrl
     * @param string $targetUrl
     * @dataProvider provideLegacyUrls
     */
    public function testLegacyRedirects(string $legacyUrl, string $targetUrl)
    {
        $client = $this->getClient();

        $client->request('GET', $legacyUrl);

        $this->assertTrue($client->getResponse()->isRedirect(sprintf('http://localhost%s', $targetUrl)));
    }

    /**
     * @return array
     */
    public function provideUrls(): array
    {
        return [
            ['/'],
            ['/fun'],
            ['/packages'],
            ['/api/packages?startMonth=201812'],
            ['/api/packages/pacman?startMonth=201812'],
            ['/api/packages/pacman/series?startMonth=201812'],
            ['/packages/pacman'],
            ['/compare/packages'],
            ['/api/doc'],
            ['/api/doc.json'],
            ['/sitemap.xml'],
            ['/impressum'],
            ['/privacy-policy']
        ];
    }

    /**
     * @return array
     */
    public function provideLegacyUrls(): array
    {
        return [
            ['/package', '/packages']
        ];
    }
}
