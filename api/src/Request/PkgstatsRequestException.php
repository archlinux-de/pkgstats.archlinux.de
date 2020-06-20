<?php

namespace App\Request;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class PkgstatsRequestException extends BadRequestHttpException
{
    /**
     * @param ConstraintViolationListInterface $constraintViolationList
     */
    public function __construct(ConstraintViolationListInterface $constraintViolationList)
    {
        parent::__construct(
            implode(
                "\n",
                iterator_to_array(
                    (function () use ($constraintViolationList) {
                        /** @var ConstraintViolationInterface $constraintViolation */
                        foreach ($constraintViolationList as $constraintViolation) {
                            yield sprintf(
                                'Validation of %s failed. %s',
                                json_encode($constraintViolation->getInvalidValue()),
                                (string)$constraintViolation->getMessage()
                            );
                        }
                    })()
                )
            )
        );
    }
}
