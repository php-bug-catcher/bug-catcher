<?php

namespace BugCatcher\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use BugCatcher\Controller\AbstractCrudController;
use BugCatcher\Entity\Client\Client;
use BugCatcher\Entity\Role;
use BugCatcher\Entity\User;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserCrudController extends AbstractCrudController {
	public function __construct(
		public UserPasswordHasherInterface $userPasswordHasher,
		public array                       $roles
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
		$parameterBag = $this->container->get(ParameterBagInterface::class);
		$password = TextField::new('plainPassword')
			->setFormType(PasswordType::class)
			->setFormTypeOptions([
				'mapped'             => false,
				'hash_property_path' => "password",
			])
			->setLabel("New password")
			->setRequired($pageName === Crud::PAGE_NEW)
			->onlyOnForms();


		return [
			TextField::new('email')->setColumns(8),
			BooleanField::new("enabled")->setColumns(4),
			TextField::new('fullname')->setColumns(4),
			ChoiceField::new('roles')
				->setChoices($this->roles)
				->allowMultipleChoices()
				->setRequired(true)
				->onlyOnForms(),
			AssociationField::new('projects')->setColumns(6)->onlyOnForms(),
			$password,
		];


	}


	public static function getSubscribedServices(): array {
		$services                               = parent::getSubscribedServices();
		$services[ParameterBagInterface::class] = ParameterBagInterface::class;

		return $services;
	}


}
