<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\AuditLogger;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Core\User\UserInterface;

class JWTSessionListener
{
    private $auditLogger;

    public function __construct(AuditLogger $auditLogger)
    {
        $this->auditLogger = $auditLogger;
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $this->auditLogger->log('LOGIN', User::class, (string) $user->getId(), [
            'method' => 'JWT',
            'message' => 'Token issued successfully',
            'email' => $user->getEmail()
        ]);
    }
}
