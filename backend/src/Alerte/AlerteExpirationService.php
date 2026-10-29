<?php

declare(strict_types=1);

namespace App\Alerte;

use App\Entity\Deal;
use App\Entity\User;
use App\Enum\RemboursementType;
use App\Enum\TypeAlerte;
use App\Notification\LigneAlerte;
use App\Notification\MessageAlerte;
use App\Notification\Notificateur;
use App\Repository\DealRepository;
use App\Repository\FavoriteRepository;
use App\Repository\UserRepository;
use Symfony\Component\Clock\ClockInterface;

/**
 * Alertes avant expiration : à J-{seuil} (3 par défaut) et à J-1, sur les favoris
 * et, si l'utilisateur le souhaite, sur toutes les offres 100 % remboursées.
 */
final class AlerteExpirationService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly DealRepository $deals,
        private readonly FavoriteRepository $favoris,
        private readonly Notificateur $notificateur,
        private readonly LiensAppli $liens,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return int nombre de notifications envoyées (tous canaux confondus)
     */
    public function executer(): int
    {
        $aujourdhui = $this->clock->now();
        /** @var array<int, list<Deal>> $parEcheance cache des bons plans finissant à J-n */
        $parEcheance = [];
        $envois = 0;

        /** @var list<User> $users */
        $users = $this->users->findAll();
        foreach ($users as $user) {
            $seuil = $user->getPreference()->getSeuilAlerteJours();
            $echeances = array_values(array_unique(array_filter([$seuil, 1], static fn (int $j): bool => $j >= 1)));

            foreach ($echeances as $jours) {
                $parEcheance[$jours] ??= $this->deals->findFinissantLe($aujourdhui->modify(\sprintf('+%d days', $jours)));
                $concernes = $this->selectionner($user, $parEcheance[$jours]);
                if ([] === $concernes) {
                    continue;
                }

                $type = 1 === $jours ? TypeAlerte::ExpirationJ1 : TypeAlerte::ExpirationJ3;
                $bilan = $this->notificateur->alerterSurDeals($user, $type, $concernes, fn (array $d): MessageAlerte => $this->message($d, $jours, $aujourdhui));
                $envois += \count($bilan);
            }
        }

        return $envois;
    }

    /**
     * @param list<Deal> $candidats
     *
     * @return list<Deal>
     */
    private function selectionner(User $user, array $candidats): array
    {
        if ([] === $candidats) {
            return [];
        }
        $ids = array_map(static fn (Deal $d): int => (int) $d->getId(), $candidats);
        $favoris = $this->favoris->idsDealsFavoris($user, $ids);
        $rembourse100 = $user->getPreference()->isAlertesRembourse100();

        return array_values(array_filter(
            $candidats,
            static fn (Deal $d): bool => isset($favoris[(int) $d->getId()]) || ($rembourse100 && RemboursementType::Rembourse100 === $d->getSousType()),
        ));
    }

    /**
     * @param list<Deal> $deals
     */
    private function message(array $deals, int $jours, \DateTimeImmutable $aujourdhui): MessageAlerte
    {
        $n = \count($deals);
        $quand = 1 === $jours ? 'demain' : \sprintf('dans %d jours', $jours);

        return new MessageAlerte(
            type: 1 === $jours ? TypeAlerte::ExpirationJ1 : TypeAlerte::ExpirationJ3,
            sujet: 1 === $n
                ? \sprintf('⏰ Se termine %s : %s', $quand, $deals[0]->getEnseigne() ?? $deals[0]->getMarque() ?? $deals[0]->getTitre())
                : \sprintf('⏰ %d offres se terminent %s', $n, $quand),
            intro: 1 === $jours ? 'Dernière ligne droite : ces offres se terminent demain soir.' : 'Pensez à en profiter avant la fin :',
            sections: ['À saisir' => array_map(static fn (Deal $d): LigneAlerte => LigneAlerte::depuisDeal($d, $aujourdhui), $deals)],
            lienAppli: $this->liens->vers('/?urgence=1'),
        );
    }
}
