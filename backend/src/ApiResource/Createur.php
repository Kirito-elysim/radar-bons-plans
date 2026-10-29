<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\State\CreateurProcessor;
use App\State\CreateurProvider;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Créateurs de contenu suivis (lecture pour tous, modification réservée aux administrateurs).
 */
#[ApiResource(
    shortName: 'Createur',
    operations: [
        new GetCollection(uriTemplate: '/createurs', paginationEnabled: false),
        new Get(uriTemplate: '/createurs/{id}'),
        new Post(uriTemplate: '/createurs', security: "is_granted('ROLE_ADMIN')"),
        new Patch(uriTemplate: '/createurs/{id}', security: "is_granted('ROLE_ADMIN')"),
    ],
    provider: CreateurProvider::class,
    processor: CreateurProcessor::class,
)]
final class Createur
{
    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^@?[\w.-]{2,80}$/', message: 'Pseudo invalide.')]
    public string $pseudo = '';

    /** tiktok | instagram | youtube */
    #[Assert\Choice(choices: ['tiktok', 'instagram', 'youtube'])]
    public string $plateforme = 'tiktok';

    #[Assert\Url(requireTld: true)]
    public ?string $urlProfil = null;

    /** Page « lien en bio » (Linktree, blog…), lue par le connecteur lien_bio si son robots.txt l'autorise. */
    #[Assert\Url(requireTld: true, message: 'Lien en bio invalide.')]
    #[Assert\Regex(pattern: '#^https://#', message: 'Le lien en bio doit commencer par https://')]
    public ?string $lienBio = null;

    public bool $actif = true;
}
