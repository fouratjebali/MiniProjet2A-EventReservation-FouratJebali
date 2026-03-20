<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\WebauthnCredentialRepository;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

class PasskeyAuthService
{
    private const CHALLENGE_LENGTH = 32;
    private const TIMEOUT = 60000; // 60 secondes

    public function __construct(
        private RequestStack $requestStack,
        private WebauthnCredentialRepository $credentialRepo,
        private string $rpName,
        private string $rpId
    ) {}

    public function getRegistrationOptions(User $user): PublicKeyCredentialCreationOptions
    {
        $userEntity = new PublicKeyCredentialUserEntity(
            $user->getEmail(),
            Uuid::fromString($user->getId())->toBinary(),
            $user->getEmail()
        );

        $rpEntity = new PublicKeyCredentialRpEntity(
            $this->rpName,
            $this->rpId
        );

        $challenge = random_bytes(self::CHALLENGE_LENGTH);

        $pubKeyCredParams = [
            new PublicKeyCredentialParameters('public-key', -7),  // ES256
            new PublicKeyCredentialParameters('public-key', -257), // RS256
        ];

        $authenticatorSelection = new AuthenticatorSelectionCriteria(
            null,
            'preferred',
            'preferred'
        );

        $excludeCredentials = array_map(
            fn($cred) => new PublicKeyCredentialDescriptor(
                'public-key',
                base64_decode($cred->getCredentialId())
            ),
            $this->credentialRepo->findAllForUser($user)
        );

        $options = new PublicKeyCredentialCreationOptions(
            $rpEntity,
            $userEntity,
            $challenge,
            $pubKeyCredParams,
            $authenticatorSelection,
            'none',
            $excludeCredentials,
            self::TIMEOUT,
            null
        );

        $session = $this->requestStack->getSession();
        $session->set('webauthn_registration_challenge', base64_encode($challenge));
        $session->set('webauthn_registration_user_id', $user->getId());

        return $options;
    }

    public function getLoginOptions(): PublicKeyCredentialRequestOptions
    {
        $challenge = random_bytes(self::CHALLENGE_LENGTH);

        $options = new PublicKeyCredentialRequestOptions(
            $challenge,
            $this->rpId,
            [],
            'preferred',
            self::TIMEOUT,
            null
        );

        $session = $this->requestStack->getSession();
        $session->set('webauthn_login_challenge', base64_encode($challenge));

        return $options;
    }

    public function getStoredRegistrationChallenge(): ?string
    {
        $session = $this->requestStack->getSession();
        return $session->get('webauthn_registration_challenge');
    }

    public function getStoredLoginChallenge(): ?string
    {
        $session = $this->requestStack->getSession();
        return $session->get('webauthn_login_challenge');
    }

    public function clearRegistrationChallenge(): void
    {
        $session = $this->requestStack->getSession();
        $session->remove('webauthn_registration_challenge');
        $session->remove('webauthn_registration_user_id');
    }

    public function clearLoginChallenge(): void
    {
        $session = $this->requestStack->getSession();
        $session->remove('webauthn_login_challenge');
    }
}
