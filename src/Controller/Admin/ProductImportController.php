<?php

namespace App\Controller\Admin;

use App\Service\HtmlProductImporter;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ProductImportController extends AbstractController
{
    public function __construct(private readonly HtmlProductImporter $importer)
    {
    }

    #[AdminRoute('/products/import-html', name: 'products_import_html')]
    public function import(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($request->isMethod('POST')) {
            $token = (string) $request->request->get('_token', '');
            if (!$this->isCsrfTokenValid('import_html', $token)) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $html = (string) $request->request->get('html', '');
            $hint = (string) $request->request->get('titleHint', '');
            $hint = trim($hint) !== '' ? trim($hint) : null;
            $bulk = (string) $request->request->get('bulk', '') === '1';

            if (trim($html) === '') {
                $this->addFlash('error', 'HTML vide.');
                return $this->redirectToRoute('admin_products_import_html');
            }

            try {
                if ($bulk) {
                    $res = $this->importer->importManyFromHtml($html, $hint);
                    $msg = sprintf(
                        'Import termine. Crees: %d, Mis a jour: %d, Ignores: %d, Erreurs: %d',
                        $res['created'],
                        $res['updated'],
                        $res['skipped'],
                        $res['errors'],
                    );
                    $this->addFlash('success', $msg);
                    foreach ($res['errorSamples'] as $sample) {
                        $this->addFlash('error', $sample);
                    }
                    return $this->redirectToRoute('admin_product_index');
                }

                $res = $this->importer->importFromHtml($html, $hint);
                $product = $res['product'];
                $this->addFlash('success', ($res['created'] ? 'Produit cree: ' : 'Produit mis a jour: ') . $product->getTitle());
                return $this->redirectToRoute('admin_product_edit', ['entityId' => $product->getId()]);
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('admin_products_import_html');
            }
        }

        return $this->render('admin/product_import.html.twig');
    }
}
