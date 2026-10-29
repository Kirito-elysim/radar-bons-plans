<?php

declare(strict_types=1);

namespace App\Collecte;

use App\Collecte\DTO\DealDTO;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Contrat commun à tous les connecteurs de sources. Un connecteur lit une source
 * et renvoie des métadonnées de bons plans ; il ne touche jamais à la base.
 */
#[AutoconfigureTag(self::TAG)]
interface SourceConnectorInterface
{
    public const TAG = 'app.connecteur';

    /**
     * Code de la source en base (table « source »), ex. « anti_crise_catalogue ».
     */
    public function getCode(): string;

    /**
     * @return list<DealDTO>
     *
     * @throws Exception\CollecteException si la source est inexploitable
     */
    public function fetch(): array;
}
