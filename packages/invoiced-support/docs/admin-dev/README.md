Invoiced Customer Service Application 
==================

Admin panel for managing Invoiced's customer data. 

## Developing

### Requirements

- PHP 8.3
- MariaDB 10.5
- [Invoiced project](https://github.com/Invoiced/invoiced) installed and running
- [Docker](https://docker.com)

## Setup Instructions

Follow these steps to set up a development server locally:

1. **Clone the project**

       git clone git@github.com:Invoiced/csadmin.git
       cd csadmin
       git checkout dev

2. **Install Composer Dependencies**

   PHP package management is handled by composer. In order to install all third-party PHP dependencies run:

       composer install

3. **App Configuration**

   You will need to populate `.env.local` with any keys and secrets for services you plan to use. You can use `.env` as a template.

       cp .env .env.local

5. **Static Assets**

   Download and compile the static assets with:

       yarn install
       yarn encore dev

6. **Docker**

   You can now start the web server and database services with docker:

       docker-compose up -d

7. **Database Migrations**

   The admin panel database schema can be installed with Doctrine migrations:
   
       docker-compose exec php-fpm bin/console doctrine:migrations:migrate --em=CustomerAdmin_ORM --no-interaction

You can now open up the application in your web browser at [localhost:1237](http://localhost:1237). You can [create a user account here](http://localhost:1237/register).

## Database Permissions

These permissions are needed for the database user in order to function. These permissions must be granted instead of `%.%` in a production environment. `InvoicedSandbox` should be replaced with the name of the main database.

    GRANT SELECT ON InvoicedSandbox.AccountSecurityEvents TO 'csadmin'@'%';
    GRANT SELECT ON InvoicedSandbox.AdyenAccounts TO 'csadmin'@'%';
    GRANT SELECT ON InvoicedSandbox.CanceledCompanies TO 'csadmin'@'%';
    GRANT SELECT ON InvoicedSandbox.Invoices TO 'csadmin'@'%';
    GRANT SELECT ON InvoicedSandbox.MarketingAttributions TO 'csadmin'@'%';
    GRANT SELECT ON InvoicedSandbox.OverageCharges TO 'csadmin'@'%';
    GRANT SELECT ON InvoicedSandbox.PaymentMethods TO 'csadmin'@'%';
    GRANT SELECT, DELETE ON InvoicedSandbox.InstalledProducts TO 'csadmin'@'%';
    GRANT SELECT, DELETE ON InvoicedSandbox.MerchantAccounts TO 'csadmin'@'%';
    GRANT SELECT, INSERT, UPDATE ON InvoicedSandbox.BillingProfiles TO 'csadmin'@'%';
    GRANT SELECT, INSERT, UPDATE ON InvoicedSandbox.Templates TO 'csadmin'@'%';
    GRANT SELECT, INSERT, UPDATE, DELETE ON InvoicedSandbox.AccountingSyncFieldMappings TO 'csadmin'@'%';
    GRANT SELECT, INSERT, UPDATE, DELETE ON InvoicedSandbox.AccountingSyncReadFilters TO 'csadmin'@'%';
    GRANT SELECT, INSERT, UPDATE, DELETE ON InvoicedSandbox.Charges TO 'csadmin'@'%';
    GRANT SELECT, INSERT, UPDATE, DELETE ON InvoicedSandbox.CompanyNotes TO 'csadmin'@'%';
    GRANT SELECT, INSERT, UPDATE, DELETE ON InvoicedSandbox.Dashboards TO 'csadmin'@'%';
    GRANT SELECT, INSERT, UPDATE, DELETE ON InvoicedSandbox.Features TO 'csadmin'@'%';
    GRANT SELECT, INSERT, UPDATE, DELETE ON InvoicedSandbox.Members TO 'csadmin'@'%';
    GRANT SELECT, INSERT, UPDATE, DELETE ON InvoicedSandbox.ProductFeatures TO 'csadmin'@'%';
    GRANT SELECT, INSERT, UPDATE, DELETE ON InvoicedSandbox.ProductPricingPlans TO 'csadmin'@'%';
    GRANT SELECT, INSERT, UPDATE, DELETE ON InvoicedSandbox.Products TO 'csadmin'@'%';
    GRANT SELECT, INSERT, UPDATE, DELETE ON InvoicedSandbox.PurchasePageContexts TO 'csadmin'@'%';
    GRANT SELECT, INSERT, UPDATE, DELETE ON InvoicedSandbox.Quotas TO 'csadmin'@'%';
    GRANT SELECT, INSERT, UPDATE, DELETE ON InvoicedSandbox.UsagePricingPlans TO 'csadmin'@'%';
    GRANT SELECT, INSERT, UPDATE, DELETE ON InvoicedSandbox.Users TO 'csadmin'@'%';
    GRANT SELECT, UPDATE ON InvoicedSandbox.AccountingSyncProfiles TO 'csadmin'@'%';
    GRANT SELECT, UPDATE ON InvoicedSandbox.AccountsPayableSettings TO 'csadmin'@'%';
    GRANT SELECT, UPDATE ON InvoicedSandbox.AccountsReceivableSettings TO 'csadmin'@'%';
    GRANT SELECT, UPDATE ON InvoicedSandbox.BilledVolumes TO 'csadmin'@'%';
    GRANT SELECT, UPDATE ON InvoicedSandbox.CashApplicationSettings TO 'csadmin'@'%';
    GRANT SELECT, UPDATE ON InvoicedSandbox.Companies TO 'csadmin'@'%';
    GRANT SELECT, UPDATE ON InvoicedSandbox.CompanySamlSettings TO 'csadmin'@'%';
    GRANT SELECT, UPDATE ON InvoicedSandbox.CustomerPortalSettings TO 'csadmin'@'%';
    GRANT SELECT, UPDATE ON InvoicedSandbox.CustomerVolumes TO 'csadmin'@'%';
    GRANT SELECT, UPDATE ON InvoicedSandbox.IntacctSyncProfiles TO 'csadmin'@'%';
    GRANT SELECT, UPDATE ON InvoicedSandbox.InvoiceVolumes TO 'csadmin'@'%';
    GRANT SELECT, UPDATE ON InvoicedSandbox.NetSuiteSyncProfiles TO 'csadmin'@'%';
    GRANT SELECT, UPDATE ON InvoicedSandbox.QuickBooksDesktopSyncProfiles TO 'csadmin'@'%';
    GRANT SELECT, UPDATE ON InvoicedSandbox.QuickBooksOnlineSyncProfiles TO 'csadmin'@'%';
    GRANT SELECT, UPDATE ON InvoicedSandbox.SubscriptionBillingSettings TO 'csadmin'@'%';
    GRANT SELECT, UPDATE ON InvoicedSandbox.XeroSyncProfiles TO 'csadmin'@'%';
    GRANT SELECT, UPDATE, DELETE ON InvoicedSandbox.BlockListEmailAddresses TO 'csadmin'@'%';
    GRANT SELECT, UPDATE, DELETE ON InvoicedSandbox.InitiatedChargeDocuments TO 'csadmin'@'%';
    GRANT SELECT, UPDATE, DELETE ON InvoicedSandbox.InitiatedCharges TO 'csadmin'@'%';

    GRANT ALL PRIVILEGES ON CustomerAdmin.* TO 'csadmin'@'%';

    GRANT SELECT ON InvoicedSandbox.* TO 'csadmin_query'@'%';

    FLUSH PRIVILEGES;

### Code Style

It is recommended to follow [Symfony Coding Standards](https://symfony.com/doc/current/contributing/code/standards.html)
