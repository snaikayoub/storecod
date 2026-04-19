<?php

namespace App\Tests\Entity;

use App\Entity\Product;
use PHPUnit\Framework\TestCase;

class ProductPromoTest extends TestCase
{
    public function testTotalForQuantityUsesBestTier(): void
    {
        $p = (new Product())
            ->setPriceSaleCents(16000)
            ->setPromoTiersEditor('[{"qty":1,"totalCents":29900},{"qty":2,"totalCents":50000},{"qty":3,"totalCents":71000}]');

        self::assertSame(29900, $p->getTotalForQuantityCents(1));
        self::assertSame(50000, $p->getTotalForQuantityCents(2));
        self::assertSame(71000, $p->getTotalForQuantityCents(3));

        // qty above last tier should use last tier
        self::assertSame(71000, $p->getTotalForQuantityCents(5));
    }

    public function testTotalForQuantityFallsBackToUnitPriceWhenNoTiers(): void
    {
        $p = (new Product())->setPriceSaleCents(16000);
        self::assertSame(16000, $p->getTotalForQuantityCents(1));
        self::assertSame(32000, $p->getTotalForQuantityCents(2));
    }
}
