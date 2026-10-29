<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PushSubscriptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Abonnement Web Push (VAPID) d'un navigateur ou d'un mobile.
 */
#[ORM\Entity(repositoryClass: PushSubscriptionRepository::class)]
#[ORM\Table(name: 'push_subscription')]
class PushSubscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: Types::TEXT, unique: true)]
    private string $endpoint;

    #[ORM\Column(length: 255)]
    private string $cleP256dh;

    #[ORM\Column(length: 255)]
    private string $cleAuth;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column]
    private \DateTimeImmutable $creeLe;

    public function __construct(User $user, string $endpoint, string $cleP256dh, string $cleAuth, ?string $userAgent = null)
    {
        $this->user = $user;
        $this->endpoint = $endpoint;
        $this->cleP256dh = $cleP256dh;
        $this->cleAuth = $cleAuth;
        $this->userAgent = null !== $userAgent ? mb_substr($userAgent, 0, 255) : null;
        $this->creeLe = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getCleP256dh(): string
    {
        return $this->cleP256dh;
    }

    public function getCleAuth(): string
    {
        return $this->cleAuth;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getCreeLe(): \DateTimeImmutable
    {
        return $this->creeLe;
    }
}
