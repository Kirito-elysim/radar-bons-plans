<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\DealType;
use App\Enum\RemboursementType;
use App\Repository\DealRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un bon plan collecté (ou saisi à la main). On ne stocke que des métadonnées
 * et un lien vers la source : jamais de copie des images ni des textes complets.
 */
#[ORM\Entity(repositoryClass: DealRepository::class)]
#[ORM\Table(name: 'deal')]
#[ORM\Index(name: 'idx_deal_date_fin', columns: ['date_fin'])]
#[ORM\Index(name: 'idx_deal_type_expire', columns: ['type', 'expire'])]
#[ORM\Index(name: 'idx_deal_enseigne', columns: ['enseigne'])]
#[ORM\Index(name: 'idx_deal_premiere_vue', columns: ['premiere_vue_le'])]
#[ORM\HasLifecycleCallbacks]
class Deal
{
    /** Longueur maximale de la description courte (pas de copie du texte complet). */
    public const DESCRIPTION_MAX = 280;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Source $source;

    #[ORM\Column(length: 24, enumType: DealType::class)]
    private DealType $type;

    #[ORM\Column(length: 24, nullable: true, enumType: RemboursementType::class)]
    private ?RemboursementType $sousType = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $enseigne = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $marque = null;

    #[ORM\Column(length: 255)]
    private string $titre;

    #[ORM\Column(length: self::DESCRIPTION_MAX, nullable: true)]
    private ?string $descriptionCourte = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $categorie = null;

    /** Montant en euros, stocké en décimal (chaîne) pour éviter les erreurs d'arrondi. */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $valeurEuros = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $valeurPourcent = null;

    /** Condition d'utilisation, ex. « dès 69 € d'achat ». */
    #[ORM\Column(name: 'condition_offre', length: 255, nullable: true)]
    private ?string $condition = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateFin = null;

    /** Vrai si la date de fin est déduite (ex. « 3j restants » sur Shopmium). */
    #[ORM\Column(options: ['default' => false])]
    private bool $dateFinEstimee = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $exclusifAppli = false;

    /** Déclinaison régionale éventuelle (ex. « Provencia Sud-Est »). */
    #[ORM\Column(length: 80, nullable: true)]
    private ?string $region = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $url;

    /** URL de la vignette sur le site source (aucune copie locale). */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $imageUrl = null;

    /** Empreinte SHA-256 (source + URL normalisée + titre), voir DealHasher. */
    #[ORM\Column(length: 64, unique: true)]
    private string $hashUnicite;

    #[ORM\Column]
    private \DateTimeImmutable $premiereVueLe;

    #[ORM\Column]
    private \DateTimeImmutable $miseAJourLe;

    #[ORM\Column(options: ['default' => false])]
    private bool $expire = false;

    /** Créateur à l'origine du bon plan (saisie TikTok). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?WatchedCreator $createur = null;

    /** Utilisateur ayant saisi le bon plan à la main, le cas échéant. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $ajoutePar = null;

    public function __construct(
        Source $source,
        DealType $type,
        string $titre,
        string $url,
        string $hashUnicite,
        ?\DateTimeImmutable $vuLe = null,
    ) {
        $this->source = $source;
        $this->type = $type;
        $this->titre = $titre;
        $this->url = $url;
        $this->hashUnicite = $hashUnicite;
        $this->premiereVueLe = $vuLe ?? new \DateTimeImmutable();
        $this->miseAJourLe = $this->premiereVueLe;
    }

    #[ORM\PreUpdate]
    public function toucher(): void
    {
        $this->miseAJourLe = new \DateTimeImmutable();
    }

    /**
     * Nombre de jours avant la fin de l'offre (0 = dernier jour, négatif = terminée).
     * Null si la date de fin est inconnue.
     */
    public function joursRestants(\DateTimeImmutable $aujourdhui): ?int
    {
        if (null === $this->dateFin) {
            return null;
        }

        $debutJour = $aujourdhui->setTime(0, 0);
        $diff = $debutJour->diff($this->dateFin->setTime(0, 0));

        return (int) $diff->format('%r%a');
    }

