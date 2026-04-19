<?php

namespace App\Service;

use App\Entity\OrderItem;

class OrderCsv
{
    public function normalizeHeader(string $h): string
    {
        $h = preg_replace('/^\xEF\xBB\xBF/', '', $h) ?? $h;
        $h = strtoupper(trim($h));
        return $h;
    }

    public function parseMoneyToCents(string $input): int
    {
        $s = trim($input);
        if ($s === '') {
            return 0;
        }

        $s = str_replace([" ", "\t"], '', $s);
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
    public function parseProductCell(string $raw): array
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

    public function orderItemLabel(OrderItem $item): string
    {
        $label = $item->getTitleSnapshot();
        if ($item->getVariantSnapshot()) {
            $label .= ' (' . $item->getVariantSnapshot() . ')';
        }
        if ($item->getQuantity() > 1) {
            $label .= ' x' . $item->getQuantity();
        }
        return $label;
    }
}
