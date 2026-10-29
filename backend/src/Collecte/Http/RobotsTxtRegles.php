<?php

declare(strict_types=1);

namespace App\Collecte\Http;

/**
 * Règles d'un robots.txt (RFC 9309) : groupes par User-Agent, Allow/Disallow avec jokers « * » et « $ ».
 * La règle la plus longue l'emporte ; à longueur égale, Allow l'emporte.
 */
final class RobotsTxtRegles
{
    /**
     * @param array<string, list<array{autorise: bool, motif: string}>> $groupes règles par agent (en minuscules)
     */
    private function __construct(
        private readonly array $groupes,
        private readonly bool $toutInterdit = false,
    ) {
    }

    public static function toutAutoriser(): self
    {
        return new self([]);
    }

    public static function toutInterdire(): self
    {
        return new self([], true);
    }

    public static function parse(string $contenu): self
    {
        $groupes = [];
        $agentsCourants = [];
        $dansRegles = false;

        foreach (preg_split('/\R/', $contenu) ?: [] as $ligne) {
            $ligne = trim((string) preg_replace('/#.*$/', '', $ligne));
            if (!str_contains($ligne, ':')) {
                continue;
            }
            [$cle, $valeur] = array_map('trim', explode(':', $ligne, 2));
            $cle = strtolower($cle);

            if ('user-agent' === $cle) {
                // Une ligne User-agent après des règles ouvre un nouveau groupe.
                if ($dansRegles) {
                    $agentsCourants = [];
                    $dansRegles = false;
                }
                $agentsCourants[] = strtolower($valeur);
                continue;
            }

            if ('allow' !== $cle && 'disallow' !== $cle) {
                continue;
            }
            $dansRegles = true;
            if ('' === $valeur) {
                // « Disallow: » vide = aucune restriction.
                continue;
            }
            foreach ($agentsCourants as $agent) {
                $groupes[$agent][] = ['autorise' => 'allow' === $cle, 'motif' => $valeur];
            }
        }

        return new self($groupes);
    }

    /**
     * @param string $chemin   chemin + requête de l'URL (ex. « /catalogue/?page=2 »)
     * @param string $produit  jeton produit du User-Agent (ex. « RadarBonsPlans »)
     */
    public function estAutorise(string $chemin, string $produit): bool
    {
        if ($this->toutInterdit) {
            return false;
        }

        $regles = $this->reglesPour(strtolower($produit));
        $meilleure = null;
        $longueur = -1;

        foreach ($regles as $regle) {
            if (!self::correspond($regle['motif'], $chemin)) {
                continue;
            }
            $l = \strlen($regle['motif']);
            if ($l > $longueur || ($l === $longueur && $regle['autorise'])) {
                $meilleure = $regle['autorise'];
                $longueur = $l;
            }
        }

        return $meilleure ?? true;
    }

    /**
     * @return list<array{autorise: bool, motif: string}>
     */
    private function reglesPour(string $produit): array
    {
        foreach ($this->groupes as $agent => $regles) {
            if ('*' !== $agent && '' !== $agent && str_contains($produit, $agent)) {
                return $regles;
            }
        }

        return $this->groupes['*'] ?? [];
    }

    private static function correspond(string $motif, string $chemin): bool
    {
        $ancre = str_ends_with($motif, '$');
        $regex = str_replace('\*', '.*', preg_quote($ancre ? substr($motif, 0, -1) : $motif, '#'));

        return 1 === preg_match('#^'.$regex.($ancre ? '$' : '').'#', $chemin);
    }
}
