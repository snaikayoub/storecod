<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

class JsonArrayType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            function ($value): string {
                if (!is_array($value)) {
                    return '';
                }
                $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                return is_string($json) ? $json : '';
            },
            function ($value): ?array {
                if (!is_string($value)) {
                    return null;
                }

                $value = trim($value);
                if ($value === '') {
                    return null;
                }

                $decoded = json_decode($value, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new TransformationFailedException('Invalid JSON: ' . json_last_error_msg());
                }
                if (!is_array($decoded)) {
                    throw new TransformationFailedException('JSON must decode to an array.');
                }
                return $decoded;
            }
        ));
    }

    public function getParent(): string
    {
        return TextareaType::class;
    }
}
