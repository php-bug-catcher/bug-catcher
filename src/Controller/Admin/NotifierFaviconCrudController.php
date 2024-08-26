<?php

namespace BugCatcher\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use BugCatcher\Entity\NotifierFavicon;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class NotifierFaviconCrudController extends NotifierCrudController
{
	public static function getEntityFqcn(): string {
		return NotifierFavicon::class;
	}

}
