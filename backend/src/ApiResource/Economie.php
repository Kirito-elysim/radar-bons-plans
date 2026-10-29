<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\QueryParameter;
use App\State\EconomieProcessor;
use App\State\EconomieProvider;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Économie réalisée (« J'en ai profité »). Avec bonPlanId, libellé, montant et enseigne sont
 * pré-remplis depuis le bon plan et restent modifiables.
 */
#[ApiResource(
    shortName: 'Economie',
    operations: [
        new GetCollection(
            uriTemplate: '/economies',
            parameters: [
                'du' => new QueryParameter(description: 'Date de début incluse (AAAA-MM-JJ)', schema: ['type' => 'string', 'format' => 'date']),
                'au' => new QueryParameter(description: 'Date de fin incluse (AAAA-MM-JJ)', schema: ['type' => 'string', 'format' => 'date']),
            ],
        ),
        new Get(uriTemplate: '/economies/{id}', requirements: ['id' => '\d+']),
        new Post(uriTemplate: '/economies'),
        new Patch(uriTemplate: '/economies/{id}', requirements: ['id' => '\d+']),
        new Delete(uriTemplate: '/economies/{id}', requirements: ['id' => '\d+']),
    ],
    provider: EconomieProvider::class,
    processor: EconomieProcessor::class,
)]
final class Economie
{
    #[ApiProperty(identifier: true, writable: false)]
    public ?int $id = null;

    #[Assert\Length(max: 255)]
    public ?string $libelle = null;

    /** Montant en euros, « 2.50 » ou « 2,50 ». */
    #[Assert\Regex(pattern: '/^\d{1,7}([.,]\d{1,2})?$/', message: 'Montant invalide (ex. 2,50).')]
    public ?string $montant = null;

    /** AAAA-MM-JJ, aujourd'hui par défaut. */
    #[Assert\Date(message: 'Date invalide (attendu AAAA-MM-JJ).')]
    public ?string $date = null;

    #[Assert\Length(max: 120)]
    public ?string $enseigne = null;

    #[Assert\Positive]
    public ?int $bonPlanId = null;

    #[ApiProperty(writable: false)]
    public ?string $creeLe = null;

    #[Assert\Callback]
    public function verifier(ExecutionContextInterface $context): void
    {
        if (null === $this->id && null === $this->bonPlanId && (null === $this->libelle || '' === trim($this->libelle))) {
            $context->buildViolation('Indiquez un libellé ou un bon plan.')->atPath('libelle')->addViolation();
        }
        if (null === $this->id && null === $this->bonPlanId && null === $this->montant) {
            $context->buildViolation('Indiquez le montant économisé.')->atPath('montant')->addViolation();
        }
    }
}
