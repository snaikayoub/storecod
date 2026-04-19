<?php

namespace App\Command;

use App\Entity\Product;
use App\Entity\ProductMedia;
use App\Entity\ProductVariant;
use App\Entity\User;
use App\Service\Slugger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed-single',
    description: 'Seed/update a single-product storefront demo data'
)]
class SeedSingleProductCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly Slugger $slugger,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userRepo = $this->em->getRepository(User::class);
        $productRepo = $this->em->getRepository(Product::class);

        $adminEmail = 'admin@storecod.local';
        /** @var User|null $admin */
        $admin = $userRepo->findOneBy(['email' => $adminEmail]);
        if (!$admin) {
            $admin = (new User())
                ->setEmail($adminEmail)
                ->setRoles(['ROLE_ADMIN']);
            $admin->setPassword($this->passwordHasher->hashPassword($admin, 'cod'));
            $this->em->persist($admin);
            $output->writeln('Created admin user: ' . $adminEmail);
        }

        $title = "T-Shirt Tommy Premium - Essentia MultiColor ref-ESS7395";
        $slug = $this->slugger->slugify($title);
        $category = 'Packs';

        /** @var Product|null $product */
        $product = $productRepo->findOneBy(['slug' => $slug]);
        $created = false;
        if (!$product) {
            $product = (new Product())->setSlug($slug);
            $created = true;
        }

        $product
            ->setTitle($title)
            ->setCategory($category)
            ->setActive(true)
            ->setPriceBaseCents(8000)
            ->setPriceSaleCents(8000)
            ->setDescription(
                "Choisissez vos couleurs preferees.\n\n"
                . "1 T-shirt = 199 DH\n"
                . "2 T-shirts = 319 DH\n"
                . "3 T-shirts = 400 DH\n"
            )
            ->setDescriptionFr(
                "Choisissez vos couleurs preferees.\n\n"
                . "1 T-shirt = 199 DH\n"
                . "2 T-shirts = 319 DH\n"
                . "3 T-shirts = 400 DH\n"
            );

        // promo tiers (total pricing)
        $product->setPromoTiersEditor(json_encode([
            ['qty' => 1, 'totalCents' => 19900],
            ['qty' => 2, 'totalCents' => 31900],
            ['qty' => 3, 'totalCents' => 40000],
        ], JSON_UNESCAPED_SLASHES));

        // Placeholder image; update in admin with real image URLs.
        if (trim($product->getReferenceImageUrl()) === '') {
            $img = 'https://picsum.photos/seed/storephp-single/900/900';
            $product->setReferenceImageUrl($img)->setImageUrls([$img]);
        }

        $this->em->persist($product);
        $this->em->flush();

        // idempotent: replace variants/media
        $this->em->createQuery('DELETE FROM App\\Entity\\ProductMedia m WHERE m.product = :p')
            ->setParameter('p', $product)
            ->execute();
        $this->em->createQuery('DELETE FROM App\\Entity\\ProductVariant v WHERE v.product = :p')
            ->setParameter('p', $product)
            ->execute();

        $sizes = ['M', 'L', 'XL', 'XXL'];
        $colors = ['Noir', 'Blanc', 'Vert', 'Vert Claire', 'Rouge', 'Gris', 'Marron'];

        $pos = 0;
        foreach ($sizes as $s) {
            foreach ($colors as $c) {
                $label = $s . ' ' . $c;
                $v = (new ProductVariant())
                    ->setProduct($product)
                    ->setLabel($label)
                    ->setStock(50)
                    ->setActive(true)
                    ->setPosition($pos++);
                $this->em->persist($v);
            }
        }

        // Ensure at least 1 media row so the public page has a gallery source.
        $m = (new ProductMedia())
            ->setProduct($product)
            ->setKind('image')
            ->setUrl($product->getReferenceImageUrl())
            ->setPrimary(true)
            ->setPosition(0);
        $this->em->persist($m);

        $this->em->flush();

        $output->writeln(($created ? 'Created' : 'Updated') . ' single product: ' . $product->getTitle());
        $output->writeln('Slug: ' . $product->getSlug());
        $output->writeln('Tip: update images in Admin > Produit.');

        return Command::SUCCESS;
    }
}
