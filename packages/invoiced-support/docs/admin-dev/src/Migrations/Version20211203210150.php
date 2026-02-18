<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211203210150 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE cs_contract_overage_thresholds (id INT AUTO_INCREMENT NOT NULL, contract_id INT NOT NULL, description VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, INDEX IDX_A1604B282576E0FD (contract_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE cs_contract_tenants (id INT AUTO_INCREMENT NOT NULL, contract_id INT NOT NULL, tenant_id INT NOT NULL, INDEX IDX_7219212F2576E0FD (contract_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE cs_contract_overage_thresholds ADD CONSTRAINT FK_A1604B282576E0FD FOREIGN KEY (contract_id) REFERENCES cs_contracts (id)');
        $this->addSql('ALTER TABLE cs_contract_tenants ADD CONSTRAINT FK_7219212F2576E0FD FOREIGN KEY (contract_id) REFERENCES cs_contracts (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE cs_contract_overage_thresholds');
        $this->addSql('DROP TABLE cs_contract_tenants');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
