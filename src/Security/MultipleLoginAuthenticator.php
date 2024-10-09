<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 9. 10. 2024
 * Time: 21:14
 */

namespace BugCatcher\Security;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

#[AsDecorator('security.authenticator.form_login.main')]
class MultipleLoginAuthenticator implements AuthenticationEntryPointInterface, InteractiveAuthenticatorInterface
{
    public function __construct(
        #[AutowireDecorated]
        private readonly FormLoginAuthenticator $inner,
        private readonly Security $security
    ) {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return $this->inner->start($request, $authException);
    }

    public function supports(Request $request): ?bool
    {
        return ($request->query->has("_switch_user"))
            || $this->inner->supports($request);
    }

    public function authenticate(Request $request): Passport
    {
        $currentToken = $this->security->getToken();
        if ($currentToken instanceof MultipleLoginToken && $request->query->has("_switch_user")) {
            $identifier = $request->query->get("_switch_user");
            $userBadge = new UserBadge($identifier, function () use ($currentToken, $identifier) {
                foreach ($currentToken->getTokens() as $token) {
                    if ($token->getUserIdentifier() == $identifier) {
                        return $token->getUser();
                    }
                }
                return null;
            });

            return new SelfValidatingPassport($userBadge);
        }
        return $this->inner->authenticate($request);
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $currentToken = $this->security->getToken();
        $newToken = $this->inner->createToken($passport, $firewallName);
        if ($currentToken === null) {
            return $newToken;
        }
        if ($currentToken instanceof MultipleLoginToken) {
            if ($currentToken->trySwitchUser($newToken)) {
                return $currentToken;
            }
            $currentToken->setToken($newToken);
            return $currentToken;
        }
        return new MultipleLoginToken($newToken, $currentToken);


    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return $this->inner->onAuthenticationSuccess($request, $token, $firewallName);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return $this->inner->onAuthenticationFailure($request, $exception);
    }

    public function isInteractive(): bool
    {
        return $this->inner->isInteractive();
    }
}