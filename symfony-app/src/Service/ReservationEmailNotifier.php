<?php

namespace App\Service;

use App\Entity\Reservation;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class ReservationEmailNotifier
{
    public function __construct(
        private MailerInterface $mailer,
    ) {
    }

    public function sendReservationConfirmation(Reservation $reservation): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address('no-reply@resevents.local', 'ResEvents'))
            ->to($reservation->getEmail())
            ->subject('Confirmation de reservation - ' . $reservation->getEvent()?->getTitle())
            ->htmlTemplate('emails/reservation_confirmation.html.twig')
            ->context([
                'reservation' => $reservation,
            ]);

        $this->mailer->send($email);
    }

    public function sendReservationCancellation(Reservation $reservation): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address('no-reply@resevents.local', 'ResEvents'))
            ->to($reservation->getEmail())
            ->subject('Annulation de reservation - ' . $reservation->getEvent()?->getTitle())
            ->htmlTemplate('emails/reservation_cancellation.html.twig')
            ->context([
                'reservation' => $reservation,
            ]);

        $this->mailer->send($email);
    }
}
