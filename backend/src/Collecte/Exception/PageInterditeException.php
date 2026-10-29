<?php

declare(strict_types=1);

namespace App\Collecte\Exception;

/**
 * La page est interdite aux robots par le robots.txt du site : elle n'est pas téléchargée.
 */
final class PageInterditeException extends CollecteException
{
    public function __construct(public readonly string $url)
    {
        parent::__construct(\sprintf('Page interdite par robots.txt : %s', $url));
    }
}
