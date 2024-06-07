<?php

namespace PhpSentinel\BugCatcher\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use PhpSentinel\BugCatcher\Entity\NotifierEmail;

abstract class NotifierCrudController extends AbstractCrudController {

	public function configureFields(string $pageName): iterable {
		return [
			TextField::new('name')->setColumns(6),
			ChoiceField::new('minimalImportance')->setColumns(6),
			AssociationField::new('projects')->setColumns(12)->onlyOnForms(),
		];
	}

}
