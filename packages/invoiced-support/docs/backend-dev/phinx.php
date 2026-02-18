<?php

use App\Core\Multitenant\MultitenantModelMigration;

// parse DSN because this functionality is currently broken in phinx
// https://github.com/cakephp/phinx/issues/1406
$database = parse_url(getenv('PHINX_DATABASE_URL'));

$phinxConfig = [
    'migration_base_class' => MultitenantModelMigration::class,
    'paths' => [
        'migrations' => getenv('PHINX_MIGRATION_PATH'),
    ],
    'environments' => [
        'default_migration_table' => 'Migrations',
        'default_environment' => 'main',
        'main' => [
            'adapter' => $database['scheme'],
            'user' => $database['user'],
            'pass' => $database['pass'],
            'host' => $database['host'],
            'port' => $database['port'],
            'name' => str_replace('/', '', $database['path']),
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
        ],
    ],
];

return $phinxConfig;
