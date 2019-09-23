<?php

namespace App\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class StartController extends AbstractController
{
    /**
     * @Route("/", methods={"GET"}, name="app_start")
     * @Cache(smaxage="+1 hour", maxage="+5 minutes")
     * @return Response
     */
    public function indexAction(): Response
    {
        return $this->render('start.html.twig');
    }
}
