<?php

namespace App\Tests\Serializer;

use App\Serializer\TextEncoder;
use PHPUnit\Framework\TestCase;

class TextEncoderTest extends TestCase
{
    public function testSupportsEncoding(): void
    {
        $this->assertTrue((new TextEncoder())->supportsEncoding('text'));
    }

    /**
     * @dataProvider provideData
     */
    public function testEncode(mixed $data, string $expected, array $context): void
    {
        $encoder = new TextEncoder();

        if (is_array($data) && isset($data['trace'])) {
            $data['trace'] = 'trace-mock';
        }

        $this->assertEquals($expected, $encoder->encode($data, 'text', $context));
    }

    public function testEncodeException(): void
    {
        $encoder = new TextEncoder();
        $result = $encoder->encode(['trace' => 'foo'], 'text', ['exception' => new \RuntimeException('bar')]);

        $this->assertStringNotContainsString('foo', $result);
        $this->assertStringContainsString('bar', $result);
    }

    public function provideData(): array
    {
        return [
            [[], '', []],
            [['a' => 'b'], "a: b\n", []],
            ['', '', []],
        ];
    }
}
