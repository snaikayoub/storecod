<?php

namespace App\Service;

use App\Entity\Product;
use App\Entity\ProductMedia;
use App\Entity\ProductVariant;
use Doctrine\ORM\EntityManagerInterface;

class HtmlProductImporter
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Slugger $slugger,
    ) {
    }

    /**
     * @return array{product:Product, created:bool}
     */
    public function importFromHtml(string $html, ?string $titleHint = null): array
    {
        $html = $this->prepareHtmlForDom($html);

        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $loaded = $doc->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (!$loaded) {
            throw new \RuntimeException('Unable to parse HTML.');
        }

        $xpath = new \DOMXPath($doc);

        $candidates = $this->findOfferCandidates($xpath);
        $node = $this->pickCandidate($candidates, $titleHint);
        if (!$node) {
            throw new \RuntimeException('No product block found in HTML.');
        }

        return $this->importFromNode($xpath, $node, $titleHint, true);
    }

    /**
     * Bulk import: imports all products found in the pasted HTML.
     *
     * @return array{created:int, updated:int, skipped:int, errors:int, errorSamples:list<string>}
     */
    public function importManyFromHtml(string $html, ?string $titleFilter = null): array
    {
        $html = $this->prepareHtmlForDom($html);

        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $loaded = $doc->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (!$loaded) {
            throw new \RuntimeException('Unable to parse HTML.');
        }

        $xpath = new \DOMXPath($doc);
        $candidates = $this->findOfferCandidates($xpath);
        if ($candidates === []) {
            throw new \RuntimeException('No product block found in HTML.');
        }

        $titleFilter = $titleFilter !== null ? $this->norm($titleFilter) : '';

        $seen = [];
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = 0;
        $errorSamples = [];
        $i = 0;

        foreach ($candidates as $node) {
            $title = $this->extractTitle($node);
            if ($title === '') {
                $skipped++;
                continue;
            }
            if ($titleFilter !== '' && mb_stripos($title, $titleFilter) === false) {
                $skipped++;
                continue;
            }

            $slug = $this->slugger->slugify($title);
            if (isset($seen[$slug])) {
                $skipped++;
                continue;
            }
            $seen[$slug] = true;

            try {
                $res = $this->importFromNode($xpath, $node, null, false);
                if ($res['created']) {
                    $created++;
                } else {
                    $updated++;
                }
            } catch (\Throwable $e) {
                $errors++;
                if (count($errorSamples) < 8) {
                    $errorSamples[] = $title . ': ' . $e->getMessage();
                }
            }

            $i++;
            if ($i % 25 === 0) {
                $this->em->flush();
                $this->em->clear();
            }
        }

        $this->em->flush();

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
            'errorSamples' => $errorSamples,
        ];
    }

    /**
     * @return array{product:Product, created:bool}
     */
    private function importFromNode(\DOMXPath $xpath, \DOMElement $node, ?string $titleHint, bool $doFlush): array
    {
        $title = $this->extractTitle($node);
        if ($title === '') {
            throw new \RuntimeException('Unable to extract product title from HTML.');
        }

        $slug = $this->slugger->slugify($title);
        $category = $this->extractCategory($node);
        $priceDh = $this->extractPriceDh($node);
        if ($priceDh <= 0) {
            throw new \RuntimeException('Unable to extract a valid price from HTML.');
        }
        $description = $this->extractDescriptionForNode($xpath, $node, $title);
        $sizes = $this->extractSizes($node);
        $colors = $this->extractColors($node);
        $imageUrls = $this->extractImageUrls($xpath, $node);
        $videoUrls = $this->extractVideoUrls($xpath, $node);

        if ($imageUrls === []) {
            throw new \RuntimeException('Unable to extract product images from HTML.');
        }

        $productRepo = $this->em->getRepository(Product::class);
        /** @var Product|null $product */
        $product = $productRepo->findOneBy(['slug' => $slug]);
        $created = false;
        if (!$product) {
            $product = new Product();
            $product->setSlug($slug);
            $created = true;
        }

        $product
            ->setTitle($title)
            ->setCategory($category)
            ->setDescription($description)
            ->setDescriptionFr($description)
            ->setPriceSaleCents((int) round($priceDh * 100))
            ->setPriceBaseCents(max($product->getPriceBaseCents(), $product->getPriceSaleCents()))
            ->setActive(true);

        if ($imageUrls !== []) {
            $product->setReferenceImageUrl($imageUrls[0]);
            $product->setImageUrls($imageUrls);
        }

        $this->em->persist($product);

        // idempotent: replace variants/media for this product (only for existing rows)
        if (!$created) {
            $this->deleteProductMediaAndVariants($product);
        }

        $variantLabels = $this->buildVariantLabels($sizes, $colors);

        foreach ($variantLabels as $pos => $label) {
            $v = (new ProductVariant())
                ->setProduct($product)
                ->setLabel($label)
                ->setStock(50)
                ->setActive(true)
                ->setPosition($pos);
            $this->em->persist($v);
        }

        $pos = 0;
        foreach ($imageUrls as $i => $url) {
            $m = (new ProductMedia())
                ->setProduct($product)
                ->setKind('image')
                ->setUrl($url)
                ->setPrimary($i === 0)
                ->setPosition($pos++);
            $this->em->persist($m);
        }

        foreach ($videoUrls as $url) {
            $m = (new ProductMedia())
                ->setProduct($product)
                ->setKind('video')
                ->setUrl($url)
                ->setPrimary(false)
                ->setPosition($pos++);
            $this->em->persist($m);
        }

        if ($doFlush) {
            $this->em->flush();
        }

        return ['product' => $product, 'created' => $created];
    }

    /**
     * @return list<\DOMElement>
     */
    private function findOfferCandidates(\DOMXPath $xpath): array
    {
        // Prefer tighter matches: product cards (avoid picking the whole page container).
        $out = [];

        // 1) CODOutfit-style offer cards: rounded border card with an h5 title + product image.
        $cards = $xpath->query(
            '//div'
            . '[contains(concat(" ", normalize-space(@class), " "), " rounded-xl ")]'
            . '[contains(concat(" ", normalize-space(@class), " "), " border ")]'
            . '[.//h5]'
            . '[.//img[contains(@src, "/storage/uploads/products/") or contains(@data-src, "/storage/uploads/products/")]]'
        );
        if ($cards) {
            foreach ($cards as $c) {
                if ($c instanceof \DOMElement) {
                    $out[] = $c;
                }
            }
        }

        // 2) Livewire offer cards often have a dispatch call to open the drawer.
        // Note: attributes like "wire:click" are namespace-like in XPath; match via name().
        $wireCards = $xpath->query(
            '//*[ @*[name() = "wire:click"]'
            . ' and contains(@*[name() = "wire:click"], "offer:view")'
            . ' and .//h5 and .//img ]'
        );
        if ($wireCards) {
            foreach ($wireCards as $c) {
                if ($c instanceof \DOMElement) {
                    $out[] = $c;
                }
            }
        }

        // 3) Fallback: build a "product root" around headings (works for detail views
        // where title + images may live in different sibling cards).
        if ($out === []) {
            $nodes = $xpath->query('//h1 | //h5');
            if (!$nodes) {
                return [];
            }

            foreach ($nodes as $n) {
                if (!$n instanceof \DOMElement) {
                    continue;
                }
                $t = $this->norm($n->textContent);
                if ($t === '' || mb_strlen($t) < 6) {
                    continue;
                }

                $root = $this->closestProductRoot($xpath, $n);
                if ($root) {
                    $out[] = $root;
                }
            }
        }

        return $this->dedupeElements($out);
    }

    private function closestProductRoot(\DOMXPath $xpath, \DOMElement $from): ?\DOMElement
    {
        $cur = $from;
        for ($i = 0; $i < 14; $i++) {
            $p = $cur->parentNode;
            if (!$p instanceof \DOMElement) {
                break;
            }
            $cur = $p;

            $tag = mb_strtolower($cur->tagName);
            if ($tag === 'body' || $tag === 'html') {
                break;
            }

            $hasTitle = $xpath->query('.//h1 | .//h5', $cur);
            if (!$hasTitle || $hasTitle->length === 0) {
                continue;
            }

            $hasProductImg = $xpath->query(
                './/img[contains(@src, "/storage/uploads/products/") or contains(@data-src, "/storage/uploads/products/")]',
                $cur
            );
            if (!$hasProductImg || $hasProductImg->length === 0) {
                continue;
            }

            // Require a price marker somewhere in the block.
            $hasPrice = $xpath->query(
                './/*[contains(translate(normalize-space(string(.)), "DHS", "dhs"), "dhs") or contains(translate(normalize-space(string(.)), "DH", "dh"), "dh")]',
                $cur
            );
            if (!$hasPrice || $hasPrice->length === 0) {
                continue;
            }

            return $cur;
        }

        return null;
    }

    /**
     * @param list<\DOMElement> $els
     * @return list<\DOMElement>
     */
    private function dedupeElements(array $els): array
    {
        $uniq = [];
        $seen = [];
        foreach ($els as $el) {
            $hash = spl_object_hash($el);
            if (!isset($seen[$hash])) {
                $seen[$hash] = true;
                $uniq[] = $el;
            }
        }
        return $uniq;
    }

    /**
     * @param list<\DOMElement> $candidates
     */
    private function pickCandidate(array $candidates, ?string $titleHint): ?\DOMElement
    {
        if ($candidates === []) {
            return null;
        }

        $titleHint = $titleHint !== null ? $this->norm($titleHint) : '';
        if ($titleHint !== '') {
            foreach ($candidates as $c) {
                $t = $this->extractTitle($c);
                if ($t !== '' && mb_stripos($t, $titleHint) !== false) {
                    return $c;
                }
            }
        }

        // fallback: first candidate
        return $candidates[0];
    }

    private function extractTitle(\DOMElement $root): string
    {
        foreach ($root->getElementsByTagName('h1') as $h) {
            $t = $this->norm($h->textContent);
            if ($t !== '') {
                return $t;
            }
        }
        foreach ($root->getElementsByTagName('h5') as $h) {
            $t = $this->norm($h->textContent);
            if ($t !== '') {
                return $t;
            }
        }
        return '';
    }

    private function extractCategory(\DOMElement $root): string
    {
        $spans = $root->getElementsByTagName('span');
        foreach ($spans as $s) {
            $t = $this->norm($s->textContent);
            if ($t !== '' && mb_strlen($t) <= 60) {
                return $t;
            }
        }
        return 'Catalogue';
    }

    private function extractPriceDh(\DOMElement $root): float
    {
        $text = $root->textContent;
        if (!is_string($text)) {
            return 0.0;
        }
        if (preg_match('/(\d+[\.,]?\d*)\s*(?:dhs?|dh)\b/i', $text, $m) === 1) {
            $v = str_replace(',', '.', $m[1]);
            return (float) $v;
        }

        return 0.0;
    }

    /**
     * @return list<string>
     */
    private function extractSizes(\DOMElement $root): array
    {
        $out = [];

        // Look for small label "Taille" and the next UL with li values
        $walker = $root->getElementsByTagName('*');
        foreach ($walker as $el) {
            if (!$el instanceof \DOMElement) {
                continue;
            }
            $label = $this->norm($el->textContent);
            if (mb_strtolower($label) !== 'taille') {
                continue;
            }

            $parent = $el->parentNode;
            if (!$parent instanceof \DOMElement) {
                continue;
            }
            $uls = $parent->getElementsByTagName('ul');
            foreach ($uls as $ul) {
                foreach ($ul->getElementsByTagName('li') as $li) {
                    $v = $this->norm($li->textContent);
                    if ($v !== '') {
                        $out[] = $v;
                    }
                }
                if ($out !== []) {
                    return array_values(array_unique($out));
                }
            }
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function extractColors(\DOMElement $root): array
    {
        $out = [];

        // Look for label "Couleur" (or "Color") and the UL with li values
        $walker = $root->getElementsByTagName('*');
        foreach ($walker as $el) {
            if (!$el instanceof \DOMElement) {
                continue;
            }
            $label = mb_strtolower($this->norm($el->textContent));
            if ($label !== 'couleur' && $label !== 'color') {
                continue;
            }

            $parent = $el->parentNode;
            if (!$parent instanceof \DOMElement) {
                continue;
            }
            $uls = $parent->getElementsByTagName('ul');
            foreach ($uls as $ul) {
                foreach ($ul->getElementsByTagName('li') as $li) {
                    $v = $this->norm($li->textContent);
                    if ($v !== '') {
                        $out[] = $v;
                    }
                }
                if ($out !== []) {
                    return array_values(array_unique($out));
                }
            }
        }

        return [];
    }

    /**
     * @param list<string> $sizes
     * @param list<string> $colors
     * @return list<string>
     */
    private function buildVariantLabels(array $sizes, array $colors): array
    {
        $sizes = array_values(array_unique(array_values(array_filter(array_map([$this, 'norm'], $sizes), static fn (string $v): bool => $v !== ''))));
        $colors = array_values(array_unique(array_values(array_filter(array_map([$this, 'norm'], $colors), static fn (string $v): bool => $v !== ''))));

        if ($sizes === [] && $colors === []) {
            return [];
        }
        if ($colors === []) {
            return $sizes;
        }
        if ($sizes === []) {
            return $colors;
        }

        $out = [];
        foreach ($sizes as $s) {
            foreach ($colors as $c) {
                $out[] = $s . ' ' . $c;
                if (count($out) >= 120) {
                    return $out;
                }
            }
        }
        return $out;
    }

    private function extractDescriptionForNode(\DOMXPath $xpath, \DOMElement $root, string $title): string
    {
        // Prefer blocks inside the selected product root (avoid picking unrelated HTML).
        // Keep HTML formatting to match the original description.
        $nodes = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " to-html ")]', $root);
        if ($nodes && $nodes->length > 0) {
            $best = '';
            foreach ($nodes as $n) {
                if (!$n instanceof \DOMElement) {
                    continue;
                }
                $html = $this->sanitizeHtmlFragment($this->innerHtml($n));
                if ($this->stripTagsText($html) !== '' && mb_strlen($html) > mb_strlen($best)) {
                    $best = $html;
                }
            }
            if ($best !== '') {
                return $best;
            }
        }

        // Fallback: if importing a detail HTML dump where the description lives outside
        // the chosen node, pick the longest known description block in the whole page.
        $global = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " to-html ")]');
        if ($global && $global->length > 0) {
            $best = '';
            foreach ($global as $n) {
                if (!$n instanceof \DOMElement) {
                    continue;
                }
                $html = $this->sanitizeHtmlFragment($this->innerHtml($n));
                if ($this->stripTagsText($html) !== '' && mb_strlen($html) > mb_strlen($best)) {
                    $best = $html;
                }
            }
            if ($best !== '' && mb_strlen($best) >= 40) {
                return $best;
            }
        }

        // Fallback: use title as base.
        return $this->escapeAsParagraph($title);
    }

    private function escapeAsParagraph(string $text): string
    {
        $text = $this->norm($text);
        if ($text === '') {
            return '';
        }
        return '<p>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
    }

    private function stripTagsText(string $html): string
    {
        $t = trim(strip_tags($html));
        return $this->norm($t);
    }

    private function innerHtml(\DOMElement $el): string
    {
        $doc = $el->ownerDocument;
        if (!$doc) {
            return '';
        }

        $out = '';
        foreach ($el->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
        return is_string($out) ? $out : '';
    }

    private function sanitizeHtmlFragment(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $doc = new \DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $wrapped = '<?xml encoding="utf-8" ?><div id="ea_root">' . $html . '</div>';
        $doc->loadHTML($wrapped, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $xpath = new \DOMXPath($doc);

        $remove = $xpath->query('//script|//style|//link|//meta');
        if ($remove) {
            foreach ($remove as $n) {
                if ($n instanceof \DOMNode && $n->parentNode) {
                    $n->parentNode->removeChild($n);
                }
            }
        }

        $els = $xpath->query('//*[@*]');
        if ($els) {
            foreach ($els as $node) {
                if (!$node instanceof \DOMElement) {
                    continue;
                }
                // Copy attributes list first (live NamedNodeMap)
                $attrs = [];
                foreach ($node->attributes ?? [] as $attr) {
                    if ($attr instanceof \DOMAttr) {
                        $attrs[] = $attr;
                    }
                }
                foreach ($attrs as $attr) {
                    $name = mb_strtolower($attr->name);
                    $value = (string) $attr->value;
                    if (str_starts_with($name, 'on')) {
                        $node->removeAttributeNode($attr);
                        continue;
                    }
                    if (($name === 'href' || $name === 'src') && preg_match('/^\s*javascript:/i', $value) === 1) {
                        $node->removeAttributeNode($attr);
                        continue;
                    }
                    if ($name === 'style' && preg_match('/expression\s*\(|javascript\s*:/i', $value) === 1) {
                        $node->removeAttributeNode($attr);
                        continue;
                    }
                }
            }
        }

        $root = $xpath->query('//*[@id="ea_root"]')->item(0);
        if (!$root instanceof \DOMElement) {
            return '';
        }

        $out = $this->innerHtml($root);
        $out = trim($out);
        return $out;
    }

    /**
     * @return list<string>
     */
    private function extractImageUrls(\DOMXPath $xpath, \DOMElement $root): array
    {
        $urls = [];

        // 1) within the selected block (prefer real product images)
        $imgs = $xpath->query('.//img', $root);
        if ($imgs) {
            foreach ($imgs as $img) {
                if (!$img instanceof \DOMElement) {
                    continue;
                }
                foreach ($this->extractImgCandidateUrls($img) as $u) {
                    if (!$this->isImageUrl($u)) {
                        continue;
                    }
                    $lu = mb_strtolower($u);
                    if (str_contains($lu, 'placeholder')) {
                        continue;
                    }
                    if (str_contains($lu, '/storage/uploads/categories/')) {
                        continue;
                    }
                    // Allow anything, but product images get priority later.
                    $urls[] = $u;
                }
            }
        }

        // 2) global product images: only if we couldn't get any local images.
        // This avoids mixing images when importing a full "offers list" page.
        if ($urls === []) {
            $global = $xpath->query('//img[contains(@src, "/storage/uploads/products/") or contains(@data-src, "/storage/uploads/products/")]');
            if ($global) {
                foreach ($global as $img) {
                    if (!$img instanceof \DOMElement) {
                        continue;
                    }
                    foreach ($this->extractImgCandidateUrls($img) as $u) {
                        if (str_contains($u, '/storage/uploads/products/') && $this->isImageUrl($u)) {
                            $urls[] = $u;
                        }
                    }
                }
            }
        }

        // Prefer product images first
        $urls = array_values(array_unique($urls));
        usort($urls, static function (string $a, string $b): int {
            $ap = str_contains($a, '/storage/uploads/products/') ? 0 : 1;
            $bp = str_contains($b, '/storage/uploads/products/') ? 0 : 1;
            return $ap <=> $bp;
        });

        return array_slice($urls, 0, 12);
    }

    /**
     * @return list<string>
     */
    private function extractImgCandidateUrls(\DOMElement $img): array
    {
        $out = [];

        $src = trim((string) $img->getAttribute('src'));
        if ($src !== '') {
            $out[] = $src;
        }

        foreach (['data-src', 'data-lazy-src', 'data-original', 'data-url'] as $attr) {
            $v = trim((string) $img->getAttribute($attr));
            if ($v !== '') {
                $out[] = $v;
            }
        }

        $srcset = trim((string) $img->getAttribute('srcset'));
        if ($srcset !== '') {
            // Take the last (usually largest) candidate.
            $parts = array_map('trim', explode(',', $srcset));
            $last = $parts !== [] ? $parts[count($parts) - 1] : '';
            if ($last !== '') {
                $bits = preg_split('/\s+/', $last) ?: [];
                if (isset($bits[0]) && is_string($bits[0]) && trim($bits[0]) !== '') {
                    $out[] = trim($bits[0]);
                }
            }
        }

        $uniq = [];
        $seen = [];
        foreach ($out as $u) {
            $u = trim($u);
            if ($u === '') {
                continue;
            }
            if (!isset($seen[$u])) {
                $seen[$u] = true;
                $uniq[] = $u;
            }
        }
        return $uniq;
    }

    /**
     * @return list<string>
     */
    private function extractVideoUrls(\DOMXPath $xpath, \DOMElement $root): array
    {
        $out = [];

        $iframes = $xpath->query('.//iframe[@src]', $root);
        if ($iframes) {
            foreach ($iframes as $i) {
                if ($i instanceof \DOMElement) {
                    $src = trim((string) $i->getAttribute('src'));
                    if ($src !== '' && (str_contains($src, 'youtube.com') || str_contains($src, 'youtu.be'))) {
                        $out[] = $src;
                    }
                }
            }
        }

        $videos = $xpath->query('.//video[@src]', $root);
        if ($videos) {
            foreach ($videos as $v) {
                if ($v instanceof \DOMElement) {
                    $src = trim((string) $v->getAttribute('src'));
                    if ($src !== '') {
                        $out[] = $src;
                    }
                }
            }
        }

        return array_values(array_unique($out));
    }

    private function isImageUrl(string $url): bool
    {
        $u = mb_strtolower($url);
        return $u !== '' && (str_contains($u, '.jpg') || str_contains($u, '.jpeg') || str_contains($u, '.png') || str_contains($u, '.webp'));
    }

    private function norm(?string $s): string
    {
        $s = is_string($s) ? trim($s) : '';
        $s = $this->fixMojibake($s);
        $s = preg_replace('/\s+/', ' ', $s) ?? $s;
        return trim($s);
    }

    private function prepareHtmlForDom(string $html): string
    {
        // DOMDocument assumes ISO-8859-1 when no encoding is given.
        // This forces UTF-8 parsing for pasted HTML.
        $html = trim($html);
        if ($html === '') {
            return $html;
        }

        if (!preg_match('/charset\s*=\s*utf-8/i', $html)) {
            // Ensure the document declares UTF-8.
            $meta = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
            if (str_contains($html, '<head')) {
                $html = preg_replace('/<head(\b[^>]*)>/i', '<head$1>' . $meta, $html, 1) ?? $html;
            } else {
                $html = $meta . $html;
            }
        }

        return '<?xml encoding="utf-8" ?>' . $html;
    }

    private function fixMojibake(string $s): string
    {
        $s = trim($s);
        if ($s === '') {
            return $s;
        }

        // Common symptoms when UTF-8 bytes were interpreted as ISO-8859-1/Windows-1252.
        $hasSymptom = str_contains($s, 'Ã') || str_contains($s, 'Â') || str_contains($s, "\u{FFFD}") || str_contains($s, 'â€™') || str_contains($s, 'â€“');
        if (!$hasSymptom) {
            return $s;
        }

        $fixed = @utf8_encode(@utf8_decode($s));
        if (!is_string($fixed) || $fixed === '') {
            return $s;
        }

        return $this->mojibakeScore($fixed) < $this->mojibakeScore($s) ? $fixed : $s;
    }

    private function mojibakeScore(string $s): int
    {
        $score = 0;
        $score += substr_count($s, 'Ã') * 3;
        $score += substr_count($s, 'Â') * 2;
        $score += substr_count($s, "\u{FFFD}") * 8;
        $score += substr_count($s, 'â€™') * 3;
        $score += substr_count($s, 'â€“') * 3;
        return $score;
    }

    private function closestBlock(\DOMElement $el): ?\DOMElement
    {
        $cur = $el;
        for ($i = 0; $i < 6; $i++) {
            $p = $cur->parentNode;
            if (!$p instanceof \DOMElement) {
                break;
            }
            $cur = $p;
            $tag = mb_strtolower($cur->tagName);
            if ($tag === 'article' || $tag === 'section') {
                return $cur;
            }
            if ($tag === 'div') {
                $class = (string) $cur->getAttribute('class');
                if (str_contains($class, 'rounded') || str_contains($class, 'card') || str_contains($class, 'border')) {
                    return $cur;
                }
            }
        }
        return $el;
    }

    private function deleteProductMediaAndVariants(Product $product): void
    {
        $this->em->createQuery('DELETE FROM App\\Entity\\ProductMedia m WHERE m.product = :p')
            ->setParameter('p', $product)
            ->execute();
        $this->em->createQuery('DELETE FROM App\\Entity\\ProductVariant v WHERE v.product = :p')
            ->setParameter('p', $product)
            ->execute();
    }
}
