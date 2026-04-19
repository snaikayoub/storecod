<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use App\Entity\ProductMedia;
use App\Form\Type\JsonArrayStringType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ProductCrudController extends AbstractCrudController
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Produit')
            ->setEntityLabelInPlural('Produits')
            ->setDefaultSort(['id' => 'DESC'])
            ->overrideTemplate('crud/edit', 'admin/product_edit.html.twig');
    }

    public function configureActions(Actions $actions): Actions
    {
        $import = Action::new('importHtml', 'Import HTML', 'fa fa-file-code')
            ->createAsGlobalAction()
            ->linkToRoute('admin_products_import_html')
            ->addCssClass('btn btn-secondary');

        return $actions
            ->add(Crud::PAGE_INDEX, $import);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();

        yield TextField::new('primaryImageUrl', 'Photo')
            ->onlyOnIndex()
            ->setTemplatePath('admin/field/thumbnail.html.twig');

        if (Crud::PAGE_INDEX === $pageName) {
            yield TextField::new('title', 'Titre')
                ->setTemplatePath('admin/field/title_with_eye.html.twig');
        } else {
            yield TextField::new('title', 'Titre');
        }
        yield SlugField::new('slug')->setTargetFieldName('title')->onlyOnForms();
        yield TextareaField::new('descriptionFr', 'Description (FR)')->hideOnIndex();
        yield TextareaField::new('descriptionAr', 'Description (AR)')
            ->setFormTypeOptions(['attr' => ['dir' => 'rtl']])
            ->hideOnIndex();
        yield TextField::new('category', 'Categorie')->setRequired(false);
        yield BooleanField::new('active', 'En ligne');

        yield MoneyField::new('priceBaseCents', 'Prix base')
            ->setCurrency('MAD')
            ->setStoredAsCents(true);

        yield MoneyField::new('priceSaleCents', 'Prix vente')
            ->setCurrency('MAD')
            ->setStoredAsCents(true);

        yield IntegerField::new('ordersCount', 'Cmd')->onlyOnIndex();

        if (Crud::PAGE_EDIT === $pageName || Crud::PAGE_NEW === $pageName) {
            yield Field::new('mediaUploads', 'Uploader des photos')
                ->setFormType(FileType::class)
                ->setFormTypeOptions([
                    'multiple' => true,
                    'mapped' => true,
                    'required' => false,
                ])
                ->setHelp('Selectionnez plusieurs images. Elles seront attachees a ce produit et supprimees au delete du produit.')
                ->hideOnIndex();

            yield Field::new('videoUpload', 'Uploader une video')
                ->setFormType(FileType::class)
                ->setFormTypeOptions([
                    'multiple' => false,
                    'mapped' => true,
                    'required' => false,
                ])
                ->setHelp('Optionnel. La video apparaitra comme une vignette et pourra etre lue sur la page produit.')
                ->hideOnIndex();
        }

        yield TextareaField::new('promoTiersEditor', 'Promotions (JSON)')
            ->setHelp('Ex: [{"qty":1,"totalCents":29900},{"qty":2,"totalCents":50000}]')
            ->setFormType(JsonArrayStringType::class)
            ->hideOnIndex();
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        parent::persistEntity($entityManager, $entityInstance);
        if ($entityInstance instanceof Product) {
            $this->handleMediaUploads($entityManager, $entityInstance);
        }
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        parent::updateEntity($entityManager, $entityInstance);
        if ($entityInstance instanceof Product) {
            $this->handleMediaUploads($entityManager, $entityInstance);
        }
    }

    private function handleMediaUploads(EntityManagerInterface $em, Product $product): void
    {
        $video = $product->getVideoUpload();
        if ($video instanceof UploadedFile) {
            // Replace any existing video media for this product
            $em->createQuery('DELETE FROM App\\Entity\\ProductMedia m WHERE m.product = :p AND m.kind = :k')
                ->setParameter('p', $product)
                ->setParameter('k', 'video')
                ->execute();

            $media = (new ProductMedia())
                ->setProduct($product)
                ->setVideoFile($video)
                ->setPrimary(false)
                ->setPosition(-10);
            $em->persist($media);

            $product->setVideoUpload(null);
            $em->persist($product);
        }

        $files = $product->getMediaUploads();
        if ($files === []) {
            $em->flush();
            return;
        }

        $qb = $em->createQueryBuilder();
        $maxPos = (int) ($qb
            ->select('COALESCE(MAX(m.position), 0)')
            ->from(ProductMedia::class, 'm')
            ->andWhere('m.product = :p')
            ->setParameter('p', $product)
            ->getQuery()
            ->getSingleScalarResult());

        $hasPrimary = (int) ($em->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(ProductMedia::class, 'm')
            ->andWhere('m.product = :p')
            ->andWhere('m.kind = :k')
            ->andWhere('m.primary = 1')
            ->setParameter('p', $product)
            ->setParameter('k', 'image')
            ->getQuery()
            ->getSingleScalarResult()) > 0;

        $pos = $maxPos + 1;
        foreach ($files as $f) {
            if (!$f instanceof UploadedFile) {
                continue;
            }

            $media = (new ProductMedia())
                ->setProduct($product)
                ->setImageFile($f)
                ->setPrimary(!$hasPrimary)
                ->setPosition($pos++);

            $hasPrimary = true;
            $em->persist($media);
        }

        $product->setMediaUploads([]);
        $em->persist($product);
        $em->flush();
    }
}
