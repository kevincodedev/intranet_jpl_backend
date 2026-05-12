<?php

namespace App\EventListener;

use App\Service\AuditLogger;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Symfony\Component\HttpFoundation\JsonResponse;

class AuthenticationFailureListener
{
    private $auditLogger;

    public function __construct(AuditLogger $auditLogger)
    {
        $this->auditLogger = $auditLogger;
    }

    /**
     * @param AuthenticationFailureEvent $event
     */
    public function onAuthenticationFailureResponse(AuthenticationFailureEvent $event)
    {
        // Try to get the email from the request if possible
        $request = \Symfony\Component\HttpFoundation\Request::createFromGlobals();
        $data = json_decode($request->getContent(), true);
        $attemptedUser = $data['email'] ?? 'unknown';

        $this->auditLogger->log('LOGIN_FAILED', 'App\Entity\User', $attemptedUser, [
            'message' => 'Intento de inicio de sesión fallido',
            'error' => $event->getException()->getMessageKey()
        ]);
    }
}
