<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use App\Entity\UserPreference;
use App\Enum\NotificationChannel;
use App\State\PreferencesProcessor;
use App\State\PreferencesProvider;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Préférences d'alertes et de récapitulatif de l'utilisateur connecté.
 */
#[ApiResource(
    shortName: 'Preferences',
    operations: [
        new Get(uriTemplate: '/preferences'),
        new Patch(uriTemplate: '/preferences'),
    ],
    provider: PreferencesProvider::class,
    processor: PreferencesProcessor::class,
)]
final class Preferences
{
    /** @var list<string> */
    #[Assert\All([new Assert\Type('string'), new Assert\Length(min: 1, max: 120)])]
    #[Assert\Count(max: 50)]
    public array $enseignesSuivies = [];

    /** @var list<string> */
    #[Assert\All([new Assert\Type('string'), new Assert\Length(min: 1, max: 80)])]
    #[Assert\Count(max: 50)]
    public array $categories = [];

    #[Assert\Range(min: 0, max: 30, notInRangeMessage: 'Le seuil doit être compris entre {{ min }} et {{ max }} jours.')]
    public int $seuilAlerteJours = UserPreference::SEUIL_ALERTE_DEFAUT;

    /** @var list<string> push | email | telegram */
    #[Assert\Choice(callback: [self::class, 'canauxPossibles'], multiple: true, multipleMessage: 'Canal inconnu : {{ value }}.')]
    public array $canaux = [];

    /** HH:MM, heure de Paris. */
    #[Assert\Regex(pattern: '/^([01]\d|2[0-3]):[0-5]\d$/', message: 'Heure invalide (attendu HH:MM).')]
    public string $heureRecap = UserPreference::HEURE_RECAP_DEFAUT;

    public bool $recapActif = true;
    public bool $alertesRembourse100 = true;
    public bool $alertesNouveauCatalogue = true;

    #[Assert\Regex(pattern: '/^-?\d{1,20}$/', message: 'Identifiant de discussion Telegram invalide.')]
    public ?string $telegramChatId = null;

    /**
     * @return list<string>
     */
    public static function canauxPossibles(): array
    {
        return array_map(static fn (NotificationChannel $c): string => $c->value, NotificationChannel::cases());
    }

    public static function depuis(UserPreference $p): self
    {
        $r = new self();
        $r->enseignesSuivies = $p->getEnseignesSuivies();
        $r->categories = $p->getCategories();
        $r->seuilAlerteJours = $p->getSeuilAlerteJours();
        $r->canaux = array_map(static fn (NotificationChannel $c): string => $c->value, $p->getCanaux());
        $r->heureRecap = $p->getHeureRecap()->format('H:i');
        $r->recapActif = $p->isRecapActif();
        $r->alertesRembourse100 = $p->isAlertesRembourse100();
        $r->alertesNouveauCatalogue = $p->isAlertesNouveauCatalogue();
        $r->telegramChatId = $p->getTelegramChatId();

        return $r;
    }
}
