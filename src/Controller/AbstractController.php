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
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as SymfonyAbstractController;
use Symfony\Component\HttpFoundation\RequestStack;

class AbstractController extends SymfonyAbstractController {


	public function __construct(
		protected readonly RequestStack $requestStack
	) {}

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

	protected function getClient(): Client {
		$request = $this->requestStack->getMainRequest();
		$client  = $request->attributes->get("client");
		assert($client instanceof Client);

		return $client;
	}

}