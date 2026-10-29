<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\State\TikTokBonPlanProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Saisie d'un bon plan repéré dans une vidéo TikTok.
 */
#[ApiResource(
    shortName: 'TikTokBonPlan',
    operations: [new Post(uriTemplate: '/tiktok/bons-plans', output: BonPlan::class, processor: TikTokBonPlanProcessor::class)],
)]
final class TikTokBonPlan
{
    #[Assert\NotBlank(message: 'Collez le lien de la vidéo.')]
    #[Assert\Url(message: 'Lien invalide.', requireTld: true)]
    public string $url = '';

    #[Assert\NotBlank(message: 'Indiquez le produit.')]
    #[Assert\Length(max: 255)]
    public string $produit = '';

    #[Assert\Length(max: 120)]
    public ?string $enseigne = null;

    /** Économie en euros, ex. « 2,50 ». */
    #[Assert\Regex(pattern: '/^\d{1,7}([.,]\d{1,2})?\s*(?:€)?$/u', message: 'Montant invalide (ex. 2,50).')]
    public ?string $economie = null;

    #[Assert\Date(message: 'Date invalide (attendu AAAA-MM-JJ).')]
    public ?string $dateFin = null;

    #[Assert\Length(max: 255)]
    public ?string $condition = null;
}
