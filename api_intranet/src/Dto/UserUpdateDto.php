<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

//Receives and validates the json
class UserUpdateDto
{
    /**
     * The ranked roles that are mutually exclusive.
     * Only one of these should be stored on a user at a time.
     */
    private const TIERED_ROLES = ['ROLE_USER', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'];

    /** @Assert\Email */
    public $email;

    /** @Assert\Type("array") */
    public $roles;

    public $password;

    /**
     * When a tiered role is being set, strip other tiered roles from the current
     * user roles but keep non-tiered ones (e.g. ROLE_EDITOR).
     */
    private function resolveRoles(array $currentRoles, array $newRoles): array
    {
        $incomingTiered = array_intersect($newRoles, self::TIERED_ROLES);

        if (empty($incomingTiered)) {
            // No tiered role in the new set — just merge with existing
            return array_values(array_unique(array_merge($currentRoles, $newRoles)));
        }

        // Strip all existing tiered roles, keep non-tiered ones (e.g. ROLE_EDITOR)
        $preserved = array_filter($currentRoles, fn($r) => !in_array($r, self::TIERED_ROLES));

        // Merge preserved roles with the incoming roles (which already contain the new tiered role)
        return array_values(array_unique(array_merge(array_values($preserved), $newRoles)));
    }

    public function updateEntity($user, $encoder, $canChangeRoles): void
    {
        if ($this->email) {
            $user->setEmail($this->email);
        }

        if ($this->password) {
            $user->setPassword($encoder->encodePassword($user, $this->password));
        }

        if ($this->roles && $canChangeRoles) {
            $resolved = $this->resolveRoles($user->getRoles(), $this->roles);
            $user->setRoles($resolved);
        }
    }
}

