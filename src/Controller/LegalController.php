<?php

namespace App\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Cache(smaxage="+1 hour", maxage="+5 minutes")
 */
class LegalController extends AbstractController
{
    /**
     * @Route("/impressum", methods={"GET"}, name="app_impressum")
     * @return Response
     */
    public function impressumAction(): Response
    {
        return $this->render('impressum.html.twig');
    }

    /**
     * @Route("/privacy-policy", methods={"GET"}, name="app_privacy")
     * @return Response
     */
    public function privacyAction(): Response
    {
        return $this->render('privacy_policy.html.twig');
    }
}
