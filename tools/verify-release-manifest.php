#!/usr/bin/env php
<?php
/**
 * Verify RELEASE_MANIFEST.json signature from protected package.
 *
 * Usage:
 *   php tools/verify-release-manifest.php path/to/RELEASE_MANIFEST.json
 *
 * Env:
 *   PCKZCE_RELEASE_SIGNING_KEY=...
 */

declare(strict_types=1);

if ($argc < 2) {
	fwrite(STDERR, "Usage: php tools/verify-release-manifest.php <manifest-path>\n");
	exit(1);
}

$manifestPath = (string) $argv[1];
if (!is_readable($manifestPath)) {
	fwrite(STDERR, "Manifest not readable: {$manifestPath}\n");
	exit(1);
}

$raw = file_get_contents($manifestPath);
if (!is_string($raw)) {
	fwrite(STDERR, "Could not read manifest.\n");
	exit(1);
}
$data = json_decode($raw, true);
if (!is_array($data)) {
	fwrite(STDERR, "Manifest JSON invalid.\n");
	exit(1);
}

$sig = isset($data['signature']) ? (string) $data['signature'] : '';
$alg = isset($data['signature_alg']) ? (string) $data['signature_alg'] : 'none';
unset($data['signature'], $data['signature_alg'], $data['signature_hint']);
$normalized = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
if (!is_string($normalized)) {
	fwrite(STDERR, "Failed to normalize manifest.\n");
	exit(1);
}

if ($alg === 'none') {
	echo "Manifest is unsigned.\n";
	exit(0);
}

$key = getenv('PCKZCE_RELEASE_SIGNING_KEY') ?: '';
if ($key === '') {
	fwrite(STDERR, "Missing PCKZCE_RELEASE_SIGNING_KEY for verification.\n");
	exit(1);
}
$expected = hash_hmac('sha256', $normalized, (string) $key);
if (!hash_equals($expected, $sig)) {
	fwrite(STDERR, "Manifest signature INVALID.\n");
	exit(1);
}
echo "Manifest signature valid.\n";
