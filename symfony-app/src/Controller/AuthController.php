<?php

namespace App\Controller;

use App\Entity\Admin;
use App\Entity\User;
use App\Repository\AdminRepository;
use App\Repository\UserRepository;
use App\Security\EmailVerifier;
use App\Service\PasskeyAuthService;
use App\Service\WebAuthnVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

#[Route('/api/auth', name: 'api_auth_')]
class AuthController extends AbstractController
{
    private const REFRESH_TOKEN_TTL = 2592000;

    public function __construct(
        private JWTTokenManagerInterface $jwtManager,
        private RefreshTokenManagerInterface $refreshTokenManager,
        private RefreshTokenGeneratorInterface $refreshTokenGenerator,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private PasskeyAuthService $passkeyService,
        private UserRepository $userRepository,
        private AdminRepository $adminRepository,
        private EmailVerifier $emailVerifier,
        private ValidatorInterface $validator
    ) {
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email'], $data['password'])) {
            return $this->json([
                'error' => 'Email et password requis',
            ], Response::HTTP_BAD_REQUEST);
        }

        $existingUser = $this->userRepository->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            return $this->json([
                'error' => 'Cet email est deja utilise',
            ], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setPassword(
            $this->passwordHasher->hashPassword($user, $data['password'])
        );

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            return $this->json([
                'error' => 'Donnees invalides',
                'details' => (string) $errors,
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        try {
            $this->emailVerifier->sendEmailConfirmation('api_auth_verify_email', $user);
        } catch (\Throwable $exception) {
            $this->entityManager->remove($user);
            $this->entityManager->flush();

            return $this->json([
                'error' => 'Impossible d envoyer l email de verification',
                'message' => $exception->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'success' => true,
            'message' => 'Compte cree. Verifiez votre boite email avant de vous connecter.',
            'verification_sent' => true,
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'is_verified' => $user->isVerified(),
            ],
            'verification_url' => $this->getParameter('kernel.environment') !== 'prod'
                ? $this->emailVerifier->getSignedUrl('api_auth_verify_email', $user)
                : null,
        ], Response::HTTP_CREATED);
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email'], $data['password'])) {
            return $this->json([
                'error' => 'Email et password requis',
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneBy(['email' => $data['email']]);

        if (!$user || !$this->passwordHasher->isPasswordValid($user, $data['password'])) {
            return $this->json([
                'error' => 'Identifiants invalides',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user->isVerified()) {
            return $this->json([
                'error' => 'Veuillez verifier votre adresse email avant de vous connecter',
                'is_verified' => false,
            ], Response::HTTP_FORBIDDEN);
        }

        $jwt = $this->jwtManager->create($user);
        $refreshToken = $this->refreshTokenGenerator->createForUserWithTtl($user, self::REFRESH_TOKEN_TTL);
        $this->refreshTokenManager->save($refreshToken);

        return $this->json([
            'success' => true,
            'token' => $jwt,
            'refresh_token' => $refreshToken->getRefreshToken(),
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'is_verified' => $user->isVerified(),
            ],
        ]);
    }

    #[Route('/admin/login', name: 'admin_login', methods: ['POST'])]
    public function adminLogin(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email'], $data['password'])) {
            return $this->json([
                'error' => 'Email et password requis',
            ], Response::HTTP_BAD_REQUEST);
        }

        $admin = $this->adminRepository->findOneBy(['email' => $data['email']]);

        if (!$admin instanceof Admin || !$this->passwordHasher->isPasswordValid($admin, $data['password'])) {
            return $this->json([
                'error' => 'Identifiants admin invalides',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $jwt = $this->jwtManager->create($admin);

        return $this->json([
            'success' => true,
            'token' => $jwt,
            'user' => [
                'id' => $admin->getId(),
                'email' => $admin->getEmail(),
                'roles' => $admin->getRoles(),
            ],
        ]);
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if ($user instanceof Admin) {
            return $this->json([
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'is_verified' => true,
                'passkeys_count' => 0,
            ]);
        }

        if (!$user instanceof User) {
            return $this->json([
                'error' => 'Non authentifie',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'is_verified' => $user->isVerified(),
            'passkeys_count' => $user->getWebauthnCredentials()->count(),
        ]);
    }

    #[Route('/verify/email', name: 'verify_email', methods: ['GET'])]
    public function verifyUserEmail(Request $request): Response
    {
        $id = $request->query->get('id');
        if ($id === null) {
            return $this->redirect('/?verified=missing');
        }

        $user = $this->userRepository->find((string) $id);
        if (!$user instanceof User) {
            return $this->redirect('/?verified=invalid');
        }

        if ($user->isVerified()) {
            return $this->redirect('/?verified=already');
        }

        try {
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface) {
            return $this->redirect('/?verified=failed');
        }

        return $this->redirect('/?verified=success');
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $refreshToken = $data['refresh_token'] ?? null;

        if ($refreshToken) {
            $token = $this->refreshTokenManager->get($refreshToken);
            if ($token) {
                $this->refreshTokenManager->delete($token);
            }
        }

        return $this->json([
            'success' => true,
            'message' => 'Deconnexion reussie',
        ]);
    }

    #[Route('/passkey/register/options', name: 'passkey_register_options', methods: ['POST'])]
    public function passkeyRegisterOptions(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return $this->json([
                'error' => 'Email requis',
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            $user = new User();
            $user->setEmail($email);
            $user->setPassword(
                $this->passwordHasher->hashPassword($user, bin2hex(random_bytes(32)))
            );

            $errors = $this->validator->validate($user);
            if (count($errors) > 0) {
                return $this->json([
                    'error' => 'Donnees invalides',
                    'details' => (string) $errors,
                ], Response::HTTP_BAD_REQUEST);
            }

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            try {
                $this->emailVerifier->sendEmailConfirmation('api_auth_verify_email', $user);
            } catch (\Throwable) {
            }
        }

        try {
            $options = $this->passkeyService->getRegistrationOptions($user);

            return $this->json([
                'rp' => [
                    'name' => $options->rp->name,
                    'id' => $options->rp->id,
                ],
                'user' => [
                    'id' => base64_encode($options->user->id),
                    'name' => $options->user->name,
                    'displayName' => $options->user->displayName,
                ],
                'challenge' => base64_encode($options->challenge),
                'pubKeyCredParams' => array_map(
                    static fn ($param) => [
                        'type' => $param->type,
                        'alg' => $param->alg,
                    ],
                    $options->pubKeyCredParams
                ),
                'timeout' => $options->timeout,
                'excludeCredentials' => array_map(
                    static fn ($cred) => [
                        'type' => $cred->type,
                        'id' => base64_encode($cred->id),
                    ],
                    $options->excludeCredentials
                ),
                'authenticatorSelection' => [
                    'authenticatorAttachment' => $options->authenticatorSelection?->authenticatorAttachment,
                    'residentKey' => $options->authenticatorSelection?->residentKey,
                    'userVerification' => $options->authenticatorSelection?->userVerification,
                ],
                'attestation' => $options->attestation,
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'Erreur lors de la generation des options',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/passkey/register/verify', name: 'passkey_register_verify', methods: ['POST'])]
    public function passkeyRegisterVerify(Request $request, WebAuthnVerifier $verifier): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;
        $credential = $data['credential'] ?? null;

        if (!$email || !is_array($credential)) {
            return $this->json([
                'error' => 'Email et credential requis',
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if (!$user) {
            return $this->json([
                'error' => 'Utilisateur non trouve',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $webauthnCredential = $verifier->verifyAndSaveRegistration($credential, $user);

            if (!$user->isVerified()) {
                return $this->json([
                    'success' => true,
                    'message' => 'Passkey enregistree. Verifiez maintenant votre email avant de vous connecter.',
                    'verification_sent' => true,
                    'user' => [
                        'id' => $user->getId(),
                        'email' => $user->getEmail(),
                        'roles' => $user->getRoles(),
                        'is_verified' => $user->isVerified(),
                    ],
                    'passkey' => [
                        'id' => $webauthnCredential->getId(),
                        'name' => $webauthnCredential->getName(),
                    ],
                ], Response::HTTP_CREATED);
            }

            $jwt = $this->jwtManager->create($user);
            $refreshToken = $this->refreshTokenGenerator->createForUserWithTtl($user, self::REFRESH_TOKEN_TTL);
            $this->refreshTokenManager->save($refreshToken);

            return $this->json([
                'success' => true,
                'token' => $jwt,
                'refresh_token' => $refreshToken->getRefreshToken(),
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'roles' => $user->getRoles(),
                    'is_verified' => $user->isVerified(),
                ],
                'passkey' => [
                    'id' => $webauthnCredential->getId(),
                    'name' => $webauthnCredential->getName(),
                ],
            ], Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'Echec de la verification',
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/passkey/login/options', name: 'passkey_login_options', methods: ['POST'])]
    public function passkeyLoginOptions(): JsonResponse
    {
        try {
            $options = $this->passkeyService->getLoginOptions();

            return $this->json([
                'challenge' => base64_encode($options->challenge),
                'timeout' => $options->timeout,
                'rpId' => $options->rpId,
                'allowCredentials' => array_map(
                    static fn ($cred) => [
                        'type' => $cred->type,
                        'id' => base64_encode($cred->id),
                        'transports' => $cred->transports,
                    ],
                    $options->allowCredentials
                ),
                'userVerification' => $options->userVerification,
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'Erreur lors de la generation des options',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/passkey/login/verify', name: 'passkey_login_verify', methods: ['POST'])]
    public function passkeyLoginVerify(Request $request, WebAuthnVerifier $verifier): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $credential = $data['credential'] ?? null;

        if (!is_array($credential)) {
            return $this->json([
                'error' => 'Credential requis',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $verifier->verifyAssertion($credential);

            if (!$user->isVerified()) {
                return $this->json([
                    'error' => 'Veuillez verifier votre adresse email avant de vous connecter',
                    'is_verified' => false,
                ], Response::HTTP_FORBIDDEN);
            }

            $jwt = $this->jwtManager->create($user);
            $refreshToken = $this->refreshTokenGenerator->createForUserWithTtl($user, self::REFRESH_TOKEN_TTL);
            $this->refreshTokenManager->save($refreshToken);

            return $this->json([
                'success' => true,
                'token' => $jwt,
                'refresh_token' => $refreshToken->getRefreshToken(),
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'roles' => $user->getRoles(),
                    'is_verified' => $user->isVerified(),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'Echec de l\'authentification',
                'message' => $e->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        }
    }
}
