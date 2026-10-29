<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Aide à trouver son identifiant de discussion : écrivez d'abord « /start » à votre bot, puis lancez la commande.
 */
#[AsCommand(name: 'app:telegram:discussions', description: 'Liste les discussions récentes du bot Telegram (pour trouver son identifiant)')]
final class TelegramChatsCommand
{
    public function __construct(
        private readonly HttpClientInterface $client,
        #[Autowire('%env(TELEGRAM_DSN)%')]
        private readonly string $dsn,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        if (!preg_match('#^telegram://([^@]+)@#', $this->dsn, $m)) {
            $io->error('TELEGRAM_DSN non configuré (attendu : telegram://JETON_DU_BOT@default).');

            return Command::FAILURE;
        }

        $reponse = $this->client->request('GET', \sprintf('https://api.telegram.org/bot%s/getUpdates', $m[1]))->toArray(false);
        $discussions = [];
        foreach (\is_array($reponse['result'] ?? null) ? $reponse['result'] : [] as $maj) {
            $chat = \is_array($maj) ? ($maj['message']['chat'] ?? null) : null;
            if (\is_array($chat) && isset($chat['id'])) {
                $discussions[(string) $chat['id']] = [(string) $chat['id'], (string) ($chat['username'] ?? $chat['first_name'] ?? $chat['title'] ?? '?')];
            }
        }

        if ([] === $discussions) {
            $io->warning('Aucune discussion : écrivez « /start » à votre bot dans Telegram, puis relancez.');

            return Command::SUCCESS;
        }
        $io->table(['Identifiant (à coller dans les préférences)', 'Nom'], array_values($discussions));

        return Command::SUCCESS;
    }
}
