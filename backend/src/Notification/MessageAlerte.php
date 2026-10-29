<?php

declare(strict_types=1);

namespace App\Notification;

use App\Enum\TypeAlerte;

/**
 * Contenu d'une notification, rendu ensuite par chaque canal (e-mail, Telegram, push).
 */
final readonly class MessageAlerte
{
    /**
     * @param array<string, list<LigneAlerte>> $sections titre de section → lignes
     * @param list<string>                     $resume   phrases courtes (compteurs, totaux)
     */
    public function __construct(
        public TypeAlerte $type,
        public string $sujet,
        public string $intro,
        public array $sections = [],
        public array $resume = [],
        public ?string $lienAppli = null,
    ) {
    }

    public function nombreLignes(): int
    {
        return array_sum(array_map('count', $this->sections));
    }

    /**
     * Texte court pour une notification push (titre + corps limités par les navigateurs).
     */
    public function corpsCourt(): string
    {
        if ([] !== $this->resume) {
            return implode(' · ', $this->resume);
        }
        foreach ($this->sections as $lignes) {
            if ([] !== $lignes) {
                $premiere = $lignes[0]->enTexte();
                $autres = $this->nombreLignes() - 1;

                return $autres > 0 ? \sprintf('%s (+%d autre%s)', $premiere, $autres, $autres > 1 ? 's' : '') : $premiere;
            }
        }

        return $this->intro;
    }
}
