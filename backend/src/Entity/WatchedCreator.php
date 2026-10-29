<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Plateforme;
use App\Repository\WatchedCreatorRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Créateur de contenu suivi (ex. @nelson_mrq sur TikTok). Aucun scraping de la
 * plateforme : seules les pages « lien en bio » peuvent être lues (robots.txt).
 */
#[ORM\Entity(repositoryClass: WatchedCreatorRepository::class)]
#[ORM\Table(name: 'watched_creator')]
#[ORM\UniqueConstraint(name: 'uniq_creator_plateforme_pseudo', columns: ['plateforme', 'pseudo'])]
class WatchedCreator
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Pseudo sans « @ ». */
    #[ORM\Column(length: 80)]
    private string $pseudo;

    #[ORM\Column(length: 16, enumType: Plateforme::class)]
    private Plateforme $plateforme;

    #[ORM\Column(type: Types::TEXT)]
    private string $urlProfil;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lienBio = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $actif = true;

    #[ORM\Column]
    private \DateTimeImmutable $ajouteLe;

    public function __construct(string $pseudo, Plateforme $plateforme, string $urlProfil)
    {
        $this->pseudo = ltrim(trim($pseudo), '@');
        $this->plateforme = $plateforme;
        $this->urlProfil = $urlProfil;
        $this->ajouteLe = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPseudo(): string
    {
        return $this->pseudo;
    }

    public function getPlateforme(): Plateforme
    {
        return $this->plateforme;
    }

    public function getUrlProfil(): string
    {
        return $this->urlProfil;
    }

    public function getLienBio(): ?string
    {
        return $this->lienBio;
    }

    public function setLienBio(?string $lienBio): static
    {
        $this->lienBio = $lienBio;

        return $this;
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

    public function getAjouteLe(): \DateTimeImmutable
    {
        return $this->ajouteLe;
    }
}
