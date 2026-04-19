<?php

namespace App\Service;

class Slugger
{
    public function slugify(string $input): string
    {
        $s = trim(mb_strtolower($input));
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        $s = preg_replace('/[^a-z0-9]+/i', '-', $s) ?? $s;
        $s = trim($s, '-');
        return $s ?: 'product';
    }
}
