<?php

declare(strict_types=1);

namespace App\Alerte;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Construit les liens vers la PWA à partir de APP_URL_FRONT.
 */
final class LiensAppli
{
    public function __construct(
        #[Autowire('%env(APP_URL_FRONT)%')]
        private readonly string $base,
    ) {
    }

    public function vers(string $chemin = '/'): string
    {
        return rtrim($this->base, '/').'/'.ltrim($chemin, '/');
    }
}
