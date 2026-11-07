<!-- BEGIN: MAIN -->
<h1>{L_EDITING}</h1>
<code>{FILE_REL}</code>
{FILE "{PHP.cfg.themes_dir}/admin/{PHP.cfg.admintheme}/warnings.tpl"}

<div class="d-flex flex-wrap gap-2 align-items-center my-3">
	<a href="{BACK_URL}" class="btn btn-secondary">
		<i class="fa-solid fa-arrow-left"></i> {L_BACK}
	</a>
	<button type="button" id="pagehtml-save" class="btn btn-danger">
		<i class="fa-solid fa-floppy-disk"></i> {L_SAVE}
	</button>
	<a href="{BACKUP_URL}" class="btn btn-outline-success" target="_blank" rel="noopener">
		<i class="fa-solid fa-download"></i> {L_BACKUP}
	</a>
	<button type="button" id="pagehtml-delete" class="btn btn-outline-danger">
		<i class="fa-solid fa-trash"></i> {L_DELETE}
	</button>
	<span id="pagehtml-status" class="text-muted ms-2"></span>
</div>

<div id="pagehtml-cm-wrap">
	<textarea id="pagehtml-source">{FILE_CONTENT}</textarea>
</div>

<div id="pagehtml-meta"
	data-file="{FILE_REL}"
	data-x="{X_TOKEN}"
	data-ajax-url="{AJAX_URL}"
	data-msg-saved="{L_SAVED}"
	data-msg-error="{L_ERROR}"
	data-msg-confirm="{L_CONFIRM_DEL}"
	data-back-url="{BACK_URL}"
	style="display:none"></div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/lib/codemirror.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/theme/eclipse.min.css">
<style>
	#pagehtml-cm-wrap {
		resize: vertical;
		overflow: hidden;
		height: 600px;
		min-height: 200px;
		max-height: 90vh;
		border: 1px solid #444;
	}
	#pagehtml-cm-wrap .CodeMirror {
		height: 100%;
		font-size: 14px;
	}
	.cm-s-eclipse.CodeMirror {
		background: #ffffff;
		color: #000000;
	}
	.cm-s-eclipse span.cm-tag { color: #500573; }
	.cm-s-eclipse span.cm-attribute { color: #d40f0f; }
	.cm-s-eclipse span.cm-string { color: #0942ad; }
	.cm-s-eclipse span.cm-comment { color: #008000; font-style: italic; }
	.cm-s-eclipse span.cm-keyword { color: #0000ff; }
	.cm-s-eclipse span.cm-number { color: #ff6600; }
	.cm-s-eclipse span.cm-meta { color: #808080; }
</style>
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/lib/codemirror.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/mode/xml/xml.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/mode/javascript/javascript.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/mode/css/css.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/mode/htmlmixed/htmlmixed.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/mode/markdown/markdown.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/addon/edit/matchbrackets.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/codemirror@5.65.16/addon/edit/closebrackets.min.js"></script>
<script>
(function () {
	var meta = document.getElementById('pagehtml-meta');
	if (!meta) return;

	var file = meta.dataset.file;
	var xToken = meta.dataset.x;
	var ajaxUrl = meta.dataset.ajaxUrl;
	var msgSaved = meta.dataset.msgSaved || 'Saved';
	var msgError = meta.dataset.msgError || 'Error';
	var msgConfirm = meta.dataset.msgConfirm || 'Delete?';
	var backUrl = meta.dataset.backUrl || '';
	var statusEl = document.getElementById('pagehtml-status');
	var saveBtn = document.getElementById('pagehtml-save');
	var delBtn = document.getElementById('pagehtml-delete');
	var wrap = document.getElementById('pagehtml-cm-wrap');
	var source = document.getElementById('pagehtml-source');

	if (!source || typeof CodeMirror === 'undefined') {
		return;
	}

	var mode = 'htmlmixed';
	if (/\.md$/i.test(file)) {
		mode = 'markdown';
	} else if (/\.txt$/i.test(file)) {
		mode = null;
	}

	var editorOpts = {
		lineNumbers: true,
		lineWrapping: true,
		matchBrackets: true,
		autoCloseBrackets: true,
		indentUnit: 4,
		tabSize: 4,
		theme: 'eclipse',
		extraKeys: {
			'Ctrl-S': function () { save(); return false; },
			'Cmd-S': function () { save(); return false; },
			'Tab': function (cm) { cm.indentSelection('add'); },
			'Shift-Tab': function (cm) { cm.indentSelection('subtract'); }
		}
	};
	if (mode) {
		editorOpts.mode = mode;
	}

	var editor = CodeMirror.fromTextArea(source, editorOpts);

	if (window.ResizeObserver && wrap) {
		new ResizeObserver(function () {
			editor.refresh();
		}).observe(wrap);
	}
	setTimeout(function () {
		editor.refresh();
	}, 0);

	function save() {
		if (!saveBtn) return;
		statusEl.textContent = '...';
		statusEl.className = 'text-muted ms-2';
		saveBtn.disabled = true;

		var body = new URLSearchParams();
		body.set('x', xToken);
		body.set('a', 'save');
		body.set('file', file);
		body.set('content', editor.getValue());

		fetch(ajaxUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body
		})
		.then(function (r) {
			return r.json();
		})
		.then(function (data) {
			saveBtn.disabled = false;
			if (data.success) {
				statusEl.textContent = msgSaved + ' (' + (data.bytes || 0) + ' B)';
				statusEl.className = 'text-success ms-2';
			} else {
				statusEl.textContent = msgError + ': ' + (data.message || '?');
				statusEl.className = 'text-danger ms-2';
			}
		})
		.catch(function (err) {
			saveBtn.disabled = false;
			statusEl.textContent = msgError + ': ' + err.message;
			statusEl.className = 'text-danger ms-2';
			console.error(err);
		});
	}

	if (saveBtn) {
		saveBtn.addEventListener('click', save);
	}

	if (delBtn) {
		delBtn.addEventListener('click', function () {
			if (!window.confirm(msgConfirm)) return;
			var body = new URLSearchParams();
			body.set('x', xToken);
			body.set('a', 'delete');
			body.set('file', file);
			fetch(ajaxUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: body
			})
			.then(function (r) {
				return r.json();
			})
			.then(function (data) {
				if (data.success) {
					window.location.href = backUrl;
				} else {
					window.alert(data.message || msgError);
				}
			})
			.catch(function (err) {
				window.alert(err.message || msgError);
			});
		});
	}
})();
</script>
<!-- END: MAIN -->
