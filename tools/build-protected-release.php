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

$opts = getopt('', array('version:', 'build::', 'output::', 'channel::', 'slug::', 'sync-version'));
$version = isset($opts['version']) ? (string) $opts['version'] : '';
$build = isset($opts['build']) ? (string) $opts['build'] : $version;
$outputDir = isset($opts['output']) ? (string) $opts['output'] : ($root . '/dist');
$channel = isset($opts['channel']) ? (string) $opts['channel'] : 'customer-protected';
$slug = isset($opts['slug']) ? (string) $opts['slug'] : 'pckz-canonical-engine';

if ($version === '') {
	fwrite(STDERR, "Missing required --version\n");
	exit(1);
}

/**
 * @param string $root Plugin root directory.
 * @param string $version Release version.
 * @param string $build_id Build identifier.
 * @return int Exit code.
 */
function pckzce_sync_source_release_version( string $root, string $version, string $build_id ): int {
	$main = $root . '/pckz-canonical-engine.php';
	$readme = $root . '/readme.txt';
	if ( ! is_readable( $main ) ) {
		fwrite( STDERR, "Version sync failed: missing pckz-canonical-engine.php\n" );
		return 1;
	}
	$content = (string) file_get_contents( $main );
	$content = preg_replace( '/^\s*\*\s*Version:\s*.+$/m', ' * Version:           ' . $version, $content, 1 );
	$content = preg_replace(
		"/define\\s*\\(\\s*['\"]PCKZCE_VERSION['\"]\\s*,\\s*['\"][^'\"]+['\"]\\s*\\)/",
		"define( 'PCKZCE_VERSION', '" . $version . "' )",
		$content,
		1
	);
	$content = preg_replace(
		"/define\\s*\\(\\s*['\"]PCKZCE_BUILD['\"]\\s*,\\s*['\"][^'\"]+['\"]\\s*\\)/",
		"define( 'PCKZCE_BUILD', '" . $build_id . "' )",
		$content,
		1
	);
	if ( false === file_put_contents( $main, $content ) ) {
		fwrite( STDERR, "Version sync failed: could not write pckz-canonical-engine.php\n" );
		return 1;
	}
	if ( is_readable( $readme ) ) {
		$readme_content = (string) file_get_contents( $readme );
		$readme_content = preg_replace( '/^Stable tag:\s*.+$/mi', 'Stable tag: ' . $version, $readme_content, 1 );
		file_put_contents( $readme, $readme_content );
	}
	fwrite( STDOUT, "Synced source files to version {$version} (build {$build_id})\n" );
	return 0;
}

if ( ! empty( $opts['sync-version'] ) ) {
	$build = isset( $opts['build'] ) ? (string) $opts['build'] : $version;
	if ( 0 !== pckzce_sync_source_release_version( $root, $version, $build ) ) {
		exit( 1 );
	}
}

/**
 * @param string $version Expected release version.
 * @return int Exit code.
 */
function pckzce_validate_source_release_version( string $root, string $version ): int {
	$main = $root . '/pckz-canonical-engine.php';
	$readme = $root . '/readme.txt';
	if ( ! is_readable( $main ) ) {
		fwrite( STDERR, "Pre-build validation failed: missing pckz-canonical-engine.php\n" );
		return 1;
	}
	$content = (string) file_get_contents( $main );
	$header  = '';
	if ( preg_match( '/^[ \t\/*#@]*Version:\s*(.+)$/mi', $content, $matches ) ) {
		$header = trim( (string) ( $matches[1] ?? '' ) );
	}
	$pckzce = '';
	if ( preg_match( "/define\\s*\\(\\s*['\"]PCKZCE_VERSION['\"]\\s*,\\s*['\"]([^'\"]+)['\"]\\s*\\)/", $content, $matches ) ) {
		$pckzce = trim( (string) ( $matches[1] ?? '' ) );
	}
	$build_constant = '';
	if ( preg_match( "/define\\s*\\(\\s*['\"]PCKZCE_BUILD['\"]\\s*,\\s*['\"]([^'\"]+)['\"]\\s*\\)/", $content, $matches ) ) {
		$build_constant = trim( (string) ( $matches[1] ?? '' ) );
	}
	$stable = '';
	if ( is_readable( $readme ) && preg_match( '/^Stable tag:\s*(.+)$/mi', (string) file_get_contents( $readme ), $matches ) ) {
		$stable = trim( (string) ( $matches[1] ?? '' ) );
	}
	$errors = array();
	if ( $header !== $version ) {
		$errors[] = "Plugin header Version ({$header}) != release version ({$version})";
	}
	if ( $pckzce !== $version ) {
		$errors[] = "PCKZCE_VERSION constant ({$pckzce}) != release version ({$version})";
	}
	if ( $stable !== '' && $stable !== $version ) {
		$errors[] = "readme.txt Stable tag ({$stable}) != release version ({$version})";
	}
	if ( $build_constant === '' || strpos( $build_constant, $version . '.' ) !== 0 ) {
		$errors[] = "PCKZCE_BUILD ({$build_constant}) must start with {$version}.";
	}
	if ( ! empty( $errors ) ) {
		fwrite( STDERR, "Pre-build version validation failed:\n - " . implode( "\n - ", $errors ) . "\n" );
		return 1;
	}
	fwrite( STDOUT, "Pre-build validation passed for {$version}\n" );
	return 0;
}

