<?php

namespace App\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SitemapController extends AbstractController
{
    #[Route(path: '/sitemap.xml', name: 'app_sitemap', methods: ['GET'])]
    #[Cache(maxage: '+5 minutes', smaxage: '+1 hour')]
    public function indexAction(): Response
    {
        $response = $this->render('sitemap.xml.twig');
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        return $response;
    }
}
