<?php

namespace Tests\App\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PostPackageListControllerTest extends WebTestCase
{
    public function setUp()
    {
        //TODO: Remove entries from previous runs to avoid the rate limit.
        // Should be obsolete once we are able to setup a proper test environment.
        $kernel = static::bootKernel();
        $kernel->getContainer()
            ->get('database_connection')
            ->exec('DELETE FROM pkgstats_users WHERE ip IN (SHA1("127.0.0.1"), SHA1("::1"))');
    }

    public function testPostPackageListIsSuccessful()
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/',
            ['pkgstatsver' => '2.3', 'arch' => 'x86_64', 'packages' => 'pkgstats', 'modules' => 'snd']
        );

        $this->assertTrue($client->getResponse()->isSuccessful());
        $this->assertContains('Thanks for your submission. :-)', $client->getResponse()->getContent());
    }
}
