<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210321194017 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('UPDATE cs_orders SET start_date=date WHERE start_date IS NULL');
        $this->addSql('CREATE TABLE cs_new_accounts (id INT AUTO_INCREMENT NOT NULL, company_name VARCHAR(255) NOT NULL, plan VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, country VARCHAR(2) NOT NULL, first_name VARCHAR(255) DEFAULT NULL, last_name VARCHAR(255) DEFAULT NULL, billing_system VARCHAR(25) NOT NULL, invoiced_customer VARCHAR(255) DEFAULT NULL, stripe_customer VARCHAR(255) DEFAULT NULL, reseller_id INT DEFAULT NULL, referred_by VARCHAR(255) DEFAULT NULL, modules LONGTEXT NOT NULL COMMENT \'(DC2Type:simple_array)\', num_invoices INT NOT NULL, num_customers INT NOT NULL, num_users INT NOT NULL, customer_overage INT NOT NULL, invoice_overage INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE cs_orders DROP description, DROP provisioning_email, DROP referred_by, CHANGE reseller_id reseller_id VARCHAR(255) DEFAULT NULL, CHANGE sales_rep sales_rep VARCHAR(255) DEFAULT NULL, CHANGE date_fulfilled date_fulfilled DATETIME DEFAULT NULL, CHANGE fulfilled_by fulfilled_by VARCHAR(255) DEFAULT NULL, CHANGE attachment attachment VARCHAR(255) DEFAULT NULL, CHANGE attachment_name attachment_name VARCHAR(255) DEFAULT NULL, CHANGE start_date start_date DATE NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE cs_new_accounts');
        $this->addSql('ALTER TABLE cs_orders ADD description VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, ADD provisioning_email VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_unicode_ci`, ADD referred_by VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_unicode_ci`, CHANGE reseller_id reseller_id VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_unicode_ci`, CHANGE sales_rep sales_rep VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_unicode_ci`, CHANGE start_date start_date DATE DEFAULT \'NULL\', CHANGE date_fulfilled date_fulfilled DATETIME DEFAULT \'NULL\', CHANGE fulfilled_by fulfilled_by VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_unicode_ci`, CHANGE attachment attachment VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_unicode_ci`, CHANGE attachment_name attachment_name VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT \'NULL\' COLLATE `utf8mb4_unicode_ci`');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
