<?php

namespace PhpSentinel\BugCatcher\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use PhpSentinel\BugCatcher\Entity\NotifierEmail;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

abstract class NotifierCrudController extends AbstractCrudController {


	public function __construct(
		private readonly array $components
	) {}

	public function configureFields(string $pageName): iterable {
		$parameterBag = $this->container->get(ParameterBagInterface::class);
		$components = $this->components;
		if (array_is_list($components)) {
			$components = array_combine($components, $components);
		}
		return [
			FormField::addFieldset('Basic settings'),
			TextField::new('name')->setColumns(3),
			ChoiceField::new('minimalImportance')->setColumns(3),
			NumberField::new('threshold')->setColumns(3),
			ChoiceField::new("component")->setChoices($components)->setColumns(3)->setRequired(true),
			AssociationField::new('projects')->setColumns(12)->onlyOnForms(),
			FormField::addFieldset('Timing settings'),
			ChoiceField::new('delay')->setColumns(3),
			NumberField::new('delayInterval')->setColumns(3),
			ChoiceField::new('repeat')->setColumns(3),
			NumberField::new('repeatInterval')->setColumns(3),
			ChoiceField::new('clearAt')->setColumns(3),
			NumberField::new('clearInterval')->setColumns(3),
		];
	}

	public static function getSubscribedServices(): array {
		$services                               = parent::getSubscribedServices();
		$services[ParameterBagInterface::class] = ParameterBagInterface::class;

		return $services;
	}


}
