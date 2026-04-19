<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class CartController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CsrfTokenManagerInterface $csrf,
    ) {
    }

    #[Route('/cart', name: 'cart')]
    public function cart(Request $request): Response
    {
        $items = $this->getCartItems($request);
        $lines = [];
        $totalCents = 0;

        if ($items !== []) {
            $ids = array_values(array_unique(array_map(static fn (array $it) => (int) $it['productId'], $items)));
            $products = $this->em->getRepository(Product::class)->findBy(['id' => $ids]);
            $productsById = [];
            foreach ($products as $p) {
                $productsById[$p->getId()] = $p;
            }

            foreach ($items as $it) {
                $productId = (int) $it['productId'];
                $qty = (int) $it['qty'];
                $variant = $it['variant'] !== null ? (string) $it['variant'] : null;

                $p = $productsById[$productId] ?? null;
                if (!$p) {
                    continue;
                }

                $lineTotal = $p->getPriceSaleCents() * $qty;
                $totalCents += $lineTotal;
                $lines[] = [
                    'product' => $p,
                    'qty' => $qty,
                    'variant' => $variant,
                    'lineTotalCents' => $lineTotal,
                ];
            }
        }

        return $this->render('cart/index.html.twig', [
            'lines' => $lines,
            'totalCents' => $totalCents,
        ]);
    }

    #[Route('/cart/add/{id}', name: 'cart_add', methods: ['POST'])]
    public function add(int $id, Request $request): RedirectResponse
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->csrf->isTokenValid(new CsrfToken('cart_add_' . $id, $token))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        /** @var Product|null $product */
        $product = $this->em->getRepository(Product::class)->find($id);
        if (!$product || !$product->isActive()) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        $variant = trim((string) $request->request->get('variant', ''));
        $variant = $variant !== '' ? $variant : null;
        $variant = $this->sanitizeVariant($product, $variant);

        $items = $this->getCartItems($request);
        $items = $this->increaseCartItem($items, $id, $variant, 1);
        $this->setCartItems($request, $items);

        $this->addFlash('success', 'Ajoute au panier.');
        return $this->redirectToRoute('cart');
    }

    #[Route('/cart/remove/{id}', name: 'cart_remove', methods: ['POST'])]
    public function remove(int $id, Request $request): RedirectResponse
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->csrf->isTokenValid(new CsrfToken('cart_remove_' . $id, $token))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $variant = trim((string) $request->request->get('variant', ''));
        $variant = $variant !== '' ? $variant : null;

        $items = $this->getCartItems($request);
        $items = array_values(array_filter($items, static function (array $it) use ($id, $variant): bool {
            if ((int) $it['productId'] !== $id) {
                return true;
            }
            if ($variant === null) {
                return false;
            }
            return ($it['variant'] ?? null) !== $variant;
        }));
        $this->setCartItems($request, $items);

        return $this->redirectToRoute('cart');
    }

    #[Route('/cart/inc/{id}', name: 'cart_inc', methods: ['POST'])]
    public function inc(int $id, Request $request): RedirectResponse
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->csrf->isTokenValid(new CsrfToken('cart_inc_' . $id, $token))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $variant = trim((string) $request->request->get('variant', ''));
        $variant = $variant !== '' ? $variant : null;

        $items = $this->getCartItems($request);
        $items = $this->increaseCartItem($items, $id, $variant, 1);
        $this->setCartItems($request, $items);

        return $this->redirectToRoute('cart');
    }

    #[Route('/cart/dec/{id}', name: 'cart_dec', methods: ['POST'])]
    public function dec(int $id, Request $request): RedirectResponse
    {
        $token = (string) $request->request->get('_token', '');
        if (!$this->csrf->isTokenValid(new CsrfToken('cart_dec_' . $id, $token))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $variant = trim((string) $request->request->get('variant', ''));
        $variant = $variant !== '' ? $variant : null;

        $items = $this->getCartItems($request);
        foreach ($items as $i => $it) {
            if ((int) $it['productId'] === $id && (($it['variant'] ?? null) === $variant)) {
                $items[$i]['qty'] = max(0, ((int) $it['qty']) - 1);
            }
        }
        $items = array_values(array_filter($items, static fn (array $it): bool => ((int) $it['qty']) > 0));
        $this->setCartItems($request, $items);

        return $this->redirectToRoute('cart');
    }

    #[Route('/checkout', name: 'checkout')]
    public function checkout(Request $request): Response
    {
        $items = $this->getCartItems($request);
        if ($items === []) {
            return $this->redirectToRoute('cart');
        }

        if ($request->isMethod('POST')) {
            $token = (string) $request->request->get('_token', '');
            if (!$this->csrf->isTokenValid(new CsrfToken('checkout', $token))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $name = trim((string) $request->request->get('customerName', ''));
            $phone = trim((string) $request->request->get('customerPhone', ''));
            $city = trim((string) $request->request->get('customerCity', ''));
            $address = trim((string) $request->request->get('customerAddress', ''));
            $comment = trim((string) $request->request->get('comment', ''));
            $comment = $comment !== '' ? $comment : null;

            if ($name === '' || $phone === '' || $city === '' || $address === '') {
                $this->addFlash('error', 'Veuillez remplir tous les champs obligatoires.');
                return $this->redirectToRoute('checkout');
            }

            $ids = array_values(array_unique(array_map(static fn (array $it) => (int) $it['productId'], $items)));
            $products = $this->em->getRepository(Product::class)->findBy(['id' => $ids]);
            $productsById = [];
            foreach ($products as $p) {
                $productsById[$p->getId()] = $p;
            }

            $order = (new Order())
                ->setCustomerName($name)
                ->setCustomerPhone($phone)
                ->setCustomerCity($city)
                ->setCustomerAddress($address)
                ->setComment($comment)
                ->setStatus('pending');

            $totalCents = 0;
            foreach ($items as $it) {
                $productId = (int) $it['productId'];
                $qty = (int) $it['qty'];
                $variant = $it['variant'] !== null ? (string) $it['variant'] : null;

                $p = $productsById[$productId] ?? null;
                if (!$p) {
                    continue;
                }

                $unitCents = $p->getPriceSaleCents();
                $totalCents += ($unitCents * $qty);

                $item = (new OrderItem())
                    ->setProduct($p)
                    ->setTitleSnapshot($p->getTitle())
                    ->setVariantSnapshot($variant)
                    ->setPriceCentsSnapshot($unitCents)
                    ->setQuantity($qty);
                $order->addItem($item);
            }

            if ($order->getItems()->count() === 0) {
                $this->addFlash('error', 'Votre panier ne contient aucun produit valide.');
                return $this->redirectToRoute('cart');
            }

            $order->setTotalCents($totalCents);

            $this->em->persist($order);
            $this->em->flush();

            $this->setCartItems($request, []);

            return $this->redirectToRoute('checkout_success', ['id' => $order->getId()]);
        }

        return $this->render('checkout/index.html.twig');
    }

    #[Route('/checkout/success/{id}', name: 'checkout_success')]
    public function success(int $id): Response
    {
        /** @var Order|null $order */
        $order = $this->em->getRepository(Order::class)->find($id);
        if (!$order) {
            throw $this->createNotFoundException('Commande introuvable.');
        }

        return $this->render('checkout/success.html.twig', [
            'order' => $order,
        ]);
    }

    /**
     * @return list<array{productId:int,variant:?string,qty:int}>
     */
    private function getCartItems(Request $request): array
    {
        $session = $request->getSession();
        $raw = $session->get('cart', []);

        // Backward-compat: old format {productId => qty}
        if (is_array($raw) && $raw !== [] && array_is_list($raw) === false) {
            $items = [];
            foreach ($raw as $k => $v) {
                $id = (int) $k;
                $qty = (int) $v;
                if ($id > 0 && $qty > 0) {
                    $items[] = ['productId' => $id, 'variant' => null, 'qty' => $qty];
                }
            }
            $this->setCartItems($request, $items);
            return $items;
        }

        if (!is_array($raw) || $raw === []) {
            return [];
        }

        $items = [];
        foreach ($raw as $it) {
            if (!is_array($it)) {
                continue;
            }

            $id = (int) ($it['productId'] ?? 0);
            $qty = (int) ($it['qty'] ?? 0);
            $variant = $it['variant'] ?? null;
            $variant = is_string($variant) ? trim($variant) : null;
            $variant = $variant !== '' ? $variant : null;

            if ($id > 0 && $qty > 0) {
                $items[] = ['productId' => $id, 'variant' => $variant, 'qty' => $qty];
            }
        }

        return $items;
    }

    /**
     * @param list<array{productId:int,variant:?string,qty:int}> $items
     */
    private function setCartItems(Request $request, array $items): void
    {
        $request->getSession()->set('cart', $items);
    }

    /**
     * @param list<array{productId:int,variant:?string,qty:int}> $items
     * @return list<array{productId:int,variant:?string,qty:int}>
     */
    private function increaseCartItem(array $items, int $productId, ?string $variant, int $delta): array
    {
        foreach ($items as $i => $it) {
            if ((int) $it['productId'] === $productId && (($it['variant'] ?? null) === $variant)) {
                $items[$i]['qty'] = max(1, ((int) $it['qty']) + $delta);
                return $items;
            }
        }
        $items[] = ['productId' => $productId, 'variant' => $variant, 'qty' => max(1, $delta)];
        return $items;
    }

    private function sanitizeVariant(Product $product, ?string $variant): ?string
    {
        if ($variant === null) {
            return null;
        }

        $variants = $product->getVariants();
        if (!is_array($variants) || $variants === []) {
            return null;
        }

        $allStrings = true;
        $allowed = [];
        foreach ($variants as $v) {
            if (!is_string($v)) {
                $allStrings = false;
                break;
            }
            $v = trim($v);
            if ($v !== '') {
                $allowed[] = $v;
            }
        }

        if ($allStrings && $allowed !== [] && !in_array($variant, $allowed, true)) {
            return null;
        }

        return $variant;
    }
}
