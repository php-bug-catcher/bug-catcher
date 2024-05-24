<?php

namespace App\Controller\Admin;

use App\Entity\Project;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

class ProjectCrudController extends AbstractCrudController {
	public static function getEntityFqcn(): string {
		return Project::class;
	}


	public function configureFields(string $pageName): iterable {
		return [
			TextField::new('code'),
			TextField::new('name'),
			BooleanField::new("enabled"),
			ChoiceField::new("pingCollector")->setChoices([
				"None" => null, 'http' => 'http', 'messenger' => 'messenger',
			])->hideOnIndex(),
			UrlField::new("url"),
			TextField::new('dbConnection')->hideOnIndex(),
			AssociationField::new('users')->setColumns(6)->onlyOnForms(),
		];
	}

}
