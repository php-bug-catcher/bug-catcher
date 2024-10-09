<?php
/**
 * Created by PhpStorm.
 * User: Jozef Môstka
 * Date: 9. 10. 2024
 * Time: 21:24
 */

namespace BugCatcher\Security;

use BadMethodCallException;
use Serializable;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;

class MultipleLoginToken implements TokenInterface, Serializable
{

    /**
     * @var TokenInterface[]
     */
    private array $tokens = [];

    public function __construct(
        private TokenInterface $currentToken,
        TokenInterface $originalToken
    ) {
        $this->tokens[] = $originalToken;
    }

    public function setToken(TokenInterface $newToken): void
    {
        $this->tokens[] = $this->currentToken;
        $this->tokens = array_unique($this->tokens, SORT_REGULAR);
        $this->currentToken = $newToken;
    }

    public function trySwitchUser(TokenInterface $newToken): bool
    {
        foreach ($this->tokens as $token) {
            if ($token->getUserIdentifier() == $newToken->getUserIdentifier()) {
                $this->tokens[] = $this->currentToken;
                $this->tokens = array_unique($this->tokens, SORT_REGULAR);
                $this->currentToken = $token;
                return true;
            }
        }
        return false;
    }

    public function getUserIdentifiers(): array
    {
        $data = [];
        foreach ($this->tokens as $token) {
            $data[] = $token->getUserIdentifier();
        }
        return $data;
    }

    /**
     * @return TokenInterface[]
     */
    public function getTokens(): array
    {
        return $this->tokens;
    }

    public function __serialize(): array
    {
        $data = $this->tokens;
        $data[] = $this->currentToken;
        return $data;
    }

    public function __unserialize(array $data): void
    {
        $currentToken = array_pop($data);
        $this->currentToken = $currentToken;
        $this->tokens = $data;
    }

    public function __toString(): string
    {
        return $this->currentToken->__toString();
    }

    public function getUserIdentifier(): string
    {
        return $this->currentToken->getUserIdentifier();
    }

    public function getRoleNames(): array
    {
        return $this->currentToken->getRoleNames();
    }

    public function getUser(): ?UserInterface
    {
        return $this->currentToken->getUser();
    }

    public function setUser(UserInterface $user): void
    {
        $this->currentToken->setUser($user);
    }

    public function eraseCredentials(): void
    {
        $this->currentToken->eraseCredentials();
    }

    public function getAttributes(): array
    {
        return $this->currentToken->getAttributes();
    }

    public function setAttributes(array $attributes): void
    {
        $this->currentToken->setAttributes($attributes);
    }

    public function hasAttribute(string $name): bool
    {
        return $this->currentToken->hasAttribute($name);
    }

    public function getAttribute(string $name): mixed
    {
        return $this->currentToken->getAttribute($name);
    }

    public function setAttribute(string $name, mixed $value): void
    {
        $this->currentToken->setAttribute($name, $value);
    }

    public function serialize()
    {
        throw new BadMethodCallException('Cannot serialize ' . __CLASS__);
    }

    public function unserialize(string $serialized)
    {
        $this->__unserialize(unserialize($serialized));
    }


}