/**
 * @param string $zipPath Zip path.
 * @param string $version Expected release version.
 * @return int Exit code.
 */
function pckzce_validate_built_release_zip( string $zipPath, string $version ): int {
	$basename = basename( $zipPath );
	if ( ! preg_match( '/^pckz-canonical-engine-([0-9]+(?:\.[0-9]+)*)-protected\.zip$/i', $basename, $matches ) ) {
		fwrite( STDERR, "Post-build validation failed: invalid ZIP filename {$basename}\n" );
		return 1;
	}
	$filename_version = trim( (string) ( $matches[1] ?? '' ) );
	if ( $filename_version !== $version ) {
		fwrite( STDERR, "Post-build validation failed: filename version {$filename_version} != {$version}\n" );
		return 1;
	}
	if ( ! class_exists( 'ZipArchive' ) ) {
		fwrite( STDERR, "Post-build validation failed: ZipArchive extension required\n" );
		return 1;
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $zipPath ) ) {
		fwrite( STDERR, "Post-build validation failed: could not open {$zipPath}\n" );
		return 1;
	}
	$main = $zip->getFromName( 'pckz-canonical-engine/pckz-canonical-engine.php' );
	if ( false === $main ) {
		$zip->close();
		fwrite( STDERR, "Post-build validation failed: missing plugin main file in archive\n" );
		return 1;
	}
	$header = '';
	if ( preg_match( '/^[ \t\/*#@]*Version:\s*(.+)$/mi', (string) $main, $header_matches ) ) {
		$header = trim( (string) ( $header_matches[1] ?? '' ) );
	}
	$pckzce = '';
	if ( preg_match( "/define\\s*\\(\\s*['\"]PCKZCE_VERSION['\"]\\s*,\\s*['\"]([^'\"]+)['\"]\\s*\\)/", (string) $main, $constant_matches ) ) {
		$pckzce = trim( (string) ( $constant_matches[1] ?? '' ) );
	}
	$manifest_raw = $zip->getFromName( 'pckz-canonical-engine/RELEASE_MANIFEST.json' );
	$zip->close();
	if ( false === $manifest_raw ) {
		fwrite( STDERR, "Post-build validation failed: missing RELEASE_MANIFEST.json\n" );
		return 1;
	}
	$manifest = json_decode( (string) $manifest_raw, true );
	$manifest_version = is_array( $manifest ) ? trim( (string) ( $manifest['version'] ?? '' ) ) : '';
	$errors = array();
	if ( $header !== $version ) {
		$errors[] = "Plugin header Version ({$header}) != release version ({$version})";
	}
	if ( $pckzce !== $version ) {
		$errors[] = "PCKZCE_VERSION constant ({$pckzce}) != release version ({$version})";
	}
	if ( $manifest_version !== $version ) {
		$errors[] = "RELEASE_MANIFEST.json version ({$manifest_version}) != release version ({$version})";
	}
	if ( ! empty( $errors ) ) {
		@unlink( $zipPath );
		fwrite( STDERR, "Post-build version validation failed:\n - " . implode( "\n - ", $errors ) . "\n" );
		return 1;
	}
	fwrite( STDOUT, "Post-build validation passed for {$zipPath}\n" );
	return 0;
}

if ( 0 !== pckzce_validate_source_release_version( $root, $version ) ) {
	exit( 1 );
}

$config_path = $root . '/tools/release-config.json';
$config = is_readable( $config_path ) ? json_decode( (string) file_get_contents( $config_path ), true ) : array();
$excludes = isset( $config['exclude_directories'] ) && is_array( $config['exclude_directories'] )
	? $config['exclude_directories']
	: array(
		'.git',
		'.github',
		'.cursor',
		'dist',
		'release-packages',
		'tmp',
		'node_modules',
		'tests',
		'import',
		'tools',
		'.DS_Store',
		'.env',
	);
$client_excludes = isset( $config['client_exclude_paths'] ) && is_array( $config['client_exclude_paths'] )
	? $config['client_exclude_paths']
	: array();
$excludes = array_merge( $excludes, $client_excludes );

$tempRoot = sys_get_temp_dir() . '/pckzce-release-' . bin2hex(random_bytes(6));
$stageDir = $tempRoot . '/' . $slug;
$delete = static function ( $dir ) use ( &$delete ): void {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	$items = scandir( $dir );
	if ( ! is_array( $items ) ) {
		return;
	}
	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}
		$path = $dir . '/' . $item;
		if ( is_dir( $path ) ) {
			$delete( $path );
		} else {
			@unlink( $path );
		}
	}
	@rmdir( $dir );
};
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

if ( 0 !== pckzce_validate_built_release_zip( $zipPath, $version ) ) {
	$delete( $tempRoot );
	exit( 1 );
}

echo "Built protected release: {$zipPath}\n";
echo "SHA256: " . hash_file( 'sha256', $zipPath ) . "\n";
echo "Manifest signature: " . ( $signature ? 'signed' : 'unsigned' ) . "\n";

$delete( $tempRoot );
