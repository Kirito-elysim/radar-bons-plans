<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\State\TikTokApercuProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Aperçu d'une vidéo collée par l'utilisateur (oEmbed public TikTok), avant saisie du bon plan.
 */
#[ApiResource(
    shortName: 'TikTokApercu',
    operations: [new Post(uriTemplate: '/tiktok/apercu', status: 200, processor: TikTokApercuProcessor::class)],
)]
final class TikTokApercu
{
    #[Assert\NotBlank(message: 'Collez le lien de la vidéo.')]
    #[Assert\Url(message: 'Lien invalide.', requireTld: true)]
    public string $url = '';

    #[ApiProperty(writable: false)]
    public ?string $titre = null;
    #[ApiProperty(writable: false)]
    public ?string $auteurPseudo = null;
    #[ApiProperty(writable: false)]
    public ?string $miniatureUrl = null;
    #[ApiProperty(writable: false)]
    public ?string $idVideo = null;
    /** Vrai si l'auteur fait partie des créateurs suivis. */
    #[ApiProperty(writable: false)]
    public bool $createurSuivi = false;
}
