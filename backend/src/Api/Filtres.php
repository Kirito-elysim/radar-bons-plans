<?php

declare(strict_types=1);

namespace App\Api;

/**
 * Lecture des paramètres de requête bruts transmis par API Platform ($context['filters']).
 */
final class Filtres
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(private readonly array $context)
    {
    }

    /**
     * @return list<string> valeurs séparées par des virgules (ou tableau ?x[]=a&x[]=b)
     */
    public function liste(string $cle): array
    {
        $brut = $this->brut($cle);
        $valeurs = \is_array($brut) ? $brut : explode(',', \is_scalar($brut) ? (string) $brut : '');
        $valeurs = array_map(static fn (mixed $v): string => trim(\is_scalar($v) ? (string) $v : ''), $valeurs);

        return array_values(array_unique(array_filter($valeurs, static fn (string $v): bool => '' !== $v)));
    }

    public function texte(string $cle): ?string
    {
        $brut = $this->brut($cle);
        $texte = \is_scalar($brut) ? trim((string) $brut) : '';

        return '' !== $texte ? $texte : null;
    }

    public function booleen(string $cle): bool
    {
        return \in_array(mb_strtolower((string) $this->texte($cle)), ['1', 'true', 'oui', 'on'], true);
    }

    public function entier(string $cle): ?int
    {
        $texte = $this->texte($cle);

        return null !== $texte && preg_match('/^\d{1,4}$/', $texte) ? (int) $texte : null;
    }

    private function brut(string $cle): mixed
    {
        $filtres = $this->context['filters'] ?? [];

        return \is_array($filtres) ? ($filtres[$cle] ?? null) : null;
    }
}
