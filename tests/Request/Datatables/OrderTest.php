<?php

namespace App\Tests\Request\Datatables;

use App\Request\Datatables\Column;
use App\Request\Datatables\Order;
use App\Request\Datatables\Search;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Request\Datatables\Order
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
