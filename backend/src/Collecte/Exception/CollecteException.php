<?php

declare(strict_types=1);

namespace App\Collecte\Exception;

/**
 * Erreur empêchant un connecteur de lire sa source (HTTP, réseau, contenu illisible).
 */
class CollecteException extends \RuntimeException
{
}
