<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240524142950 extends AbstractMigration {
	public function getDescription(): string {
		return '';
	}

	public function up(Schema $schema): void {
		// this up() migration is auto-generated, please modify it to your needs
		$this->addSql('DROP INDEX message_idx ON log_record');
		$this->addSql('ALTER TABLE log_record ADD stack_trace LONGTEXT DEFAULT NULL');
		$this->addSql('CREATE INDEX message_idx ON log_record (checked(1), message(255))');
	}

	public function down(Schema $schema): void {
		// this down() migration is auto-generated, please modify it to your needs
		$this->addSql('DROP INDEX message_idx ON log_record');
		$this->addSql('ALTER TABLE log_record DROP stack_trace');
		$this->addSql('CREATE INDEX message_idx ON log_record (checked, message(255))');
	}
}
