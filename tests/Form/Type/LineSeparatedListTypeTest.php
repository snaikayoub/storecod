<?php

namespace App\Tests\Form\Type;

use App\Form\Type\LineSeparatedListType;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;

class LineSeparatedListTypeTest extends TypeTestCase
{
    protected function getExtensions(): array
    {
        return [new PreloadedExtension([new LineSeparatedListType()], [])];
    }

    public function testSubmitBuildsUniqueList(): void
    {
        $form = $this->factory->create(LineSeparatedListType::class);
        $form->submit("a\n\n b \n a \n");

        self::assertTrue($form->isSynchronized());
        self::assertSame(['a', 'b'], $form->getData());
    }

    public function testViewDataIsJoinedByNewlines(): void
    {
        $form = $this->factory->create(LineSeparatedListType::class);
        $form->setData(['a', 'b']);
        self::assertSame("a\nb", $form->getViewData());
    }
}
