<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210118034638 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE cs_orders (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(50) NOT NULL, date DATE NOT NULL, customer VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, provisioning_email VARCHAR(255) DEFAULT NULL, reseller_id VARCHAR(255) DEFAULT NULL, referred_by VARCHAR(255) DEFAULT NULL, sales_rep VARCHAR(255) DEFAULT NULL, date_fulfilled DATETIME DEFAULT NULL, fulfilled_by VARCHAR(255) DEFAULT NULL, status VARCHAR(50) NOT NULL, notes LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE cs_orders');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
