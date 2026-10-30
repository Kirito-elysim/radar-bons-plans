<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\State\SourceAdminProcessor;
use App\State\SourceAdminProvider;

/**
 * État d'une source de collecte pour l'administration (réservé à ROLE_ADMIN).
 */
#[ApiResource(
    shortName: 'SourceAdmin',
    operations: [
        new GetCollection(uriTemplate: '/admin/sources', paginationEnabled: false),
        new Get(uriTemplate: '/admin/sources/{id}', requirements: ['id' => '\d+']),
        new Patch(uriTemplate: '/admin/sources/{id}', requirements: ['id' => '\d+'], description: 'Active ou désactive la collecte de la source.'),
        new Post(
            uriTemplate: '/admin/sources/{id}/relancer',
            requirements: ['id' => '\d+'],
            status: 200,
            description: 'Lance immédiatement la collecte de la source et renvoie son nouvel état.',
            deserialize: false,
            name: 'source_relancer',
        ),
    ],
    security: "is_granted('ROLE_ADMIN')",
    provider: SourceAdminProvider::class,
    processor: SourceAdminProcessor::class,
)]
final class SourceAdmin
{
    #[ApiProperty(identifier: true, writable: false)]
    public int $id;
    #[ApiProperty(writable: false)]
    public string $code;
    #[ApiProperty(writable: false)]
    public string $nom;
    #[ApiProperty(writable: false)]
    public string $url;
    /** scraping | saisie_manuelle | lien_bio | api */
    #[ApiProperty(writable: false)]
    public string $type;
    /** Seul champ modifiable : la collecte automatique est-elle active ? */
    public bool $actif = true;
    /** Un connecteur existe-t-il pour cette source (sinon : saisie manuelle uniquement) ? */
    #[ApiProperty(writable: false)]
    public bool $collectable = false;
    #[ApiProperty(writable: false)]
    public ?string $dernierRun = null;
    /** succes | vide | partiel | erreur | en_cours, null si jamais lancée */
    #[ApiProperty(writable: false)]
    public ?string $dernierStatut = null;
    #[ApiProperty(writable: false)]
    public ?int $dernierNbDeals = null;
    #[ApiProperty(writable: false)]
    public ?string $dernierErreur = null;
    /** Bons plans encore valables issus de cette source. */
    #[ApiProperty(writable: false)]
    public int $dealsEnCours = 0;
    /**
     * Rapport de la relance qui vient d'être exécutée (POST …/relancer uniquement).
     *
     * @var array{statut: string, recus: int, nouveaux: int, misAJour: int, pagesIgnorees: list<string>, erreur: ?string}|null
     */
    #[ApiProperty(writable: false)]
    public ?array $rapport = null;
}
