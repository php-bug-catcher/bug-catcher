<?php

namespace BugCatcher\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use BugCatcher\Entity\Project;
use BugCatcher\Enum\PerfProfile;
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
			// turns this project's dashboard row from "how many errors" into "how many errors,
			// and are the people using it waiting" - see docs/performance.md
			BooleanField::new("perfEnabled")
				->setLabel('Performance')
				->setHelp('Show latency on the dashboard row. Needs the collector shipping buckets for this project.'),
			// which of the two performance rows, once the boolean above has said there is one.
			// A cron box has nobody waiting on it - see BugCatcher\Enum\PerfProfile for why Apdex
			// cannot simply be given a bigger threshold instead
			ChoiceField::new("perfProfile")
				->setLabel('Workload')
				->setChoices([
					'Web — people are waiting'             => PerfProfile::Web,
					'Worker — cron jobs, messenger, loops' => PerfProfile::Worker,
				])
				// without it the choices are the enum *objects*, which Symfony cannot name, so the
				// options come out as `value="0"` and `value="1"` - the submitted value would then
				// depend on the order they are written in above
				->setFormTypeOption('choice_value', static fn (?PerfProfile $profile): ?string => $profile?->value)
				// no empty option: the column is nullable only so that an existing installation
				// upgrades into it, and getPerfProfile() answers that null as Web - so there is
				// nothing for a blank choice to mean. EasyAdmin adds the placeholder on its own,
				// so saying `required` is not enough to be rid of it
				->setRequired(true)
				->setFormTypeOption('placeholder', false)
				->setHelp('A worker row drops Apdex and p95, which measure how long somebody waited, and shows a regression count against this project\'s own baseline plus the rate it is running at.'),
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
