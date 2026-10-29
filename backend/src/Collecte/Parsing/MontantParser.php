<?php

declare(strict_types=1);

namespace App\Collecte\Parsing;

/**
 * Extraction des montants écrits à la française : « -0,50 € », « 200€ », « 1 234,50 € »,
 * « 19,99€ », « 2€50 », « 15 % », « dès 69 € d'achat »…
 * Les montants sont renvoyés en chaînes décimales à 2 chiffres (« 0.50 »).
 */
final class MontantParser
{
    private const NOMBRE = '(\d{1,3}(?:[\s\x{00A0}\x{202F}.]\d{3})*(?:,\d{1,2})?|\d+(?:[.,]\d{1,2})?)';

    /**
     * Premier montant en euros du texte (signe ignoré), ou null.
     */
    public function euros(string $texte): ?string
    {
        $t = self::normaliser($texte);

        // « 2€50 »
        if (preg_match('/(\d+)\s*€\s*(\d{2})\b/u', $t, $m)) {
            return self::decimal($m[1].','.$m[2]);
        }
        if (preg_match('/'.self::NOMBRE.'\s*(?:€|euros?\b|eur\b)/u', $t, $m)) {
            return self::decimal($m[1]);
        }
        if (preg_match('/€\s*'.self::NOMBRE.'/u', $t, $m)) {
            return self::decimal($m[1]);
        }

        return null;
    }

    /**
     * Premier pourcentage du texte, ou null (« 100% remboursé » → « 100.00 »).
     */
    public function pourcent(string $texte): ?string
    {
        if (preg_match('/(\d{1,3}(?:[.,]\d{1,2})?)\s*%/u', self::normaliser($texte), $m)) {
            $valeur = self::decimal($m[1]);

            return null !== $valeur && is_numeric($valeur) && bccomp($valeur, '100', 2) <= 0 ? $valeur : null;
        }

        return null;
    }

    /**
     * Condition d'achat minimum : « dès 69 € », « à partir de 50€ d'achat », « pour 2 produits achetés ».
     */
    public function condition(string $texte): ?string
    {
        $t = self::normaliser($texte);
        if (preg_match('/\b(d[eè]s|à partir de|a partir de|pour)\s+'.self::NOMBRE.'\s*(€|euros?)(\s+d.achats?)?/iu', $t, $m)) {
            return 'dès '.str_replace('.', ',', (string) self::decimalAffiche($m[2])).' €'.('' !== ($m[4] ?? '') ? " d'achat" : '');
        }
        if (preg_match('/\bpour\s+(\d+)\s+(produits?|articles?)\s+achet[ée]s?/iu', $t, $m)) {
            return \sprintf('pour %d %s achetés', (int) $m[1], (int) $m[1] > 1 ? 'produits' : 'produit');
        }

        return null;
    }

    private static function decimal(string $nombre): ?string
    {
        $n = (string) preg_replace('/[\s\x{00A0}\x{202F}]/u', '', $nombre);
        // « 1.234,50 » : le point est un séparateur de milliers s'il est suivi de 3 chiffres.
        $n = (string) preg_replace('/\.(?=\d{3}(?:\D|$))/', '', $n);
        $n = str_replace(',', '.', $n);

        return is_numeric($n) ? bcadd($n, '0', 2) : null;
    }

    private static function decimalAffiche(string $nombre): ?string
    {
        $d = self::decimal($nombre);

        return null !== $d && str_ends_with($d, '.00') ? substr($d, 0, -3) : $d;
    }

    private static function normaliser(string $texte): string
    {
        // Espaces insécables → espaces simples (\s ne les reconnaît pas sans le mode UCP).
        return str_replace(["\u{00A0}", "\u{202F}"], ' ', html_entity_decode($texte, \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));
    }
}
