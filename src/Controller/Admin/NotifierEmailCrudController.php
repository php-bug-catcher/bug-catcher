<?php

namespace BugCatcher\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use BugCatcher\Entity\NotifierEmail;

class NotifierEmailCrudController extends NotifierCrudController {
	public static function getEntityFqcn(): string {
		return NotifierEmail::class;
	}

	/*
	public function configureFields(string $pageName): iterable
	{
		return [
			IdField::new('id'),
			TextField::new('title'),
			TextEditorField::new('description'),
		];
	}
	*/
}
