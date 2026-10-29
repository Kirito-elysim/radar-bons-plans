<?php

declare(strict_types=1);

namespace App\Notification\Canal;

use App\Enum\NotificationChannel;
use App\Notification\Destinataire;
use App\Notification\EnvoiImpossibleException;
use App\Notification\MessageAlerte;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final class EmailCanal implements CanalInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $expediteur,
    ) {
    }

    public function canal(): NotificationChannel
    {
        return NotificationChannel::Email;
    }

    public function disponiblePour(Destinataire $destinataire): bool
    {
        return null !== $destinataire->email && '' !== $destinataire->email;
    }

    public function envoyer(Destinataire $destinataire, MessageAlerte $message): void
    {
        $email = (new TemplatedEmail())
            ->from(Address::create($this->expediteur))
            ->to((string) $destinataire->email)
            ->subject($message->sujet)
            ->htmlTemplate('emails/alerte.html.twig')
            ->textTemplate('emails/alerte.txt.twig')
            ->context(['message' => $message]);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            throw new EnvoiImpossibleException('E-mail non envoyé : '.$e->getMessage(), 0, $e);
        }
    }
}
