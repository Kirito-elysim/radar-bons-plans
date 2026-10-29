<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\Deal;
use App\Entity\NotificationLog;
use App\Entity\User;
use App\Enum\NotificationChannel;
use App\Enum\TypeAlerte;
use App\Notification\Canal\CanalInterface;
use App\Repository\NotificationLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Envoie une notification sur les canaux choisis par l'utilisateur, sans jamais répéter
 * une même alerte (journal NotificationLog avec clé d'unicité par canal).
 */
class Notificateur
{
    /** @var array<string, CanalInterface> */
    private array $canaux = [];

    /**
     * @param iterable<CanalInterface> $canaux
     */
    public function __construct(
        #[AutowireIterator(CanalInterface::TAG)]
        iterable $canaux,
        private readonly NotificationLogRepository $journal,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
        private readonly LoggerInterface $logger,
    ) {
        foreach ($canaux as $canal) {
            $this->canaux[$canal->canal()->value] = $canal;
        }
    }

    /**
     * Alerte portant sur des bons plans : chaque bon plan n'est signalé qu'une fois par type et par canal.
     *
     * @param list<Deal>                          $deals
     * @param callable(list<Deal>): MessageAlerte $construire construit le message pour les bons plans restant à signaler
     *
     * @return array<string, int> canal → nombre de bons plans signalés
     */
    public function alerterSurDeals(User $user, TypeAlerte $type, array $deals, callable $construire): array
    {
        $bilan = [];
        foreach ($this->canauxUtilisables($user) as $canal) {
            $aSignaler = array_values(array_filter(
                $deals,
                fn (Deal $d): bool => !$this->journal->existe(NotificationLog::construireCle($type, $canal->canal(), $user->getId(), $d->getId())),
            ));
            if ([] === $aSignaler) {
                continue;
            }

            if ($this->envoyerSurCanal($canal, Destinataire::depuisUtilisateur($user), $construire($aSignaler))) {
                foreach ($aSignaler as $d) {
                    $this->journaliser($user, $d, $type, $canal->canal(), NotificationLog::construireCle($type, $canal->canal(), $user->getId(), $d->getId()));
                }
                $bilan[$canal->canal()->value] = \count($aSignaler);
            }
        }
        $this->em->flush();

        return $bilan;
    }

    /**
     * Message unique identifié par un discriminant (ex. récap du 2026-10-29) : envoyé une fois par canal.
     *
     * @return list<string> canaux sur lesquels le message est parti
     */
    public function envoyerUneFois(User $user, MessageAlerte $message, string $discriminant): array
    {
        $envoyes = [];
        foreach ($this->canauxUtilisables($user) as $canal) {
            $cle = NotificationLog::construireCle($message->type, $canal->canal(), $user->getId(), null, $discriminant);
            if ($this->journal->existe($cle)) {
                continue;
            }
            if ($this->envoyerSurCanal($canal, Destinataire::depuisUtilisateur($user), $message)) {
                $this->journaliser($user, null, $message->type, $canal->canal(), $cle);
                $envoyes[] = $canal->canal()->value;
            }
        }
        $this->em->flush();

        return $envoyes;
    }

    /**
     * Notification à un destinataire hors compte (administrateur), une fois par discriminant et par canal.
     *
     * @return list<string>
     */
    public function envoyerAdministrateur(Destinataire $admin, MessageAlerte $message, string $discriminant): array
    {
        $envoyes = [];
        foreach ($this->canaux as $canal) {
            if (NotificationChannel::Push === $canal->canal() || !$canal->disponiblePour($admin)) {
                continue;
            }
            $cle = NotificationLog::construireCle($message->type, $canal->canal(), null, null, $discriminant);
            if (!$this->journal->existe($cle) && $this->envoyerSurCanal($canal, $admin, $message)) {
                $this->journaliser(null, null, $message->type, $canal->canal(), $cle);
                $envoyes[] = $canal->canal()->value;
            }
        }
        $this->em->flush();

        return $envoyes;
    }

    /**
     * Envoi de test sur tous les canaux, sans journal : renvoie le résultat par canal.
     *
     * @return array<string, string> canal → « envoyé », « non configuré » ou message d'erreur
     */
    public function tester(User $user, MessageAlerte $message): array
    {
        $destinataire = Destinataire::depuisUtilisateur($user);
        $resultats = [];
        foreach ($this->canaux as $code => $canal) {
            if (!$canal->disponiblePour($destinataire)) {
                $resultats[$code] = 'non configuré';
                continue;
            }
            try {
                $canal->envoyer($destinataire, $message);
                $resultats[$code] = 'envoyé';
            } catch (EnvoiImpossibleException $e) {
                $resultats[$code] = $e->getMessage();
            }
        }

        return $resultats;
    }

    /**
     * @return list<CanalInterface>
     */
    private function canauxUtilisables(User $user): array
    {
        $destinataire = Destinataire::depuisUtilisateur($user);
        $resultat = [];
        foreach ($user->getPreference()->getCanaux() as $choisi) {
            $canal = $this->canaux[$choisi->value] ?? null;
            if (null !== $canal && $canal->disponiblePour($destinataire)) {
                $resultat[] = $canal;
            }
        }

        return $resultat;
    }

    private function envoyerSurCanal(CanalInterface $canal, Destinataire $destinataire, MessageAlerte $message): bool
    {
        try {
            $canal->envoyer($destinataire, $message);

            return true;
        } catch (EnvoiImpossibleException $e) {
            $this->logger->error('Notification « {sujet} » non remise par {canal} : {erreur}', [
                'sujet' => $message->sujet,
                'canal' => $canal->canal()->value,
                'erreur' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function journaliser(?User $user, ?Deal $deal, TypeAlerte $type, NotificationChannel $canal, string $cle): void
    {
        $this->em->persist(new NotificationLog($user, $deal, $type, $canal, $cle, $this->clock->now()));
    }
}
