<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\NotificationChannel;
use App\Repository\UserPreferenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Préférences d'alertes et de récapitulatif d'un utilisateur.
 */
#[ORM\Entity(repositoryClass: UserPreferenceRepository::class)]
#[ORM\Table(name: 'user_preference')]
class UserPreference
{
    public const SEUIL_ALERTE_DEFAUT = 3;
    public const HEURE_RECAP_DEFAUT = '07:40';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'preference')]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private User $user;

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $enseignesSuivies = [];

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $categories = [];

    /** Nombre de jours avant expiration à partir duquel on alerte. */
    #[ORM\Column(type: Types::SMALLINT, options: ['default' => self::SEUIL_ALERTE_DEFAUT])]
    private int $seuilAlerteJours = self::SEUIL_ALERTE_DEFAUT;

    /** @var list<string> valeurs de NotificationChannel */
    #[ORM\Column(type: Types::JSON)]
    private array $canaux = [NotificationChannel::Email->value];

    /** Heure locale (Europe/Paris) d'envoi du récap quotidien. */
    #[ORM\Column(type: Types::TIME_IMMUTABLE)]
    private \DateTimeImmutable $heureRecap;

    #[ORM\Column(options: ['default' => true])]
    private bool $recapActif = true;

    #[ORM\Column(options: ['default' => true])]
    private bool $alertesRembourse100 = true;

    #[ORM\Column(options: ['default' => true])]
    private bool $alertesNouveauCatalogue = true;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $telegramChatId = null;

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->heureRecap = new \DateTimeImmutable('1970-01-01 '.self::HEURE_RECAP_DEFAUT);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @return list<string>
     */
    public function getEnseignesSuivies(): array
    {
        return $this->enseignesSuivies;
    }

    /**
     * @param list<string> $enseignes
     */
    public function setEnseignesSuivies(array $enseignes): static
    {
        $this->enseignesSuivies = self::nettoyerListe($enseignes);

        return $this;
    }

    public function suitEnseigne(?string $enseigne): bool
    {
        if (null === $enseigne) {
            return false;
        }
        $cible = mb_strtolower(trim($enseigne));
        foreach ($this->enseignesSuivies as $suivie) {
            if (mb_strtolower($suivie) === $cible) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function getCategories(): array
    {
        return $this->categories;
    }

    /**
     * @param list<string> $categories
     */
    public function setCategories(array $categories): static
    {
        $this->categories = self::nettoyerListe($categories);

        return $this;
    }

    public function getSeuilAlerteJours(): int
    {
        return $this->seuilAlerteJours;
    }

    public function setSeuilAlerteJours(int $jours): static
    {
        if ($jours < 0 || $jours > 30) {
            throw new \InvalidArgumentException('Le seuil d\'alerte doit être compris entre 0 et 30 jours.');
        }
        $this->seuilAlerteJours = $jours;

        return $this;
    }

    /**
     * @return list<NotificationChannel>
     */
    public function getCanaux(): array
    {
        return array_values(array_filter(array_map(
            static fn (string $c): ?NotificationChannel => NotificationChannel::tryFrom($c),
            $this->canaux,
        )));
    }

    /**
     * @param list<NotificationChannel> $canaux
     */
    public function setCanaux(array $canaux): static
    {
        $this->canaux = array_values(array_unique(array_map(
            static fn (NotificationChannel $c): string => $c->value,
            $canaux,
        )));

        return $this;
    }

    public function aCanal(NotificationChannel $canal): bool
    {
        return \in_array($canal->value, $this->canaux, true);
    }

    public function getHeureRecap(): \DateTimeImmutable
    {
        return $this->heureRecap;
    }

    /**
     * @param string $heure format « HH:MM »
     */
    public function setHeureRecap(string $heure): static
    {
        $parsee = \DateTimeImmutable::createFromFormat('!H:i', $heure);
        if (false === $parsee) {
            throw new \InvalidArgumentException(\sprintf('Heure de récap invalide : « %s » (attendu HH:MM).', $heure));
        }
        $this->heureRecap = $parsee;

        return $this;
    }

    public function isRecapActif(): bool
    {
        return $this->recapActif;
    }

    public function setRecapActif(bool $recapActif): static
    {
        $this->recapActif = $recapActif;

        return $this;
    }

    public function isAlertesRembourse100(): bool
    {
        return $this->alertesRembourse100;
    }

    public function setAlertesRembourse100(bool $actif): static
    {
        $this->alertesRembourse100 = $actif;

        return $this;
    }

    public function isAlertesNouveauCatalogue(): bool
    {
        return $this->alertesNouveauCatalogue;
    }

    public function setAlertesNouveauCatalogue(bool $actif): static
    {
        $this->alertesNouveauCatalogue = $actif;

        return $this;
    }

    public function getTelegramChatId(): ?string
    {
        return $this->telegramChatId;
    }

    public function setTelegramChatId(?string $telegramChatId): static
    {
        $this->telegramChatId = $telegramChatId;

        return $this;
    }

    /**
     * @param list<string> $valeurs
     *
     * @return list<string>
     */
    private static function nettoyerListe(array $valeurs): array
    {
        $propres = array_filter(array_map('trim', $valeurs), static fn (string $v): bool => '' !== $v);

        return array_values(array_unique($propres));
    }
}
