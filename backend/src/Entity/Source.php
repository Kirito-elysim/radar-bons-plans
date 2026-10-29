<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\RunStatus;
use App\Enum\SourceType;
use App\Repository\SourceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Site ou canal d'où proviennent les bons plans. Un connecteur par source.
 */
#[ORM\Entity(repositoryClass: SourceRepository::class)]
#[ORM\Table(name: 'source')]
class Source
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Identifiant technique stable, utilisé par le connecteur (ex. « anti_crise_catalogue »). */
    #[ORM\Column(length: 64, unique: true)]
    private string $code;

    #[ORM\Column(length: 120)]
    private string $nom;

    #[ORM\Column(type: Types::TEXT)]
    private string $url;

    #[ORM\Column(length: 32, enumType: SourceType::class)]
    private SourceType $type;

    #[ORM\Column(options: ['default' => true])]
    private bool $actif = true;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dernierRun = null;

    #[ORM\Column(length: 16, nullable: true, enumType: RunStatus::class)]
    private ?RunStatus $dernierStatut = null;

    #[ORM\Column(nullable: true)]
    private ?int $dernierNbDeals = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $dernierErreur = null;

    public function __construct(string $code, string $nom, string $url, SourceType $type)
    {
        $this->code = $code;
        $this->nom = $nom;
        $this->url = $url;
        $this->type = $type;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getType(): SourceType
    {
        return $this->type;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;

        return $this;
    }

    public function getDernierRun(): ?\DateTimeImmutable
    {
        return $this->dernierRun;
    }

    public function getDernierStatut(): ?RunStatus
    {
        return $this->dernierStatut;
    }

    public function getDernierNbDeals(): ?int
    {
        return $this->dernierNbDeals;
    }

    public function getDernierErreur(): ?string
    {
        return $this->dernierErreur;
    }

    /**
     * Enregistre le résultat d'une exécution du connecteur.
     */
    public function enregistrerRun(\DateTimeImmutable $quand, RunStatus $statut, int $nbDeals, ?string $erreur = null): static
    {
        $this->dernierRun = $quand;
        $this->dernierStatut = $statut;
        $this->dernierNbDeals = $nbDeals;
        $this->dernierErreur = $erreur;

        return $this;
    }
}
