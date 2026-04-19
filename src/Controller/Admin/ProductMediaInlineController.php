<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\ProductMedia;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ProductMediaInlineController extends AbstractController
{
    #[Route('/admin/product/{productId}/media/{mediaId}/delete', name: 'admin_product_media_inline_delete', methods: ['POST'])]
    public function delete(int $productId, int $mediaId, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('delete_media_' . $mediaId, $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var ProductMedia|null $media */
        $media = $em->getRepository(ProductMedia::class)->find($mediaId);
        if ($media) {
            $p = $media->getProduct();
            if ($p && $p->getId() === $productId) {
                $em->remove($media);
                $em->flush();
            }
        }

        return $this->redirectToRoute('admin_product_edit', ['entityId' => $productId]);
    }
}
