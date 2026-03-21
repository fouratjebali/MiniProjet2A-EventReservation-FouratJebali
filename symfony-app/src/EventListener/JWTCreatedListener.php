<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\HttpFoundation\RequestStack;

class JWTCreatedListener
{
    public function __construct(
        private RequestStack $requestStack
    ) {
    }

    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $payload = $event->getData();
        $user = $event->getUser();

        if (method_exists($user, 'getId')) {
            $payload['id'] = $user->getId();
        }

        $payload['ip'] = $request?->getClientIp();

        $event->setData($payload);
    }
}
