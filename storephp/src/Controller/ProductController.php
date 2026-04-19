<?php

namespace App\Controller;

use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProductController extends AbstractController
{
    #[Route('/product/{slug}', name: 'product_show')]
    public function show(string $slug, EntityManagerInterface $em): Response
    {
        /** @var Product|null $product */
        $product = $em->getRepository(Product::class)->findOneBy(['slug' => $slug, 'active' => true]);
        if (!$product) {
            throw $this->createNotFoundException('Produit non trouve');
        }

        return $this->render('product/show.html.twig', [
            'product' => $product,
            'images' => $product->getImageUrls(),
        ]);
    }
}
