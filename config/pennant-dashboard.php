<?php

return [

    /*
    | The dashboard lives at /{path}. Turn it off with PENNANT_DASHBOARD_ENABLED=false
    | (the Rollout helper and the audit log keep working).
    */
    'enabled' => env('PENNANT_DASHBOARD_ENABLED', true),

    'path' => env('PENNANT_DASHBOARD_PATH', 'pennant'),

    'domain' => env('PENNANT_DASHBOARD_DOMAIN'),

    // The gates viewPennantDashboard and managePennantDashboard are checked on top of these.
    'middleware' => ['web'],

    // When true, nobody can change anything from the dashboard, whatever the gates say.
    'read_only' => env('PENNANT_DASHBOARD_READ_ONLY', false),

    /*
    | Class-based features in app/Features are only known to Pennant once they are used or
    | discovered. When this is true, the dashboard calls Feature::discover() for this
    | namespace and path (only while showing the dashboard, never on your app's own requests).
    */
    'discover' => [
        'enabled' => true,
        'namespace' => 'App\\Features',
        'path' => null, // null = app_path('Features')
    ],

    /*
    | Model types you can pick as a scope in the dashboard (user, team, any Eloquent model).
    | Types already found in Pennant's stored values are added automatically.
    | 'label' is a list of attributes tried in order to show a readable name.
    */
    'scopes' => [
        'App\\Models\\User' => ['label' => ['name', 'email']],
        // 'App\\Models\\Team' => ['label' => ['name']],
    ],

    'label_attributes' => ['name', 'email', 'title', 'slug'],

    // Search box for model scopes: these columns are matched with LIKE when they exist.
    'search_columns' => ['name', 'email'],

    'audit' => [
        // Also log changes made by your own code (Feature::activate(), pennant:purge, ...).
        'code_changes' => true,

        // Entries older than this are removed by `php artisan model:prune` (null keeps everything).
        'retention_days' => 180,
    ],

    // The package's own tables: rollout rules, dashboard overrides and the audit log.
    'connection' => env('PENNANT_DASHBOARD_DB_CONNECTION'),

    'tables' => [
        'rules' => 'pennant_dashboard_rules',
        'overrides' => 'pennant_dashboard_overrides',
        'audit' => 'pennant_dashboard_audit',
    ],

    // Load the package migrations automatically. Set false after publishing them.
    'run_migrations' => true,

    'per_page' => 50,

    /*
    | Extra nav tabs, added by the app or by other packages. Entries whose route does not exist
    | are skipped. 'active' is a route-name pattern, defaulting to the route itself.
    | ['label' => 'Experiments', 'route' => 'pennant-experiments.index', 'active' => 'pennant-experiments.*']
    */
    'tabs' => [],
];
