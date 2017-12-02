<?php

namespace App\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

class LegalController extends Controller
{
    /**
     * @Route("/impressum", methods={"GET"})
     * @Cache(smaxage="900")
     * @return Response
     */
    public function impressumAction(): Response
    {
        return $this->render('impressum.html.twig');
    }
}
