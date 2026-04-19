<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;

class OrderCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Order::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Commande')
            ->setEntityLabelInPlural('Commandes')
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield DateTimeField::new('createdAt', 'Cree le')->onlyOnIndex();
        yield ChoiceField::new('status', 'Statut')->setChoices([
            'pending' => 'pending',
            'paid' => 'paid',
            'shipped' => 'shipped',
            'cancelled' => 'cancelled',
        ]);

        yield TextField::new('customerName', 'Destinataire');
        yield TextField::new('customerPhone', 'Telephone');
        yield TextField::new('customerCity', 'Ville');
        yield TextareaField::new('customerAddress', 'Adresse')->hideOnIndex();
        yield TextareaField::new('comment', 'Commentaire')->hideOnIndex();

        yield MoneyField::new('totalCents', 'Total')
            ->setCurrency('MAD')
            ->setStoredAsCents(true)
            ->onlyOnIndex();
    }
}
