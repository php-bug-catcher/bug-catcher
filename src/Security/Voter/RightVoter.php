<?php

namespace App\Security\Voter;

use App\Entity\Role;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class RightVoter extends Voter {


	protected function supports(string $attribute, mixed $subject): bool {
		return $subject === null && str_starts_with($attribute, "RIGHT_");
	}

	protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool {

		$user = $token->getUser();
		assert($user instanceof UserInterface);

		return in_array($attribute, $user->getRoles());
	}
}
