#!/usr/bin/env php
<?php
/**
 * Build a customer-bound protected distribution ZIP from a release package.
 *
 * Usage:
 *   php tools/build-customer-protected-package.php \
 *     --source=dist/pckz-canonical-engine-2.22.0-protected.zip \
 *     --license=PCKZCE-XXXX \
 *     --domain=client.example.com \
 *     --install=uuid-optional \
 *     --output=dist/customers
 *
 * Env (optional):
 *   PCKZCE_RELEASE_SIGNING_KEY=...
 */

declare(strict_types=1);

function pckz_tool_extract_zip(string $zipPath, string $targetDir): void {
	if (class_exists('ZipArchive')) {
		$zip = new ZipArchive();
		if (true !== $zip->open($zipPath)) {
			throw new RuntimeException("Could not open zip: {$zipPath}");
		}
		if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
			throw new RuntimeException("Could not create extract dir: {$targetDir}");
		}
		if (!$zip->extractTo($targetDir)) {
			$zip->close();
			throw new RuntimeException("Could not extract zip to {$targetDir}");
		}
		$zip->close();
		return;
	}
	$unzip = trim((string) shell_exec('command -v unzip'));
	if ($unzip === '') {
		throw new RuntimeException('Neither ZipArchive nor unzip command is available.');
	}
	$cmd = sprintf('%s -q %s -d %s', escapeshellarg($unzip), escapeshellarg($zipPath), escapeshellarg($targetDir));
	exec($cmd, $out, $code);
	if ($code !== 0) {
		throw new RuntimeException("unzip command failed with code {$code}");
	}
}

function pckz_tool_pack_zip(string $sourceDir, string $zipPath): void {
	if (class_exists('ZipArchive')) {
		$zip = new ZipArchive();
		if (true !== $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
			throw new RuntimeException("Could not create zip: {$zipPath}");
		}
		$iter = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::LEAVES_ONLY
		);
		foreach ($iter as $fileInfo) {
			$path = $fileInfo->getPathname();
			$rel = ltrim(str_replace($sourceDir, '', $path), DIRECTORY_SEPARATOR);
			$zip->addFile($path, $rel);
		}
		$zip->close();
		return;
	}
	$zipBin = trim((string) shell_exec('command -v zip'));
	if ($zipBin === '') {
		throw new RuntimeException('Neither ZipArchive nor zip command is available.');
	}
	$cmd = sprintf(
		'cd %s && %s -qr %s .',
		escapeshellarg($sourceDir),
		escapeshellarg($zipBin),
		escapeshellarg($zipPath)
	);
	exec($cmd, $out, $code);
	if ($code !== 0) {
		throw new RuntimeException("zip command failed with code {$code}");
	}
}

function pckz_tool_rrmdir(string $dir): void {
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
		$path = $dir . DIRECTORY_SEPARATOR . $item;
		if (is_dir($path)) {
			pckz_tool_rrmdir($path);
		} else {
			@unlink($path);
		}
	}
	@rmdir($dir);
}

$opts = getopt('', array('source:', 'license:', 'domain:', 'install::', 'output::'));
$source = (string) ($opts['source'] ?? '');
$license = (string) ($opts['license'] ?? '');
$domain = strtolower(trim((string) ($opts['domain'] ?? '')));
$install = (string) ($opts['install'] ?? '');
$outputDir = (string) ($opts['output'] ?? (dirname(__DIR__) . '/dist/customers'));

if ($source === '' || !is_readable($source)) {
	fwrite(STDERR, "Missing or unreadable --source ZIP\n");
	exit(1);
}
if ($license === '' || $domain === '') {
	fwrite(STDERR, "Missing --license or --domain\n");
	exit(1);
}

$tmp = sys_get_temp_dir() . '/pckz-customer-package-' . bin2hex(random_bytes(6));
$extract = $tmp . '/extract';
$packRoot = $tmp . '/pack';

try {
	if (!mkdir($extract, 0775, true) && !is_dir($extract)) {
		throw new RuntimeException("Could not create {$extract}");
	}
	pckz_tool_extract_zip($source, $extract);

	$entries = array_values(array_filter(scandir($extract) ?: array(), static function ($name): bool {
		return $name !== '.' && $name !== '..';
	}));
	if (count($entries) !== 1 || !is_dir($extract . '/' . $entries[0])) {
		throw new RuntimeException('Source zip must contain a single plugin root folder.');
	}
	$pluginRootName = $entries[0];
	$pluginRoot = $extract . '/' . $pluginRootName;

	$manifest = array(
		'license_key_mask' => substr($license, 0, 6) . '...' . substr($license, -4),
		'domain'           => $domain,
		'install_uuid'     => $install,
		'issued_at'        => gmdate('c'),
		'source_package'   => basename($source),
		'workflow'         => 'customer-protected-distribution',
	);
	$manifestJson = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	if (!is_string($manifestJson)) {
		throw new RuntimeException('Could not encode customer manifest JSON.');
	}
	$signingKey = getenv('PCKZCE_RELEASE_SIGNING_KEY') ?: '';
	$signature = $signingKey !== '' ? hash_hmac('sha256', $manifestJson, (string) $signingKey) : '';
	$manifest['signature'] = $signature;
	$manifest['signature_alg'] = $signature !== '' ? 'hmac-sha256' : 'none';
	$finalManifest = json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
	if (!is_string($finalManifest)) {
		throw new RuntimeException('Could not encode final manifest JSON.');
	}
	if (file_put_contents($pluginRoot . '/LICENSE_BINDING.json', $finalManifest) === false) {
		throw new RuntimeException('Could not write LICENSE_BINDING.json');
	}

	if (!is_dir($packRoot) && !mkdir($packRoot, 0775, true) && !is_dir($packRoot)) {
		throw new RuntimeException("Could not create {$packRoot}");
	}
	$customerSafeDomain = preg_replace('/[^a-z0-9\.\-]+/i', '-', $domain) ?: 'domain';
	$destName = $pluginRootName . '-' . $customerSafeDomain . '-licensed.zip';
	$destPath = rtrim($outputDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $destName;
	if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
		throw new RuntimeException("Could not create output dir {$outputDir}");
	}

	$packageContentRoot = $packRoot . '/' . $pluginRootName;
	rename($pluginRoot, $packageContentRoot);
	pckz_tool_pack_zip($packRoot, $destPath);

	echo "Built customer-protected package: {$destPath}\n";
	echo "Binding signature: " . ($signature !== '' ? 'signed' : 'unsigned') . "\n";
} catch (Throwable $e) {
	fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
	pckz_tool_rrmdir($tmp);
	exit(1);
}

pckz_tool_rrmdir($tmp);
