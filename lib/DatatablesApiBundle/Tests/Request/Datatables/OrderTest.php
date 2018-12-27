<?php

namespace DatatablesApiBundle\Tests\Request\Datatables;

use DatatablesApiBundle\Request\Datatables\Column;
use DatatablesApiBundle\Request\Datatables\Order;
use DatatablesApiBundle\Request\Datatables\Search;
use PHPUnit\Framework\TestCase;

/**
 * @covers \DatatablesApiBundle\Request\Datatables\Order
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
            new Search(
                '',
                false
            )
        );
        $order = new Order(
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
