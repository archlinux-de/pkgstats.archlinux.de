<?php

namespace App\Tests\Request\ParamConverter;

use App\Request\Datatables\Request as DatatablesRequest;
use App\Request\ParamConverter\DatatablesRequestParamConverter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DatatablesRequestParamConverterTest extends TestCase
{
    /** @var ParamConverterInterface */
    private $paramConverter;

    /** @var ValidatorInterface|MockObject */
    private $validator;

    /** @var ParamConverter|MockObject */
    private $configuration;

    public function setUp()
    {
        $this->validator = $this->createMock(ValidatorInterface::class);

        $this->configuration = $this->createMock(ParamConverter::class);
        $this->configuration->method('getName')->willReturn(DatatablesRequest::class);

        $this->paramConverter = new DatatablesRequestParamConverter($this->validator);
    }

    public function testDatatablesRequestIsAttachedToRequest()
    {
        $this->configureValidator(0);

        $request = new Request();
        $this->assertTrue($this->paramConverter->apply($request, $this->configuration));
        /** @var DatatablesRequest $datatablesRequest */
        $datatablesRequest = $request->attributes->get(DatatablesRequest::class);
        $this->assertInstanceOf(DatatablesRequest::class, $datatablesRequest);
    }

    private function configureValidator(int $errors): void
    {
        /** @var ConstraintViolationList|MockObject $constraintViolationList */
        $constraintViolationList = $this->createMock(ConstraintViolationList::class);
        $constraintViolationList->method('count')->willReturn($errors);

        $this->validator->method('validate')->willReturn($constraintViolationList);
    }

    public function testParamconverterSupportsDatatablesRequest()
    {
        $this->configureValidator(0);

        $this->configuration->method('getClass')->willReturn(DatatablesRequest::class);
        $this->assertTrue($this->paramConverter->supports($this->configuration));
    }

    public function testValidationErrorsPreventAttachmentToRequest()
    {
        $this->configureValidator(1);

        $request = new Request();
        $this->expectException(\InvalidArgumentException::class);
        $this->paramConverter->apply($request, $this->configuration);
        $this->assertFalse($request->attributes->has(DatatablesRequest::class));
    }

    /**
     * @param bool $isRegex
     * @dataProvider provideSearchFlags
     */
    public function testSearch(bool $isRegex)
    {
        $this->configureValidator(0);

        $request = new Request();
        $request->query->set('search', ['value' => 'foo', 'regex' => $isRegex]);
        $this->assertTrue($this->paramConverter->apply($request, $this->configuration));
        /** @var DatatablesRequest $datatablesRequest */
        $datatablesRequest = $request->attributes->get(DatatablesRequest::class);

        $this->assertEquals('foo', $datatablesRequest->getSearch()->getValue());
        $this->assertEquals($isRegex, $datatablesRequest->getSearch()->isRegex());
    }

    /**
     * @return array
     */
    public function provideSearchFlags(): array
    {
        return [
            [false],
            [true]
        ];
    }

    /**
     * @param bool $searchable
     * @param bool $orderable
     * @param bool $isRegex
     * @dataProvider provideColumnFlags
     */
    public function testColumn(bool $searchable, bool $orderable, bool $isRegex)
    {
        $this->configureValidator(0);

        $request = new Request();
        $request->query->set(
            'columns',
            [
                0 => [
                    'data' => 'FooData',
                    'name' => 'FooName',
                    'searchable' => $searchable,
                    'orderable' => $orderable,
                    'search' => [
                        'value' => 'FooSearch',
                        'regex' => $isRegex
                    ]
                ]
            ]
        );
        $this->assertTrue($this->paramConverter->apply($request, $this->configuration));
        /** @var DatatablesRequest $datatablesRequest */
        $datatablesRequest = $request->attributes->get(DatatablesRequest::class);

        $this->assertCount(1, $datatablesRequest->getColumns());
        $this->assertSame($datatablesRequest->getColumn(0), $datatablesRequest->getColumns()[0]);
        $this->assertEquals('FooData', $datatablesRequest->getColumn(0)->getData());
        $this->assertEquals('FooName', $datatablesRequest->getColumn(0)->getName());
        $this->assertEquals($searchable, $datatablesRequest->getColumn(0)->isSearchable());
        $this->assertEquals($orderable, $datatablesRequest->getColumn(0)->isOrderable());
        $this->assertEquals('FooSearch', $datatablesRequest->getColumn(0)->getSearch()->getValue());
        $this->assertEquals($isRegex, $datatablesRequest->getColumn(0)->getSearch()->isRegex());
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
                foreach ($bools as $isRegex) {
                    $result[] = [$searchable, $orderable, $isRegex];
                }
            }
        }

        return $result;
    }

    /**
     * @param string $orderDirection
     * @dataProvider provideOrderDirections
     */
    public function testOrderColumn(string $orderDirection)
    {
        $this->configureValidator(0);

        $request = new Request();
        $request->query->set(
            'columns',
            [
                0 => [
                    'data' => '',
                    'name' => 'FooColumn',
                    'searchable' => false,
                    'orderable' => true,
                    'search' => [
                        'value' => '',
                        'regex' => false
                    ]
                ]
            ]
        );
        $request->query->set(
            'order',
            [
                [
                    'column' => 0,
                    'dir' => $orderDirection
                ]
            ]
        );
        $this->assertTrue($this->paramConverter->apply($request, $this->configuration));
        /** @var DatatablesRequest $datatablesRequest */
        $datatablesRequest = $request->attributes->get(DatatablesRequest::class);
        $orders = $datatablesRequest->getOrders();
        $this->assertCount(1, $orders);
        $this->assertEquals('FooColumn', $orders[0]->getColumn()->getName());
        $this->assertEquals($orderDirection, $orders[0]->getDir());
    }

    /**
     * @return array
     */
    public function provideOrderDirections(): array
    {
        return [
            ['asc'],
            ['desc']
        ];
    }

    public function testRequestIsInitialized()
    {
        $this->configureValidator(0);

        $request = new Request();
        $request->query->set('draw', 1);
        $request->query->set('start', 2);
        $request->query->set('length', 3);
        $this->assertTrue($this->paramConverter->apply($request, $this->configuration));
        /** @var DatatablesRequest $datatablesRequest */
        $datatablesRequest = $request->attributes->get(DatatablesRequest::class);
        $this->assertEquals(1, $datatablesRequest->getDraw());
        $this->assertEquals(2, $datatablesRequest->getStart());
        $this->assertEquals(3, $datatablesRequest->getLength());
    }
}
