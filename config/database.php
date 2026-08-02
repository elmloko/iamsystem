<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_0900_ai_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_uca1400_ai_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

        /*
        |----------------------------------------------------------------
        | Conexiones a los sistemas remotos administrados por el IAM
        |----------------------------------------------------------------
        | Todas viven en el mismo servidor central (172.65.10.108), salvo
        | trackpak que migró a un host distinto. Host/usuario/password
        | salen de variables de entorno; nombre de BD y motor son fijos
        | porque así están configurados en cada sistema real.
        */

        'sys_bolipost' => [
            'driver' => 'pgsql',
            'host' => env('SYSTEMS_DB_HOST'),
            'port' => 5432,
            'database' => 'bolipost',
            'username' => env('SYSTEMS_DB_USERNAME'),
            'password' => env('SYSTEMS_DB_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sys_apifacturacion' => [
            'driver' => 'pgsql',
            'host' => env('SYSTEMS_DB_HOST'),
            'port' => 5432,
            'database' => 'facturacion',
            'username' => env('SYSTEMS_DB_USERNAME'),
            'password' => env('SYSTEMS_DB_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sys_back_atencion' => [
            'driver' => 'pgsql',
            'host' => env('SYSTEMS_DB_HOST'),
            'port' => 5432,
            'database' => 'atencion_cliente',
            'username' => env('SYSTEMS_DB_USERNAME'),
            'password' => env('SYSTEMS_DB_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sys_backgescon' => [
            'driver' => 'pgsql',
            'host' => env('SYSTEMS_DB_HOST'),
            'port' => 5432,
            'database' => 'gescon',
            'username' => env('SYSTEMS_DB_USERNAME'),
            'password' => env('SYSTEMS_DB_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sys_sistema_documentos' => [
            'driver' => 'pgsql',
            'host' => env('SYSTEMS_DB_HOST'),
            'port' => 5432,
            'database' => 'Correos_documentos',
            'username' => env('SYSTEMS_DB_USERNAME'),
            'password' => env('SYSTEMS_DB_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sys_filatelia' => [
            'driver' => 'pgsql',
            'host' => env('SYSTEMS_DB_HOST'),
            'port' => 5432,
            'database' => 'filatelia',
            'username' => env('SYSTEMS_DB_USERNAME'),
            'password' => env('SYSTEMS_DB_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sys_helpdesk' => [
            'driver' => 'pgsql',
            'host' => env('SYSTEMS_DB_HOST'),
            'port' => 5432,
            'database' => 'helpdesk',
            'username' => env('SYSTEMS_DB_USERNAME'),
            'password' => env('SYSTEMS_DB_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sys_sitra' => [
            'driver' => 'pgsql',
            'host' => env('SYSTEMS_DB_HOST'),
            'port' => 5432,
            'database' => 'sitra',
            'username' => env('SYSTEMS_DB_USERNAME'),
            'password' => env('SYSTEMS_DB_PASSWORD'),
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sys_backcasillas' => [
            'driver' => 'mysql',
            'host' => env('SYSTEMS_DB_HOST'),
            'port' => 3306,
            'database' => 'casillasagbc2023',
            'username' => env('SYSTEMS_DB_USERNAME'),
            'password' => env('SYSTEMS_DB_PASSWORD'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false,
        ],

        'sys_calcupost' => [
            'driver' => 'mysql',
            'host' => env('SYSTEMS_DB_HOST'),
            'port' => 3306,
            'database' => 'calcupost',
            'username' => env('SYSTEMS_DB_USERNAME'),
            'password' => env('SYSTEMS_DB_PASSWORD'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false,
        ],

        'sys_sysreclamos' => [
            'driver' => 'mysql',
            'host' => env('SYSTEMS_DB_HOST'),
            'port' => 3306,
            'database' => 'sysreclamos',
            'username' => env('SYSTEMS_DB_USERNAME'),
            'password' => env('SYSTEMS_DB_PASSWORD'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false,
        ],

        'sys_trackpak' => [
            'driver' => 'mysql',
            'host' => env('TRACKPAK_DB_HOST'),
            'port' => 3306,
            'database' => 'trackpak',
            'username' => env('TRACKPAK_DB_USERNAME'),
            'password' => env('TRACKPAK_DB_PASSWORD'),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],

    ],

];
