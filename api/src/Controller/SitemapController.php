<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SitemapController extends AbstractController
{
    public function __construct(private readonly string $environment)
    {
    }

    #[Route(path: '/sitemap.xml', name: 'app_sitemap', methods: ['GET'])]
    public function indexAction(): Response
    {
        $response = $this->render('sitemap.xml.twig');
        $response->headers->set('Content-Type', 'application/xml; charset=UTF-8');
        if ($this->environment === 'prod') {
            $response->setSharedMaxAge(60 * 60);
            $response->setMaxAge(5 * 60);
        }
        return $response;
    }
}