    /**
     * Une offre est terminée le lendemain de sa date de fin.
     */
    public function estTermineeAu(\DateTimeImmutable $aujourdhui): bool
    {
        $jours = $this->joursRestants($aujourdhui);

        return null !== $jours && $jours < 0;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSource(): Source
    {
        return $this->source;
    }

    public function getType(): DealType
    {
        return $this->type;
    }

    public function getSousType(): ?RemboursementType
    {
        return $this->sousType;
    }

    public function setSousType(?RemboursementType $sousType): static
    {
        $this->sousType = $sousType;

        return $this;
    }

    public function getEnseigne(): ?string
    {
        return $this->enseigne;
    }

    public function setEnseigne(?string $enseigne): static
    {
        $this->enseigne = $enseigne;

        return $this;
    }

    public function getMarque(): ?string
    {
        return $this->marque;
    }

    public function setMarque(?string $marque): static
    {
        $this->marque = $marque;

        return $this;
    }

    public function getTitre(): string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = mb_substr($titre, 0, 255);

        return $this;
    }

    public function getDescriptionCourte(): ?string
    {
        return $this->descriptionCourte;
    }

    /**
     * Tronque volontairement la description : on ne conserve qu'un résumé.
     */
    public function setDescriptionCourte(?string $description): static
    {
        if (null !== $description && mb_strlen($description) > self::DESCRIPTION_MAX) {
            $description = rtrim(mb_substr($description, 0, self::DESCRIPTION_MAX - 1)).'…';
        }
        $this->descriptionCourte = $description;

        return $this;
    }

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(?string $categorie): static
    {
        $this->categorie = $categorie;

        return $this;
    }

    public function getValeurEuros(): ?string
    {
        return $this->valeurEuros;
    }

    public function setValeurEuros(?string $valeurEuros): static
    {
        $this->valeurEuros = $valeurEuros;

        return $this;
    }

    public function getValeurPourcent(): ?string
    {
        return $this->valeurPourcent;
    }

    public function setValeurPourcent(?string $valeurPourcent): static
    {
        $this->valeurPourcent = $valeurPourcent;

        return $this;
    }

    public function getCondition(): ?string
    {
        return $this->condition;
    }

    public function setCondition(?string $condition): static
    {
        $this->condition = $condition;

        return $this;
    }

    public function getDateDebut(): ?\DateTimeImmutable
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeImmutable $dateDebut): static
    {
        $this->dateDebut = $dateDebut;

        return $this;
    }

    public function getDateFin(): ?\DateTimeImmutable
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeImmutable $dateFin, bool $estimee = false): static
    {
        $this->dateFin = $dateFin;
        $this->dateFinEstimee = null !== $dateFin && $estimee;

        return $this;
    }

    public function isDateFinEstimee(): bool
    {
        return $this->dateFinEstimee;
    }

    public function isExclusifAppli(): bool
    {
        return $this->exclusifAppli;
    }

    public function setExclusifAppli(bool $exclusifAppli): static
    {
        $this->exclusifAppli = $exclusifAppli;

        return $this;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(?string $region): static
    {
        $this->region = $region;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): static
    {
        $this->imageUrl = $imageUrl;

        return $this;
    }

    public function getHashUnicite(): string
    {
        return $this->hashUnicite;
    }

    public function getPremiereVueLe(): \DateTimeImmutable
    {
        return $this->premiereVueLe;
    }

    public function getMiseAJourLe(): \DateTimeImmutable
    {
        return $this->miseAJourLe;
    }

    public function isExpire(): bool
    {
        return $this->expire;
    }

    public function setExpire(bool $expire): static
    {
        $this->expire = $expire;

        return $this;
    }

    public function getCreateur(): ?WatchedCreator
    {
        return $this->createur;
    }

    public function setCreateur(?WatchedCreator $createur): static
    {
        $this->createur = $createur;

        return $this;
    }

    public function getAjoutePar(): ?User
    {
        return $this->ajoutePar;
    }

    public function setAjoutePar(?User $ajoutePar): static
    {
        $this->ajoutePar = $ajoutePar;

        return $this;
    }
}
