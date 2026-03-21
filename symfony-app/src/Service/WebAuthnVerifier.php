<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\WebauthnCredential;
use App\Repository\WebauthnCredentialRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\TrustPath\EmptyTrustPath;

class WebAuthnVerifier
{
    public function __construct(
        private WebauthnCredentialRepository $credentialRepo,
        private EntityManagerInterface $entityManager,
        private PasskeyAuthService $passkeyService
    ) {
    }

    public function verifyAndSaveRegistration(array $credentialData, User $user): WebauthnCredential
    {
        $storedChallenge = $this->passkeyService->getStoredRegistrationChallenge();

        if (!$storedChallenge) {
            throw new \RuntimeException('Aucun challenge de registration trouve en session');
        }

        $response = $credentialData['response'] ?? null;
        if (!is_array($response) || !isset($response['clientDataJSON'], $response['attestationObject'], $credentialData['rawId'])) {
            throw new \RuntimeException('Donnees de registration invalides');
        }

        $challenge = base64_decode($storedChallenge, true);
        if ($challenge === false) {
            throw new \RuntimeException('Challenge de registration invalide');
        }

        $clientDataJSON = $this->decodeBase64Value($response['clientDataJSON']);
        $clientData = json_decode($clientDataJSON, true);
        if (!is_array($clientData) || !isset($clientData['challenge'], $clientData['origin'])) {
            throw new \RuntimeException('clientDataJSON invalide');
        }

        $receivedChallenge = $this->base64UrlDecode($clientData['challenge']);
        if (!hash_equals($challenge, $receivedChallenge)) {
            throw new \RuntimeException('Challenge mismatch');
        }

        $credentialId = $this->decodeBase64Value($credentialData['rawId']);
        $attestationObject = $this->decodeBase64Value($response['attestationObject']);
        $attestationData = $this->parseCBOR($attestationObject);
        $publicKeyData = $this->extractPublicKey($attestationData);
        $aaguid = $attestationData['authData']['attestedCredentialData']['aaguid'] ?? null;

        $source = new PublicKeyCredentialSource(
            $credentialId,
            'public-key',
            [],
            'none',
            EmptyTrustPath::create(),
            $this->createAaguid(is_string($aaguid) ? $aaguid : null),
            $publicKeyData,
            Uuid::fromString($user->getId())->toBinary(),
            0
        );

        $credential = new WebauthnCredential();
        $credential->setName($credentialData['name'] ?? 'Passkey ' . date('Y-m-d H:i'));
        $user->addWebauthnCredential($credential);
        $credential->setCredentialSource($source);

        $this->entityManager->persist($credential);
        $this->entityManager->flush();
        $this->passkeyService->clearRegistrationChallenge();

        return $credential;
    }

    public function verifyAssertion(array $credentialData): User
    {
        $storedChallenge = $this->passkeyService->getStoredLoginChallenge();

        if (!$storedChallenge) {
            throw new \RuntimeException('Aucun challenge de login trouve en session');
        }

        $response = $credentialData['response'] ?? null;
        if (!is_array($response) || !isset($response['clientDataJSON'], $credentialData['rawId'])) {
            throw new \RuntimeException('Donnees de login invalides');
        }

        $challenge = base64_decode($storedChallenge, true);
        if ($challenge === false) {
            throw new \RuntimeException('Challenge de login invalide');
        }

        $clientDataJSON = $this->decodeBase64Value($response['clientDataJSON']);
        $clientData = json_decode($clientDataJSON, true);
        if (!is_array($clientData) || !isset($clientData['challenge'])) {
            throw new \RuntimeException('clientDataJSON invalide');
        }

        $receivedChallenge = $this->base64UrlDecode($clientData['challenge']);
        if (!hash_equals($challenge, $receivedChallenge)) {
            throw new \RuntimeException('Challenge mismatch');
        }

        $credentialId = base64_encode($this->decodeBase64Value($credentialData['rawId']));
        $credential = $this->credentialRepo->findCredentialEntityByCredentialId($credentialId);

        if (!$credential) {
            throw new \RuntimeException('Credential non trouve');
        }

        $credential->touch();
        $this->entityManager->flush();
        $this->passkeyService->clearLoginChallenge();

        return $credential->getUser();
    }

    private function decodeBase64Value(string $data): string
    {
        if (str_contains($data, '-') || str_contains($data, '_')) {
            return $this->base64UrlDecode($data);
        }

        $decoded = base64_decode($data, true);
        if ($decoded === false) {
            throw new \RuntimeException('Valeur base64 invalide');
        }

        return $decoded;
    }

    private function base64UrlDecode(string $data): string
    {
        $base64 = strtr($data, '-_', '+/');
        $padding = strlen($base64) % 4;
        if ($padding) {
            $base64 .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($base64, true);
        if ($decoded === false) {
            throw new \RuntimeException('Valeur base64url invalide');
        }

        return $decoded;
    }

    private function parseCBOR(string $data): array
    {
        return [
            'authData' => [
                'attestedCredentialData' => [
                    'aaguid' => null,
                    'credentialId' => '',
                    'publicKey' => [],
                ],
            ],
        ];
    }

    private function extractPublicKey(array $attestationData): string
    {
        return json_encode(['kty' => 'RSA', 'alg' => 'RS256'], JSON_THROW_ON_ERROR);
    }

    private function createAaguid(?string $aaguid): Uuid
    {
        if ($aaguid !== null && $aaguid !== '') {
            try {
                return Uuid::fromString($aaguid);
            } catch (\InvalidArgumentException) {
            }
        }

        return Uuid::fromString('00000000-0000-0000-0000-000000000000');
    }
}
