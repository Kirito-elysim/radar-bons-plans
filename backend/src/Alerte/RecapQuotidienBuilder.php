<?php

declare(strict_types=1);

namespace App\Alerte;

use App\Entity\Deal;
use App\Entity\User;
use App\Enum\DealType;
use App\Enum\RemboursementType;
use App\Enum\TypeAlerte;
use App\Notification\LigneAlerte;
use App\Notification\MessageAlerte;
use App\Repository\CriteresBonsPlans;
use App\Repository\DealRepository;
use App\Repository\FavoriteRepository;
use App\Repository\SavingRepository;

/**
 * Récapitulatif quotidien : nouveaux catalogues, nouvelles offres 100 % remboursées,
 * ce qui expire sous 3 jours, économie potentielle repérée, économies de la semaine.
 */
final class RecapQuotidienBuilder
{
    /** Nombre maximal de lignes par section (le reste est résumé). */
    public const LIGNES_MAX = 12;
    public const SECTION_CATALOGUES = 'Nouveaux catalogues';
    public const SECTION_100 = 'Nouvelles offres 100 % remboursées';
    public const SECTION_EXPIRENT = 'Se terminent sous 3 jours';
    public const SECTION_AUTRES = 'Autres nouveautés';

    public function __construct(
        private readonly DealRepository $deals,
        private readonly FavoriteRepository $favoris,
        private readonly SavingRepository $savings,
        private readonly LiensAppli $liens,
    ) {
    }

    public function construire(User $user, \DateTimeImmutable $maintenant): MessageAlerte
    {
        $pref = $user->getPreference();
        $depuis = $maintenant->modify('-24 hours');
        $nouveaux = $this->deals->findNouveauxDepuis($depuis, $maintenant);

        $catalogues = array_filter($nouveaux, static fn (Deal $d): bool => DealType::Catalogue === $d->getType());
        // Les enseignes suivies d'abord ; sans enseigne suivie, tous les catalogues.
        if ([] !== $pref->getEnseignesSuivies()) {
            $catalogues = array_filter($catalogues, static fn (Deal $d): bool => $pref->suitEnseigne($d->getEnseigne()));
        }
        $rembourses100 = array_filter($nouveaux, static fn (Deal $d): bool => RemboursementType::Rembourse100 === $d->getSousType());
        $autres = array_filter($nouveaux, static fn (Deal $d): bool => DealType::Catalogue !== $d->getType() && RemboursementType::Rembourse100 !== $d->getSousType() && null !== $d->getValeurEuros());
        usort($autres, static fn (Deal $a, Deal $b): int => (float) $b->getValeurEuros() <=> (float) $a->getValeurEuros());

        $expirent = $this->expirentBientot($user, $maintenant);

        $potentiel = '0.00';
        foreach ($nouveaux as $d) {
            if (null !== $d->getValeurEuros() && is_numeric($d->getValeurEuros())) {
                $potentiel = bcadd($potentiel, $d->getValeurEuros(), 2);
            }
        }
        $semaine = $this->savings->total($user, $maintenant->modify('-6 days'), $maintenant);

        $resume = [
            \sprintf('%d nouveau%s bon%s plan%s repéré%s', $n = \count($nouveaux), $n > 1 ? 'x' : '', $n > 1 ? 's' : '', $n > 1 ? 's' : '', $n > 1 ? 's' : ''),
            \sprintf('%s € d\'économie potentielle repérée', self::euros($potentiel)),
            \sprintf('%s € économisés ces 7 derniers jours', self::euros($semaine)),
        ];

        return new MessageAlerte(
            type: TypeAlerte::RecapQuotidien,
            sujet: \sprintf('☀️ Vos bons plans du %s', self::dateFr($maintenant)),
            intro: 'Voici ce qui a changé depuis hier.',
            sections: [
                self::SECTION_CATALOGUES => $this->lignes($catalogues, $maintenant),
                self::SECTION_100 => $this->lignes($rembourses100, $maintenant),
                self::SECTION_EXPIRENT => $this->lignes($expirent, $maintenant),
                self::SECTION_AUTRES => $this->lignes($autres, $maintenant),
            ],
            resume: $resume,
            lienAppli: $this->liens->vers('/'),
        );
    }

    /**
     * Favoris, offres 100 % remboursées et bons plans des enseignes suivies qui finissent sous 3 jours.
     *
     * @return list<Deal>
     */
    private function expirentBientot(User $user, \DateTimeImmutable $maintenant): array
    {
        $proches = $this->deals->rechercher(new CriteresBonsPlans(expireSousJours: 3), $maintenant, 0, 500)['elements'];
        if ([] === $proches) {
            return [];
        }
        $favoris = $this->favoris->idsDealsFavoris($user, array_map(static fn (Deal $d): int => (int) $d->getId(), $proches));
        $pref = $user->getPreference();

        return array_values(array_filter($proches, static fn (Deal $d): bool => isset($favoris[(int) $d->getId()])
            || RemboursementType::Rembourse100 === $d->getSousType()
            || ($pref->suitEnseigne($d->getEnseigne() ?? $d->getMarque()) && DealType::Catalogue !== $d->getType())));
    }

    /**
     * @param array<Deal> $deals
     *
     * @return list<LigneAlerte>
     */
    private function lignes(array $deals, \DateTimeImmutable $maintenant): array
    {
        $deals = array_values($deals);
        $lignes = array_map(static fn (Deal $d): LigneAlerte => LigneAlerte::depuisDeal($d, $maintenant), \array_slice($deals, 0, self::LIGNES_MAX));
        $reste = \count($deals) - self::LIGNES_MAX;
        if ($reste > 0) {
            $lignes[] = new LigneAlerte(\sprintf('… et %d autre%s dans l\'application', $reste, $reste > 1 ? 's' : ''), $this->liens->vers('/?nouveau=1'));
        }

        return $lignes;
    }

    private static function euros(string $montant): string
    {
        return number_format((float) $montant, 2, ',', ' ');
    }

    private static function dateFr(\DateTimeImmutable $d): string
    {
        $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
        $mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

        return \sprintf('%s %d %s', $jours[(int) $d->format('w')], (int) $d->format('j'), $mois[(int) $d->format('n') - 1]);
    }
}
