<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;

class AuthenticationSuccessListener
{
    public function onAuthenticationSuccessResponse(AuthenticationSuccessEvent $event): void
    {
        $data = $event->getData();
        $user = $event->getUser();

        $userData = [
            'email' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
        ];

        if (method_exists($user, 'getId')) {
            $userData['id'] = $user->getId();
        }

        if (method_exists($user, 'isVerified')) {
            $userData['is_verified'] = $user->isVerified();
        }

        $data['user'] = $userData;

        $event->setData($data);
    }
}
