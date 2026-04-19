<?php

namespace App\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class CartExtension extends AbstractExtension
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('cart_count', [$this, 'cartCount']),
        ];
    }

    public function cartCount(): int
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request || !$request->hasSession()) {
            return 0;
        }

        $raw = $request->getSession()->get('cart', []);
        if (!is_array($raw) || $raw === []) {
            return 0;
        }

        // New format: list of items
        if (array_is_list($raw)) {
            $sum = 0;
            foreach ($raw as $it) {
                if (is_array($it)) {
                    $sum += max(0, (int) ($it['qty'] ?? 0));
                }
            }
            return $sum;
        }

        // Old format: map productId => qty
        $sum = 0;
        foreach ($raw as $v) {
            $sum += max(0, (int) $v);
        }
        return $sum;
    }
}
