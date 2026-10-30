<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\SourceRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /api/sante — sonde publique pour Coolify / Docker : base joignable et fraîcheur de la collecte.
 * Ne révèle aucune donnée personnelle.
 */
final class SanteController
{
    /** Au-delà, la collecte (toutes les 6 h) est considérée en retard : le worker est sans doute arrêté. */
    private const RETARD_MAX_HEURES = 7;

    public function __construct(
        private readonly Connection $connexion,
        private readonly SourceRepository $sources,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('/api/sante', name: 'sante', methods: ['GET'], priority: 10)]
    public function __invoke(): JsonResponse
    {
        try {
            $this->connexion->executeQuery('SELECT 1');
        } catch (\Throwable) {
            return new JsonResponse(['statut' => 'erreur', 'base' => 'injoignable'], 503);
        }

        $derniere = null;
        foreach ($this->sources->findActives() as $source) {
            $run = $source->getDernierRun();
            if (null !== $run && (null === $derniere || $run > $derniere)) {
                $derniere = $run;
            }
        }
        $retard = null !== $derniere && ($this->clock->now()->getTimestamp() - $derniere->getTimestamp()) > self::RETARD_MAX_HEURES * 3600;

        return new JsonResponse([
            'statut' => 'ok',
            'base' => 'ok',
            'derniereCollecte' => $derniere?->format(\DateTimeInterface::ATOM),
            'collecteEnRetard' => $retard,
        ], 200, ['Cache-Control' => 'no-store']);
    }
}
