<?php

declare(strict_types=1);

namespace App\Notification\Canal;

use App\Enum\NotificationChannel;
use App\Notification\Destinataire;
use App\Notification\EnvoiImpossibleException;
use App\Notification\MessageAlerte;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramOptions;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Exception\ExceptionInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

/**
 * Bot Telegram (TELEGRAM_DSN). Le destinataire doit avoir écrit au bot et renseigné son identifiant de discussion.
 */
final class TelegramCanal implements CanalInterface
{
    /** Limite d'un message Telegram. */
    private const LONGUEUR_MAX = 4096;

    public function __construct(
        private readonly ChatterInterface $chatter,
        #[Autowire('%env(TELEGRAM_DSN)%')]
        private readonly string $dsn,
    ) {
    }

    public function canal(): NotificationChannel
    {
        return NotificationChannel::Telegram;
    }

    public function disponiblePour(Destinataire $destinataire): bool
    {
        return !str_starts_with($this->dsn, 'null://') && null !== $destinataire->telegramChatId && '' !== $destinataire->telegramChatId;
    }

    public function envoyer(Destinataire $destinataire, MessageAlerte $message): void
    {
        $options = (new TelegramOptions())
            ->chatId((string) $destinataire->telegramChatId)
            ->parseMode(TelegramOptions::PARSE_MODE_HTML)
            ->disableWebPagePreview(true);

        try {
            $this->chatter->send((new ChatMessage(self::formater($message)))->transport('telegram')->options($options));
        } catch (ExceptionInterface $e) {
            throw new EnvoiImpossibleException('Telegram : '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Mise en forme HTML Telegram (balises b, a uniquement), tronquée à 4096 caractères.
     */
    public static function formater(MessageAlerte $message): string
    {
        $e = static fn (string $t): string => htmlspecialchars($t, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $lignes = ['<b>'.$e($message->sujet).'</b>', $e($message->intro)];
        if ([] !== $message->resume) {
            $lignes[] = $e(implode(' · ', $message->resume));
        }
        foreach ($message->sections as $titre => $items) {
            if ([] === $items) {
                continue;
            }
            $lignes[] = '';
            $lignes[] = '<b>'.$e($titre).'</b>';
            foreach ($items as $item) {
                $lignes[] = '• <a href="'.$e($item->url).'">'.$e($item->enTexte()).'</a>';
            }
        }
        if (null !== $message->lienAppli) {
            $lignes[] = '';
            $lignes[] = '<a href="'.$e($message->lienAppli).'">Ouvrir Radar</a>';
        }

        $texte = implode("\n", $lignes);
        if (mb_strlen($texte) <= self::LONGUEUR_MAX) {
            return $texte;
        }

        // Coupe proprement à la dernière ligne complète.
        $coupe = mb_substr($texte, 0, self::LONGUEUR_MAX - 20);

        return mb_substr($coupe, 0, (int) mb_strrpos($coupe, "\n"))."\n…";
    }
}
