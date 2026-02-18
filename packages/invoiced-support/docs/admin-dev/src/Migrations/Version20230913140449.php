<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230913140449 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE cs_orders ADD product_pricing_plans LONGTEXT NOT NULL COMMENT \'(DC2Type:simple_array)\', ADD usage_pricing_plans LONGTEXT NOT NULL COMMENT \'(DC2Type:simple_array)\', ADD new_user_count INT DEFAULT NULL, ADD billing_interval INT DEFAULT NULL, ADD tenant_id INT DEFAULT NULL, ADD changeset LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf('mysql' !== $this->connection->getDatabasePlatform()->getName(), 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE cs_orders DROP product_pricing_plans, DROP usage_pricing_plans, DROP new_user_count, DROP billing_interval, DROP tenant_id, DROP changeset');
    }
}
