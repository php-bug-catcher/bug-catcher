<?php

namespace BugCatcher\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use BugCatcher\Entity\Project;
use BugCatcher\Form\NotifierType;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class ProjectCrudController extends AbstractCrudController
{


	public function __construct(
		private readonly array $collectors,
	) {}

	public static function getEntityFqcn(): string {
		return Project::class;
	}

	public function configureActions(Actions $actions): Actions {
		return parent::configureActions($actions)
			->remove(Crud::PAGE_INDEX, Action::DELETE);
	}


	public function configureFields(string $pageName): iterable {
		$collectorTypes = $this->collectors;
		if (array_is_list($collectorTypes)) {
			$collectorTypes = array_combine($collectorTypes, $collectorTypes);
		}
		return [
			TextField::new('code'),
			TextField::new('name'),
			BooleanField::new("enabled"),
			ChoiceField::new("pingCollector")->setChoices($collectorTypes)->hideOnIndex(),
			UrlField::new("url"),
			TextField::new('dbConnection')->hideOnIndex(),
			AssociationField::new('users')->setColumns(6)->onlyOnForms(),
		];
	}

	public static function getSubscribedServices(): array {
		$services                               = parent::getSubscribedServices();
		$services[ParameterBagInterface::class] = ParameterBagInterface::class;

		return $services;
	}


}
