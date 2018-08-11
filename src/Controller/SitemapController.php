<?php

namespace App\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class SitemapController extends Controller
{

    /**
     * @Route("/sitemap.xml", methods={"GET"})
     * @Cache(smaxage="600")
     * @return Response
     */
    public function indexAction(): Response
    {
        $response = $this->render('sitemap.xml.twig');
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        return $response;
    }
}
