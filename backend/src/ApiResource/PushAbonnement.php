<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\State\PushAbonnementProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Abonnement Web Push envoyé par le navigateur (format PushSubscription.toJSON()).
 */
#[ApiResource(
    shortName: 'PushAbonnement',
    operations: [
        new Post(uriTemplate: '/push/abonnements', processor: PushAbonnementProcessor::class, output: false, status: 204),
        new Post(uriTemplate: '/push/desabonnement', processor: PushAbonnementProcessor::class, output: false, status: 204, name: 'push_desabonnement', validationContext: ['groups' => ['desabonnement']]),
    ],
)]
final class PushAbonnement
{
    #[Assert\NotBlank(groups: ['Default', 'desabonnement'])]
    #[Assert\Url(requireTld: true, groups: ['Default', 'desabonnement'])]
    #[Assert\Regex(pattern: '#^https://#', message: 'Point de terminaison push invalide.', groups: ['Default', 'desabonnement'])]
    public string $endpoint = '';

    /** @var array{p256dh?: string, auth?: string} */
    #[Assert\Collection(fields: ['p256dh' => [new Assert\NotBlank(), new Assert\Length(max: 255)], 'auth' => [new Assert\NotBlank(), new Assert\Length(max: 255)]], allowExtraFields: true)]
    public array $keys = [];
}
