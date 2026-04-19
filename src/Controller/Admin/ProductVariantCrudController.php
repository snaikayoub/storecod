<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\ProductVariant;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class ProductVariantCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ProductVariant::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Variante')
            ->setEntityLabelInPlural('Variantes')
            ->setDefaultSort(['product' => 'ASC', 'position' => 'ASC', 'id' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();

        yield TextField::new('productReferenceImageUrl', 'Photo')
            ->onlyOnIndex()
            ->setTemplatePath('admin/field/thumbnail.html.twig');

        yield TextField::new('productTitle', 'Produit')
            ->onlyOnIndex()
            ->setTemplatePath('admin/field/variant_product_with_eye.html.twig');

        yield MoneyField::new('productPriceSaleCents', 'Prix')
            ->setCurrency('MAD')
            ->setStoredAsCents(true)
            ->onlyOnIndex();

        yield AssociationField::new('product', 'Produit')->setFormTypeOption('choice_label', 'title');
        yield TextField::new('label', 'Label');
        yield TextField::new('sku', 'Ref/SKU')->setRequired(false);
        yield IntegerField::new('stock', 'Stock');
        yield BooleanField::new('active', 'Actif');
        yield IntegerField::new('position', 'Position')->hideOnIndex();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('product', 'Produit')->autocomplete());
    }
}
