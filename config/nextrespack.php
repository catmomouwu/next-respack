<?php

return [
    'version' => '1.0.1',
    'product' => 'Next Respack',

    /*
    |--------------------------------------------------------------------------
    | Installer paths
    |--------------------------------------------------------------------------
    | These paths can be overridden in tests. The installer never trusts a path
    | supplied by a browser request; all paths below are resolved server-side.
    */
    'env_file' => env('NEXT_RESPACK_ENV_FILE', base_path('.env')),
    'install_lock' => env('NEXT_RESPACK_INSTALL_LOCK', storage_path('app/installed.lock')),
    'install_state' => env('NEXT_RESPACK_INSTALL_STATE', storage_path('app/install-state.json')),
    'installer_key' => env('NEXT_RESPACK_INSTALLER_KEY', storage_path('app/.installer-key')),
    'force_installed' => (bool) env('NEXT_RESPACK_FORCE_INSTALLED', false),

    /*
    |--------------------------------------------------------------------------
    | Resource pack storage
    |--------------------------------------------------------------------------
    */
    'storage_root' => env('NEXT_RESPACK_STORAGE_ROOT', storage_path('app/private/resource-packs')),

    /*
    |--------------------------------------------------------------------------
    | Minecraft pack format data
    |--------------------------------------------------------------------------
    */
    'pack_format_source_url' => 'https://minecraft.wiki/w/Pack_format',
    'pack_format_api_url' => 'https://minecraft.wiki/api.php',
    'pack_format_parser_version' => '1.0.0',
    'pack_formats_bundled' => database_path('data/resource_pack_formats.json'),
    'pack_format_sync_interval' => 86400,

    /*
    |--------------------------------------------------------------------------
    | Upload/validation limits (defaults, overridable via system settings)
    |--------------------------------------------------------------------------
    */
    'max_upload_mb' => 512,
    'default_quota_mb' => 2048,
    'max_zip_entries' => 10000,
    'max_uncompressed_bytes' => 2 * 1024 * 1024 * 1024,
    'max_compression_ratio' => 1000,
    'max_mcmeta_bytes' => 1024 * 1024,
    'download_stream_chunk' => 8192,
];
