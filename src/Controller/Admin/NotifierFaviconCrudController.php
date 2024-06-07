<?php

namespace PhpSentinel\BugCatcher\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use PhpSentinel\BugCatcher\Entity\NotifierFavicon;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class NotifierFaviconCrudController extends NotifierCrudController {
	public static function getEntityFqcn(): string {
		return NotifierFavicon::class;
	}

	public function configureFields(string $pageName): iterable {
		$fields = iterator_to_array(parent::configureFields($pageName));

		return array_merge($fields, [
			NumberField::new('importance')->setColumns(4),
		]);
	}
}
