<?php

namespace BugCatcher\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use BugCatcher\Entity\Project;
use BugCatcher\Service\ProjectDeleteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class ProjectCrudController extends AbstractCrudController
{


	public function __construct(
		private readonly array                $collectors,
		private readonly ProjectDeleteService $projectDelete,
	) {}

	public static function getEntityFqcn(): string {
		return Project::class;
	}

	/**
	 * A project is the parent of every record, perf bucket and withholder collected under it, and
	 * none of that is reachable once it is gone - so the delete takes them with it. Letting
	 * EasyAdmin call `remove()` instead would be refused by the withholder foreign key.
	 */
	public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void {
		if (!$entityInstance instanceof Project) {
			parent::deleteEntity($entityManager, $entityInstance);

			return;
		}

		$this->projectDelete->delete($entityInstance);
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
