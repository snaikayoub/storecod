<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\ProductMedia;
use App\Entity\ProductVariant;
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

        $variants = $em->getRepository(ProductVariant::class)->findBy(
            ['product' => $product, 'active' => true],
            ['position' => 'ASC', 'id' => 'ASC']
        );

        $medias = $em->getRepository(ProductMedia::class)->findBy(
            ['product' => $product],
            ['position' => 'ASC', 'primary' => 'DESC', 'id' => 'ASC']
        );

        return $this->render('product/show.html.twig', [
            'product' => $product,
            'images' => $product->getImageUrls(),
            'variants' => $variants,
            'medias' => $medias,
        ]);
    }
}
