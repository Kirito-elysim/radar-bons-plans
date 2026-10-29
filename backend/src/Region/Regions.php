<?php

declare(strict_types=1);

namespace App\Region;

/**
 * Zones utilisées par les enseignes pour leurs catalogues régionaux (« Exclus PACA », « Provencia »,
 * « Nord », « Sud-Ouest »…). Un catalogue peut couvrir plusieurs zones (« Sud » = PACA + Occitanie + Sud-Ouest).
 * En base, Deal::region contient les codes séparés par des virgules (« paca,occitanie,sud-ouest ») ;
 * null = catalogue national.
 */
final class Regions
{
    public const DEFAUT = 'paca';

    /** Code → libellé affiché. */
    public const ZONES = [
        'paca' => "Provence-Alpes-Côte d'Azur (Provencia)",
        'occitanie' => 'Occitanie / Languedoc',
        'sud-ouest' => 'Sud-Ouest',
        'rhone-alpes' => 'Auvergne-Rhône-Alpes',
        'idf' => 'Île-de-France',
        'nord' => 'Nord / Hauts-de-France',
        'nord-est' => 'Grand Est',
        'bretagne-normandie' => 'Bretagne / Normandie',
        'ouest' => 'Ouest / Centre-Ouest',
        'centre' => 'Centre / Bourgogne',
        'corse' => 'Corse',
    ];

    /**
     * Motifs reconnus dans les intitulés, du plus précis au plus général (un motif trouvé est retiré
     * du texte avant de chercher les suivants : « Nord-Est » ne compte pas aussi pour « Nord »).
     * Les mots doivent être entiers : « Estissac » ou « La Pacaudière » ne correspondent à rien.
     */
    private const MOTIFS = [
        'provencia|paca|provence|cote d azur|mediterranee|sud est' => ['paca'],
        'nord est|grand est|alsace|lorraine|champagne' => ['nord-est'],
        'sud ouest|aquitaine' => ['sud-ouest'],
        'centre ouest|pays de la loire|poitou' => ['ouest'],
        'rhone alpes|auvergne|savoie' => ['rhone-alpes'],
        'ile de france|idf' => ['idf'],
        'hauts de france|picardie' => ['nord'],
        'bretagne normandie|bretagne|normandie' => ['bretagne-normandie'],
        'languedoc|occitanie' => ['occitanie'],
        'bourgogne' => ['centre'],
        'corse' => ['corse'],
        'nord' => ['nord'],
        'est' => ['nord-est'],
        'ouest' => ['ouest'],
        'centre' => ['centre'],
        'sud' => ['paca', 'occitanie', 'sud-ouest'],
    ];

    /**
     * Zones couvertes par un intitulé de catalogue, ou null si le catalogue est national.
     */
    public static function detecter(string $texte): ?string
    {
        $t = ' '.self::normaliser($texte).' ';
        $zones = [];
        foreach (self::MOTIFS as $motif => $codes) {
            $regex = '/(?<= )(?:'.$motif.')(?= )/';
            if (preg_match($regex, $t)) {
                $zones = [...$zones, ...$codes];
                $t = (string) preg_replace($regex, ' ', $t);
            }
        }
        if ([] === $zones) {
            return null;
        }
        $zones = array_values(array_unique($zones));
        // Ordre stable, celui du référentiel.
        usort($zones, static fn (string $a, string $b): int => array_search($a, array_keys(self::ZONES), true) <=> array_search($b, array_keys(self::ZONES), true));

        return implode(',', $zones);
    }

    /**
     * Le catalogue concerne-t-il cette zone ? (national = toujours oui)
     */
    public static function couvre(?string $regionDeal, ?string $zone): bool
    {
        return null === $regionDeal || null === $zone || \in_array($zone, explode(',', $regionDeal), true);
    }

    public static function libelle(?string $regionDeal): ?string
    {
        if (null === $regionDeal || '' === $regionDeal) {
            return null;
        }
        $libelles = array_map(static fn (string $c): string => self::ZONES[$c] ?? $c, explode(',', $regionDeal));

        return implode(' · ', $libelles);
    }

    public static function existe(string $code): bool
    {
        return isset(self::ZONES[$code]);
    }

    /**
     * Minuscules, sans accents, ponctuation et tirets remplacés par des espaces.
     */
    private static function normaliser(string $texte): string
    {
        $t = mb_strtolower($texte);
        $translitere = \Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');
        if (null !== $translitere) {
            $t = (string) $translitere->transliterate($t);
        }

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $t));
    }
}
