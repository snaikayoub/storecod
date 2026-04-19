<?php

namespace App\Controller\Admin;

use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserCrudController extends AbstractCrudController
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Utilisateur')
            ->setEntityLabelInPlural('Utilisateurs')
            ->setDefaultSort(['id' => 'DESC']);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield EmailField::new('email', 'Email');

        yield ChoiceField::new('roles', 'Roles')
            ->setChoices([
                'Admin' => 'ROLE_ADMIN',
                'User' => 'ROLE_USER',
            ])
            ->allowMultipleChoices();

        yield TextField::new('plainPassword', 'Mot de passe')
            ->setFormType(PasswordType::class)
            ->setRequired($pageName === Crud::PAGE_NEW)
            ->setHelp('Laissez vide pour ne pas changer le mot de passe.');
    }

    public function persistEntity($entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            $this->hashPasswordIfNeeded($entityInstance);
        }
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity($entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof User) {
            $this->hashPasswordIfNeeded($entityInstance);
        }
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function hashPasswordIfNeeded(User $user): void
    {
        $plain = $user->getPlainPassword();
        $plain = is_string($plain) ? trim($plain) : '';
        if ($plain === '') {
            return;
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plain));
        $user->eraseCredentials();
    }
}
