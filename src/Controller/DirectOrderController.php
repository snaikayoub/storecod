<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\ProductVariant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class DirectOrderController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CsrfTokenManagerInterface $csrf,
    ) {
    }

    #[Route('/order/create/{id}', name: 'order_create', methods: ['POST'])]
    public function create(int $id, Request $request): RedirectResponse
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->csrf->isTokenValid(new CsrfToken('order_create_' . $id, $token))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var Product|null $product */
        $product = $this->em->getRepository(Product::class)->find($id);
        if (!$product || !$product->isActive()) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        $name = mb_substr(strip_tags(trim((string) $request->request->get('name', ''))), 0, 100);
        $phone = preg_replace('/[^0-9+]/', '', trim((string) $request->request->get('phone', '')));
        $email = trim((string) $request->request->get('email', ''));
        $city = mb_substr(strip_tags(trim((string) $request->request->get('city', ''))), 0, 100);
        $address = strip_tags(trim((string) $request->request->get('address', '')));
        $comment = mb_substr(strip_tags(trim((string) $request->request->get('comment', ''))), 0, 500);

        if ($phone === '' || strlen($phone) < 8) {
            $isRtl = str_starts_with($request->getLocale(), 'ar');
            $this->addFlash('error', $isRtl ? 'رقم الهاتف غير صالح.' : 'Numero de telephone invalide.');
            return $this->redirectToRoute('product_show', ['slug' => $product->getSlug()]);
        }

        $email = $email !== '' ? $email : null;
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $isRtl = str_starts_with($request->getLocale(), 'ar');
            $this->addFlash('error', $isRtl ? 'البريد الإلكتروني غير صالح.' : 'Email invalide.');
            return $this->redirectToRoute('product_show', ['slug' => $product->getSlug()]);
        }

        if ($name === '' || $city === '' || $address === '') {
            $isRtl = str_starts_with($request->getLocale(), 'ar');
            $this->addFlash('error', $isRtl ? 'يرجى ملء جميع الحقول المطلوبة.' : 'Veuillez remplir tous les champs obligatoires.');
            return $this->redirectToRoute('product_show', ['slug' => $product->getSlug()]);
        }

        $comment = $comment !== '' ? $comment : null;

        $qty = (int) $request->request->get('qty', 1);
        $qty = max(1, min(20, $qty));

        $variantId = (int) $request->request->get('variantId', 0);
        $variantLabel = null;
        if ($variantId > 0) {
            /** @var ProductVariant|null $variant */
            $variant = $this->em->getRepository(ProductVariant::class)->find($variantId);
            if ($variant && $variant->getProduct() && $variant->getProduct()->getId() === $product->getId() && $variant->isActive()) {
                $variantLabel = $variant->getLabel();
            } else {
                $variantId = 0;
            }
        }

        $totalCents = $product->getTotalForQuantityCents($qty);
        $unitCents = (int) round($totalCents / max(1, $qty));

        $order = (new Order())
            ->setCustomerName($name)
            ->setCustomerPhone($phone)
            ->setCustomerEmail($email)
            ->setCustomerCity($city)
            ->setCustomerAddress($address)
            ->setComment($comment)
            ->setStatus('pending')
            ->setTotalCents($totalCents);

        $item = (new OrderItem())
            ->setProduct($product)
            ->setTitleSnapshot($product->getTitle())
            ->setVariantSnapshot($variantLabel)
            ->setPriceCentsSnapshot($unitCents)
            ->setQuantity($qty);
        $order->addItem($item);

        $this->em->persist($order);
        $this->em->flush();

        $isRtl = str_starts_with($request->getLocale(), 'ar');
        $this->addFlash('success', $isRtl ? 'تم تسجيل طلبك بنجاح. سنتواصل معك لتأكيد الطلب.' : 'Commande enregistree. Nous vous contacterons pour confirmer.');
        return $this->redirectToRoute('thank_you', ['id' => $order->getId()]);
    }
}
