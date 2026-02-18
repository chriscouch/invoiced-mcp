<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210321211818 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE cs_orders ADD new_account_id INT DEFAULT NULL, CHANGE sales_rep sales_rep VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE cs_orders ADD CONSTRAINT FK_2048FAAF4654BC76 FOREIGN KEY (new_account_id) REFERENCES cs_new_accounts (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_2048FAAF4654BC76 ON cs_orders (new_account_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE cs_orders DROP FOREIGN KEY FK_2048FAAF4654BC76');
        $this->addSql('DROP INDEX UNIQ_2048FAAF4654BC76 ON cs_orders');
        $this->addSql('ALTER TABLE cs_orders DROP new_account_id, CHANGE sales_rep sales_rep VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
