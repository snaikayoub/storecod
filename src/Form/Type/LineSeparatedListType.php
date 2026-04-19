<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

class LineSeparatedListType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            function ($value): string {
                if (!is_array($value)) {
                    return '';
                }

                $lines = [];
                foreach ($value as $v) {
                    $v = is_string($v) ? trim($v) : '';
                    if ($v !== '') {
                        $lines[] = $v;
                    }
                }
                return implode("\n", $lines);
            },
            function ($value): array {
                if (!is_string($value)) {
                    return [];
                }

                $out = [];
                foreach (preg_split('/\R/', $value) ?: [] as $line) {
                    $line = trim($line);
                    if ($line !== '') {
                        $out[] = $line;
                    }
                }
                return array_values(array_unique($out));
            }
        ));
    }

    public function getParent(): string
    {
        return TextareaType::class;
    }
}
