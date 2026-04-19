<?php

namespace App\Controller\Admin;

use App\Entity\ProductMedia;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use Vich\UploaderBundle\Form\Type\VichImageType;

class ProductMediaCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ProductMedia::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Media')
            ->setEntityLabelInPlural('Medias')
            ->setDefaultSort(['product' => 'ASC', 'position' => 'ASC', 'id' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield AssociationField::new('product', 'Produit');
        yield AssociationField::new('variant', 'Variante')->setRequired(false);
        yield ChoiceField::new('kind', 'Type')->setChoices([
            'image' => 'image',
            'video' => 'video',
        ]);

        if (Crud::PAGE_INDEX === $pageName) {
            yield TextField::new('url', 'Media')
                ->setTemplatePath('admin/field/thumbnail.html.twig');
        }

        yield Field::new('imageFile', 'Image (upload)')
            ->setFormType(VichImageType::class)
            ->setRequired(false)
            ->hideOnIndex();

        yield UrlField::new('url', 'Video URL')
            ->setRequired(false)
            ->hideOnIndex();

        yield BooleanField::new('primary', 'Principale');
        yield IntegerField::new('position', 'Position')->hideOnIndex();
    }
}
