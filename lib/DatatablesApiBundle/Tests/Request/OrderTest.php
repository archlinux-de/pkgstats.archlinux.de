<?php

namespace DatatablesApiBundle\Tests\Request;

use DatatablesApiBundle\Request\Column;
use PHPUnit\Framework\TestCase;

/**
 * @covers \DatatablesApiBundle\Request\Order
 */
class OrderTest extends TestCase
{
    /**
     * @param string $direction
     * @dataProvider provideDirections
     */
    public function testJsonSerialize(string $direction)
    {
        $column = new Column(
            0,
            '',
            '',
            false,
            false,
            new \DatatablesApiBundle\Request\Search(
                '',
                false
            )
        );
        $order = new \DatatablesApiBundle\Request\Order(
            $column,
            $direction
        );

        $json = json_encode($order);
        $this->assertJson($json);
        $jsonArray = json_decode($json, true);
        $this->assertEquals(
            [
                'column' => [
                    'id' => 0
                ],
                'dir' => $direction
            ],
            $jsonArray
        );
    }

    /**
     * @return array
     */
    public function provideDirections(): array
    {
        return [
            ['asc'],
            ['desc']
        ];
    }
}
