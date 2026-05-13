<?php

namespace App\EventListener;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AuditLogger;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Symfony\Component\HttpFoundation\RequestStack;

class AuthenticationFailureListener
{
    private $auditLogger;
    private $userRepository;
    private $requestStack;

    public function __construct(AuditLogger $auditLogger, UserRepository $userRepository, RequestStack $requestStack)
    {
        $this->auditLogger = $auditLogger;
        $this->userRepository = $userRepository;
        $this->requestStack = $requestStack;
    }

    /**
     * @param AuthenticationFailureEvent $event
     */
    public function onAuthenticationFailureResponse(AuthenticationFailureEvent $event)
    {
        $request = $this->requestStack->getCurrentRequest();
        $attemptedUser = 'unknown';
        $entityId = 'unknown';

        if ($request) {
            $data = json_decode($request->getContent(), true);
            $attemptedUser = $data['email'] ?? 'unknown';
            $entityId = $attemptedUser;

            // Try to find the user to get their ID
            if ($attemptedUser !== 'unknown') {
                $user = $this->userRepository->findOneBy(['email' => $attemptedUser]);
                if ($user instanceof User) {
                    $entityId = (string) $user->getId();
                }
            }
        }

        $this->auditLogger->log('LOGIN_FAILED', User::class, $entityId, [
            'attempted_email' => $attemptedUser,
            'message' => 'Intento de inicio de sesión fallido',
            'error' => $event->getException()->getMessageKey()
        ]);
    }
}
