<?php

namespace App\Tests\Form\Type;

use App\Form\Type\JsonArrayType;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;

class JsonArrayTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        return [new PreloadedExtension([new JsonArrayType()], [])];
    }

    public function testSubmitDecodesJsonArray(): void
    {
        $form = $this->factory->create(JsonArrayType::class);
        $form->submit('["S","M","L"]');

        self::assertTrue($form->isSynchronized());
        self::assertSame(['S', 'M', 'L'], $form->getData());
    }

    public function testInvalidJsonMakesFormNotSynchronized(): void
    {
        $form = $this->factory->create(JsonArrayType::class);
        $form->submit('{bad');

        self::assertFalse($form->isSynchronized());
    }
}
