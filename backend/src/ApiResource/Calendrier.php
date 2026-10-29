<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use App\State\CalendrierProvider;

/**
 * Débuts et fins des offres d'un mois (vue calendrier).
 */
#[ApiResource(
    shortName: 'Calendrier',
    operations: [new Get(
        uriTemplate: '/calendrier',
        provider: CalendrierProvider::class,
        parameters: [
            'mois' => new QueryParameter(description: 'Mois AAAA-MM (mois courant par défaut)', schema: ['type' => 'string', 'pattern' => '^\d{4}-\d{2}$']),
            'type' => new QueryParameter(description: 'Types de bons plans (virgules possibles)', schema: ['type' => 'string']),
        ],
    )],
)]
final class Calendrier
{
    /** AAAA-MM */
    public string $mois;

    /**
     * @var list<array{date: string, nature: string, bonPlanId: int, titre: string, type: string, enseigne: ?string, url: string}>
     */
    #[ApiProperty(readableLink: true)]
    public array $evenements = [];
}
