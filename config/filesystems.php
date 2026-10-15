<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        // The app calls Storage::disk('public') everywhere for user uploads.
        // Laravel Cloud's compute is ephemeral, so in production this disk is
        // backed by the attached Laravel Cloud Object Storage bucket (S3-compatible,
        // powered by Cloudflare R2) instead of the local filesystem. Laravel Cloud
        // injects the AWS_* credentials automatically once a bucket is attached;
        // set PUBLIC_DISK_DRIVER=s3 as a custom env var to switch this disk over
        // (Cloud only injects credentials, it does not rewrite this config file).
        'public' => array_filter([
            'driver' => in_array(env('PUBLIC_DISK_DRIVER', env('MEDIA_DISK', 'local')), ['local', 's3'], true) ? env('PUBLIC_DISK_DRIVER', env('MEDIA_DISK', 'local')) : 'local',
            // A local filesystem path here would be wrong for the s3 driver (it
            // would prefix every object key with this absolute path and break
            // every existing "programs/xxx.png"-style path already in the DB),
            // so only set it for the local driver.
            'root' => (env('PUBLIC_DISK_DRIVER', env('MEDIA_DISK', 'local')) === 's3') ? null : storage_path('app/public'),
            // Must also switch on the driver, not just on whether AWS_URL happens
            // to be set - otherwise local dev would generate Cloud bucket URLs for
            // files it is actually serving from its own local /storage symlink.
            'url' => (env('PUBLIC_DISK_DRIVER', env('MEDIA_DISK', 'local')) === 's3')
                ? env('AWS_URL')
                : rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            // Cloudflare R2 (what Laravel Cloud Object Storage runs on) manages
            // visibility at the bucket level and rejects per-object ACL requests
            // with a "NotImplemented" error, so this must only be set for local.
            'visibility' => (env('PUBLIC_DISK_DRIVER', env('MEDIA_DISK', 'local')) === 's3') ? null : 'public',
            'throw' => false,
            'report' => false,
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'endpoint' => env('AWS_ENDPOINT'),
        ], fn ($value) => $value !== null),

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
