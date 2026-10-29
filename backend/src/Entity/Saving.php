<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SavingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Économie réellement réalisée (bouton « J'en ai profité » ou saisie libre).
 * L'enseigne est recopiée pour conserver les statistiques après la purge du deal.
 */
#[ORM\Entity(repositoryClass: SavingRepository::class)]
#[ORM\Table(name: 'saving')]
#[ORM\Index(name: 'idx_saving_user_date', columns: ['user_id', 'date'])]
class Saving
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Deal $deal = null;

    #[ORM\Column(length: 255)]
    private string $libelle;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private string $montant;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $date;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $enseigne = null;

    #[ORM\Column]
    private \DateTimeImmutable $creeLe;

    public function __construct(User $user, string $libelle, string $montant, \DateTimeImmutable $date)
    {
        $this->user = $user;
        $this->libelle = $libelle;
        $this->setMontant($montant);
        $this->date = $date->setTime(0, 0);
        $this->creeLe = new \DateTimeImmutable();
    }

    /**
     * Crée une économie pré-remplie à partir d'un bon plan (montant modifiable ensuite).
     */
    public static function depuisDeal(User $user, Deal $deal, \DateTimeImmutable $date, ?string $montant = null): self
    {
        $saving = new self($user, $deal->getTitre(), $montant ?? $deal->getValeurEuros() ?? '0.00', $date);
        $saving->deal = $deal;
        $saving->enseigne = $deal->getEnseigne() ?? $deal->getMarque();

        return $saving;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getDeal(): ?Deal
    {
        return $this->deal;
    }

    public function getLibelle(): string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getMontant(): string
    {
        return $this->montant;
    }

    /**
     * @param string $montant décimal positif, point ou virgule acceptés (ex. « 2,50 »)
     */
    public function setMontant(string $montant): static
    {
        $normalise = str_replace([',', ' ', "\u{00A0}", '€'], ['.', '', '', ''], trim($montant));
        if (!is_numeric($normalise) || (float) $normalise < 0) {
            throw new \InvalidArgumentException(\sprintf('Montant invalide : « %s ».', $montant));
        }
        $this->montant = bcadd($normalise, '0', 2);

        return $this;
    }

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date->setTime(0, 0);

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

    public function getCreeLe(): \DateTimeImmutable
    {
        return $this->creeLe;
    }
}
