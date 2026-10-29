<?php

declare(strict_types=1);

namespace App\Social;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Choisit le premier fournisseur capable de traiter l'URL (ordre de priorité des services tagués).
 */
final class SocialVideoResolver
{
    /**
     * @param iterable<SocialVideoProviderInterface> $fournisseurs
     */
    public function __construct(
        #[AutowireIterator(SocialVideoProviderInterface::TAG)]
        private readonly iterable $fournisseurs,
    ) {
    }

    public function recuperer(string $url): VideoMetadata
    {
        foreach ($this->fournisseurs as $fournisseur) {
            if ($fournisseur->supports($url)) {
                return $fournisseur->recuperer($url);
            }
        }

        throw new VideoIntrouvableException('Plateforme non prise en charge pour ce lien.');
    }
}
