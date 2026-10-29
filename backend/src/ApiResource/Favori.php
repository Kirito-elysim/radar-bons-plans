<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\State\FavoriProcessor;
use App\State\FavoriProvider;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Favoris de l'utilisateur connecté (identifiés par le bon plan).
 * Pour la liste détaillée : GET /api/bons-plans?favoris=1.
 */
#[ApiResource(
    shortName: 'Favori',
    operations: [
        new GetCollection(uriTemplate: '/favoris', paginationEnabled: false),
        new Post(uriTemplate: '/favoris', description: 'Ajoute un bon plan aux favoris (idempotent).'),
        new Delete(uriTemplate: '/favoris/{bonPlanId}'),
    ],
    provider: FavoriProvider::class,
    processor: FavoriProcessor::class,
)]
final class Favori
{
    #[ApiProperty(identifier: true)]
    #[Assert\NotNull(message: 'Le bon plan est obligatoire.')]
    #[Assert\Positive]
    public ?int $bonPlanId = null;

    #[ApiProperty(writable: false)]
    public ?string $creeLe = null;
}
