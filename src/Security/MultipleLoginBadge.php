<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 9. 10. 2024
 * Time: 22:11
 */

namespace BugCatcher\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class MultipleLoginBadge extends UserBadge
{
    public function __construct(public readonly TokenInterface $token)
    {
        parent::__construct($token->getUserIdentifier(), function () {
            return $this->token->getUser();
        });
    }
}