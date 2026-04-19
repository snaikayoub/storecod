<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

class OrderCsvController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route('/admin/orders/export', name: 'admin_orders_export', methods: ['GET'])]
    public function export(): Response
    {
        $orders = $this->em->getRepository(Order::class)->findBy([], ['id' => 'DESC']);
        $maxItems = 0;
        foreach ($orders as $o) {
            $maxItems = max($maxItems, $o->getItems()->count());
        }

        $headers = ['DESTINATAIRE', 'TELEPHONE', 'ADRESSE', 'PRIX', 'VILLE', 'COMMENTAIRE'];
        for ($i = 1; $i <= $maxItems; $i++) {
            $headers[] = 'PRODUIT ' . $i;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'storephp-orders-');
        if (!is_string($tmp)) {
            throw new \RuntimeException('Unable to create temp file.');
        }

        $fp = fopen($tmp, 'wb');
        if ($fp === false) {
            throw new \RuntimeException('Unable to write temp file.');
        }

        // UTF-8 BOM for Excel compatibility
        fwrite($fp, "\xEF\xBB\xBF");

        fputcsv($fp, $headers, ';');

        foreach ($orders as $o) {
            $row = [
                $o->getCustomerName(),
                $o->getCustomerPhone(),
                $o->getCustomerAddress(),
                number_format($o->getTotalCents() / 100, 2, '.', ''),
                $o->getCustomerCity(),
                $o->getComment() ?? '',
            ];

            $items = $o->getItems()->toArray();
            foreach ($items as $item) {
                if (!$item instanceof OrderItem) {
                    continue;
                }
                $label = $item->getTitleSnapshot();
                if ($item->getVariantSnapshot()) {
                    $label .= ' (' . $item->getVariantSnapshot() . ')';
                }
                if ($item->getQuantity() > 1) {
                    $label .= ' x' . $item->getQuantity();
                }
                $row[] = $label;
            }

            while (count($row) < count($headers)) {
                $row[] = '';
            }

            fputcsv($fp, $row, ';');
        }

        fclose($fp);

        $filename = 'orders-' . date('Y-m-d_His') . '.csv';
        $response = new BinaryFileResponse($tmp);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
        $response->deleteFileAfterSend(true);
        return $response;
    }

    #[Route('/admin/orders/import', name: 'admin_orders_import', methods: ['GET', 'POST'])]
    public function import(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            /** @var UploadedFile|null $file */
            $file = $request->files->get('csv');
            if (!$file) {
                $this->addFlash('error', 'Veuillez choisir un fichier CSV.');
                return $this->redirectToRoute('admin_orders_import');
            }

            $imported = $this->importCsvFile($file);
            $this->addFlash('success', 'Import termine: ' . $imported . ' commande(s).');
            return $this->redirectToRoute('admin');
        }

        return $this->render('admin/orders_import.html.twig');
    }

    private function importCsvFile(UploadedFile $file): int
    {
        $path = $file->getRealPath();
        if (!is_string($path)) {
            throw new \RuntimeException('Invalid upload.');
        }

        $fp = fopen($path, 'rb');
        if ($fp === false) {
            throw new \RuntimeException('Unable to read CSV.');
        }

        $headerLine = fgets($fp);
        if ($headerLine === false) {
            fclose($fp);
            return 0;
        }

        $delimiter = (substr_count($headerLine, ';') >= substr_count($headerLine, ',')) ? ';' : ',';
        rewind($fp);

        $headers = fgetcsv($fp, 0, $delimiter);
        if (!is_array($headers)) {
            fclose($fp);
            return 0;
        }

        $idx = [];
        foreach ($headers as $i => $h) {
            $h = (string) $h;
            $h = preg_replace('/^\xEF\xBB\xBF/', '', $h) ?? $h;
            $h = strtoupper(trim($h));
            if ($h !== '') {
                $idx[$h] = (int) $i;
            }
        }

        $productCols = [];
        foreach (array_keys($idx) as $h) {
            if (str_starts_with($h, 'PRODUIT')) {
                $productCols[] = $idx[$h];
            }
        }
        sort($productCols);

        $imported = 0;

        while (($row = fgetcsv($fp, 0, $delimiter)) !== false) {
            if (!is_array($row) || count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $name = $this->cell($row, $idx, 'DESTINATAIRE');
            $phone = $this->cell($row, $idx, 'TELEPHONE');
            $address = $this->cell($row, $idx, 'ADRESSE');
            $city = $this->cell($row, $idx, 'VILLE');
            $comment = $this->cell($row, $idx, 'COMMENTAIRE');
            $price = $this->cell($row, $idx, 'PRIX');

            if ($name === '' && $phone === '' && $address === '') {
                continue;
            }

            $providedTotalCents = $this->parseMoneyToCents($price);

            $order = (new Order())
                ->setCustomerName($name)
                ->setCustomerPhone($phone)
                ->setCustomerAddress($address)
                ->setCustomerCity($city)
                ->setComment($comment !== '' ? $comment : null)
                ->setStatus('pending')
                ->setTotalCents(0);

            $computedTotalCents = 0;

            foreach ($productCols as $col) {
                $raw = trim((string) ($row[$col] ?? ''));
                if ($raw === '') {
                    continue;
                }

                [$title, $variant, $qty] = $this->parseProductCell($raw);
                if ($title === '' || $qty <= 0) {
                    continue;
                }

                /** @var Product|null $product */
                $product = $this->findProductByTitle($title);
                $unitCents = $product ? $product->getPriceSaleCents() : 0;
                $computedTotalCents += ($unitCents * $qty);

                $item = (new OrderItem())
                    ->setProduct($product)
                    ->setTitleSnapshot($title)
                    ->setVariantSnapshot($variant)
                    ->setPriceCentsSnapshot($unitCents)
                    ->setQuantity($qty);

                $order->addItem($item);
            }

            $this->em->persist($order);
            $order->setTotalCents($providedTotalCents > 0 ? $providedTotalCents : $computedTotalCents);
            $imported++;
        }

        fclose($fp);
        $this->em->flush();

        return $imported;
    }

    private function cell(array $row, array $idx, string $key): string
    {
        $pos = $idx[$key] ?? null;
        if (!is_int($pos)) {
            return '';
        }
        return trim((string) ($row[$pos] ?? ''));
    }

    private function parseMoneyToCents(string $input): int
    {
        $s = trim($input);
        if ($s === '') {
            return 0;
        }

        $s = str_replace([' ', '\t'], '', $s);
        $s = str_replace(',', '.', $s);
        $s = preg_replace('/[^0-9.\-]/', '', $s) ?? '';
        if ($s === '' || $s === '.' || $s === '-') {
            return 0;
        }

        $f = (float) $s;
        return (int) round($f * 100);
    }

    /**
     * @return array{0:string,1:?string,2:int}
     */
    private function parseProductCell(string $raw): array
    {
        $raw = trim($raw);

        // Accept common formats:
        // - "Title x2"
        // - "Title (Variant) x2"
        // - "Title - Variant x2"
        $qty = 1;
        if (preg_match('/\s*[xX]\s*(\d+)\s*$/', $raw, $m) === 1) {
            $qty = max(1, (int) $m[1]);
            $raw = trim(preg_replace('/\s*[xX]\s*\d+\s*$/', '', $raw) ?? $raw);
        }

        $variant = null;
        if (preg_match('/^(.*)\((.*)\)$/', $raw, $m) === 1) {
            $raw = trim((string) $m[1]);
            $variant = trim((string) $m[2]);
        } elseif (preg_match('/^(.*?)\s*-\s*(.+)$/', $raw, $m) === 1) {
            $raw = trim((string) $m[1]);
            $variant = trim((string) $m[2]);
        }

        $variant = $variant !== null && $variant !== '' ? $variant : null;
        return [$raw, $variant, $qty];
    }

    private function findProductByTitle(string $title): ?Product
    {
        $title = trim($title);
        if ($title === '') {
            return null;
        }

        $repo = $this->em->getRepository(Product::class);
        $qb = $repo->createQueryBuilder('p');

        // 1) Exact match (case-insensitive)
        $p = $qb
            ->andWhere('LOWER(p.title) = :t')
            ->setParameter('t', mb_strtolower($title))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        if ($p instanceof Product) {
            return $p;
        }

        // 2) Slug match
        $slug = $this->slugFromString($title);
        if ($slug !== '') {
            $qb = $repo->createQueryBuilder('p');
            $p = $qb
                ->andWhere('p.slug = :s')
                ->setParameter('s', $slug)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
            if ($p instanceof Product) {
                return $p;
            }
        }

        // 3) Partial match
        $qb = $repo->createQueryBuilder('p');
        $p = $qb
            ->andWhere('LOWER(p.title) LIKE :q')
            ->setParameter('q', '%' . mb_strtolower($title) . '%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $p instanceof Product ? $p : null;
    }

    private function slugFromString(string $s): string
    {
        $s = trim(mb_strtolower($s));
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        $s = preg_replace('/[^a-z0-9]+/i', '-', $s) ?? $s;
        $s = trim($s, '-');
        return $s;
    }
}
