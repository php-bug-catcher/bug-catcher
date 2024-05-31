<?php

namespace PhpSentinel\BugCatcher\Security\Voter;

use PhpSentinel\BugCatcher\Entity\Role;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class RoleVoter extends Voter {


	public function __construct(private readonly AccessDecisionManagerInterface $accessDecision) {}

	protected function supports(string $attribute, mixed $subject): bool {
		return $attribute == "role" && $subject instanceof Role;
	}

	protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool {
		$user = $token->getUser();
		assert($subject instanceof Role);

		// if the user is anonymous, do not grant access
		if (!$user instanceof UserInterface) {
			return false;
		}

		return $this->accessDecision->decide($token, [$subject->value]);
	}
}
