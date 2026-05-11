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

        // Must be authenticated
        if (!$user instanceof UserInterface) {
            error_log("[UserVoter] Denied: user is not authenticated.");
            return false;
        }

        // Token user must be our concrete App\Entity\User to call getId() / getRoles()
        if (!$user instanceof User) {
            error_log("[UserVoter] Denied: token user is not App\\Entity\\User.");
            return false;
        }

        /** @var User $targetUser */
        $targetUser = $subject;

        // Super Admin gets unconditional access for all voter attributes
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles())) {
            error_log("[UserVoter] Granted: {$user->getEmail()} is ROLE_SUPER_ADMIN.");
            return true;
        }

        $result = false;
        switch ($attribute) {
            case self::EDIT:
                $result = $this->canEdit($targetUser, $user);
                break;
            case self::DELETE:
                $result = $this->canDelete($targetUser, $user);
                break;
            case self::EDIT_ROLES:
                $result = $this->canEditRoles($targetUser, $user);
                break;
        }

        if (!$result) {
            error_log("[UserVoter] Denied: '{$attribute}' on target ID={$targetUser->getId()} by user ID={$user->getId()} roles=" . implode(',', $user->getRoles()));
        }

        return $result;
    }

    /**
     * ROLE_USER can only edit themselves.
     * ROLE_ADMIN can edit any non-admin user.
     * ROLE_SUPER_ADMIN is handled above (unconditional grant).
     */
    private function canEdit(User $targetUser, User $authenticatedUser): bool
    {
        // A user can always edit their own profile
        if ($authenticatedUser->getId() === $targetUser->getId()) {
            return true;
        }

        // Admin can edit regular users but NOT other admins or super admins
        $authRoles = $authenticatedUser->getRoles();
        if (in_array('ROLE_ADMIN', $authRoles)) {
            $targetRoles = $targetUser->getRoles();
            return !in_array('ROLE_ADMIN', $targetRoles)
                && !in_array('ROLE_SUPER_ADMIN', $targetRoles);
        }

        return false;
    }

    /**
     * Only admins can edit roles.
     * Admins cannot promote to / edit a Super Admin's roles.
     * ROLE_SUPER_ADMIN is handled above (unconditional grant).
     */
    private function canEditRoles(User $targetUser, User $authenticatedUser): bool
    {
        $authRoles = $authenticatedUser->getRoles();
        if (in_array('ROLE_ADMIN', $authRoles)) {
            // Admin cannot edit roles of another Admin or Super Admin
            return !in_array('ROLE_SUPER_ADMIN', $targetUser->getRoles());
        }

        return false;
    }

    /**
     * You cannot delete yourself.
     * ROLE_ADMIN can delete regular users only.
     * ROLE_SUPER_ADMIN is handled above (unconditional grant).
     */
    private function canDelete(User $targetUser, User $authenticatedUser): bool
    {
        // Cannot delete yourself
        if ($authenticatedUser->getId() === $targetUser->getId()) {
            error_log("[UserVoter] Denied: user tried to delete themselves.");
            return false;
        }

        // Admin can delete regular users but NOT other admins or super admins
        $authRoles = $authenticatedUser->getRoles();
        if (in_array('ROLE_ADMIN', $authRoles)) {
            $targetRoles = $targetUser->getRoles();
            return !in_array('ROLE_ADMIN', $targetRoles)
                && !in_array('ROLE_SUPER_ADMIN', $targetRoles);
        }

        return false;
    }
}
