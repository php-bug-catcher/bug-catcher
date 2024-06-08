<?php

namespace PhpSentinel\BugCatcher\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use PhpSentinel\BugCatcher\Entity\NotifierFavicon;
use PhpSentinel\BugCatcher\Entity\NotifierSound;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Image;

class NotifierSoundCrudController extends NotifierCrudController {
	public static function getEntityFqcn(): string {
		return NotifierSound::class;
	}

	public function configureFields(string $pageName): iterable {
		$fields = iterator_to_array(parent::configureFields($pageName));

		return array_merge($fields, [
			FormField::addFieldset('Others'),
			ImageField::new('file')
				->setBasePath('/uploads/sound/')
				->setUploadDir('public/uploads/sound/')
				->onlyOnForms()
				->setRequired(false)
				->setFileConstraints(new File(
					maxSize: '100k',
					mimeTypes: [
						'audio/mpeg',
						'audio/wav',
						'audio/ogg',
					],
					mimeTypesMessage: 'Please upload a valid audio file'
				)),
		]);
	}
}
