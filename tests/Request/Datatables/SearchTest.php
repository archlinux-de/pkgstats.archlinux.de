<?php

namespace App\Tests\Request\Datatables;

use App\Request\Datatables\Search;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Request\Datatables\Search
 */
class SearchTest extends TestCase
{
    /**
     * @param string $value
     * @param bool $isValid
     * @dataProvider provideValidValues
     */
    public function testIsValid(string $value, bool $isValid)
    {
        $search = new Search($value, false);
        $this->assertEquals($isValid, $search->isValid());
    }

    /**
     * @return array
     */
    public function provideValidValues(): array
    {
        return [
            ['', false],
            ['foo', true]
        ];
    }

    /**
     * @param bool $isRegex
     * @dataProvider provideIsRegex
     */
    public function testJsonSerialize(bool $isRegex)
    {
        $search = new Search('foo', $isRegex);

        $json = json_encode($search);
        $this->assertJson($json);
        $jsonArray = json_decode($json, true);
        $this->assertEquals(
            [
                'value' => 'foo',
                'regex' => $isRegex
            ],
            $jsonArray
        );
    }

    /**
     * @return array
     */
    public function provideIsRegex(): array
    {
        return [
            [true],
            [false]
        ];
    }
}
