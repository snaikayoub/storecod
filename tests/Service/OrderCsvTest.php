<?php

namespace App\Tests\Service;

use App\Entity\OrderItem;
use App\Service\OrderCsv;
use PHPUnit\Framework\TestCase;

class OrderCsvTest extends TestCase
{
    public function testNormalizeHeaderStripsBomAndUppercases(): void
    {
        $csv = new OrderCsv();
        self::assertSame('DESTINATAIRE', $csv->normalizeHeader("\xEF\xBB\xBFdestinataire"));
        self::assertSame('PRODUIT 1', $csv->normalizeHeader(' produit 1 '));
    }

    public function testParseMoneyToCents(): void
    {
        $csv = new OrderCsv();

        self::assertSame(0, $csv->parseMoneyToCents(''));
        self::assertSame(14900, $csv->parseMoneyToCents('149'));
        self::assertSame(14990, $csv->parseMoneyToCents('149.90'));
        self::assertSame(14990, $csv->parseMoneyToCents('149,90'));
        self::assertSame(14990, $csv->parseMoneyToCents(' 149,90 DH '));
    }

    public function testParseProductCell(): void
    {
        $csv = new OrderCsv();

        self::assertSame(['Chemise lin', null, 1], $csv->parseProductCell('Chemise lin'));
        self::assertSame(['Chemise lin', null, 2], $csv->parseProductCell('Chemise lin x2'));
        self::assertSame(['Chemise lin', 'M', 2], $csv->parseProductCell('Chemise lin (M) x2'));
        self::assertSame(['Chemise lin', 'Bleu', 3], $csv->parseProductCell('Chemise lin - Bleu x3'));
    }

    public function testOrderItemLabelIncludesVariantAndQty(): void
    {
        $csv = new OrderCsv();

        $item = (new OrderItem())
            ->setTitleSnapshot('Chemise lin')
            ->setVariantSnapshot('M')
            ->setQuantity(2);

        self::assertSame('Chemise lin (M) x2', $csv->orderItemLabel($item));
    }
}
