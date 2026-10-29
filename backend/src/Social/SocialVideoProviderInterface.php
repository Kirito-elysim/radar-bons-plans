<?php

declare(strict_types=1);

namespace App\Social;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Point d'extension : récupère les métadonnées d'une vidéo à partir de son URL.
 * Implémentation actuelle : oEmbed public TikTok. Une implémentation basée sur l'API
 * officielle TikTok (si un accès est obtenu) pourra être ajoutée avec une priorité plus haute.
 */
#[AutoconfigureTag(self::TAG)]
interface SocialVideoProviderInterface
{
    public const TAG = 'app.fournisseur_video';

    public function supports(string $url): bool;

    /**
     * @throws VideoIntrouvableException
     */
    public function recuperer(string $url): VideoMetadata;
}
