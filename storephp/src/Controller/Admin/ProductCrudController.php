<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Form\Type\JsonArrayType;
use App\Form\Type\LineSeparatedListType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class ProductCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Produit')
            ->setEntityLabelInPlural('Produits')
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('title', 'Titre');
        yield SlugField::new('slug')->setTargetFieldName('title')->onlyOnForms();
        yield TextareaField::new('description', 'Description')->hideOnIndex();
        yield TextField::new('category', 'Categorie')->setRequired(false);
        yield BooleanField::new('active', 'En ligne');

        yield MoneyField::new('priceBaseCents', 'Prix base')
            ->setCurrency('MAD')
            ->setStoredAsCents(true);

        yield MoneyField::new('priceSaleCents', 'Prix vente')
            ->setCurrency('MAD')
            ->setStoredAsCents(true);

        yield IntegerField::new('ordersCount', 'Cmd')->onlyOnIndex();

        yield UrlField::new('referenceImageUrl', 'Image (reference)')->hideOnIndex();

        yield TextareaField::new('imageUrls', 'Images (1 URL par ligne)')
            ->setFormType(LineSeparatedListType::class)
            ->hideOnIndex();

        yield TextareaField::new('variants', 'Variantes (JSON)')
            ->setFormType(JsonArrayType::class)
            ->hideOnIndex();
    }
}
