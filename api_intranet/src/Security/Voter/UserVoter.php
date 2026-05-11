<?php

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;

class UserVoter extends Voter
{
    public const EDIT = 'USER_EDIT';
    public const DELETE = 'USER_DELETE';
    public const EDIT_ROLES = 'USER_EDIT_ROLES';

    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::DELETE, self::EDIT_ROLES])
            && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        // if the user is not logged in, deny access
        if (!$user instanceof UserInterface) {
            return false;
        }

        /** @var User $targetUser */
        $targetUser = $subject;

        // SUPER_ADMIN can do anything. Checking the roles array directly from the user object 
        // prevents infinite loops or false evaluations within the Security component context.
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles())) {
            return true;
        }

        switch ($attribute) {
            case self::EDIT:
                return $this->canEdit($targetUser, $user);
            case self::DELETE:
                return $this->canDelete($targetUser, $user);
            case self::EDIT_ROLES:
                return $this->canEditRoles($targetUser, $user);
        }

        return false;
    }

    private function canEdit(User $targetUser, User $authenticatedUser): bool
    {
        // User can edit themselves
        if ($authenticatedUser->getId() === $targetUser->getId()) {
            return true;
        }

        // Super Admin can edit anyone (already checked at the top of the voter)
        if (in_array('ROLE_SUPER_ADMIN', $authenticatedUser->getRoles())) {
            return true;
        }

        // Regular Admin can edit users, but NOT other Admins or Super Admins
        if ($this->security->isGranted('ROLE_ADMIN')) {
            $targetRoles = $targetUser->getRoles();
            return !in_array('ROLE_ADMIN', $targetRoles) && !in_array('ROLE_SUPER_ADMIN', $targetRoles);
        }

        return false;
    }

    private function canEditRoles(User $targetUser, User $authenticatedUser): bool
    {
        // Only ADMIN or above can edit roles and they can't edit roles of a SUPER_ADMIN
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return !in_array('ROLE_SUPER_ADMIN', $targetUser->getRoles());
        }

        return false;
    }

    private function canDelete(User $targetUser, User $authenticatedUser): bool
    {
        // Cannot delete yourself
        if ($authenticatedUser->getId() === $targetUser->getId()) {
            return false;
        }

        // Super Admin can delete anyone (already checked at the top of the voter)
        if (in_array('ROLE_SUPER_ADMIN', $authenticatedUser->getRoles())) {
            return true;
        }

        // Admin can delete users, but NOT other Admins or Super Admins
        if ($this->security->isGranted('ROLE_ADMIN')) {
            $targetRoles = $targetUser->getRoles();
            return !in_array('ROLE_ADMIN', $targetRoles) && !in_array('ROLE_SUPER_ADMIN', $targetRoles);
        }

        return false;
    }
}
