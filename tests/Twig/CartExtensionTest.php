<?php

namespace App\Tests\Twig;

use App\Twig\CartExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class CartExtensionTest extends TestCase
{
    public function testCartCountWithNewFormat(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('cart', [
            ['productId' => 1, 'variant' => null, 'qty' => 2],
            ['productId' => 1, 'variant' => 'M', 'qty' => 1],
        ]);

        $request = Request::create('/');
        $request->setSession($session);

        $stack = new RequestStack();
        $stack->push($request);

        $ext = new CartExtension($stack);
        self::assertSame(3, $ext->cartCount());
    }

    public function testCartCountWithOldFormat(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('cart', [
            1 => 2,
            2 => 1,
        ]);

        $request = Request::create('/');
        $request->setSession($session);

        $stack = new RequestStack();
        $stack->push($request);

        $ext = new CartExtension($stack);
        self::assertSame(3, $ext->cartCount());
    }
}
