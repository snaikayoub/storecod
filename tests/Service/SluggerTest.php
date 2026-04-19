<?php

namespace App\Tests\Service;

use App\Service\Slugger;
use PHPUnit\Framework\TestCase;

class SluggerTest extends TestCase
{
    public function testSlugifyTransliteratesAndNormalizes(): void
    {
        $slugger = new Slugger();

        self::assertSame('sac-en-cuir', $slugger->slugify('  Sac en cuir  '));
        self::assertSame('chemise-lin', $slugger->slugify('Chemise lin'));
        self::assertSame('cafe-creme', $slugger->slugify('Cafe creme'));
        self::assertSame('cote-d-azur', $slugger->slugify("Cote d'Azur"));
    }

    public function testSlugifyFallsBackOnEmpty(): void
    {
        $slugger = new Slugger();
        self::assertSame('product', $slugger->slugify('   '));
        self::assertSame('product', $slugger->slugify('---'));
    }
}
