<?php

namespace PhpSentinel\BugCatcher\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use PhpSentinel\BugCatcher\Entity\NotifierFavicon;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class NotifierFaviconCrudController extends NotifierCrudController {
	public static function getEntityFqcn(): string {
		return NotifierFavicon::class;
	}

	public function configureFields(string $pageName): iterable {
		$parameterBag = $this->container->get(ParameterBagInterface::class);
		$components   = $parameterBag->get('favicon_components');
		if (array_is_list($components)) {
			$components = array_combine($components, $components);
		}
		$fields = iterator_to_array(parent::configureFields($pageName));

		return array_merge($fields, [
			FormField::addFieldset('Others'),
			NumberField::new('threshold')->setColumns(4),
			ChoiceField::new("component")->setChoices($components)->setRequired(true),
		]);
	}

	public static function getSubscribedServices(): array {
		$services                               = parent::getSubscribedServices();
		$services[ParameterBagInterface::class] = ParameterBagInterface::class;

		return $services;
	}

}
