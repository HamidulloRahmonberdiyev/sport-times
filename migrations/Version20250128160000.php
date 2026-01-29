<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * telegram_subscriber, daily_broadcast_log, game.reminder_sent_at.
 */
final class Version20250128160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'telegram_subscriber, daily_broadcast_log jadvallari va game.reminder_sent_at ustuni.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE telegram_subscriber (
            id INT AUTO_INCREMENT NOT NULL,
            chat_id BIGINT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            is_active TINYINT(1) DEFAULT 1 NOT NULL,
            UNIQUE INDEX uq_telegram_subscriber_chat_id (chat_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE daily_broadcast_log (
            id INT AUTO_INCREMENT NOT NULL,
            broadcast_date DATE NOT NULL COMMENT \'(DC2Type:date_immutable)\',
            sent_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX uq_daily_broadcast_log_date (broadcast_date),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE game ADD reminder_sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\' AFTER venue');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game DROP reminder_sent_at');
        $this->addSql('DROP TABLE daily_broadcast_log');
        $this->addSql('DROP TABLE telegram_subscriber');
    }
}
