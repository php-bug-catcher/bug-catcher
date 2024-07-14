<?php

namespace PhpSentinel\BugCatcher\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use PhpSentinel\BugCatcher\Entity\Project;
use PhpSentinel\BugCatcher\Entity\RecordLogWithholder;
use PhpSentinel\BugCatcher\Form\NotifierType;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class RecordWithholderCrudController extends AbstractCrudController {
	public static function getEntityFqcn(): string {
		return RecordLogWithholder::class;
	}

	public static function getSubscribedServices(): array {
		$services                               = parent::getSubscribedServices();
		$services[ParameterBagInterface::class] = ParameterBagInterface::class;

		return $services;
	}

	public function configureCrud(Crud $crud): Crud {
		return parent::configureCrud($crud)
			->setEntityLabelInPlural("Withholders")
			->setEntityLabelInSingular("Withholder");
	}

	public function configureActions(Actions $actions): Actions {
		return parent::configureActions($actions);
	}

	public function configureFields(string $pageName): iterable {

		return [
			TextField::new('name'),
			TextField::new('regex'),
			NumberField::new("threshold")
				->setHelp("The number of times the regex must be matched within the threshold interval to trigger a notification"),
			NumberField::new("thresholdInterval")
				->setHelp("The time interval in seconds over which the threshold is calculated"),
			AssociationField::new('project'),
		];
	}


}
