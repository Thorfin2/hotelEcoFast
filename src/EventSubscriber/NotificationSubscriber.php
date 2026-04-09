<?php

namespace App\EventSubscriber;

use App\Service\NotificationService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sends queued notification emails AFTER the HTTP response has been sent.
 * This prevents email sending from blocking the user's page load.
 */
class NotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'onKernelTerminate',
        ];
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        if ($this->notificationService->hasPendingEmails()) {
            $this->notificationService->flushQueue();
        }
    }
}
