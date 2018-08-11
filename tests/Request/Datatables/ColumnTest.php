<?php

namespace App\Tests\Request\Datatables;

use App\Request\Datatables\Column;
use App\Request\Datatables\Search;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Request\Datatables\Column
 */
class ColumnTest extends TestCase
{
    /**
     * @param bool $searchable
     * @param bool $orderable
     * @dataProvider provideColumnFlags
     */
    public function testJsonSerialize(bool $searchable, bool $orderable)
    {
        $column = new Column(
            0,
            'FooData',
            'FooName',
            $searchable,
            $orderable,
            new Search(
                '',
                false
            )
        );

        $json = json_encode($column);
        $this->assertJson($json);
        $jsonArray = json_decode($json, true);
        $this->assertEquals(
            [
                'id' => 0,
                'data' => 'FooData',
                'name' => 'FooName',
                'searchable' => $searchable,
                'orderable' => $orderable,
                'search' => [
                    'value' => '',
                    'regex' => false
                ]
            ],
            $jsonArray
        );
    }

    /**
     * @return array
     */
    public function provideColumnFlags(): array
    {
        $result = [];
        $bools = [true, false];

        foreach ($bools as $searchable) {
            foreach ($bools as $orderable) {
                $result[] = [$searchable, $orderable];
            }
        }

        return $result;
    }
}
