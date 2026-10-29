<?php

declare(strict_types=1);

namespace App\Collecte\Parsing;

/**
 * Lecture des dates telles qu'écrites sur les sites français :
 * « Du 03 au 15 novembre 2026 », « du 28 décembre au 3 janvier 2027 »,
 * « 27/10/2026 - 08/11/2026 », « jusqu'au 15/11/2026 », « 3j », « 5h restantes »…
 * Toutes les dates renvoyées sont à minuit, fuseau Europe/Paris.
 */
final class FrenchDateParser
{
    private const MOIS = [
        'janvier' => 1, 'janv' => 1, 'fevrier' => 2, 'fevr' => 2, 'fev' => 2, 'mars' => 3,
        'avril' => 4, 'avr' => 4, 'mai' => 5, 'juin' => 6, 'juillet' => 7, 'juil' => 7,
        'aout' => 8, 'septembre' => 9, 'sept' => 9, 'octobre' => 10, 'oct' => 10,
        'novembre' => 11, 'nov' => 11, 'decembre' => 12, 'dec' => 12,
    ];

    private const MOTIF_MOIS = '(janvier|janv|fevrier|fevr|fev|mars|avril|avr|mai|juin|juillet|juil|aout|septembre|sept|octobre|oct|novembre|nov|decembre|dec)\.?';

    private readonly \DateTimeZone $fuseau;

    public function __construct(?\DateTimeZone $fuseau = null)
    {
        $this->fuseau = $fuseau ?? new \DateTimeZone('Europe/Paris');
    }

    /**
     * Lit une période. Le début peut être absent (« jusqu'au … »).
     *
     * @return array{debut: ?\DateTimeImmutable, fin: \DateTimeImmutable}|null
     */
    public function parsePeriode(string $texte, ?\DateTimeImmutable $reference = null): ?array
    {
        $t = self::normaliser($texte);

        // 27/10/2026 - 08/11/2026  ou  du 27/10/2026 au 08/11/2026
        if (preg_match('#(\d{1,2})/(\d{1,2})/(\d{2,4})\s*(?:-|au|a)\s*(\d{1,2})/(\d{1,2})/(\d{2,4})#', $t, $m)) {
            $debut = $this->creer((int) $m[3], (int) $m[2], (int) $m[1]);
            $fin = $this->creer((int) $m[6], (int) $m[5], (int) $m[4]);

            return null !== $debut && null !== $fin ? ['debut' => $debut, 'fin' => $fin] : null;
        }

        // du 20 octobre [2026] au 02 novembre 2026  /  du 03 au 15 novembre 2026
        $motif = '#du\s+(\d{1,2})(?:er)?(?:\s+'.self::MOTIF_MOIS.')?(?:\s+(\d{4}))?\s+au\s+(\d{1,2})(?:er)?\s+'.self::MOTIF_MOIS.'(?:\s+(\d{4}))?#';
        if (preg_match($motif, $t, $m)) {
            $moisFin = self::MOIS[$m[5]];
            $anneeFin = '' !== ($m[6] ?? '') ? (int) $m[6] : $this->anneeProbable($moisFin, (int) $m[4], $reference);
            $moisDebut = '' !== $m[2] ? self::MOIS[$m[2]] : $moisFin;
            $anneeDebut = '' !== $m[3] ? (int) $m[3] : ($moisDebut > $moisFin ? $anneeFin - 1 : $anneeFin);

            $debut = $this->creer($anneeDebut, $moisDebut, (int) $m[1]);
            $fin = $this->creer($anneeFin, $moisFin, (int) $m[4]);

            return null !== $debut && null !== $fin ? ['debut' => $debut, 'fin' => $fin] : null;
        }

        // jusqu'au 15 novembre 2026  /  jusqu'au 15/11/2026  /  valable jusqu'au …
        if (preg_match('#jusqu.?(?:au|a)\s+(.+)$#', $t, $m) && null !== $fin = $this->parseDate($m[1], $reference)) {
            return ['debut' => null, 'fin' => $fin];
        }

        return null;
    }

