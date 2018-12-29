<?php

namespace DatatablesApiBundle\Tests;

use DatatablesApiBundle\DatatablesColumnConfiguration;
use PHPUnit\Framework\TestCase;

class DatatablesColumnConfigurationTest extends TestCase
{
    public function testAddTextSearchableColumn()
    {
        $configuration = new DatatablesColumnConfiguration();
        $configuration->addTextSearchableColumn('a', 'foo');
        $configuration->addTextSearchableColumn('b', 'bar');

        $this->assertTrue($configuration->hasSearchableColumn('a'));
        $this->assertTrue($configuration->hasSearchableColumn('b'));
        $this->assertEquals('foo', $configuration->getSearchableColumn('a'));
        $this->assertEquals('bar', $configuration->getSearchableColumn('b'));
        $this->assertEquals(['a' => 'foo', 'b' => 'bar'], $configuration->getSearchableColumns());
        $this->assertEquals(['a' => 'foo', 'b' => 'bar'], $configuration->getTextSearchableColumns());
    }

    public function testAddOrderableColumn()
    {
        $configuration = new DatatablesColumnConfiguration();
        $configuration->addOrderableColumn('a', 'foo');
        $configuration->addOrderableColumn('b', 'bar');

        $this->assertTrue($configuration->hasOrderableColumn('a'));
        $this->assertTrue($configuration->hasOrderableColumn('b'));
        $this->assertEquals('foo', $configuration->getOrderableColumn('a'));
        $this->assertEquals('bar', $configuration->getOrderableColumn('b'));
        $this->assertEquals(['a' => 'foo', 'b' => 'bar'], $configuration->getOrderableColumns());
    }

    public function testAddCompareableColumn()
    {
        $configuration = new DatatablesColumnConfiguration();
        $configuration->addCompareableColumn('a', 'foo');
        $configuration->addCompareableColumn('b', 'bar');

        $this->assertTrue($configuration->hasCompareableColumn('a'));
        $this->assertTrue($configuration->hasCompareableColumn('b'));
        $this->assertEquals('foo', $configuration->getCompareableColumn('a'));
        $this->assertEquals('bar', $configuration->getCompareableColumn('b'));
        $this->assertEquals(['a' => 'foo', 'b' => 'bar'], $configuration->getCompareableColumns());

        $this->assertTrue($configuration->hasOrderableColumn('a'));
        $this->assertTrue($configuration->hasOrderableColumn('b'));
        $this->assertEquals('foo', $configuration->getOrderableColumn('a'));
        $this->assertEquals('bar', $configuration->getOrderableColumn('b'));
        $this->assertEquals(['a' => 'foo', 'b' => 'bar'], $configuration->getOrderableColumns());

        $this->assertTrue($configuration->hasSearchableColumn('a'));
        $this->assertTrue($configuration->hasSearchableColumn('b'));
        $this->assertEquals('foo', $configuration->getSearchableColumn('a'));
        $this->assertEquals('bar', $configuration->getSearchableColumn('b'));
        $this->assertEquals(['a' => 'foo', 'b' => 'bar'], $configuration->getSearchableColumns());
    }
}
