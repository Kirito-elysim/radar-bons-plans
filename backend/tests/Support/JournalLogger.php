<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Psr\Log\AbstractLogger;

/**
 * Logger de test qui conserve les messages pour les assertions.
 */
final class JournalLogger extends AbstractLogger
{
    /** @var list<array{niveau: string, message: string}> */
    public array $messages = [];

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $remplacements = [];
        foreach ($context as $cle => $valeur) {
            if (\is_scalar($valeur) || $valeur instanceof \Stringable) {
                $remplacements['{'.$cle.'}'] = (string) $valeur;
            }
        }
        $this->messages[] = ['niveau' => (string) $level, 'message' => strtr((string) $message, $remplacements)];
    }

    public function contient(string $niveau, string $extrait): bool
    {
        foreach ($this->messages as $m) {
            if ($m['niveau'] === $niveau && str_contains($m['message'], $extrait)) {
                return true;
            }
        }

        return false;
    }
}
