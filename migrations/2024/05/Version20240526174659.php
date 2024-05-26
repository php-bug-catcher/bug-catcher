<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240526174659 extends AbstractMigration {
	public function getDescription(): string {
		return '';
	}

	public function up(Schema $schema): void {
		// this up() migration is auto-generated, please modify it to your needs
		$this->addSql('DROP INDEX done_idx ON log_record');
		$this->addSql('DROP INDEX message_idx ON log_record');
		$this->addSql('ALTER TABLE log_record ADD status VARCHAR(25) NOT NULL, DROP checked');
		$this->addSql('CREATE INDEX done_idx ON log_record (project_id, status)');
		$this->addSql('CREATE INDEX message_idx ON log_record (status(1), message(255))');
		$this->addSql('update log_record set status=\'resolved\'');
	}

	public function down(Schema $schema): void {
		// this down() migration is auto-generated, please modify it to your needs
		$this->addSql('DROP INDEX done_idx ON log_record');
		$this->addSql('DROP INDEX message_idx ON log_record');
		$this->addSql('ALTER TABLE log_record ADD checked TINYINT(1) NOT NULL, DROP status');
		$this->addSql('CREATE INDEX done_idx ON log_record (project_id, checked)');
		$this->addSql('CREATE INDEX message_idx ON log_record (checked, message(255))');
	}
}
