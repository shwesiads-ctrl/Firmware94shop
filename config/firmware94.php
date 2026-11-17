<?php

// Firmware94shop platform-specific configuration (Phase 2).
// Every value here has a safe development default and can be overridden via .env.
// Nothing here is a real credential — see docs/SECURITY.md before going live.

return [
    'currency' => env('F94_CURRENCY', 'USD'),

    'brand' => [
        'name' => 'Firmware94shop',
        'short_name' => 'F94',
        'colors' => [
            'primary' => '#0B1F3A',   // Deep Navy
            'secondary' => '#0F766E', // Teal
            'accent' => '#06B6D4',    // Cyan
            'background' => '#F8FAFC',
            'admin_sidebar' => '#0A1830',
        ],
    ],

    // Supplier adapters. "mode" is either "mock" or "live". Phase 2 ships only
    // "mock" drivers — switching to "live" requires real, documented
    // credentials supplied by the project owner (see docs/SECURITY.md and
    // app/Integrations/Suppliers/README.md). Never flip this without them.
    'suppliers' => [
        'dhru_classic' => [
            'mode' => env('F94_SUPPLIER_DHRU_CLASSIC_MODE', 'mock'),
            'adapter' => \App\Integrations\Suppliers\DhruClassic\DhruClassicAdapter::class,
            'mock_adapter' => \App\Integrations\Suppliers\DhruClassic\MockDhruClassicSupplier::class,
        ],
        'gsm_theme' => [
            'mode' => env('F94_SUPPLIER_GSM_THEME_MODE', 'mock'),
            'adapter' => \App\Integrations\Suppliers\GsmTheme\GsmThemeAdapter::class,
            'mock_adapter' => \App\Integrations\Suppliers\GsmTheme\MockGsmThemeSupplier::class,
        ],
        'fusion_pro' => [
            'mode' => env('F94_SUPPLIER_FUSION_PRO_MODE', 'mock'),
            'adapter' => \App\Integrations\Suppliers\FusionPro\FusionProAdapter::class,
            'mock_adapter' => \App\Integrations\Suppliers\FusionPro\MockFusionProSupplier::class,
        ],
        'gsmss' => [
            'mode' => env('F94_SUPPLIER_GSMSS_MODE', 'mock'),
            'adapter' => \App\Integrations\Suppliers\Gsmss\GsmssAdapter::class,
            'mock_adapter' => \App\Integrations\Suppliers\Gsmss\MockGsmssSupplier::class,
        ],
    ],

    'api' => [
        'rate_limit_per_minute' => (int) env('F94_API_RATE_LIMIT_PER_MINUTE', 60),
        'ip_allowlist_enabled_by_default' => (bool) env('F94_API_DEFAULT_IP_ALLOWLIST_ENABLED', false),
    ],

    'payments' => [
        'methods' => [
            'usdt_trc20' => [
                'label' => 'USDT (TRC20)',
                'address' => env('F94_PAY_USDT_TRC20_ADDRESS'), // never hardcode; empty until admin configures it
            ],
            'usdt_bep20' => [
                'label' => 'USDT (BEP20)',
                'address' => env('F94_PAY_USDT_BEP20_ADDRESS'),
            ],
            'manual' => [
                'label' => 'Manual Deposit',
                'enabled' => (bool) env('F94_PAY_MANUAL_DEPOSIT_ENABLED', true),
            ],
        ],
        // Phase 2 note: no automatic on-chain verification. Every deposit is
        // pending until an admin reviews the proof and approves it.
        'auto_verify_blockchain' => false,
    ],

    'roles' => [
        'super-admin', 'admin', 'staff', 'distributor', 'reseller', 'customer',
    ],
];
