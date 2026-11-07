<?php
/* ====================
[BEGIN_COT_EXT]
Hooks=ajax
[END_COT_EXT]
==================== */

/**
 * Page HTML Files — AJAX handler (save / create / delete / download).
 *
 * File: plugins/pagehtml/pagehtml.ajax.php
 *
 * @package   pagehtml
 * @version   1.0.0
 * @author    webitproff
 * @copyright Copyright (c) 2026 webitproff
 * @license   BSD
 */

defined('COT_CODE') or die('Wrong URL.');

ini_set('display_errors', '0');
error_reporting(0);
ob_start();

$response = ['success' => false, 'message' => 'Unknown error'];

try {
	require_once cot_incfile('pagehtml', 'plug');
	require_once cot_langfile('pagehtml', 'plug');

	if (!cot_auth('admin', 'a', 'A') && !cot_auth('plug', 'pagehtml', 'A')) {
		ob_end_clean();
		http_response_code(403);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(['success' => false, 'message' => 'Forbidden']);
		exit;
	}

	$action = isset($_POST['a']) ? (string) $_POST['a']
		: (isset($_GET['a']) ? (string) $_GET['a'] : '');
	$file = isset($_POST['file']) ? trim((string) $_POST['file'])
		: (isset($_GET['file']) ? trim((string) $_GET['file']) : '');
	$dir = isset($_POST['dir']) ? trim((string) $_POST['dir'])
		: (isset($_GET['dir']) ? trim((string) $_GET['dir']) : '');
	$name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
	$hasContent = array_key_exists('content', $_POST);
	$content = $hasContent ? (string) $_POST['content'] : null;

	// CSRF
	if ($action === 'download') {
		if (!cot_check_xg(false)) {
			ob_end_clean();
			http_response_code(400);
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
			exit;
		}
	} else {
		if (!cot_check_xp()) {
			ob_end_clean();
			http_response_code(400);
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
			exit;
		}
	}

	$base = pagehtml_base_dir();
	if ($base === null) {
		throw new RuntimeException(Cot::$L['pagehtml_err_base'] ?? 'Base directory not found');
	}

	// ── download ────────────────────────────────────────────────────
	if ($action === 'download') {
		$abs = pagehtml_validate_file($file);
		if ($abs === null || !is_readable($abs)) {
			ob_end_clean();
			http_response_code(404);
			exit;
		}
		while (ob_get_level() > 0) {
			ob_end_clean();
		}
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="' . basename($abs) . '"');
		header('Content-Length: ' . (string) filesize($abs));
		header('X-Content-Type-Options: nosniff');
		readfile($abs);
		exit;
	}

	// ── save ────────────────────────────────────────────────────────
	if ($action === 'save') {
		$abs = pagehtml_validate_file($file);
		if ($abs === null) {
			throw new RuntimeException(Cot::$L['pagehtml_err_path'] ?? 'Invalid path');
		}
		if (!is_writable($abs)) {
			throw new RuntimeException(Cot::$L['pagehtml_err_writable'] ?? 'File not writable');
		}
		if (!$hasContent) {
			throw new RuntimeException('No content');
		}
		$bytes = file_put_contents($abs, $content);
		if ($bytes === false) {
			throw new RuntimeException(Cot::$L['pagehtml_err_write'] ?? 'Failed to write');
		}
		$response = [
			'success' => true,
			'message' => Cot::$L['pagehtml_saved'] ?? 'Saved',
			'bytes' => $bytes,
		];

	// ── create_file ─────────────────────────────────────────────────
	} elseif ($action === 'create_file') {
		$dirAbs = pagehtml_validate_dir($dir);
		if ($dirAbs === null) {
			throw new RuntimeException(Cot::$L['pagehtml_err_dir'] ?? 'Invalid directory');
		}

		$safeName = pagehtml_safe_name($name);
		if ($safeName === null) {
			throw new RuntimeException(Cot::$L['pagehtml_err_name'] ?? 'Invalid filename');
		}

		$ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
		if (!in_array($ext, pagehtml_allowed_ext(), true)) {
			throw new RuntimeException(Cot::$L['pagehtml_err_ext'] ?? 'Extension not allowed');
		}

		$target = $dirAbs . '/' . $safeName;
		$realParent = realpath($dirAbs);
		if ($realParent === false) {
			throw new RuntimeException('Invalid parent');
		}
		$parentNorm = str_replace('\\', '/', $realParent);
		$targetNorm = str_replace('\\', '/', $target);
		if (strpos($targetNorm, $parentNorm . '/') !== 0) {
			throw new RuntimeException('Path escape');
		}

		if (file_exists($target)) {
			throw new RuntimeException(Cot::$L['pagehtml_err_exists'] ?? 'File already exists');
		}

		$bytes = file_put_contents($target, '');
		if ($bytes === false) {
			throw new RuntimeException(Cot::$L['pagehtml_err_create'] ?? 'Cannot create file');
		}

		$dirRel = pagehtml_normalize_rel($dir);
		$rel = ($dirRel === null || $dirRel === '') ? $safeName : ($dirRel . '/' . $safeName);

		$response = [
			'success' => true,
			'message' => Cot::$L['pagehtml_created'] ?? 'Created',
			'rel' => $rel,
		];

	// ── create_dir ──────────────────────────────────────────────────
	} elseif ($action === 'create_dir') {
		$dirAbs = pagehtml_validate_dir($dir);
		if ($dirAbs === null) {
			throw new RuntimeException(Cot::$L['pagehtml_err_dir'] ?? 'Invalid directory');
		}

		$safeName = pagehtml_safe_name($name);
		if ($safeName === null) {
			throw new RuntimeException(Cot::$L['pagehtml_err_name'] ?? 'Invalid folder name');
		}

		// Folder names must not look like files with forbidden extensions logic —
		// any safe name without path separators is OK for directories.
		$target = $dirAbs . '/' . $safeName;
		if (file_exists($target)) {
			throw new RuntimeException(Cot::$L['pagehtml_err_exists'] ?? 'Folder already exists');
		}

		$perms = Cot::$cfg['dir_perms'] ?? 0775;
		if (!@mkdir($target, $perms, false)) {
			throw new RuntimeException(Cot::$L['pagehtml_err_mkdir'] ?? 'Cannot create folder');
		}

		$response = [
			'success' => true,
			'message' => Cot::$L['pagehtml_dir_created'] ?? 'Folder created',
		];

	// ── delete ──────────────────────────────────────────────────────
	} elseif ($action === 'delete') {
		if ($file !== '') {
			$abs = pagehtml_validate_file($file);
			if ($abs === null) {
				throw new RuntimeException(Cot::$L['pagehtml_err_path'] ?? 'Invalid path');
			}
			if (!is_writable($abs) || !@unlink($abs)) {
				throw new RuntimeException(Cot::$L['pagehtml_err_delete'] ?? 'Cannot delete file');
			}
			$response = [
				'success' => true,
				'message' => Cot::$L['pagehtml_deleted'] ?? 'Deleted',
			];
		} elseif ($dir !== '') {
			$dirAbs = pagehtml_validate_dir($dir);
			if ($dirAbs === null) {
				throw new RuntimeException(Cot::$L['pagehtml_err_dir'] ?? 'Invalid directory');
			}

			$baseNorm = str_replace('\\', '/', $base);
			$dirNorm = str_replace('\\', '/', $dirAbs);
			if ($dirNorm === $baseNorm) {
				throw new RuntimeException(Cot::$L['pagehtml_err_root_del'] ?? 'Cannot delete root');
			}

			$items = @scandir($dirAbs);
			if ($items === false) {
				throw new RuntimeException(Cot::$L['pagehtml_err_delete'] ?? 'Cannot delete folder');
			}
			$items = array_diff($items, ['.', '..']);
			if (!empty($items)) {
				throw new RuntimeException(Cot::$L['pagehtml_err_not_empty'] ?? 'Folder is not empty');
			}

			if (!@rmdir($dirAbs)) {
				throw new RuntimeException(Cot::$L['pagehtml_err_delete'] ?? 'Cannot delete folder');
			}

			$response = [
				'success' => true,
				'message' => Cot::$L['pagehtml_deleted'] ?? 'Deleted',
			];
		} else {
			throw new RuntimeException('Nothing to delete');
		}

	} else {
		throw new RuntimeException('Unknown action');
	}

} catch (Throwable $e) {
	http_response_code(500);
	$response = ['success' => false, 'message' => $e->getMessage()];
}

ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($response);
exit;
