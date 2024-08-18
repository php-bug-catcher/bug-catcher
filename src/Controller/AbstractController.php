<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 31. 8. 2023
 * Time: 20:33
 */
namespace BugCatcher\Controller;

use LogicException;
use BugCatcher\Entity\Client\Client;
use BugCatcher\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as SymfonyAbstractController;
use Symfony\Component\HttpFoundation\RequestStack;

class AbstractController extends SymfonyAbstractController {

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


}