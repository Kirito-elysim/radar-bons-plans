<?php

declare(strict_types=1);

namespace App\Notification;

use App\Api\BonPlanMapper;
use App\Entity\Deal;
use App\Enum\DealType;

/**
 * Une ligne d'alerte ou de récap : un bon plan résumé, toujours avec le lien vers sa source.
 */
final readonly class LigneAlerte
{
    public function __construct(
        public string $titre,
        public string $url,
        public ?string $qui = null,
        public ?string $valeur = null,
        public ?string $echeance = null,
    ) {
    }

    public static function depuisDeal(Deal $d, \DateTimeImmutable $aujourdhui): self
    {
        $jours = $d->joursRestants($aujourdhui);

        return new self(
            titre: DealType::Catalogue === $d->getType() ? (string) ($d->getDescriptionCourte() ?? $d->getTitre()) : $d->getTitre(),
            url: $d->getUrl(),
            qui: $d->getEnseigne() ?? $d->getMarque(),
            valeur: self::valeur($d),
            echeance: match (true) {
                null === $jours => null,
                0 === $jours => 'dernier jour',
                default => \sprintf('J-%d', $jours).(BonPlanMapper::urgence($jours) === 'critique' ? ' ⚠️' : ''),
            },
        );
    }

    private static function valeur(Deal $d): ?string
    {
        if (null !== $d->getValeurEuros()) {
            return str_replace('.', ',', $d->getValeurEuros()).' €';
        }
        if (null !== $d->getValeurPourcent()) {
            $p = rtrim(rtrim(str_replace('.', ',', $d->getValeurPourcent()), '0'), ',');

            return '100' === $p ? '100 % remboursé' : '-'.$p.' %';
        }

        return null;
    }

    /**
     * Version texte d'une ligne (Telegram, e-mail texte).
     */
    public function enTexte(): string
    {
        $parties = array_filter([$this->qui, $this->titre, $this->valeur, $this->echeance]);

        return implode(' · ', $parties);
    }
}
