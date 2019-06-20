<?php

namespace App\Command;

use Nelmio\ApiDocBundle\Controller\DocumentationController;
use Nelmio\ApiDocBundle\Controller\SwaggerUiController;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Request;

class SwaggerCacheWarmupCommand extends Command
{
    /** @var SwaggerUiController */
    private $swaggerUiController;

    /** @var DocumentationController */
    private $documentationController;

    /**
     * @param SwaggerUiController $swaggerUiController
     * @param DocumentationController $documentationController
     */
    public function __construct(
        SwaggerUiController $swaggerUiController,
        DocumentationController $documentationController
    ) {
        parent::__construct();
        $this->swaggerUiController = $swaggerUiController;
        $this->documentationController = $documentationController;
    }

    protected function configure(): void
    {
        $this->setName('app:swagger:warmup');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->documentationController->__invoke(Request::createFromGlobals());
        $this->swaggerUiController->__invoke(Request::createFromGlobals());
        return 0;
    }
}
