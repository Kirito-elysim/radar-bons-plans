<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Entity\User;
use App\State\ProfilProvider;

#[ApiResource(
    shortName: 'Profil',
    operations: [
        new Get(uriTemplate: '/moi', description: 'Profil de l\'utilisateur connecté.', provider: ProfilProvider::class),
    ],
)]
final class Profil
{
    #[ApiProperty(identifier: true)]
    public int $id;
    public string $email;
    public ?string $pseudo = null;
    /** @var list<string> */
    public array $roles = [];
    public bool $admin = false;
    public string $creeLe;

    public static function depuis(User $user): self
    {
        $p = new self();
        $p->id = (int) $user->getId();
        $p->email = $user->getEmail();
        $p->pseudo = $user->getPseudo();
        $p->roles = $user->getRoles();
        $p->admin = $user->estAdmin();
        $p->creeLe = $user->getCreeLe()->format(\DateTimeInterface::ATOM);

        return $p;
    }
}
