<?php

namespace App\Controller;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function home(EntityManagerInterface $em): Response
    {
        return $this->redirectToRoute('shop');
    }

    #[Route('/shop', name: 'shop')]
    public function shop(EntityManagerInterface $em): RedirectResponse|Response
    {
        $products = $em->getRepository(Product::class)->findBy(['active' => true], ['id' => 'DESC']);

        return $this->render('shop/index.html.twig', [
            'products' => $products,
        ]);
    }
}
