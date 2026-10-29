<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Lance la collecte d'une source, ou de toutes les sources actives si $code est null.
 */
final readonly class CollecterSources
{
    public function __construct(public ?string $code = null)
    {
    }
}
