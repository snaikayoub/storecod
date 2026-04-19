<?php

namespace App\Command;

use App\Entity\Product;
use App\Entity\User;
use App\Service\Slugger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed',
    description: 'Seed demo admin user and products'
)]
class SeedCommand extends Command
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

        $adminEmail = trim((string) ($_ENV['SEED_ADMIN_EMAIL'] ?? $_SERVER['SEED_ADMIN_EMAIL'] ?? ''));
        $adminPassword = trim((string) ($_ENV['SEED_ADMIN_PASSWORD'] ?? $_SERVER['SEED_ADMIN_PASSWORD'] ?? ''));
        if ($adminEmail !== '' && $adminPassword !== '') {
            /** @var User|null $admin */
            $admin = $userRepo->findOneBy(['email' => $adminEmail]);
            if (!$admin) {
                $admin = (new User())
                    ->setEmail($adminEmail)
                    ->setRoles(['ROLE_ADMIN']);
                $this->em->persist($admin);
            }

            $admin->setPassword($this->passwordHasher->hashPassword($admin, $adminPassword));
            $output->writeln('Seeded admin user: ' . $adminEmail);
        } else {
            $output->writeln('Skipping admin user seed (set SEED_ADMIN_EMAIL and SEED_ADMIN_PASSWORD).');
        }

        $existingProducts = (int) $productRepo->count([]);
        if ($existingProducts === 0) {
            $seed = [
                ['Sac en cuir', 18900, 14900, 'Accessoires'],
                ['Chemise lin', 12900, 9900, 'Vetements'],
                ['Parfum atelier', 25900, 21900, 'Beaute'],
                ['Bougie ambree', 7900, 5900, 'Maison'],
                ['Ceinture classic', 9900, 7900, 'Accessoires'],
                ['Chaussons laine', 8900, 6900, 'Maison'],
            ];

            $i = 1;
            foreach ($seed as [$title, $base, $sale, $category]) {
                $slug = $this->slugger->slugify($title);
                $img = 'https://picsum.photos/seed/storephp-' . $i . '/900/900';

                $p = (new Product())
                    ->setTitle($title)
                    ->setSlug($slug)
                    ->setDescription('Description demo pour ' . $title . '.')
                    ->setCategory($category)
                    ->setPriceBaseCents($base)
                    ->setPriceSaleCents($sale)
                    ->setReferenceImageUrl($img)
                    ->setImageUrls([$img])
                    ->setActive(true);

                $this->em->persist($p);
                $i++;
            }

            $output->writeln('Seeded demo products: ' . count($seed));
        } else {
            $output->writeln('Products already exist: ' . $existingProducts);
        }

        $this->em->flush();
        return Command::SUCCESS;
    }
}
