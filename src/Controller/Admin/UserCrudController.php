<?php

namespace App\Controller\Admin;

use App\Controller\AbstractCrudController;
use App\Entity\Client\Client;
use App\Entity\Role;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserCrudController extends AbstractCrudController {
	public function __construct(
		public UserPasswordHasherInterface $userPasswordHasher
	) {}

	public static function getEntityFqcn(): string {
		return User::class;
	}

	public function configureActions(Actions $actions): Actions {
		$update = function (Action $action) {
			return $action->displayIf(function (User $user) {
				return $user !== $this->getUser();
			});
		};

		return $actions
			->update(Crud::PAGE_INDEX, Action::DELETE, $update);
	}


	public function configureFields(string $pageName): iterable {
		$password = TextField::new('plainPassword')
			->setFormType(PasswordType::class)
			->setFormTypeOptions([
				'mapped'             => false,
				'hash_property_path' => "password",
			])
			->setLabel("New password")
			->setRequired($pageName === Crud::PAGE_NEW)
			->onlyOnForms();

		$roles = [
			"Admin"    => Role::ROLE_ADMIN,
			"User"     => Role::ROLE_USER,
			"Customer" => Role::ROLE_CUSTOMER,
		];
		if ($this->isGranted("role", Role::ROLE_SUPER_ADMIN)) {
			$roles["Super Admin"] = Role::ROLE_SUPER_ADMIN;
		}

		return [
			TextField::new('email')->setColumns(8),
			BooleanField::new("enabled")->setColumns(4),
			TextField::new('userIdentifier')->onlyOnIndex(),
			TextField::new('fullname')->setColumns(4)->onlyOnForms(),
			ChoiceField::new('enumRoles')
				->setChoices($roles)
				->allowMultipleChoices()
				->setRequired(true)
				->onlyOnForms(),
			$password,
		];


	}


}
