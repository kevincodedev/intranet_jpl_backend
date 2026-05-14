<?php

namespace App\EventListener;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Security;

class DatabaseActivitySubscriber implements EventSubscriber
{
    private $security;
    private $requestStack;
    private $auditLogger;

    public function __construct(Security $security, RequestStack $requestStack, \App\Service\AuditLogger $auditLogger)
    {
        $this->security = $security;
        $this->requestStack = $requestStack;
        $this->auditLogger = $auditLogger;
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::postUpdate,
            Events::postRemove,
        ];
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->logActivity('CREATE', $args);
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->logActivity('EDIT', $args);
    }

    public function postRemove(LifecycleEventArgs $args): void
    {
        $this->logActivity('DELETE', $args);
    }

    private function logActivity(string $action, LifecycleEventArgs $args): void
    {
        if ($this->auditLogger->isMuted()) {
            return;
        }

        $entity = $args->getObject();

        // Skip logging for certain entities to avoid noise or infinite loops
        if (
            $entity instanceof AuditLog ||
            $entity instanceof \App\Entity\RefreshToken
        ) {
            return;
        }

        $entityManager = $args->getObjectManager();
        $user = $this->security->getUser();
        $userEmail = $user instanceof User ? $user->getEmail() : 'anonymous';

        $request = $this->requestStack->getCurrentRequest();
        $ip = $request ? $request->getClientIp() : null;

        $log = new AuditLog();
        $log->setUserEmail($userEmail);
        $log->setAction($action);
        
        $log->setEntityName(get_class($entity));
        
        $log->setEntityId(method_exists($entity, 'getId') ? (string)$entity->getId() : null);
        $log->setIpAddress($ip);

        // Capture details for EDIT actions (which fields changed)
        if ($action === 'EDIT' && $entityManager instanceof EntityManagerInterface) {
            $uow = $entityManager->getUnitOfWork();
            $changes = $uow->getEntityChangeSet($entity);
            
            // Detect Soft Delete: if 'deletedAt' changed from null to something else
            if (isset($changes['deletedAt']) && $changes['deletedAt'][0] === null && $changes['deletedAt'][1] !== null) {
                $action = 'DELETE';
            }

            // Detect Recovery: if 'deletedAt' changed from something else back to null
            if (isset($changes['deletedAt']) && $changes['deletedAt'][0] !== null && $changes['deletedAt'][1] === null) {
                $action = 'RECOVER';
            }
            
            $log->setAction($action);
            $log->setDetails($changes);
        }

        // Capture state for CREATE, DELETE, and RECOVER actions
        if ($action === 'CREATE' || $action === 'DELETE' || $action === 'RECOVER') {
            $data = $log->getDetails() ?: [];
            
            // For CREATE, let's capture ALL current field values
            if ($action === 'CREATE' && $entityManager instanceof EntityManagerInterface) {
                $meta = $entityManager->getClassMetadata(get_class($entity));
                foreach ($meta->getFieldNames() as $fieldName) {
                    $getter = 'get' . ucfirst($fieldName);
                    if (method_exists($entity, $getter)) {
                        $data[$fieldName] = $entity->$getter();
                    }
                }
            } else {
                // For DELETE/RECOVER, keep the basic identifying fields
                if (method_exists($entity, 'getEmail')) $data['email'] = $entity->getEmail();
                if (method_exists($entity, 'getName')) $data['name'] = $entity->getName();
                if (method_exists($entity, 'getNombre')) $data['nombre'] = $entity->getNombre();
                if (method_exists($entity, 'getTitle')) $data['title'] = $entity->getTitle();
            }
            
            $log->setDetails($data);
        }

        $entityManager->persist($log);
        // Important: We need to use a separate flush to avoid recursion issues in some Doctrine versions,
        // but in postPersist/postUpdate, the transaction is often still open.
        // A common trick is to use a dedicated "logging" EntityManager or just flush the log entity specifically.
        $entityManager->flush($log);
    }
}
