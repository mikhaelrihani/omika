<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241026054726 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event RENAME INDEX event_date_status_active_day_idx TO Event_dateStatus_activeDay_idx');
        $this->addSql('ALTER TABLE event RENAME INDEX event_date_status_due_date_idx TO Event_dateStatus_dueDate_idx');
        $this->addSql('CREATE INDEX Tag_dateStatus_activeDay_idx ON tag (date_status, active_day)');
        $this->addSql('CREATE INDEX Tag_dateStatus_day_idx ON tag (date_status, day)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event RENAME INDEX event_datestatus_activeday_idx TO event_date_status_active_day_idx');
        $this->addSql('ALTER TABLE event RENAME INDEX event_datestatus_duedate_idx TO event_date_status_due_date_idx');
        $this->addSql('DROP INDEX Tag_dateStatus_activeDay_idx ON tag');
        $this->addSql('DROP INDEX Tag_dateStatus_day_idx ON tag');
    }
}
