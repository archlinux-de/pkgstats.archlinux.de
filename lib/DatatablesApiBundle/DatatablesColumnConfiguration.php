<?php

namespace DatatablesApiBundle;

class DatatablesColumnConfiguration
{
    /** @var array */
    private $compareableColumns = [];
    /** @var array */
    private $textSearchableColumns = [];
    /** @var array */
    private $orderableColumns = [];

    /**
     * @param string $name
     * @param string $field
     * @return DatatablesColumnConfiguration
     */
    public function addCompareableColumn(string $name, string $field): DatatablesColumnConfiguration
    {
        $this->compareableColumns[$name] = $field;
        return $this;
    }

    /**
     * @param string $name
     * @param string $field
     * @return DatatablesColumnConfiguration
     */
    public function addTextSearchableColumn(string $name, string $field): DatatablesColumnConfiguration
    {
        $this->textSearchableColumns[$name] = $field;
        return $this;
    }

    /**
     * @param string $name
     * @param string $field
     * @return DatatablesColumnConfiguration
     */
    public function addOrderableColumn(string $name, string $field): DatatablesColumnConfiguration
    {
        $this->orderableColumns[$name] = $field;
        return $this;
    }


    /**
     * @param string $name
     * @return bool
     */
    public function hasOrderableColumn(string $name): bool
    {
        $columns = $this->getOrderableColumns();
        return isset($columns[$name]);
    }

    /**
     * @return array
     */
    public function getOrderableColumns(): array
    {
        return array_merge(
            $this->getCompareableColumns(),
            $this->orderableColumns
        );
    }

    /**
     * @return array
     */
    public function getCompareableColumns(): array
    {
        return $this->compareableColumns;
    }

    /**
     * @param string $name
     * @return string
     */
    public function getOrderableColumn(string $name): string
    {
        $columns = $this->getOrderableColumns();
        return $columns[$name];
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasSearchableColumn(string $name): bool
    {
        $columns = $this->getSearchableColumns();
        return isset($columns[$name]);
    }

    /**
     * @return array
     */
    public function getSearchableColumns(): array
    {
        return array_merge(
            $this->getCompareableColumns(),
            $this->getTextSearchableColumns()
        );
    }

    /**
     * @return array
     */
    public function getTextSearchableColumns(): array
    {
        return $this->textSearchableColumns;
    }

    /**
     * @param string $name
     * @return string
     */
    public function getSearchableColumn(string $name): string
    {
        $columns = $this->getSearchableColumns();
        return $columns[$name];
    }

    /**
     * @param string $name
     * @return bool
     */
    public function hasCompareableColumn(string $name): bool
    {
        $columns = $this->getCompareableColumns();
        return isset($columns[$name]);
    }

    /**
     * @param string $name
     * @return string
     */
    public function getCompareableColumn(string $name): string
    {
        $columns = $this->getCompareableColumns();
        return $columns[$name];
    }
}
