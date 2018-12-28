<?php

namespace DatatablesApiBundle;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;

class DatatablesQuery
{
    /**
     * @param DatatablesRequest $request
     * @param DatatablesColumnConfiguration $columnConfiguration
     * @param QueryBuilder $queryBuilder
     * @param int $recordsTotal
     * @return DatatablesResponse
     */
    public function getResult(
        DatatablesRequest $request,
        DatatablesColumnConfiguration $columnConfiguration,
        QueryBuilder $queryBuilder,
        int $recordsTotal
    ): DatatablesResponse {
        $this->configureQueryBuilder($request, $columnConfiguration, $queryBuilder);

        $pagination = new Paginator($queryBuilder, false);
        $recordsFiltered = $pagination->count();
        $data = $pagination->getQuery()->getResult();

        $response = new DatatablesResponse($data);
        $response->setRecordsFiltered($recordsFiltered);
        $response->setRecordsTotal($recordsTotal);

        $response->setDraw($request->getDraw());

        return $response;
    }

    /**
     * @param DatatablesRequest $request
     * @param DatatablesColumnConfiguration $columnConfiguration
     * @param QueryBuilder $queryBuilder
     */
    private function configureQueryBuilder(
        DatatablesRequest $request,
        DatatablesColumnConfiguration $columnConfiguration,
        QueryBuilder $queryBuilder
    ): void {
        $queryBuilder
            ->setFirstResult($request->getStart())
            ->setMaxResults($request->getLength());

        foreach ($request->getOrders() as $order) {
            $orderColumnName = $order->getColumn()->getData();
            if ($columnConfiguration->hasOrderableColumn($orderColumnName)) {
                $queryBuilder->orderBy($columnConfiguration->getOrderableColumn($orderColumnName), $order->getDir());
            }
        }

        if ($request->hasSearch() && !$request->getSearch()->isRegex()) {
            $queryBuilder->andWhere($this->createTextSearchQuery($columnConfiguration->getTextSearchableColumns()));
            $queryBuilder->setParameter(':search', '%' . $request->getSearch()->getValue() . '%');
        }

        foreach ($request->getColumns() as $column) {
            if ($column->isSearchable()) {
                $columnName = $column->getData();
                if ($columnConfiguration->hasSearchableColumn($columnName)) {
                    if (!$column->getSearch()->isRegex() && $column->getSearch()->isValid()) {
                        $queryBuilder->andWhere(
                            $columnConfiguration->getSearchableColumn($columnName)
                            . ' LIKE :columnSearch'
                            . $column->getId()
                        );
                        $searchValue = $column->getSearch()->getValue();
                        if (!$columnConfiguration->hasCompareableColumn($columnName)) {
                            $searchValue = '%' . $searchValue . '%';
                        }
                        $queryBuilder->setParameter(':columnSearch' . $column->getId(), $searchValue);
                    }
                }
            }
        }
    }

    /**
     * @param $textSearchableColumns
     * @return string
     */
    private function createTextSearchQuery($textSearchableColumns): string
    {
        $textSearchesArray = iterator_to_array($this->createTextSearchesIterator($textSearchableColumns));
        return '(' . implode(' OR ', $textSearchesArray) . ')';
    }

    /**
     * @param $textSearchableColumns
     * @return \Iterator
     */
    private function createTextSearchesIterator($textSearchableColumns): \Iterator
    {
        foreach ($textSearchableColumns as $textSearchableColumn) {
            yield $textSearchableColumn . ' LIKE :search';
        }
    }
}
