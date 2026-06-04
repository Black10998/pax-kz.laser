#!/usr/bin/env php
<?php
/**
 * Build minified/obfuscated JS artifacts for production packaging.
 *
 * Usage:
 *   php tools/build-js-protection.php
 *
 * Optional env:
 *   PCKZCE_JS_PROTECT_STRICT=1  Fail on first minification error.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$publicJs = $root . '/public/js';
$strict = getenv('PCKZCE_JS_PROTECT_STRICT') ? true : false;

if (!is_dir($publicJs)) {
	fwrite(STDERR, "public/js directory not found.\n");
	exit(1);
}

$terser = trim((string) shell_exec('command -v terser'));
if ($terser === '') {
	fwrite(STDERR, "terser is required (npm i -g terser).\n");
	exit(1);
}

$excludeDirs = array('vendor');
$sensitive = array(
	'pckz-creator-protect.js',
	'creator.js',
);

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($publicJs, FilesystemIterator::SKIP_DOTS),
	RecursiveIteratorIterator::LEAVES_ONLY
);

$built = 0;
$failed = 0;

foreach ($iterator as $fileInfo) {
	$abs = $fileInfo->getPathname();
	$rel = ltrim(str_replace($publicJs, '', $abs), DIRECTORY_SEPARATOR);
	$relNorm = str_replace('\\', '/', $rel);
	if (!preg_match('/\.js$/i', $relNorm) || preg_match('/\.min\.js$/i', $relNorm)) {
		continue;
	}
	foreach ($excludeDirs as $dir) {
		if (0 === strpos($relNorm, $dir . '/')) {
			continue 2;
		}
	}

	$dest = preg_replace('/\.js$/i', '.min.js', $abs);
	if (!is_string($dest) || $dest === '') {
		continue;
	}

	$isSensitive = in_array(basename($abs), $sensitive, true);
	$compress = 'passes=2,drop_console=true,drop_debugger=true';
	$mangle = $isSensitive ? 'toplevel=true' : 'false';
	$comments = 'false';

	$cmd = sprintf(
		'%s %s --compress %s --mangle %s --comments %s --ecma 2018 -o %s 2>&1',
		escapeshellarg($terser),
		escapeshellarg($abs),
		escapeshellarg($compress),
		escapeshellarg($mangle),
		escapeshellarg($comments),
		escapeshellarg($dest)
	);
	$out = array();
	$code = 0;
	exec($cmd, $out, $code);
	if ($code !== 0) {
		++$failed;
		fwrite(STDERR, "FAILED: {$relNorm}\n" . implode("\n", $out) . "\n");
		if ($strict) {
			exit(1);
		}
		continue;
	}
	++$built;
	echo "Built: {$relNorm}\n";
}

echo "Done. Built {$built} file(s), failed {$failed}.\n";
exit($failed > 0 && $strict ? 1 : 0);
