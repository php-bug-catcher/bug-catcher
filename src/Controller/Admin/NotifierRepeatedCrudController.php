<?php

namespace PhpSentinel\BugCatcher\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use PhpSentinel\BugCatcher\Entity\NotifierRepeated;

class NotifierRepeatedCrudController extends NotifierCrudController {
	public static function getEntityFqcn(): string {
		return NotifierRepeated::class;
	}


	public function configureFields(string $pageName): iterable {
		$fields = iterator_to_array(parent::configureFields($pageName));

		return array_merge($fields, [
			ChoiceField::new('repeat')->setColumns(8),
			NumberField::new('repeatTime')->setColumns(4),
			ChoiceField::new('clearAt')->setColumns(8),
			NumberField::new('clearTime')->setColumns(4),
		]);
	}

}
