<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\RecapApercuProvider;

/**
 * Aperçu du récap quotidien tel qu'il serait envoyé maintenant.
 */
#[ApiResource(shortName: 'RecapApercu', operations: [new Get(uriTemplate: '/recap/apercu', provider: RecapApercuProvider::class)])]
final class RecapApercu
{
    public string $sujet = '';
    public string $intro = '';
    /** @var list<string> */
    public array $resume = [];
    /** @var list<array{titre: string, lignes: list<array{titre: string, url: string, qui: ?string, valeur: ?string, echeance: ?string}>}> */
    public array $sections = [];
}
