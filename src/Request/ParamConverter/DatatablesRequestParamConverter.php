<?php

namespace App\Request\ParamConverter;

use App\Request\Datatables\Column;
use App\Request\Datatables\Order;
use App\Request\Datatables\Request as DatatablesRequest;
use App\Request\Datatables\Search;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DatatablesRequestParamConverter implements ParamConverterInterface
{
    /** @var ValidatorInterface */
    private $validator;

    /**
     * @param ValidatorInterface $validator
     */
    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    /**
     * @param Request $request
     * @param ParamConverter $configuration
     * @return bool
     */
    public function apply(Request $request, ParamConverter $configuration)
    {
        $datatablesRequest = new DatatablesRequest(
            $request->query->getInt('draw'),
            $request->query->getInt('start'),
            $request->query->getInt('length')
        );
        if ($request->query->has('search')) {
            $datatablesRequest->setSearch(
                new Search(
                    $request->query->get('search')['value'],
                    $request->query->get('search')['regex'] == 'true'
                )
            );
        }
        if ($request->query->has('columns')) {
            foreach ($request->query->get('columns') as $columnId => $column) {
                $datatablesRequest->addColumn(
                    new Column(
                        $columnId,
                        $column['data'],
                        $column['name'],
                        $column['searchable'] == 'true',
                        $column['orderable'] == 'true',
                        new Search(
                            $column['search']['value'],
                            $column['search']['regex'] == 'true'
                        )
                    )
                );
            }
            if ($request->query->has('order')) {
                foreach ($request->query->get('order') as $order) {
                    $orderColumn = $datatablesRequest->getColumn($order['column']);
                    if ($orderColumn->isOrderable()) {
                        $datatablesRequest->addOrder(
                            new Order(
                                $orderColumn,
                                $order['dir'] == Order::DESC ? Order::DESC : Order::ASC
                            )
                        );
                    }
                }
            }
        }

        $errors = $this->validator->validate($datatablesRequest);
        if ($errors->count() > 0) {
            throw new \InvalidArgumentException((string)$errors);
        }

        $request->attributes->set(
            $configuration->getName(),
            $datatablesRequest
        );

        return true;
    }

    /**
     * @param ParamConverter $configuration
     * @return bool
     */
    public function supports(ParamConverter $configuration)
    {
        return $configuration->getClass() == DatatablesRequest::class;
    }
}
