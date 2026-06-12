#!/usr/bin/env php
<?php
/**
 * Build production frontend assets for protected deployment.
 *
 * Generates:
 * - *.min.js for public JS sources.
 * - *.protected.js for sensitive JS sources.
 * - *.min.css for public CSS sources.
 *
 * Usage:
 *   php tools/build-js-protection.php
 *
 * Optional env:
 *   PCKZCE_JS_PROTECT_STRICT=1  Exit non-zero on first failure.
 *
 * @package PCKZCanonicalEngine
 */

declare(strict_types=1);

$opts = getopt('', array('root::'));
$root = isset($opts['root']) ? (string) $opts['root'] : dirname(__DIR__);
$root = rtrim($root, DIRECTORY_SEPARATOR);
$publicJs = $root . '/public/js';
$publicCss = $root . '/public/css';
$strict   = getenv('PCKZCE_JS_PROTECT_STRICT') ? true : false;

if (!is_dir($publicJs) || !is_dir($publicCss)) {
	fwrite(STDERR, "public/js or public/css directory not found.\n");
	exit(1);
}

$terser = trim((string) shell_exec('command -v terser'));
$terserInlineCommand = false;
if ($terser === '') {
	$npx = trim((string) shell_exec('command -v npx'));
	if ($npx !== '') {
		$terser = $npx . ' --yes terser';
		$terserInlineCommand = true;
	} else {
		fwrite(STDERR, "terser is required (install with: npm i -g terser).\n");
		exit(1);
	}
}

/**
 * Minify CSS with a conservative transform.
 *
 * @param string $css CSS source.
 * @return string
 */
function pckzce_minify_css(string $css): string {
	$css = preg_replace('#/\*[^!].*?\*/#s', '', $css) ?? $css;
	$css = preg_replace('/\s+/', ' ', $css) ?? $css;
	$css = str_replace(array("\r", "\n", "\t"), '', $css);
	$css = preg_replace('/\s*([{}:;,>])\s*/', '$1', $css) ?? $css;
	$css = str_replace(';}', '}', $css);
	return trim($css);
}

/**
 * Build one JS artifact with terser.
 *
 * @param string $terser Binary path.
 * @param string $source Source file.
 * @param string $target Target file.
 * @param bool   $protected Whether this is protected profile.
 * @param bool   $inlineCommand Whether terser contains prebuilt command args.
 * @return array{ok:bool,output:string}
 */
function pckzce_build_js(string $terser, string $source, string $target, bool $protected, bool $inlineCommand = false): array {
	$compress = $protected
		? 'passes=3,drop_console=true,drop_debugger=true,booleans_as_integers=true'
		: 'passes=2,drop_console=true,drop_debugger=true';
	$mangleOption = $protected ? ' --mangle ' . escapeshellarg( 'toplevel=true' ) : '';

	$commandPrefix = $inlineCommand ? $terser : escapeshellarg($terser);
	$cmd = sprintf(
		'%s %s --compress %s%s --comments false --ecma 2018 --format ascii_only=true -o %s 2>&1',
		$commandPrefix,
		escapeshellarg($source),
		escapeshellarg($compress),
		$mangleOption,
		escapeshellarg($target)
	);

	$out = array();
	$code = 0;
	exec($cmd, $out, $code);
	return array(
		'ok' => $code === 0,
		'output' => implode("\n", $out),
	);
}

$excludeDirs = array('vendor');
$sensitiveBasenames = array(
	'creator.js',
	'pckz-creator-protect.js',
	'preview-engine.js',
	'canonical-scene.js',
	'fabric-production-pipeline.js',
);

$stats = array(
	'js_min' => 0,
	'js_protected' => 0,
	'css_min' => 0,
	'failed' => 0,
);

$jsIterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($publicJs, FilesystemIterator::SKIP_DOTS),
	RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($jsIterator as $fileInfo) {
	$abs = $fileInfo->getPathname();
	$rel = ltrim(str_replace($publicJs, '', $abs), DIRECTORY_SEPARATOR);
	$rel = str_replace('\\', '/', $rel);

	if (!preg_match('/\.js$/i', $rel) || preg_match('/\.(min|protected)\.js$/i', $rel)) {
		continue;
	}
	foreach ($excludeDirs as $dir) {
		if (0 === strpos($rel, $dir . '/')) {
			continue 2;
		}
	}

	$destMin = preg_replace('/\.js$/i', '.min.js', $abs);
	if (!is_string($destMin) || $destMin === '') {
		continue;
	}
	$minBuilt = pckzce_build_js($terser, $abs, $destMin, false, $terserInlineCommand);
	if (!$minBuilt['ok']) {
		$stats['failed']++;
		fwrite(STDERR, "FAILED minify: {$rel}\n{$minBuilt['output']}\n");
		if ($strict) {
			exit(1);
		}
		continue;
	}
	$stats['js_min']++;
	echo "Built .min.js: {$rel}\n";

	if (in_array(basename($abs), $sensitiveBasenames, true)) {
		$destProtected = preg_replace('/\.js$/i', '.protected.js', $abs);
		if (is_string($destProtected) && $destProtected !== '') {
			$protectedBuilt = pckzce_build_js($terser, $abs, $destProtected, true, $terserInlineCommand);
			if (!$protectedBuilt['ok']) {
				$stats['failed']++;
				fwrite(STDERR, "FAILED protected: {$rel}\n{$protectedBuilt['output']}\n");
				if ($strict) {
					exit(1);
				}
			} else {
				$stats['js_protected']++;
				echo "Built .protected.js: {$rel}\n";
			}
		}
	}
}

$cssIterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($publicCss, FilesystemIterator::SKIP_DOTS),
	RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($cssIterator as $fileInfo) {
	$abs = $fileInfo->getPathname();
	$rel = ltrim(str_replace($publicCss, '', $abs), DIRECTORY_SEPARATOR);
	$rel = str_replace('\\', '/', $rel);

	if (!preg_match('/\.css$/i', $rel) || preg_match('/\.min\.css$/i', $rel)) {
		continue;
	}

	$source = file_get_contents($abs);
	if (!is_string($source)) {
		$stats['failed']++;
		fwrite(STDERR, "FAILED read css: {$rel}\n");
		if ($strict) {
			exit(1);
		}
		continue;
	}

	$dest = preg_replace('/\.css$/i', '.min.css', $abs);
	if (!is_string($dest) || $dest === '') {
		continue;
	}

	$minified = pckzce_minify_css($source);
	if (file_put_contents($dest, $minified) === false) {
		$stats['failed']++;
		fwrite(STDERR, "FAILED write css: {$rel}\n");
		if ($strict) {
			exit(1);
		}
		continue;
	}
	$stats['css_min']++;
	echo "Built .min.css: {$rel}\n";
}

// Ensure source maps are not shipped in production bundles.
foreach (array($publicJs, $publicCss) as $assetRoot) {
	$mapIterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($assetRoot, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::LEAVES_ONLY
	);
	foreach ($mapIterator as $fileInfo) {
		$abs = $fileInfo->getPathname();
		if (preg_match('/\.map$/i', $abs)) {
			@unlink($abs);
		}
	}
}

echo sprintf(
	"Done. JS min: %d, JS protected: %d, CSS min: %d, failed: %d.\n",
	$stats['js_min'],
	$stats['js_protected'],
	$stats['css_min'],
	$stats['failed']
);

exit(($strict && $stats['failed'] > 0) ? 1 : 0);
