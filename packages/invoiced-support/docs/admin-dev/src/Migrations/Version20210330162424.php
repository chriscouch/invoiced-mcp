<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210330162424 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE cs_orders ADD address1 VARCHAR(255) DEFAULT NULL, ADD address2 VARCHAR(255) DEFAULT NULL, ADD city VARCHAR(255) DEFAULT NULL, ADD state VARCHAR(255) DEFAULT NULL, ADD postal_code VARCHAR(255) DEFAULT NULL, ADD country VARCHAR(2) DEFAULT NULL, ADD billing_email VARCHAR(255) DEFAULT NULL, ADD billing_phone VARCHAR(255) DEFAULT NULL, ADD invoiced_customer VARCHAR(255) DEFAULT NULL, ADD sow_amount NUMERIC(10, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE cs_orders DROP address1, DROP address2, DROP city, DROP state, DROP postal_code, DROP country, DROP billing_email, DROP billing_phone, DROP invoiced_customer, DROP sow_amount');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
