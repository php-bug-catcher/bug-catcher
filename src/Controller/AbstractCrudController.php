<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 31. 8. 2023
 * Time: 20:33
 */
namespace PhpSentinel\BugCatcher\Controller;

use PhpSentinel\BugCatcher\Entity\Client\Client;
use PhpSentinel\BugCatcher\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Registry;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController as EAAbstractCrudController;
use LogicException;

abstract class AbstractCrudController extends EAAbstractCrudController {
	protected function getUser(): ?User {
		$user = parent::getUser();
		if ($user === null) {
			return null;
		}
		if (!$user instanceof User) {
			return throw new LogicException('The user is somehow not the expected UserInterface instance.');
		}

		return $user;
	}

	protected function getRegistry(): Registry {
		return $this->container->get('doctrine');
	}
}