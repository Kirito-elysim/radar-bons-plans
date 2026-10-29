<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Alerte\LiensAppli;
use App\Api\UtilisateurCourant;
use App\ApiResource\TestNotification;
use App\Enum\TypeAlerte;
use App\Notification\LigneAlerte;
use App\Notification\MessageAlerte;
use App\Notification\Notificateur;

/**
 * @implements ProcessorInterface<mixed, TestNotification>
 */
final class TestNotificationProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Notificateur $notificateur,
        private readonly UtilisateurCourant $utilisateur,
        private readonly LiensAppli $liens,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TestNotification
    {
        $r = new TestNotification();
        $r->resultats = $this->notificateur->tester($this->utilisateur->get(), new MessageAlerte(
            type: TypeAlerte::RecapQuotidien,
            sujet: '🔔 Test Radar Bons Plans',
            intro: 'Si vous lisez ceci, les notifications fonctionnent sur ce canal.',
            sections: ['Exemple' => [new LigneAlerte('Pack de Lait Viva', $this->liens->vers('/'), 'Candia', '100 % remboursé', 'J-3')]],
            lienAppli: $this->liens->vers('/'),
        ));

        return $r;
    }
}
