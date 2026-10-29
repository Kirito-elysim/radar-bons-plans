<?php

declare(strict_types=1);

namespace App\Social;

use App\Enum\Plateforme;

/**
 * Métadonnées publiques d'une vidéo (titre, auteur, miniature) : jamais la vidéo elle-même.
 */
final readonly class VideoMetadata
{
    public function __construct(
        public Plateforme $plateforme,
        public string $url,
        public ?string $idVideo,
        public string $titre,
        public ?string $auteurPseudo,
        public ?string $auteurUrl,
        public ?string $miniatureUrl,
    ) {
    }
}
