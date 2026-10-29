<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\TableauDeBordProvider;

/**
 * Compteurs et bandeau « À saisir avant expiration » du tableau de bord.
 */
#[ApiResource(
    shortName: 'TableauDeBord',
    operations: [new Get(uriTemplate: '/tableau-de-bord', provider: TableauDeBordProvider::class)],
)]
final class TableauDeBord
{
    public int $finissentSous3Jours = 0;
    public int $finissentSous7Jours = 0;
    public int $rembourses100EnCours = 0;
    public int $nouveauxDepuisHier = 0;
    /** Somme des montants connus des offres en cours. */
    public string $economiePotentielle = '0.00';
    public string $economiesMois = '0.00';
    public string $economiesTotal = '0.00';
    /** @var array<string, int> nombre d'offres en cours par type */
    public array $parType = [];
    /**
     * Offres qui finissent dans les 7 jours (favoris et enseignes suivies en premier).
     *
     * @var list<BonPlan>
     */
    #[ApiProperty(readableLink: true)]
    public array $aSaisir = [];
}
