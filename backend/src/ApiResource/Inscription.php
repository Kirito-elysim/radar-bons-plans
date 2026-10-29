<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\State\InscriptionProcessor;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Création de compte. Désactivable via INSCRIPTIONS_OUVERTES=0 (usage personnel).
 */
#[ApiResource(
    shortName: 'Inscription',
    operations: [
        new Post(uriTemplate: '/inscription', description: 'Crée un compte utilisateur.', output: Profil::class, processor: InscriptionProcessor::class),
    ],
)]
final class Inscription
{
    #[Assert\NotBlank(message: 'L\'adresse e-mail est obligatoire.')]
    #[Assert\Email(message: 'Adresse e-mail invalide.')]
    #[Assert\Length(max: 180)]
    public string $email = '';

    #[Assert\NotBlank(message: 'Le mot de passe est obligatoire.')]
    #[Assert\Length(min: 10, max: 4096, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.')]
    public string $motDePasse = '';

    #[Assert\Length(max: 80)]
    public ?string $pseudo = null;
}
