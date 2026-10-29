<?php

declare(strict_types=1);

namespace App\Controller;

use App\Api\UtilisateurCourant;
use App\Economie\ExportCsv;
use App\Repository\SavingRepository;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /api/economies/export.csv[?du=AAAA-MM-JJ&au=AAAA-MM-JJ] — protégé par le pare-feu JWT « api ».
 */
final class ExportEconomiesController
{
    public function __construct(
        private readonly SavingRepository $savings,
        private readonly ExportCsv $export,
        private readonly UtilisateurCourant $utilisateur,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('/api/economies/export.csv', name: 'economies_export_csv', methods: ['GET'], priority: 10)]
    public function __invoke(Request $requete): Response
    {
        $du = self::date($requete->query->getString('du'));
        $au = self::date($requete->query->getString('au'));
        $economies = $this->savings->toutes($this->utilisateur->get(), $du, $au);

        $nom = \sprintf('economies-radar-%s.csv', $this->clock->now()->format('Y-m-d'));

        return new Response($this->export->generer($economies), Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $nom),
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private static function date(string $texte): ?\DateTimeImmutable
    {
        $date = '' !== $texte ? \DateTimeImmutable::createFromFormat('!Y-m-d', $texte) : false;

        return false !== $date ? $date : null;
    }
}
