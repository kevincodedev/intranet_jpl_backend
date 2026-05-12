<?php

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;

class AuditLogger
{
    private $em;
    private $security;
    private $requestStack;

    public function __construct(EntityManagerInterface $em, Security $security, RequestStack $requestStack)
    {
        $this->em = $em;
        $this->security = $security;
        $this->requestStack = $requestStack;
    }

    /**
     * Records an audit log entry.
     * 
     * @param string $action CREATE, EDIT, DELETE, LOGIN, LOGOUT
     * @param string|null $entityName Name of the entity affected
     * @param string|null $entityId ID of the entity affected
     * @param array $details JSON serializable details about the change
     */
    public function log(string $action, ?string $entityName = null, ?string $entityId = null, array $details = []): void
    {
        $user = $this->security->getUser();
        $userEmail = $user instanceof User ? $user->getEmail() : 'anonymous';

        $request = $this->requestStack->getCurrentRequest();
        $ip = $request ? $request->getClientIp() : null;

        $log = new AuditLog();
        $log->setUserEmail($userEmail);
        $log->setAction($action);
        $log->setEntityName($entityName);
        $log->setEntityId($entityId);
        $log->setDetails($details);
        $log->setIpAddress($ip);

        // We use a separate connection or immediate flush if needed, 
        // but normally we can just persist and let the main flush handle it.
        // However, for Doctrine listeners, we must be careful with flushing.
        $this->em->persist($log);
        $this->em->flush();
    }
}
