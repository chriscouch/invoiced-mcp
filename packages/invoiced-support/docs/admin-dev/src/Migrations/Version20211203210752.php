<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211203210752 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE cs_contract_overage_thresholds DROP FOREIGN KEY FK_A1604B282576E0FD');
        $this->addSql('DROP INDEX idx_a1604b282576e0fd ON cs_contract_overage_thresholds');
        $this->addSql('CREATE INDEX IDX_75CB89372576E0FD ON cs_contract_overage_thresholds (contract_id)');
        $this->addSql('ALTER TABLE cs_contract_overage_thresholds ADD CONSTRAINT FK_A1604B282576E0FD FOREIGN KEY (contract_id) REFERENCES cs_contracts (id)');
        $this->addSql('ALTER TABLE cs_contract_tenants DROP FOREIGN KEY FK_7219212F2576E0FD');
        $this->addSql('DROP INDEX idx_7219212f2576e0fd ON cs_contract_tenants');
        $this->addSql('CREATE INDEX IDX_E1BFF3AA2576E0FD ON cs_contract_tenants (contract_id)');
        $this->addSql('ALTER TABLE cs_contract_tenants ADD CONSTRAINT FK_7219212F2576E0FD FOREIGN KEY (contract_id) REFERENCES cs_contracts (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE cs_contract_overage_thresholds DROP FOREIGN KEY FK_75CB89372576E0FD');
        $this->addSql('DROP INDEX idx_75cb89372576e0fd ON cs_contract_overage_thresholds');
        $this->addSql('CREATE INDEX IDX_A1604B282576E0FD ON cs_contract_overage_thresholds (contract_id)');
        $this->addSql('ALTER TABLE cs_contract_overage_thresholds ADD CONSTRAINT FK_75CB89372576E0FD FOREIGN KEY (contract_id) REFERENCES cs_contracts (id)');
        $this->addSql('ALTER TABLE cs_contract_tenants DROP FOREIGN KEY FK_E1BFF3AA2576E0FD');
        $this->addSql('DROP INDEX idx_e1bff3aa2576e0fd ON cs_contract_tenants');
        $this->addSql('CREATE INDEX IDX_7219212F2576E0FD ON cs_contract_tenants (contract_id)');
        $this->addSql('ALTER TABLE cs_contract_tenants ADD CONSTRAINT FK_E1BFF3AA2576E0FD FOREIGN KEY (contract_id) REFERENCES cs_contracts (id)');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
