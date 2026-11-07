<?php
/**
 * Page HTML Files API
 *
 * File: plugins/pagehtml/inc/pagehtml.functions.php
 *
 * @package   pagehtml
 * @version   1.0.0
 * @author    webitproff
 * @copyright Copyright (c) 2026 webitproff
 * @license   BSD
 */

defined('COT_CODE') or die('Wrong URL.');

require_once cot_langfile('pagehtml', 'plug');

/**
 * Returns list of allowed file extensions.
 *
 * @return string[]
 */
function pagehtml_allowed_ext(): array
{
	$ext = ['html', 'htm', 'txt'];
	$allowMd = (Cot::$cfg['plugin']['pagehtml']['allow_md'] ?? '1') === '1';
	if ($allowMd) {
		$ext[] = 'md';
	}
	return $ext;
}

/**
 * Absolute real path to datas/html/page directory.
 * Creates the directory if it does not exist.
 *
 * @return string|null
 */
function pagehtml_base_dir(): ?string
{
	$datas = Cot::$cfg['datas_dir'] ?? 'datas';
	$rel = rtrim($datas, '/\\') . '/html/page';
	$abs = Cot::$sys['baseDir'] . '/' . $rel;

	if (!is_dir($abs)) {
		$perms = Cot::$cfg['dir_perms'] ?? 0775;
		if (!@mkdir($abs, $perms, true) && !is_dir($abs)) {
			return null;
		}
	}

	$base = realpath($abs);
	return ($base !== false && is_dir($base)) ? $base : null;
}

/**
 * Normalize relative path segments.
 * Returns null on invalid input, empty string for root.
 *
 * @param string $rel
 * @return string|null
 */
function pagehtml_normalize_rel(string $rel): ?string
{
	$rel = str_replace(["\0", '\\'], ['', '/'], trim($rel));
	$rel = trim($rel, '/');

	if ($rel === '') {
		return '';
	}

	foreach (explode('/', $rel) as $seg) {
		if ($seg === '' || $seg === '.' || $seg === '..') {
			return null;
		}
	}

	return $rel;
}

/**
 * Validate relative path to a file inside datas/html/page.
 *
 * @param string $rel Relative path from datas/html/page
 * @return string|null Absolute realpath or null
 */
function pagehtml_validate_file(string $rel): ?string
{
	$base = pagehtml_base_dir();
	if ($base === null) {
		return null;
	}

	$rel = pagehtml_normalize_rel($rel);
	if ($rel === null || $rel === '') {
		return null;
	}

	$ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
	if (!in_array($ext, pagehtml_allowed_ext(), true)) {
		return null;
	}

	$full = $base . '/' . $rel;
	$real = realpath($full);

	if ($real === false || !is_file($real)) {
		return null;
	}

	$realNorm = str_replace('\\', '/', $real);
	$baseNorm = str_replace('\\', '/', $base);

	if (strpos($realNorm, $baseNorm . '/') !== 0) {
		return null;
	}

	return $real;
}

/**
 * Validate relative path to a directory inside datas/html/page.
 *
 * @param string $rel Relative path from datas/html/page (empty = root)
 * @return string|null Absolute realpath or null
 */
function pagehtml_validate_dir(string $rel): ?string
{
	$base = pagehtml_base_dir();
	if ($base === null) {
		return null;
	}

	$rel = pagehtml_normalize_rel($rel);
	if ($rel === null) {
		return null;
	}

	if ($rel === '') {
		return $base;
	}

	$full = $base . '/' . $rel;
	$real = realpath($full);

	if ($real === false || !is_dir($real)) {
		return null;
	}

	$realNorm = str_replace('\\', '/', $real);
	$baseNorm = str_replace('\\', '/', $base);

	if ($realNorm !== $baseNorm && strpos($realNorm, $baseNorm . '/') !== 0) {
		return null;
	}

	return $real;
}

/**
 * List directories and allowed files in a subdirectory.
 *
 * @param string $subdir Relative subdirectory (empty = root)
 * @return array{dirs: array<int, array{name: string, rel: string}>, files: array<int, array{name: string, rel: string, size: int, mtime: int}>, current: string}
 */
function pagehtml_list(string $subdir = ''): array
{
	$result = [
		'dirs' => [],
		'files' => [],
		'current' => '',
	];

	$dir = pagehtml_validate_dir($subdir);
	if ($dir === null) {
		return $result;
	}

	$rel = pagehtml_normalize_rel($subdir);
	$result['current'] = ($rel === null) ? '' : $rel;

	$items = @scandir($dir);
	if ($items === false) {
		return $result;
	}

	$allowed = pagehtml_allowed_ext();

	foreach ($items as $name) {
		if ($name === '.' || $name === '..') {
			continue;
		}

		$path = $dir . '/' . $name;
		$itemRel = ($result['current'] === '') ? $name : ($result['current'] . '/' . $name);

		if (is_dir($path)) {
			$result['dirs'][] = [
				'name' => $name,
				'rel' => $itemRel,
			];
		} elseif (is_file($path)) {
			$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
			if (!in_array($ext, $allowed, true)) {
				continue;
			}
			$result['files'][] = [
				'name' => $name,
				'rel' => $itemRel,
				'size' => (int) filesize($path),
				'mtime' => (int) filemtime($path),
			];
		}
	}

	usort($result['dirs'], static function ($a, $b) {
		return strcasecmp($a['name'], $b['name']);
	});
	usort($result['files'], static function ($a, $b) {
		return strcasecmp($a['name'], $b['name']);
	});

	return $result;
}

/**
 * Breadcrumb segments for current subdirectory.
 *
 * @param string $subdir
 * @return array<int, array{name: string, rel: string}>
 */
function pagehtml_breadcrumbs(string $subdir): array
{
	$crumbs = [];
	$rel = pagehtml_normalize_rel($subdir);
	if ($rel === null || $rel === '') {
		return $crumbs;
	}

	$parts = explode('/', $rel);
	$acc = '';
	foreach ($parts as $part) {
		$acc = ($acc === '') ? $part : ($acc . '/' . $part);
		$crumbs[] = [
			'name' => $part,
			'rel' => $acc,
		];
	}

	return $crumbs;
}

/**
 * Safe filename (no path separators or reserved characters).
 *
 * @param string $name
 * @return string|null
 */
function pagehtml_safe_name(string $name): ?string
{
	$name = str_replace(["\0", '\\', '/'], '', $name);
	$name = trim($name);

	if ($name === '' || $name === '.' || $name === '..') {
		return null;
	}

	if (preg_match('/[<>:"|?*\x00-\x1F]/', $name)) {
		return null;
	}

	return $name;
}
