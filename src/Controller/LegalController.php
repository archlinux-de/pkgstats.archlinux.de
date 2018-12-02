<?php

namespace App\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LegalController extends AbstractController
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

    /**
     * @Route("/privacy-policy", methods={"GET"})
     * @Cache(smaxage="900")
     * @return Response
     */
    public function privacyAction(): Response
    {
        return $this->render('privacy_policy.html.twig');
    }
}
