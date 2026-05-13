<?php

namespace App\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

/**
 * Clears expired JWT refresh tokens from the database once per day after 2 AM.
 * Runs on kernel.terminate so it executes AFTER the response is sent to the client,
 * meaning it has zero impact on response time.
 *
 * Uses a lock file to ensure cleanup only runs once per day.
 */
class TokenCleanupListener
{
    private EntityManagerInterface $em;
    private string $lockFile;

    public function __construct(EntityManagerInterface $em, string $kernelProjectDir)
    {
        $this->em = $em;
        // Store the lock file in var/ so it persists across requests but is ignored by git
        $this->lockFile = $kernelProjectDir . '/var/token_cleanup.lock';
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        $now = new \DateTime();

        // Only run at or after 2 AM
        if ((int) $now->format('H') < 2) {
            return;
        }

        $today = $now->format('Y-m-d');

        // Check if cleanup already ran today
        if (file_exists($this->lockFile) && file_get_contents($this->lockFile) === $today) {
            return;
        }

        // Delete all tokens that expired before now via direct DQL query
        $this->em->createQuery('DELETE FROM App\Entity\RefreshToken r WHERE r.valid < :now')
            ->setParameter('now', new \DateTime())
            ->execute();

        // Write today's date as the lock so it won't run again until tomorrow
        file_put_contents($this->lockFile, $today);
    }
}
