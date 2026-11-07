<!-- BEGIN: MAIN -->
<h1><i class="fa-solid fa-folder-open"></i> {L_TITLE}</h1>
{FILE "{PHP.cfg.themes_dir}/admin/{PHP.cfg.admintheme}/warnings.tpl"}

<nav class="mb-3" aria-label="breadcrumb">
	<ol class="breadcrumb mb-0">
		<li class="breadcrumb-item"><a href="{ROOT_URL}">{L_ROOT}</a></li>
		<!-- BEGIN: CRUMB -->
		<li class="breadcrumb-item"><a href="{CRUMB_URL}">{CRUMB_NAME}</a></li>
		<!-- END: CRUMB -->
	</ol>
</nav>

<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
	<button type="button" class="btn btn-success btn-sm" id="pagehtml-new-file">
		<i class="fa-solid fa-file-circle-plus"></i> {L_NEW_FILE}
	</button>
	<button type="button" class="btn btn-primary btn-sm" id="pagehtml-new-dir">
		<i class="fa-solid fa-folder-plus"></i> {L_NEW_DIR}
	</button>
	<small class="text-muted ms-2">{L_EXT_HINT}</small>
</div>

<div class="list-group">
	<!-- BEGIN: DIR_ROW -->
	<div class="list-group-item d-flex justify-content-between align-items-center">
		<a href="{DIR_URL}" class="text-decoration-none">
			<i class="fa-solid fa-folder text-warning"></i>
			<strong class="ms-1">{DIR_NAME}</strong>
		</a>
		<button type="button" class="btn btn-outline-danger btn-sm pagehtml-del-dir" data-rel="{DIR_REL}">
			<i class="fa-solid fa-trash"></i> {L_DELETE}
		</button>
	</div>
	<!-- END: DIR_ROW -->

	<!-- BEGIN: FILE_ROW -->
	<div class="list-group-item d-flex justify-content-between align-items-center">
		<a href="{FILE_URL}" class="text-decoration-none">
			<i class="fa-regular fa-file-code"></i>
			<code class="ms-1">{FILE_NAME}</code>
		</a>
		<span class="d-flex align-items-center gap-2">
			<small class="text-muted">{FILE_SIZE} B · {FILE_MTIME}</small>
			<button type="button" class="btn btn-outline-danger btn-sm pagehtml-del-file" data-rel="{FILE_REL}">
				<i class="fa-solid fa-trash"></i> {L_DELETE}
			</button>
		</span>
	</div>
	<!-- END: FILE_ROW -->
</div>

<!-- BEGIN: NO_FILES -->
<div class="alert alert-info mt-3">{L_NO_FILES}</div>
<!-- END: NO_FILES -->

<div id="pagehtml-meta"
	data-x="{X_TOKEN}"
	data-ajax-url="{AJAX_URL}"
	data-dir="{CURRENT_DIR}"
	data-msg-confirm="{L_CONFIRM_DEL}"
	data-msg-name="{L_NAME}"
	data-msg-create="{L_CREATE}"
	data-msg-ext="{L_EXT_HINT}"
	style="display:none"></div>

<script>
(function () {
	var meta = document.getElementById('pagehtml-meta');
	if (!meta) return;

	var x = meta.dataset.x;
	var ajaxUrl = meta.dataset.ajaxUrl;
	var dir = meta.dataset.dir || '';
	var msgConfirm = meta.dataset.msgConfirm || 'Delete?';
	var msgName = meta.dataset.msgName || 'Name';
	var msgExt = meta.dataset.msgExt || '';

	function post(data) {
		var body = new URLSearchParams();
		body.set('x', x);
		Object.keys(data).forEach(function (k) {
			body.set(k, data[k]);
		});
		return fetch(ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body
		}).then(function (r) {
			return r.json();
		});
	}

	var btnNewFile = document.getElementById('pagehtml-new-file');
	if (btnNewFile) {
		btnNewFile.addEventListener('click', function () {
			var hint = msgName + (msgExt ? '\n' + msgExt : '') + '\n(example: about.html)';
			var name = window.prompt(hint);
			if (!name) return;
			post({ a: 'create_file', dir: dir, name: name }).then(function (data) {
				if (data.success) {
					window.location.reload();
				} else {
					window.alert(data.message || 'Error');
				}
			}).catch(function (err) {
				window.alert(err.message || 'Error');
			});
		});
	}

	var btnNewDir = document.getElementById('pagehtml-new-dir');
	if (btnNewDir) {
		btnNewDir.addEventListener('click', function () {
			var name = window.prompt(msgName);
			if (!name) return;
			post({ a: 'create_dir', dir: dir, name: name }).then(function (data) {
				if (data.success) {
					window.location.reload();
				} else {
					window.alert(data.message || 'Error');
				}
			}).catch(function (err) {
				window.alert(err.message || 'Error');
			});
		});
	}

	document.querySelectorAll('.pagehtml-del-file').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (!window.confirm(msgConfirm)) return;
			post({ a: 'delete', file: btn.getAttribute('data-rel') }).then(function (data) {
				if (data.success) {
					window.location.reload();
				} else {
					window.alert(data.message || 'Error');
				}
			}).catch(function (err) {
				window.alert(err.message || 'Error');
			});
		});
	});

	document.querySelectorAll('.pagehtml-del-dir').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (!window.confirm(msgConfirm)) return;
			post({ a: 'delete', dir: btn.getAttribute('data-rel') }).then(function (data) {
				if (data.success) {
					window.location.reload();
				} else {
					window.alert(data.message || 'Error');
				}
			}).catch(function (err) {
				window.alert(err.message || 'Error');
			});
		});
	});
})();
</script>
<!-- END: MAIN -->
