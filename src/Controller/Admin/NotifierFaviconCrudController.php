<?php

namespace PhpSentinel\BugCatcher\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use PhpSentinel\BugCatcher\Entity\NotifierFavicon;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class NotifierFaviconCrudController extends NotifierCrudController {
	public static function getEntityFqcn(): string {
		return NotifierFavicon::class;
	}

}
