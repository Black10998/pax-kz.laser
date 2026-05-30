#!/usr/bin/env php
<?php
/**
 * Build protected customer ZIP package with signed manifest.
 *
 * Usage:
 *   php tools/build-protected-release.php --version=2.21.0 --build=2.21.0.x --output=dist
 *
 * Optional env:
 *   PCKZCE_RELEASE_SIGNING_KEY=your-long-random-secret
 */

declare(strict_types=1);

$root = dirname(__DIR__);

$opts = getopt('', array('version:', 'build::', 'output::', 'channel::', 'slug::'));
$version = isset($opts['version']) ? (string) $opts['version'] : '';
$build = isset($opts['build']) ? (string) $opts['build'] : $version;
$outputDir = isset($opts['output']) ? (string) $opts['output'] : ($root . '/dist');
$channel = isset($opts['channel']) ? (string) $opts['channel'] : 'customer-protected';
$slug = isset($opts['slug']) ? (string) $opts['slug'] : 'pckz-canonical-engine';

if ($version === '') {
	fwrite(STDERR, "Missing required --version\n");
	exit(1);
}

$excludes = array(
	'.git',
	'.github',
	'.cursor',
	'dist',
	'tmp',
	'node_modules',
	'tests',
	'import',
	'tools',
	'.DS_Store',
	'.env',
);

$tempRoot = sys_get_temp_dir() . '/pckzce-release-' . bin2hex(random_bytes(6));
$stageDir = $tempRoot . '/' . $slug;
if (!mkdir($stageDir, 0775, true) && !is_dir($stageDir)) {
	fwrite(STDERR, "Could not create stage dir: {$stageDir}\n");
	exit(1);
}

$iter = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
	RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iter as $fileInfo) {
	$abs = $fileInfo->getPathname();
	$rel = ltrim(str_replace($root, '', $abs), DIRECTORY_SEPARATOR);
	if ($rel === '') {
		continue;
	}
	$segments = explode(DIRECTORY_SEPARATOR, $rel);
	$skip = false;
	foreach ($segments as $seg) {
		if (in_array($seg, $excludes, true)) {
			$skip = true;
			break;
		}
	}
	if ($skip) {
		continue;
	}
	$dest = $stageDir . '/' . $rel;
	if ($fileInfo->isDir()) {
		if (!is_dir($dest) && !mkdir($dest, 0775, true) && !is_dir($dest)) {
			fwrite(STDERR, "Failed to mkdir {$dest}\n");
			exit(1);
		}
		continue;
	}
	$destDir = dirname($dest);
	if (!is_dir($destDir) && !mkdir($destDir, 0775, true) && !is_dir($destDir)) {
		fwrite(STDERR, "Failed to mkdir {$destDir}\n");
		exit(1);
	}
	if (!copy($abs, $dest)) {
		fwrite(STDERR, "Failed to copy {$rel}\n");
		exit(1);
	}
}

$manifestFiles = array();
$stageIter = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($stageDir, FilesystemIterator::SKIP_DOTS),
	RecursiveIteratorIterator::LEAVES_ONLY
);
foreach ($stageIter as $fileInfo) {
	$filePath = $fileInfo->getPathname();
	$rel = ltrim(str_replace($stageDir, '', $filePath), DIRECTORY_SEPARATOR);
	if ($rel === 'RELEASE_MANIFEST.json') {
		continue;
	}
	$manifestFiles[$rel] = hash_file('sha256', $filePath);
}
ksort($manifestFiles);

$manifest = array(
	'slug'       => $slug,
	'version'    => $version,
	'build'      => $build,
	'channel'    => $channel,
	'created_at' => gmdate('c'),
	'files'      => $manifestFiles,
);

$manifestJson = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
if (!is_string($manifestJson)) {
	fwrite(STDERR, "Failed to encode manifest\n");
	exit(1);
}

$signingKey = getenv('PCKZCE_RELEASE_SIGNING_KEY') ?: '';
$signature = '';
if ($signingKey !== '') {
	$signature = hash_hmac('sha256', $manifestJson, (string) $signingKey);
}
$manifest['signature'] = $signature;
$manifest['signature_alg'] = $signature ? 'hmac-sha256' : 'none';
$manifest['signature_hint'] = $signature ? 'signed' : 'unsigned';

$finalManifest = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
if (!is_string($finalManifest) || file_put_contents($stageDir . '/RELEASE_MANIFEST.json', $finalManifest) === false) {
	fwrite(STDERR, "Failed to write RELEASE_MANIFEST.json\n");
	exit(1);
}

if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
	fwrite(STDERR, "Failed to create output dir {$outputDir}\n");
	exit(1);
}

$zipPath = rtrim($outputDir, DIRECTORY_SEPARATOR) . '/' . $slug . '-' . $version . '-protected.zip';
if (class_exists('ZipArchive')) {
	$zip = new ZipArchive();
	if (true !== $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
		fwrite(STDERR, "Failed to open zip {$zipPath}\n");
		exit(1);
	}
	foreach (
		new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($stageDir, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::LEAVES_ONLY
		) as $fileInfo
	) {
		$filePath = $fileInfo->getPathname();
		$rel = ltrim(str_replace($tempRoot . '/', '', $filePath), DIRECTORY_SEPARATOR);
		$zip->addFile($filePath, $rel);
	}
	$zip->close();
} else {
	$zipBin = trim((string) shell_exec('command -v zip'));
	if ($zipBin === '') {
		fwrite(STDERR, "Neither ZipArchive nor system zip command is available.\n");
		exit(1);
	}
	$cmd = sprintf(
		'cd %s && %s -qr %s %s',
		escapeshellarg($tempRoot),
		escapeshellarg($zipBin),
		escapeshellarg($zipPath),
		escapeshellarg($slug)
	);
	exec($cmd, $out, $code);
	if ($code !== 0) {
		fwrite(STDERR, "zip command failed with code {$code}\n");
		exit(1);
	}
}

echo "Built protected release: {$zipPath}\n";
echo "Manifest signature: " . ($signature ? 'signed' : 'unsigned') . "\n";

// Cleanup.
$delete = function ($dir) use (&$delete): void {
	if (!is_dir($dir)) {
		return;
	}
	$items = scandir($dir);
	if (!is_array($items)) {
		return;
	}
	foreach ($items as $item) {
		if ($item === '.' || $item === '..') {
			continue;
		}
		$path = $dir . '/' . $item;
		if (is_dir($path)) {
			$delete($path);
		} else {
			@unlink($path);
		}
	}
	@rmdir($dir);
};
$delete($tempRoot);
