<?php

declare(strict_types=1);

namespace App\Economie;

use App\Entity\Saving;

/**
 * Totaux des économies réalisées : par mois (12 derniers), par enseigne, par type de bon plan.
 * Calcul en PHP avec bcmath (volumes faibles par utilisateur, aucun arrondi flottant).
 */
final class CalculateurEconomies
{
    public const SANS_ENSEIGNE = 'Sans enseigne';
    public const SAISIE_LIBRE = 'saisie_libre';
    private const NB_MOIS = 12;

    /**
     * @param list<Saving> $economies
     */
    public function calculer(array $economies, \DateTimeImmutable $aujourdhui): StatistiquesEconomies
    {
        $jour = $aujourdhui->setTime(0, 0);
        $moisCourant = $jour->format('Y-m');
        $moisPrecedent = $jour->modify('first day of previous month')->format('Y-m');
        $debutSemaine = $jour->modify('-6 days');

        // 12 mois glissants, mois courant inclus, initialisés à zéro (un mois sans économie apparaît).
        $parMois = [];
        for ($i = self::NB_MOIS - 1; $i >= 0; --$i) {
            $parMois[$jour->modify('first day of this month')->modify(\sprintf('-%d months', $i))->format('Y-m')] = ['total' => '0.00', 'nombre' => 0];
        }

        $total = $annee = $mois = $precedent = $semaine = '0.00';
        $parEnseigne = [];
        $parType = [];
        $moisAvecEconomies = [];

        foreach ($economies as $s) {
            $montant = $s->getMontant();
            if (!is_numeric($montant)) {
                continue;
            }
            $date = $s->getDate();
            $cleMois = $date->format('Y-m');

            $total = bcadd($total, $montant, 2);
            $moisAvecEconomies[$cleMois] = true;
            if ($date->format('Y') === $jour->format('Y')) {
                $annee = bcadd($annee, $montant, 2);
            }
            if ($cleMois === $moisCourant) {
                $mois = bcadd($mois, $montant, 2);
            }
            if ($cleMois === $moisPrecedent) {
                $precedent = bcadd($precedent, $montant, 2);
            }
            if ($date >= $debutSemaine && $date <= $jour) {
                $semaine = bcadd($semaine, $montant, 2);
            }
            if (isset($parMois[$cleMois])) {
                $parMois[$cleMois]['total'] = bcadd($parMois[$cleMois]['total'], $montant, 2);
                ++$parMois[$cleMois]['nombre'];
            }

            // Regroupement insensible à la casse et aux espaces ; le premier libellé rencontré est conservé.
            $enseigne = trim((string) $s->getEnseigne());
            $cleEnseigne = '' === $enseigne ? '' : mb_strtolower($enseigne);
            $parEnseigne[$cleEnseigne] ??= ['enseigne' => '' === $enseigne ? self::SANS_ENSEIGNE : $enseigne, 'total' => '0.00', 'nombre' => 0];
            $parEnseigne[$cleEnseigne]['total'] = bcadd($parEnseigne[$cleEnseigne]['total'], $montant, 2);
            ++$parEnseigne[$cleEnseigne]['nombre'];

            $type = $s->getDeal()?->getType();
            $cleType = null !== $type ? $type->value : self::SAISIE_LIBRE;
            $parType[$cleType] ??= ['type' => $cleType, 'libelle' => $type?->libelle() ?? 'Saisie libre', 'total' => '0.00', 'nombre' => 0];
            $parType[$cleType]['total'] = bcadd($parType[$cleType]['total'], $montant, 2);
            ++$parType[$cleType]['nombre'];
        }

        $parEnseigne = array_values($parEnseigne);
        usort($parEnseigne, static fn (array $a, array $b): int => [bccomp($b['total'], $a['total'], 2), $a['enseigne']] <=> [0, $b['enseigne']]);
        $parType = array_values($parType);
        usort($parType, static fn (array $a, array $b): int => bccomp($b['total'], $a['total'], 2));

        $meilleur = null;
        foreach ($parMois as $cle => $m) {
            if ($m['nombre'] > 0 && (null === $meilleur || bccomp($m['total'], $parMois[$meilleur]['total'], 2) > 0)) {
                $meilleur = $cle;
            }
        }

        // Moyenne sur les mois ayant au moins une économie (évite de diluer les premiers mois d'usage).
        $nbMois = \count($moisAvecEconomies);
        $moyenne = $nbMois > 0 ? bcdiv($total, (string) $nbMois, 2) : '0.00';

        return new StatistiquesEconomies(
            total: $total,
            nombre: \count($economies),
            totalMois: $mois,
            totalMoisPrecedent: $precedent,
            totalAnnee: $annee,
            totalSemaine: $semaine,
            moyenneMensuelle: $moyenne,
            meilleurMois: $meilleur,
            parMois: array_map(static fn (string $cle, array $m): array => ['mois' => $cle, ...$m], array_keys($parMois), array_values($parMois)),
            parEnseigne: $parEnseigne,
            parType: $parType,
        );
    }
}
