<?php

namespace App\Controller\Admin;

use App\Entity\Project;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
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

	public function configureActions(Actions $actions): Actions {
		return parent::configureActions($actions)
			->remove(Crud::PAGE_INDEX, Action::DELETE)
			->remove(Crud::PAGE_DETAIL, Action::DELETE);
	}


	public function configureFields(string $pageName): iterable {
		return [
			TextField::new('code'),
			TextField::new('name'),
			BooleanField::new("enabled"),
			ChoiceField::new("pingCollector")->setChoices([
				"None" => "none", 'http' => 'http', 'messenger' => 'messenger',
			])->hideOnIndex(),
			UrlField::new("url"),
			TextField::new('dbConnection')->hideOnIndex(),
			AssociationField::new('users')->setColumns(6)->onlyOnForms(),
		];
	}

}
