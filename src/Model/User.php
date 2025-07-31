<?php

declare(strict_types=1);

namespace App\Model;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * This is a authenticated user of the platform, a person.
 */
class User implements UserInterface
{
    /**
     * @var non-empty-string
     */
    public string $id;

    /**
     * @param non-empty-string $id
     */
    public function __construct(string $id)
    {
        $this->id = $id;
    }

    #[\Override]
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    #[\Override]
    public function eraseCredentials(): void
    {
    }

    #[\Override]
    public function getUserIdentifier(): string
    {
        return $this->id;
    }
}
