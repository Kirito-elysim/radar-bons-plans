<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\NotificationChannel;
use App\Enum\TypeAlerte;
use App\Repository\NotificationLogRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Journal des notifications envoyées. La clé d'unicité garantit qu'une même
 * alerte (ex. J-3 sur tel deal, par e-mail) n'est jamais envoyée deux fois.
 */
#[ORM\Entity(repositoryClass: NotificationLogRepository::class)]
#[ORM\Table(name: 'notification_log')]
#[ORM\Index(name: 'idx_notification_envoye_le', columns: ['envoye_le'])]
class NotificationLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Null pour une alerte destinée à l'administrateur défini dans l'environnement. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Deal $deal;

    #[ORM\Column(length: 32, enumType: TypeAlerte::class)]
    private TypeAlerte $type;

    #[ORM\Column(length: 16, enumType: NotificationChannel::class)]
    private NotificationChannel $canal;

    #[ORM\Column(length: 191, unique: true)]
    private string $cleUnicite;

    #[ORM\Column]
    private \DateTimeImmutable $envoyeLe;

    public function __construct(
        ?User $user,
        ?Deal $deal,
        TypeAlerte $type,
        NotificationChannel $canal,
        string $cleUnicite,
        ?\DateTimeImmutable $envoyeLe = null,
    ) {
        $this->user = $user;
        $this->deal = $deal;
        $this->type = $type;
        $this->canal = $canal;
        $this->cleUnicite = $cleUnicite;
        $this->envoyeLe = $envoyeLe ?? new \DateTimeImmutable();
    }

    /**
     * Construit la clé d'unicité. $discriminant permet de distinguer par ex. le récap de chaque jour.
     */
    public static function construireCle(TypeAlerte $type, NotificationChannel $canal, ?int $userId, ?int $dealId, string $discriminant = ''): string
    {
        return implode(':', [$type->value, $canal->value, 'u'.($userId ?? 'admin'), 'd'.($dealId ?? '-'), $discriminant]);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getDeal(): ?Deal
    {
        return $this->deal;
    }

    public function getType(): TypeAlerte
    {
        return $this->type;
    }

    public function getCanal(): NotificationChannel
    {
        return $this->canal;
    }

    public function getCleUnicite(): string
    {
        return $this->cleUnicite;
    }

    public function getEnvoyeLe(): \DateTimeImmutable
    {
        return $this->envoyeLe;
    }
}
