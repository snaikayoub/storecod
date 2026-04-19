<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setTitle('StorePHP Admin');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkToCrud('Produits', 'fa fa-tags', Product::class);
        yield MenuItem::linkToCrud('Commandes', 'fa fa-receipt', Order::class);
        yield MenuItem::linkToRoute('Export CSV', 'fa fa-file-export', 'admin_orders_export');
        yield MenuItem::linkToRoute('Import CSV', 'fa fa-file-import', 'admin_orders_import');
        yield MenuItem::linkToCrud('Utilisateurs', 'fa fa-user', User::class);
        yield MenuItem::linkToRoute('Retour boutique', 'fa fa-store', 'shop');
        yield MenuItem::linkToLogout('Deconnexion', 'fa fa-sign-out');
    }
}
