<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
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

    #[Route('/merci/{id}', name: 'thank_you', requirements: ['id' => '\\d+'])]
    public function thankYou(int $id, Request $request, EntityManagerInterface $em): Response
    {
        /** @var Order|null $order */
        $order = $em->getRepository(Order::class)->find($id);
        if (!$order) {
            throw $this->createNotFoundException('Commande introuvable.');
        }

        $trackOrderId = (int) $request->getSession()->get('thank_you_track_order_id', 0);
        $shouldTrack = $trackOrderId === $order->getId();

        if ($shouldTrack) {
            $request->getSession()->remove('thank_you_track_order_id');
        }

        return $this->render('thank_you.html.twig', [
            'order' => $order,
            'shouldTrackLead' => $shouldTrack,
        ]);
    }
}
