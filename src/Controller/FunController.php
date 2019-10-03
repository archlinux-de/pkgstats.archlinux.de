<?php

namespace App\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FunController extends AbstractController
{
    /**
     * @Route("/fun", methods={"GET"}, name="app_fun")
     * @Cache(smaxage="+1 day", maxage="+5 minutes")
     * @return Response
     */
    public function funAction(): Response
    {
        return $this->render('fun.html.twig');
    }
}
