<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class LocaleController extends AbstractController
{
    #[Route('/lang/{_locale}', name: 'set_locale', requirements: ['_locale' => 'fr|ar'])]
    public function setLocale(Request $request, string $_locale): RedirectResponse
    {
        $request->getSession()->set('_locale', $_locale);
        $to = $request->headers->get('referer');
        if (is_string($to) && $to !== '') {
            return new RedirectResponse($to);
        }

        return $this->redirectToRoute('home');
    }
}
