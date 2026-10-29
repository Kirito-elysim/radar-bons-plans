<?php

declare(strict_types=1);

namespace App\Alerte;

use App\Entity\Deal;
use App\Entity\User;
use App\Enum\DealType;
use App\Enum\TypeAlerte;
use App\Notification\LigneAlerte;
use App\Notification\MessageAlerte;
use App\Notification\Notificateur;
use App\Region\Regions;
use App\Repository\DealRepository;
use App\Repository\UserRepository;
use Symfony\Component\Clock\ClockInterface;

/**
 * Alerte « nouveau catalogue » pour les enseignes suivies (lancée après chaque collecte).
 */
final class NouveauCatalogueAlerteService
{
    /** Fenêtre de rattrapage : un catalogue repéré dans les 2 derniers jours peut encore être signalé. */
    private const FENETRE = '-48 hours';

    public function __construct(
        private readonly UserRepository $users,
        private readonly DealRepository $deals,
        private readonly Notificateur $notificateur,
        private readonly LiensAppli $liens,
        private readonly ClockInterface $clock,
    ) {
    }

    public function executer(): int
    {
        $maintenant = $this->clock->now();
        $nouveaux = $this->deals->findNouveauxDepuis($maintenant->modify(self::FENETRE), $maintenant, DealType::Catalogue);
        if ([] === $nouveaux) {
            return 0;
        }

        $envois = 0;
        /** @var list<User> $users */
        $users = $this->users->findAll();
        foreach ($users as $user) {
            $pref = $user->getPreference();
            if (!$pref->isAlertesNouveauCatalogue() || [] === $pref->getEnseignesSuivies()) {
                continue;
            }
            $concernes = array_values(array_filter(
                $nouveaux,
                static fn (Deal $d): bool => $pref->suitEnseigne($d->getEnseigne()) && Regions::couvre($d->getRegion(), $pref->getRegion()),
            ));
            if ([] === $concernes) {
                continue;
            }

            $envois += \count($this->notificateur->alerterSurDeals(
                $user,
                TypeAlerte::NouveauCatalogue,
                $concernes,
                fn (array $d): MessageAlerte => $this->message($d, $maintenant),
            ));
        }

        return $envois;
    }

    /**
     * @param list<Deal> $deals
     */
    private function message(array $deals, \DateTimeImmutable $maintenant): MessageAlerte
    {
        $enseignes = array_values(array_unique(array_map(static fn (Deal $d): string => (string) $d->getEnseigne(), $deals)));

        return new MessageAlerte(
            type: TypeAlerte::NouveauCatalogue,
            sujet: 1 === \count($deals)
                ? \sprintf('🗞️ Nouveau catalogue %s', $enseignes[0])
                : \sprintf('🗞️ %d nouveaux catalogues : %s', \count($deals), implode(', ', \array_slice($enseignes, 0, 4)).(\count($enseignes) > 4 ? '…' : '')),
            intro: 'De nouveaux catalogues sont disponibles pour vos enseignes suivies.',
            sections: ['Nouveaux catalogues' => array_map(static fn (Deal $d): LigneAlerte => LigneAlerte::depuisDeal($d, $maintenant), $deals)],
            lienAppli: $this->liens->vers('/?section=catalogues&nouveau=1'),
        );
    }
}
