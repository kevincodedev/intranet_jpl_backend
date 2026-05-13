<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\AuditLogger;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Security\Core\Security;

/**
 * Handles audit logging for password check actions without polluting the controller.
 */
class PasswordCheckAuditListener
{
    private AuditLogger $auditLogger;
    private Security $security;

    public function __construct(AuditLogger $auditLogger, Security $security)
    {
        $this->auditLogger = $auditLogger;
        $this->security = $security;
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();

        // Check if the route is the password check route
        if ($request->attributes->get('_route') !== 'api_check_password') {
            return;
        }

        $user = $this->security->getUser();
        $statusCode = $response->getStatusCode();

        if ($statusCode === 200) {
            // Password match success
            $this->auditLogger->log('PASSWORD_CHECK_SUCCESS', User::class, $user instanceof User ? (string) $user->getId() : null, [
                'email' => $user instanceof User ? $user->getEmail() : 'unknown'
            ]);
        } elseif ($statusCode === 400 || $statusCode === 401) {
            // Password match failure or unauthenticated attempt
            $action = ($statusCode === 401) ? 'PASSWORD_CHECK_UNAUTHENTICATED' : 'PASSWORD_CHECK_FAILED';
            
            $details = [];
            if ($user instanceof User) {
                $details['email'] = $user->getEmail();
            }
            
            // Try to extract error message from response to log the reason
            $content = json_decode($response->getContent(), true);
            if ($content && isset($content['error'])) {
                $details['reason'] = $content['error'];
            }

            $this->auditLogger->log($action, User::class, $user instanceof User ? (string) $user->getId() : null, $details);
        }
    }
}
