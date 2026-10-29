<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\EconomiesStatistiquesProvider;

/**
 * Totaux des économies de l'utilisateur connecté (12 derniers mois, par enseigne, par type).
 */
#[ApiResource(
    shortName: 'EconomiesStatistiques',
    operations: [new Get(uriTemplate: '/economies/statistiques', provider: EconomiesStatistiquesProvider::class)],
)]
final class EconomiesStatistiques
{
    public string $total = '0.00';
    public int $nombre = 0;
    public string $totalMois = '0.00';
    public string $totalMoisPrecedent = '0.00';
    public string $totalAnnee = '0.00';
    public string $totalSemaine = '0.00';
    public string $moyenneMensuelle = '0.00';
    /** AAAA-MM du mois le plus économe sur 12 mois. */
    public ?string $meilleurMois = null;
    /** @var list<array{mois: string, total: string, nombre: int}> */
    public array $parMois = [];
    /** @var list<array{enseigne: string, total: string, nombre: int}> */
    public array $parEnseigne = [];
    /** @var list<array{type: string, libelle: string, total: string, nombre: int}> */
    public array $parType = [];
}
