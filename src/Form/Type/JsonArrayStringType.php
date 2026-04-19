<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

class JsonArrayStringType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addModelTransformer(new CallbackTransformer(
            function ($value): string {
                return is_string($value) ? $value : '';
            },
            function ($value): string {
                if (!is_string($value)) {
                    return '';
                }

                $value = trim($value);
                if ($value === '') {
                    return '';
                }

                $decoded = json_decode($value, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new TransformationFailedException('Invalid JSON: ' . json_last_error_msg());
                }
                if (!is_array($decoded)) {
                    throw new TransformationFailedException('JSON must decode to an array.');
                }

                return $value;
            }
        ));
    }

    public function getParent(): string
    {
        return TextareaType::class;
    }
}
