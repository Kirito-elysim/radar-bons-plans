<?php

declare(strict_types=1);

namespace App\Economie;

use App\Entity\Saving;

/**
 * Export CSV des économies, lisible directement par Excel en français :
 * BOM UTF-8, séparateur « ; », virgule décimale, dates JJ/MM/AAAA.
 */
final class ExportCsv
{
    public const ENTETES = ['Date', 'Libellé', 'Enseigne', 'Montant (€)', 'Type de bon plan', 'Lien'];

    /**
     * @param list<Saving> $economies
     */
    public function generer(array $economies): string
    {
        $flux = fopen('php://temp', 'r+');
        if (false === $flux) {
            throw new \RuntimeException('Flux temporaire indisponible.');
        }
        fwrite($flux, "\u{FEFF}");
        fputcsv($flux, self::ENTETES, ';', '"', '');
        foreach ($economies as $s) {
            fputcsv($flux, [
                $s->getDate()->format('d/m/Y'),
                self::neutraliser($s->getLibelle()),
                self::neutraliser((string) $s->getEnseigne()),
                str_replace('.', ',', $s->getMontant()),
                $s->getDeal()?->getType()->libelle() ?? 'Saisie libre',
                $s->getDeal()?->getUrl() ?? '',
            ], ';', '"', '');
        }
        rewind($flux);
        $csv = (string) stream_get_contents($flux);
        fclose($flux);

        return $csv;
    }

    /**
     * Empêche l'interprétation d'une cellule comme formule par un tableur (injection CSV).
     */
    private static function neutraliser(string $valeur): string
    {
        return '' !== $valeur && \in_array($valeur[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$valeur : $valeur;
    }
}
