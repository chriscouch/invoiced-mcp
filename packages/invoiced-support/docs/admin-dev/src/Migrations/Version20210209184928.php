<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210209184928 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE cs_orders ADD attachment VARCHAR(255) DEFAULT NULL, ADD attachment_name VARCHAR(255) DEFAULT NULL, CHANGE provisioning_email provisioning_email VARCHAR(255) DEFAULT NULL, CHANGE reseller_id reseller_id VARCHAR(255) DEFAULT NULL, CHANGE referred_by referred_by VARCHAR(255) DEFAULT NULL, CHANGE sales_rep sales_rep VARCHAR(255) DEFAULT NULL, CHANGE date_fulfilled date_fulfilled DATETIME DEFAULT NULL, CHANGE fulfilled_by fulfilled_by VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE cs_orders DROP attachment, DROP attachment_name, CHANGE provisioning_email provisioning_email VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_unicode_ci`, CHANGE reseller_id reseller_id VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_unicode_ci`, CHANGE referred_by referred_by VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_unicode_ci`, CHANGE sales_rep sales_rep VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_unicode_ci`, CHANGE date_fulfilled date_fulfilled DATETIME DEFAULT \'NULL\', CHANGE fulfilled_by fulfilled_by VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_unicode_ci`');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
