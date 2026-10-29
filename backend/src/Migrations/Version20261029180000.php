<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Région de l'utilisateur (PACA / Provencia par défaut) et codes de zones sur les catalogues.
 */
final class Version20261029180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Préférence de région (paca par défaut) ; Deal.region passe aux codes de zones';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE user_preference ADD region VARCHAR(32) DEFAULT 'paca'");
        // Ancien libellé du connecteur → code de zone. Les autres catalogues régionaux sont
        // recalculés à la prochaine collecte (connecteur anti_crise_catalogue).
        $this->addSql("UPDATE deal SET region = 'paca' WHERE region = 'Provencia (Sud-Est)'");
        $this->addSql('CREATE INDEX idx_deal_region ON deal (region)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_deal_region');
        $this->addSql("UPDATE deal SET region = 'Provencia (Sud-Est)' WHERE region = 'paca'");
        $this->addSql('ALTER TABLE user_preference DROP region');
    }
}
