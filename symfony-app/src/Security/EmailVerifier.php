<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\Model\VerifyEmailSignatureComponents;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class EmailVerifier
{
    public function __construct(
        private VerifyEmailHelperInterface $verifyEmailHelper,
        private MailerInterface $mailer,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function sendEmailConfirmation(string $verifyEmailRouteName, User $user): void
    {
        $signatureComponents = $this->createSignatureComponents($verifyEmailRouteName, $user);

        $email = (new TemplatedEmail())
            ->from(new Address('no-reply@resevents.local', 'ResEvents'))
            ->to($user->getEmail())
            ->subject('Confirmez votre adresse email')
            ->htmlTemplate('emails/verify_email.html.twig')
            ->context([
                'user' => $user,
                'signedUrl' => $signatureComponents->getSignedUrl(),
                'expiresAtMessageKey' => $signatureComponents->getExpirationMessageKey(),
                'expiresAtMessageData' => $signatureComponents->getExpirationMessageData(),
            ]);

        $this->mailer->send($email);
    }

    public function getSignedUrl(string $verifyEmailRouteName, User $user): string
    {
        return $this->createSignatureComponents($verifyEmailRouteName, $user)->getSignedUrl();
    }

    /**
     * @throws VerifyEmailExceptionInterface
     */
    public function handleEmailConfirmation(Request $request, User $user): void
    {
        $this->verifyEmailHelper->validateEmailConfirmationFromRequest(
            $request,
            $user->getId(),
            $user->getEmail()
        );

        $user->setIsVerified(true);
        $this->entityManager->flush();
    }

    private function createSignatureComponents(string $verifyEmailRouteName, User $user): VerifyEmailSignatureComponents
    {
        return $this->verifyEmailHelper->generateSignature(
            $verifyEmailRouteName,
            $user->getId(),
            $user->getEmail(),
            ['id' => $user->getId()]
        );
    }
}
