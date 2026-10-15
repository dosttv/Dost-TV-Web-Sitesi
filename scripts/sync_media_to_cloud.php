<?php

/**
 * DOST TV - Local Media to Cloud Storage Sync Script
 * 
 * Usage:
 *   php scripts/sync_media_to_cloud.php [--disk=s3|public] [--dry-run]
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

$options = getopt('', ['disk::', 'dry-run']);
$targetDisk = $options['disk'] ?? env('FILESYSTEM_DISK', 'public');
$isDryRun = isset($options['dry-run']);

echo "===================================================\n";
echo " DOST TV MEDIA SYNC TO CLOUD STORAGE\n";
echo " Target Disk: {$targetDisk}\n";
echo " Mode: " . ($isDryRun ? "DRY-RUN (Simulated)" : "LIVE EXECUTION") . "\n";
echo "===================================================\n\n";

$sourceDir = storage_path('app/public');
if (!file_exists($sourceDir)) {
    echo "ERROR: Source directory not found: {$sourceDir}\n";
    exit(1);
}

$files = File::allFiles($sourceDir);
$stats = [
    'total' => 0,
    'uploaded' => 0,
    'skipped' => 0,
    'conflicts' => 0,
    'failed' => 0,
    'bytes_uploaded' => 0,
];

$conflictList = [];
$failedList = [];

foreach ($files as $file) {
    if ($file->getFilename() === '.gitignore' || str_starts_with($file->getFilename(), '.')) {
        continue;
    }

    $stats['total']++;
    $relativePath = $file->getRelativePathname();
    $localFullPath = $file->getRealPath();
    $fileSize = $file->getSize();
    $localHash = hash_file('sha256', $localFullPath);

    $disk = Storage::disk($targetDisk);

    $exists = false;
    try {
        $exists = $disk->exists($relativePath);
    } catch (\Throwable $e) {
    }

    if ($exists) {
        try {
            $remoteStream = $disk->readStream($relativePath);
            $remoteHash = $remoteStream ? hash('sha256', stream_get_contents($remoteStream)) : null;
            if (is_resource($remoteStream)) fclose($remoteStream);

            if ($remoteHash === $localHash) {
                echo "[SKIP] {$relativePath} (Already exists with identical SHA-256)\n";
                $stats['skipped']++;
                continue;
            } else {
                echo "[CONFLICT] {$relativePath} (File exists on target with DIFFERENT content - skipping overwrite)\n";
                $stats['conflicts']++;
                $conflictList[] = $relativePath;
                continue;
            }
        } catch (\Throwable $e) {
            echo "[CONFLICT] {$relativePath} (File exists on target - skipped overwrite for safety)\n";
            $stats['conflicts']++;
            $conflictList[] = $relativePath;
            continue;
        }
    }

    if ($isDryRun) {
        echo "[DRY-RUN UPLOAD] {$relativePath} (" . round($fileSize / 1024, 1) . " KB)\n";
        $stats['uploaded']++;
        $stats['bytes_uploaded'] += $fileSize;
        continue;
    }

    $success = false;
    $attempts = 0;
    while ($attempts < 3 && !$success) {
        $attempts++;
        try {
            $stream = fopen($localFullPath, 'r');
            $success = $disk->put($relativePath, $stream, ['visibility' => 'public']);
            if (is_resource($stream)) fclose($stream);
        } catch (\Throwable $e) {
            echo "[RETRY {$attempts}/3] Failed to upload {$relativePath}: " . $e->getMessage() . "\n";
            sleep(1);
        }
    }

    if ($success) {
        echo "[UPLOADED] {$relativePath} (" . round($fileSize / 1024, 1) . " KB)\n";
        $stats['uploaded']++;
        $stats['bytes_uploaded'] += $fileSize;
    } else {
        echo "[FAILED] {$relativePath} after 3 attempts\n";
        $stats['failed']++;
        $failedList[] = $relativePath;
    }
}

echo "\n===================================================\n";
echo " FINAL SYNC SUMMARY\n";
echo "===================================================\n";
echo " Total Files Checked: {$stats['total']}\n";
echo " Uploaded:            {$stats['uploaded']}\n";
echo " Skipped (Same Hash): {$stats['skipped']}\n";
echo " Conflicts Skipped:   {$stats['conflicts']}\n";
echo " Failed Uploads:      {$stats['failed']}\n";
echo " Total Uploaded Size: " . round($stats['bytes_uploaded'] / (1024 * 1024), 2) . " MB\n";
echo "===================================================\n\n";

if (!empty($conflictList)) {
    echo "CONFLICTED FILES (Not Overwritten):\n";
    foreach ($conflictList as $c) echo " - {$c}\n";
    echo "\n";
}

if (!empty($failedList)) {
    echo "FAILED FILES:\n";
    foreach ($failedList as $f) echo " - {$f}\n";
    echo "\n";
}

if ($stats['failed'] === 0 && $stats['total'] > 0) {
    echo "MEDIA MIGRATION SUCCESSFUL\n";
}
