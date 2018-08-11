<?php

namespace App\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class StartController extends Controller
{
    /**
     * @Route("/", methods={"GET"})
     * @Cache(smaxage="900")
     * @return Response
     */
    public function indexAction(): Response
    {
        return $this->render('start.html.twig');
    }
}
