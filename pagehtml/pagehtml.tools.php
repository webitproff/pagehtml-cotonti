<?php
/* ====================
[BEGIN_COT_EXT]
Hooks=tools
[END_COT_EXT]
==================== */

/**
 * Page HTML Files — admin tools page (file list + editor).
 *
 * File: plugins/pagehtml/pagehtml.tools.php
 *
 * @package   pagehtml
 * @version   1.0.0
 * @author    webitproff
 * @copyright Copyright (c) 2026 webitproff
 * @license   BSD
 */

defined('COT_CODE') or die('Wrong URL.');

require_once cot_incfile('pagehtml', 'plug');

cot_block(cot_auth('admin', 'a', 'A') || cot_auth('plug', 'pagehtml', 'A'));

$adminTitle = Cot::$L['pagehtml_title'];
$adminPath[] = [
	cot_url('admin', ['m' => 'other', 'p' => 'pagehtml']),
	Cot::$L['pagehtml_title'],
];

$file = cot_import('file', 'G', 'TXT');
$dir = cot_import('dir', 'G', 'TXT');
if ($dir === null) {
	$dir = '';
}

// ─── Editor mode ───────────────────────────────────────────────────
if ($file !== null && $file !== '') {
	$abs = pagehtml_validate_file($file);
	if ($abs === null || !is_readable($abs)) {
		cot_error(Cot::$L['pagehtml_err_path']);
		cot_redirect(cot_url('admin', ['m' => 'other', 'p' => 'pagehtml'], '', true));
	}

	$adminPath[] = [
		cot_url('admin', ['m' => 'other', 'p' => 'pagehtml', 'file' => $file]),
		htmlspecialchars(basename($file)),
	];

	$content = file_get_contents($abs);
	if ($content === false) {
		cot_error(Cot::$L['pagehtml_err_read']);
		cot_redirect(cot_url('admin', ['m' => 'other', 'p' => 'pagehtml'], '', true));
	}

	$parentDir = dirname(str_replace('\\', '/', $file));
	if ($parentDir === '.' || $parentDir === '') {
		$parentDir = '';
	}

	$t = new XTemplate(cot_tplfile('pagehtml.editor', 'plug', true));
	$t->assign([
		'FILE_REL' => htmlspecialchars($file),
		'FILE_CONTENT' => htmlspecialchars($content),
		'BACK_URL' => cot_url('admin', ['m' => 'other', 'p' => 'pagehtml', 'dir' => $parentDir]),
		'X_TOKEN' => Cot::$sys['xk'],
		'AJAX_URL' => cot_url('plug', ['r' => 'pagehtml']),
		'BACKUP_URL' => cot_absoluteUrl('plug', ['r' => 'pagehtml', 'a' => 'download'], '', true)
			. '&file=' . rawurlencode($file) . '&x=' . Cot::$sys['xk'],
		'L_BACKUP' => Cot::$L['pagehtml_backup'],
		'L_SAVE' => Cot::$L['pagehtml_save'],
		'L_BACK' => Cot::$L['pagehtml_back'],
		'L_EDITING' => Cot::$L['pagehtml_editing'],
		'L_SAVED' => Cot::$L['pagehtml_saved'],
		'L_ERROR' => Cot::$L['pagehtml_error'],
		'L_DELETE' => Cot::$L['pagehtml_delete'],
		'L_CONFIRM_DEL' => Cot::$L['pagehtml_confirm_del'],
	]);

	cot_display_messages($t);
	$t->parse('MAIN');
	$pluginBody = $t->text('MAIN');

// ─── List mode ─────────────────────────────────────────────────────
} else {
	$list = pagehtml_list($dir);
	$crumbs = pagehtml_breadcrumbs($list['current']);

	$t = new XTemplate(cot_tplfile('pagehtml.tools', 'plug', true));
	$t->assign([
		'X_TOKEN' => Cot::$sys['xk'],
		'AJAX_URL' => cot_url('plug', ['r' => 'pagehtml']),
		'CURRENT_DIR' => htmlspecialchars($list['current']),
		'L_TITLE' => Cot::$L['pagehtml_title'],
		'L_NO_FILES' => Cot::$L['pagehtml_no_files'],
		'L_SIZE' => Cot::$L['pagehtml_size'],
		'L_MTIME' => Cot::$L['pagehtml_mtime'],
		'L_NEW_FILE' => Cot::$L['pagehtml_new_file'],
		'L_NEW_DIR' => Cot::$L['pagehtml_new_dir'],
		'L_CREATE' => Cot::$L['pagehtml_create'],
		'L_DELETE' => Cot::$L['pagehtml_delete'],
		'L_CONFIRM_DEL' => Cot::$L['pagehtml_confirm_del'],
		'L_FOLDER' => Cot::$L['pagehtml_folder'],
		'L_FILE' => Cot::$L['pagehtml_file'],
		'L_NAME' => Cot::$L['pagehtml_name'],
		'L_ROOT' => Cot::$L['pagehtml_root'],
		'L_EXT_HINT' => Cot::$L['pagehtml_ext_hint'],
		'ROOT_URL' => cot_url('admin', ['m' => 'other', 'p' => 'pagehtml']),
	]);

	foreach ($crumbs as $c) {
		$t->assign([
			'CRUMB_NAME' => htmlspecialchars($c['name']),
			'CRUMB_URL' => cot_url('admin', ['m' => 'other', 'p' => 'pagehtml', 'dir' => $c['rel']]),
		]);
		$t->parse('MAIN.CRUMB');
	}

	foreach ($list['dirs'] as $d) {
		$t->assign([
			'DIR_NAME' => htmlspecialchars($d['name']),
			'DIR_REL' => htmlspecialchars($d['rel']),
			'DIR_URL' => cot_url('admin', ['m' => 'other', 'p' => 'pagehtml', 'dir' => $d['rel']]),
		]);
		$t->parse('MAIN.DIR_ROW');
	}

	foreach ($list['files'] as $f) {
		$t->assign([
			'FILE_NAME' => htmlspecialchars($f['name']),
			'FILE_REL' => htmlspecialchars($f['rel']),
			'FILE_SIZE' => $f['size'],
			'FILE_MTIME' => cot_date('datetime_medium', $f['mtime']),
			'FILE_URL' => cot_url('admin', ['m' => 'other', 'p' => 'pagehtml', 'file' => $f['rel']]),
		]);
		$t->parse('MAIN.FILE_ROW');
	}

	if (empty($list['dirs']) && empty($list['files'])) {
		$t->parse('MAIN.NO_FILES');
	}

	cot_display_messages($t);
	$t->parse('MAIN');
	$pluginBody = $t->text('MAIN');
}
