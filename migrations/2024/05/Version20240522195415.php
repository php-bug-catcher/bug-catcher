<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240522195415 extends AbstractMigration {
	public function getDescription(): string {
		return '';
	}

	public function up(Schema $schema): void {
		// this up() migration is auto-generated, please modify it to your needs
		$this->addSql('DROP INDEX full_idx ON log_record');
		$this->addSql('CREATE INDEX date_idx ON log_record (project_id, date)');
		$this->addSql('CREATE INDEX done_idx ON log_record (project_id, checked)');
	}

	public function down(Schema $schema): void {
		// this down() migration is auto-generated, please modify it to your needs
		$this->addSql('DROP INDEX date_idx ON log_record');
		$this->addSql('DROP INDEX done_idx ON log_record');
		$this->addSql('DROP INDEX message_idx ON log_record');
		$this->addSql('CREATE INDEX full_idx ON log_record (project_id, date, checked)');
	}
}