    /**
     * Lit une date seule : « 15/11/2026 », « 15 novembre 2026 », « 1er mai » (année déduite).
     */
    public function parseDate(string $texte, ?\DateTimeImmutable $reference = null): ?\DateTimeImmutable
    {
        $t = self::normaliser($texte);

        if (preg_match('#(\d{1,2})/(\d{1,2})/(\d{2,4})#', $t, $m)) {
            return $this->creer((int) $m[3], (int) $m[2], (int) $m[1]);
        }
        if (preg_match('#(\d{1,2})(?:er)?\s+'.self::MOTIF_MOIS.'(?:\s+(\d{4}))?#', $t, $m)) {
            $mois = self::MOIS[$m[2]];
            $annee = '' !== ($m[3] ?? '') ? (int) $m[3] : $this->anneeProbable($mois, (int) $m[1], $reference);

            return $this->creer($annee, $mois, (int) $m[1]);
        }
        if (preg_match('#(\d{4})-(\d{2})-(\d{2})#', $t, $m)) {
            return $this->creer((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        return null;
    }

    /**
     * Convertit une durée restante (« 3j », « 2 jours », « 5h », « 45 min ») en date de fin estimée.
     * Une offre qui finit dans quelques heures se termine aujourd'hui (ou demain si on passe minuit).
     */
    public function parseDureeRestante(string $texte, \DateTimeImmutable $maintenant): ?\DateTimeImmutable
    {
        $t = self::normaliser($texte);
        $maintenant = $maintenant->setTimezone($this->fuseau);

        if (preg_match('#(\d+)\s*(?:j|jours?)\b#', $t, $m)) {
            return $maintenant->setTime(0, 0)->modify(\sprintf('+%d days', (int) $m[1]));
        }
        if (preg_match('#(\d+)\s*(?:h|heures?)\b#', $t, $m)) {
            return $maintenant->modify(\sprintf('+%d hours', (int) $m[1]))->setTime(0, 0);
        }
        if (preg_match('#(\d+)\s*(?:min|minutes?)\b#', $t, $m)) {
            return $maintenant->modify(\sprintf('+%d minutes', (int) $m[1]))->setTime(0, 0);
        }

        return null;
    }

    /**
     * Sans année explicite : si la date est passée de plus de 2 mois, c'est l'année suivante.
     */
    private function anneeProbable(int $mois, int $jour, ?\DateTimeImmutable $reference): int
    {
        $reference = ($reference ?? new \DateTimeImmutable('now', $this->fuseau))->setTimezone($this->fuseau);
        $annee = (int) $reference->format('Y');
        $candidate = $this->creer($annee, $mois, $jour);

        if (null !== $candidate && $candidate < $reference->setTime(0, 0)->modify('-2 months')) {
            return $annee + 1;
        }

        return $annee;
    }

    private function creer(int $annee, int $mois, int $jour): ?\DateTimeImmutable
    {
        if ($annee < 100) {
            $annee += 2000;
        }
        if (!checkdate($mois, $jour, $annee)) {
            return null;
        }

        return new \DateTimeImmutable(\sprintf('%04d-%02d-%02d 00:00:00', $annee, $mois, $jour), $this->fuseau);
    }

    /**
     * Minuscules, sans accents, espaces fusionnés (gère les espaces insécables).
     */
    private static function normaliser(string $texte): string
    {
        $t = mb_strtolower(html_entity_decode($texte, \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));
        $t = strtr($t, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'û' => 'u', 'ù' => 'u', 'ô' => 'o', 'à' => 'a', 'â' => 'a', 'î' => 'i', 'ï' => 'i', 'ç' => 'c', '’' => "'"]);

        return trim((string) preg_replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $t));
    }
}
