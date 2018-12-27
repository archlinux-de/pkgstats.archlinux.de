<?php

namespace DatatablesApiBundle\Tests;

use DatatablesApiBundle\DatatablesResponse;
use PHPUnit\Framework\TestCase;

class DatatablesResponseTest extends TestCase
{
    public function testInitialization()
    {
        $response = new DatatablesResponse(['foo' => 'bar']);

        $this->assertEquals(['foo' => 'bar'], $response->getData());
    }

    public function testSerialization()
    {
        $response = new DatatablesResponse();
        $response->setData(['foo' => 'bar']);
        $response->setDraw(12);
        $response->setRecordsFiltered(34);
        $response->setRecordsTotal(56);

        $recodedResponse = json_decode(json_encode($response), true);

        $this->assertEquals(
            [
                'draw' => 12,
                'recordsTotal' => 56,
                'recordsFiltered' => 34,
                'data' => [
                    'foo' => 'bar'
                ]
            ],
            $recodedResponse
        );
    }
}
