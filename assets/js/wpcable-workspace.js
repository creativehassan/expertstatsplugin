/* global jQuery, wpcableWorkspace, wpcable, SimpleMDE, ajaxurl */
(function ($) {
	'use strict';

	if (typeof wpcableWorkspace === 'undefined') {
		return;
	}

	var cfg = wpcableWorkspace;
	var notesMde = null;
	var currentTask = null;

	function ajax(action, data) {
		data = data || {};
		data.action = action;
		data._wpnonce = cfg.nonce;
		return $.post(cfg.ajaxUrl || ajaxurl, data);
	}

	function esc(str) {
		return $('<div/>').text(str == null ? '' : String(str)).html();
	}

	function money(task) {
		if (!task || !task.value_known) {
			return '<span class="wpcable-source-chip" title="' + esc(cfg.i18n.notZero) + '">—</span>';
		}
		return '<span>$' + esc(parseInt(task.value, 10)) + '</span>';
	}

	function avatar(url) {
		if (!url) {
			return '';
		}
		return '<img class="wpcable-avatar" src="' + esc(url) + '" alt="" width="32" height="32" />';
	}

	function ageLabel(days, valid) {
		if (valid === false || days === null || days === undefined || days === '' || days < 0) {
			return '—';
		}
		return days + 'd ago';
	}

	function unwrapWpAjax(res) {
		if (typeof res === 'string') {
			try {
				res = JSON.parse(res);
			} catch (e) {
				return { ok: false, message: 'invalid_json' };
			}
		}
		if (!res || typeof res !== 'object') {
			return { ok: false, message: 'empty' };
		}
		if (res.success === true || res.success === 'true' || res.success === 1) {
			return { ok: true, data: res.data };
		}
		if (res.success === false || res.success === 'false' || res.success === 0) {
			return {
				ok: false,
				message: (res.data && res.data.message) ? res.data.message : 'request_failed',
				data: res.data
			};
		}
		if (res.items || res.task || res.total !== undefined) {
			return { ok: true, data: res };
		}
		if (res.data && (res.data.items || res.data.task || res.data.total !== undefined)) {
			return { ok: true, data: res.data };
		}
		return { ok: false, message: 'unexpected_shape' };
	}

	function applyCounts(data, $scope) {
		if (!$scope || !$scope.length) {
			$scope = $('.wpcable-workspace');
		}
		var filters = (data && data.filter_counts) || {};
		var counts = (data && data.counts) || {};
		function setKpi(key, value) {
			var $el = $scope.find('[data-kpi="' + key + '"]');
			if ($el.length && value !== undefined && value !== null) {
				$el.text(String(value));
			}
		}
		if (filters.all !== undefined) {
			setKpi('imported', filters.all);
		} else if (counts.imported !== undefined) {
			setKpi('imported', counts.imported);
		}
		setKpi('published', filters.published);
		setKpi('completed', filters.completed);
		setKpi('lost', filters.lost);
		$scope.find('.wpcable-filter[data-filter-key]').each(function () {
			var key = jQuery(this).data('filter-key');
			var n = filters[key];
			var $count = jQuery(this).find('.wpcable-filter-count');
			if (n === undefined) {
				return;
			}
			if (!$count.length) {
				jQuery(this).append(' <span class="wpcable-filter-count"></span>');
				$count = jQuery(this).find('.wpcable-filter-count');
			}
			$count.text('(' + n + ')');
		});
		if (data && data.sync && data.sync.running) {
			$scope.find('#wpcable-sync-banner').prop('hidden', false);
		}
	}

	function historyPanels(history, workroomUrl) {
		history = history || {};
		var desc = history.description || {};
		var comments = history.comments || {};
		var workroom = history.workroom || {};
		var url = workroom.url || workroomUrl || '';
		var html = '<section class="wpcable-history-panel" data-present="' + (desc.present_in_payload ? '1' : '0') + '">';
		html += '<h3>' + esc(desc.label || 'Project description') + '</h3>';
		if (desc.available && desc.text) {
			html += '<div class="wpcable-history-body">' + esc(desc.text).replace(/\n/g, '<br />') + '</div>';
		} else if (desc.empty) {
			html += '<p class="wpcable-history-empty">' + esc(desc.reason || 'The saved import includes an empty description field.') + '</p>';
		} else {
			html += '<p class="wpcable-history-missing">' + esc(desc.reason || cfg.i18n.historyUnavailable) + '</p>';
		}
		html += '</section>';
		html += '<section class="wpcable-history-panel" data-present="' + (comments.present_in_payload ? '1' : '0') + '">';
		html += '<h3>' + esc(comments.label || 'Workroom comments') + '</h3>';
		if (comments.available && comments.items && comments.items.length) {
			html += '<ol class="wpcable-comment-list">';
			comments.items.forEach(function (item) {
				var vis = (item && item.visibility) || 'unknown';
				html += '<li class="wpcable-comment wpcable-comment--' + esc(vis) + '">';
				html += '<span class="wpcable-comment__meta">';
				html += esc((item && item.author) ? item.author : (cfg.i18n.authorMissing || 'Author not in payload'));
				if (item && item.created_at) {
					html += ' · ' + esc(item.created_at);
				}
				html += ' · ' + esc(vis);
				html += '</span>';
				html += '<div class="wpcable-comment__body">' + esc((item && item.body) || '') + '</div>';
				html += '</li>';
			});
			html += '</ol>';
			if (comments.reason) {
				html += '<p class="description">' + esc(comments.reason) + '</p>';
			}
		} else if (comments.empty) {
			html += '<p class="wpcable-history-empty">' + esc(comments.reason || 'The saved import includes a comments field that was empty.') + '</p>';
		} else {
			html += '<p class="wpcable-history-missing">' + esc(comments.reason || cfg.i18n.commentsUnavailable) + '</p>';
		}
		if (url) {
			html +=
				'<p><a class="button" href="' +
				esc(url) +
				'" target="_blank" rel="noopener">' +
				esc(workroom.label || 'Open workroom (read-only)') +
				'</a></p>';
		}
		html += '<p class="description">' + esc(cfg.i18n.notesNotComments) + '</p>';
		if (history.can_load) {
			html +=
				'<p><button type="button" class="button button-secondary wpcable-load-history">' +
				esc(history.load_label || cfg.i18n.loadHistory || 'Load from Codeable (read-only)') +
				'</button>';
			if (history.can_load_more) {
				html +=
					' <button type="button" class="button button-secondary wpcable-load-more-comments">' +
					esc(history.load_more_label || cfg.i18n.loadMoreComments || 'Load more comments (read-only)') +
					'</button>';
			}
			html += '</p>';
			if (history.load_help) {
				html += '<p class="description">' + esc(history.load_help) + '</p>';
			}
		} else {
			html += '<p class="description">' + esc(cfg.i18n.loadHistoryDisconnected || 'Load from Codeable is off while disconnected. Saved data stays visible.') + '</p>';
		}
		if (history.shape && (history.shape.comment_keys || history.shape.envelope || history.shape.link)) {
			html += '<details class="wpcable-shape-debug"><summary>' + esc(cfg.i18n.shapeDebug || 'Response shape (keys only)') + '</summary>';
			html += '<p class="description">' + esc('Envelope: ' + (history.shape.envelope || '—') + '. Item count: ' + (history.shape.item_count || 0) + '.') + '</p>';
			if (history.shape.top_keys && history.shape.top_keys.length) {
				html += '<p class="description">' + esc('Top-level keys: ' + history.shape.top_keys.join(', ')) + '</p>';
			}
			if (history.shape.comment_keys && history.shape.comment_keys.length) {
				html += '<p class="description">' + esc('Comment keys: ' + history.shape.comment_keys.join(', ')) + '</p>';
			}
			if (history.shape.nested) {
				Object.keys(history.shape.nested).forEach(function (k) {
					var n = history.shape.nested[k] || {};
					var extra = n.keys && n.keys.length ? ' keys ' + n.keys.join(',') : '';
					html += '<p class="description">' + esc(k + ': ' + (n.kind || '') + extra) + '</p>';
				});
			}
			if (history.shape.link) {
				html += '<p class="description">' + esc('Sanitized Link: ' + history.shape.link) + '</p>';
			}
			html += '</details>';
		}
		html += '</section>';
		return html;
	}

	function pagination(el, page, perPage, total, onPage) {
		el.empty();
		var pages = Math.max(1, Math.ceil(total / perPage));
		var from = total ? (page - 1) * perPage + 1 : 0;
		var to = Math.min(total, page * perPage);
		el.append(
			'<span>' +
				esc('Showing ' + from + '–' + to + ' of ' + total) +
				'</span>'
		);
		var nav = $('<div class="wpcable-page-buttons"></div>');
		if (page > 1) {
			$('<button type="button" class="button">Previous</button>')
				.on('click', function () {
					onPage(page - 1);
				})
				.appendTo(nav);
		}
		if (page < pages) {
			$('<button type="button" class="button">Next</button>')
				.on('click', function () {
					onPage(page + 1);
				})
				.appendTo(nav);
		}
		el.append(nav);
	}

	function renderSuggestions(list) {
		if (!list || !list.length) {
			return '<p class="description">' + esc(cfg.i18n.suggested) + ' ' + esc('None matched imported fields for this record.') + '</p>';
		}
		var html = '<ul class="wpcable-suggestion-list">';
		list.forEach(function (cue) {
			html +=
				'<li><strong>' +
				esc(cue.title) +
				'</strong> <span class="wpcable-badge">' +
				esc(cue.confidence) +
				'</span><br /><span class="description">' +
				esc((cue.reasons && cue.reasons[0]) || cue.disclaimer) +
				'</span></li>';
		});
		html += '</ul>';
		return html;
	}

	function renderUnsupported(list) {
		if (!list || !list.length) {
			return '';
		}
		var html = '<div class="wpcable-unsupported"><h3>' + esc('Not available from current API map') + '</h3><ul>';
		list.forEach(function (item) {
			html += '<li><strong>' + esc(item.title) + '</strong> — ' + esc(item.reason) + '</li>';
		});
		html += '</ul></div>';
		return html;
	}

	function renderEvidence(ev) {
		if (!ev) {
			return '';
		}
		function block(title, items) {
			if (!items || !items.length) {
				return '';
			}
			return (
				'<h3>' +
				esc(title) +
				'</h3><ul>' +
				items
					.map(function (i) {
						return '<li>' + esc(i) + '</li>';
					})
					.join('') +
				'</ul>'
			);
		}
		return (
			'<div class="wpcable-evidence">' +
			block('Available now', ev.available) +
			block('Partial', ev.partial) +
			block('Not verified', ev.unverified) +
			'</div>'
		);
	}

	function renderDraft(draft) {
		if (!draft) {
			return '';
		}
		return (
			'<div class="wpcable-draft"><p><strong>' +
			esc(draft.notice) +
			'</strong></p><p><em>' +
			esc('Subject') +
			':</em> ' +
			esc(draft.subject) +
			'</p><textarea readonly>' +
			esc(draft.body) +
			'</textarea></div>'
		);
	}

	function tasksWorkspace() {
		var $root = $('.wpcable-workspace--tasks');
		if (!$root.length) {
			return;
		}

		var state = {
			page: 1,
			per_page: 25,
			search: '',
			state: 'all',
			preferred: 0,
			paid: 0,
			estimated: 0,
			lost: 0,
			completed: 0,
			has_notes: 0,
			group_by_client: 0
		};
		var searchTimer = null;

		function params() {
			return {
				page_num: state.page,
				per_page: state.per_page,
				search: state.search,
				state: state.state,
				preferred: state.preferred,
				paid: state.paid,
				estimated: state.estimated,
				lost: state.lost,
				completed: state.completed,
				has_notes: state.has_notes,
				group_by_client: state.group_by_client
			};
		}

		function tableBody() {
			var $tbody = $root.find('#wpcable-task-rows');
			return $tbody.length ? $tbody : $('#wpcable-task-rows');
		}

		function showTableMessage(msg, isError) {
			tableBody().html(
				'<tr class="wpcable-empty-row' +
					(isError ? ' wpcable-error-row' : '') +
					'"><td colspan="7">' +
					esc(msg) +
					'</td></tr>'
			);
		}

		function load() {
			showTableMessage(cfg.i18n.loading || 'Loading…', false);
			ajax('wpcable_query_tasks', params())
				.done(function (res) {
					var parsed = unwrapWpAjax(res);
					if (!parsed.ok || !parsed.data) {
						showTableMessage(
							(cfg.i18n.noTasks || 'No matching projects') +
								' (' +
								esc(parsed.message || 'error') +
								')',
							true
						);
						return;
					}
					try {
						renderRows(parsed.data);
					} catch (err) {
						showTableMessage(
							(cfg.i18n.unavailable || 'Unavailable') +
								': ' +
								(err && err.message ? err.message : String(err)),
							true
						);
					}
				})
				.fail(function (xhr, status) {
					showTableMessage(
						(cfg.i18n.unavailable || 'Unavailable') +
							' (' +
							esc(status || 'ajax') +
							')',
						true
					);
				});
		}

		function renderRows(data) {
			try {
				applyCounts(data, $root);
			} catch (err) {
				if (window.console && console.warn) {
					console.warn('wpcable applyCounts', err);
				}
			}
			data = data || {};
			var items = Array.isArray(data.items) ? data.items : [];
			var $tbody = tableBody();
			$tbody.empty();
			if (!items.length) {
				$tbody.html('<tr class="wpcable-empty-row"><td colspan="7">' + esc(cfg.i18n.noTasks) + '</td></tr>');
			} else {
				var lastClient = null;
				items.forEach(function (task) {
					if (data.group_by_client && lastClient !== task.client_id) {
						lastClient = task.client_id;
						$tbody.append(
							'<tr class="wpcable-group-row"><td colspan="7">' +
								esc(task.client_name) +
								' · client_id ' +
								esc(task.client_id) +
								'</td></tr>'
						);
					}
					var notesLabel = task.has_notes ? 'Has notes' : 'No local notes';
					var row = $(
						'<tr class="wpcable-row-link" data-task-id="' +
							esc(task.task_id) +
							'">' +
							'<td class="wpcable-client-cell">' +
							avatar(task.avatar) +
							'<span>' +
							esc(task.client_name) +
							'</span></td>' +
							'<td>' +
							esc(task.activity_valid ? (task.last_activity_date || '—') : '—') +
							'</td>' +
							'<td><a href="' +
							esc(task.workroom_url) +
							'" target="_blank" rel="noopener">#' +
							esc(task.task_id) +
							'</a></td>' +
							'<td>' +
							money(task) +
							'</td>' +
							'<td>' +
							esc(task.title) +
							'</td>' +
							'<td>' +
							esc(task.state) +
							(task.flag ? ' / ' + esc(task.flag) : '') +
							(task.preferred ? ' ★' : '') +
							'</td>' +
							'<td><div class="wpcable-notes-preview' +
							(task.has_notes ? '' : ' is-empty') +
							'" data-task-id="' +
							esc(task.task_id) +
							'">' +
							esc(notesLabel) +
							'</div></td>' +
							'</tr>'
					);
					$tbody.append(row);
				});
			}
			var $pager = $root.find('#wpcable-task-pagination');
			if (!$pager.length) {
				$pager = $('#wpcable-task-pagination');
			}
			pagination($pager, data.page, data.per_page, data.total, function (p) {
				state.page = p;
				load();
			});
		}

		function openDrawer(taskId, loadNotice) {
			var $layout = $root.find('.wpcable-workspace-layout');
			var $drawer = $('#wpcable-task-drawer');
			var $body = $('#wpcable-task-drawer-body');
			$drawer.prop('hidden', false);
			$layout.addClass('wpcable-drawer-open');
			$body.html('<p>' + esc(cfg.i18n.loading) + '</p>');
			ajax('wpcable_get_task', { task_id: taskId }).done(function (res) {
				var parsed = unwrapWpAjax(res);
				if (!parsed.ok || !parsed.data || !parsed.data.task) {
					$body.html('<p>' + esc(cfg.i18n.unavailable) + '</p>');
					return;
				}
				var task = parsed.data.task;
				currentTask = task;
				var html = '<h2>#' + esc(task.task_id) + ' · ' + esc(task.title) + '</h2>';
				html += '<p>' + esc(task.client_name) + ' · client_id ' + esc(task.client_id) + '</p>';
				html +=
					'<p><strong>' +
					esc('Status') +
					':</strong> ' +
					esc(task.state) +
					' / ' +
					esc(task.kind || '—') +
					' / flag ' +
					esc(task.flag || '—') +
					(task.preferred ? ' · preferred' : '') +
					'</p>';
				html += '<p><strong>' + esc('Value') + ':</strong> ' + money(task) + '</p>';
				html +=
					'<p><strong>' +
					esc('Last activity by') +
					':</strong> ' +
					esc(task.last_activity_by || '—') +
					' · ' +
					esc(task.activity_valid ? (task.last_activity_date || '—') : '—') +
					'</p>';
				html += historyPanels(parsed.data.history || task.history, task.workroom_url);
				html +=
					'<p><button type="button" class="button wpcable-edit-notes">' +
					esc('Edit local notes') +
					'</button></p>';
				html += '<p class="description">' + esc(cfg.i18n.notesLocal) + '</p>';
				if (task.notes) {
					html += '<pre class="wpcable-notes-full">' + esc(task.notes) + '</pre>';
				}
				html += '<h3>' + esc('Suggested next step') + '</h3>' + renderSuggestions(parsed.data.suggestions);
				html += renderUnsupported(parsed.data.unsupported);
				if (loadNotice) {
					html = '<p class="notice notice-info">' + esc(loadNotice) + '</p>' + html;
				}
				$body.html(html);
			});
		}

		$root.on('click', '.wpcable-filter', function () {
			var $btn = $(this);
			$root.find('.wpcable-filter').removeClass('is-active button-primary');
			$btn.addClass('is-active button-primary');
			state.state = $btn.data('state') || 'all';
			state.preferred = $btn.data('preferred') ? 1 : 0;
			state.paid = $btn.data('paid') ? 1 : 0;
			state.estimated = $btn.data('estimated') ? 1 : 0;
			state.lost = $btn.data('lost') ? 1 : 0;
			state.completed = $btn.data('completed') ? 1 : 0;
			state.has_notes = $btn.data('has_notes') ? 1 : 0;
			state.page = 1;
			load();
		});

		$('#wpcable-per-page').on('change', function () {
			state.per_page = parseInt($(this).val(), 10) || 25;
			state.page = 1;
			load();
		});

		$('#wpcable-group-clients').on('change', function () {
			state.group_by_client = $(this).prop('checked') ? 1 : 0;
			state.page = 1;
			load();
		});

		$('#wpcable-task-search').on('keyup search', function () {
			var val = $(this).val();
			clearTimeout(searchTimer);
			searchTimer = setTimeout(function () {
				state.search = val;
				state.page = 1;
				load();
			}, 250);
		});

		$root.on('click', '.wpcable-row-link', function (ev) {
			if ($(ev.target).closest('a, .wpcable-notes-preview').length) {
				return;
			}
			openDrawer($(this).data('task-id'));
		});

		$root.on('click', '.wpcable-notes-preview', function (ev) {
			ev.stopPropagation();
			openNotes($(this).data('task-id'));
		});

		$root.on('click', '.wpcable-drawer__close', function () {
			$('#wpcable-task-drawer').prop('hidden', true);
			$root.find('.wpcable-workspace-layout').removeClass('wpcable-drawer-open');
		});

		$root.on('click', '.wpcable-edit-notes', function () {
			if (currentTask) {
				openNotesEditor(currentTask);
			}
		});

		$root.on('click', '.wpcable-load-history', function () {
			if (!currentTask || !currentTask.task_id) {
				return;
			}
			var $btn = $(this);
			$btn.prop('disabled', true);
			ajax('wpcable_load_task_history', { task_id: currentTask.task_id }).done(function (res) {
				var parsed = unwrapWpAjax(res);
				var payload = parsed.data || {};
				var notice = payload.message || parsed.message || '';
				openDrawer(currentTask.task_id, notice);
			}).fail(function () {
				$btn.prop('disabled', false);
			});
		});

		$root.on('click', '.wpcable-load-more-comments', function () {
			if (!currentTask || !currentTask.task_id) {
				return;
			}
			var $btn = $(this);
			$btn.prop('disabled', true);
			ajax('wpcable_load_task_history', { task_id: currentTask.task_id, more: 1 }).done(function (res) {
				var parsed = unwrapWpAjax(res);
				var payload = parsed.data || {};
				var notice = payload.message || parsed.message || '';
				openDrawer(currentTask.task_id, notice);
			}).fail(function () {
				$btn.prop('disabled', false);
			});
		});

		function openNotes(taskId) {
			ajax('wpcable_get_task', { task_id: taskId }).done(function (res) {
				var parsed = unwrapWpAjax(res);
				if (parsed.ok && parsed.data && parsed.data.task) {
					openNotesEditor(parsed.data.task);
				}
			});
		}

		function openNotesEditor(task) {
			var layer = $root.find('.notes-editor-layer');
			currentTask = task;
			layer.show();
			layer.find('.task-title').text(task.title || '');
			if (notesMde) {
				notesMde.toTextArea();
				notesMde = null;
			}
			notesMde = new SimpleMDE({
				element: layer.find('textarea').val(task.notes || '')[0],
				status: false,
				spellChecker: false
			});
		}

		$root.on('click', '.btn-cancel', function () {
			$root.find('.notes-editor-layer').hide();
			if (notesMde) {
				notesMde.toTextArea();
				notesMde = null;
			}
		});

		$root.on('click', '.btn-save', function () {
			if (!currentTask || !notesMde) {
				return;
			}
			currentTask.notes = notesMde.value();
			$.post(cfg.ajaxUrl || ajaxurl, {
				action: 'wpcable_update_task',
				_wpnonce: cfg.taskNonce || wpcable.update_task_nonce,
				task: {
					task_id: currentTask.task_id,
					notes: currentTask.notes,
					flag: currentTask.flag,
					state: currentTask.state
				}
			}).done(function () {
				$root.find('.notes-editor-layer').hide();
				if (notesMde) {
					notesMde.toTextArea();
					notesMde = null;
				}
				load();
			});
		});

		$(document).on('codeable-reload-tasks', load);
		load();
	}

	function clientsWorkspace() {
		var $root = $('.wpcable-workspace--clients');
		if (!$root.length) {
			return;
		}
		var state = { page: 1, per_page: 25, search: '', filter: 'all' };
		var searchTimer = null;

		function load() {
			var $tbody = $root.find('#wpcable-client-rows');
			if (!$tbody.length) {
				$tbody = $('#wpcable-client-rows');
			}
			$tbody.html(
				'<tr class="wpcable-empty-row"><td colspan="4">' + esc(cfg.i18n.loading) + '</td></tr>'
			);
			ajax('wpcable_query_clients', {
				page_num: state.page,
				per_page: state.per_page,
				search: state.search,
				filter: state.filter
			})
				.done(function (res) {
					var parsed = unwrapWpAjax(res);
					if (!parsed.ok || !parsed.data) {
						$tbody.html(
							'<tr class="wpcable-empty-row wpcable-error-row"><td colspan="4">' +
								esc(cfg.i18n.unavailable || 'Unavailable') +
								'</td></tr>'
						);
						return;
					}
					try {
						render(parsed.data);
					} catch (err) {
						$tbody.html(
							'<tr class="wpcable-empty-row wpcable-error-row"><td colspan="4">' +
								esc(cfg.i18n.unavailable || 'Unavailable') +
								'</td></tr>'
						);
					}
				})
				.fail(function () {
					$tbody.html(
						'<tr class="wpcable-empty-row wpcable-error-row"><td colspan="4">' +
							esc(cfg.i18n.unavailable || 'Unavailable') +
							'</td></tr>'
					);
				});
		}

		function render(data) {
			var $tbody = $('#wpcable-client-rows');
			$tbody.empty();
			var items = data.items || [];
			if (!items.length) {
				$tbody.html('<tr class="wpcable-empty-row"><td colspan="4">' + esc('No matching clients in imported projects.') + '</td></tr>');
			}
			items.forEach(function (client) {
				var flags = [];
				if (client.preferred) {
					flags.push('preferred');
				}
				if (client.task_stats && client.task_stats.completed) {
					flags.push('completed ' + client.task_stats.completed);
				}
				if (client.task_stats && client.task_stats.lost) {
					flags.push('lost (local) ' + client.task_stats.lost);
				}
				var row = $(
					'<tr class="wpcable-row-link" data-client-id="' +
						esc(client.client_id) +
						'"><td class="wpcable-client-cell">' +
						avatar(client.avatar) +
						'<span>' +
						esc(client.full_name) +
						'<div class="description">' +
						esc(client.role || '') +
						' · ' +
						esc(client.client_id) +
						'</div></span></td><td>' +
						esc(client.task_stats ? client.task_stats.total : 0) +
						'</td><td>' +
						esc(flags.join(' · ') || '—') +
						'</td><td>' +
						esc(ageLabel(client.stale_days, client.activity_valid)) +
						'</td></tr>'
				);
				$tbody.append(row);
			});
			pagination($('#wpcable-client-pagination'), data.page, data.per_page, data.total, function (p) {
				state.page = p;
				load();
			});
		}

		function openClient(id) {
			var $body = $('#wpcable-client-drawer-body');
			$body.html('<p>' + esc(cfg.i18n.loading) + '</p>');
			ajax('wpcable_get_client', { client_id: id }).done(function (res) {
				var parsed = unwrapWpAjax(res);
				if (!parsed.ok || !parsed.data || !parsed.data.client) {
					$body.html('<p>' + esc(cfg.i18n.unavailable) + '</p>');
					return;
				}
				var client = parsed.data.client;
				var html = '<h2>' + esc(client.full_name) + '</h2>';
				html += '<p class="description">' + esc('Client') + ' ' + esc(client.client_id) + ' · ' + esc(client.role || '') + '</p>';
				html +=
					'<p><strong>' +
					esc('Preferred contractor') +
					':</strong> ' +
					esc(client.preferred ? 'Yes (imported flag)' : 'No') +
					'</p>';
				html +=
					'<p><strong>' +
					esc('Projects') +
					':</strong> ' +
					esc(client.task_stats.total) +
					' · completed ' +
					esc(client.task_stats.completed) +
					'</p>';
				html +=
					'<div class="wpcable-callout wpcable-callout--warn"><p>' +
					esc(client.financial.label) +
					'</p></div>';
				html += '<h3>' + esc('Linked projects') + '</h3><table class="widefat striped"><thead><tr><th>ID</th><th>Title</th><th>State</th><th>Flag</th><th>Value</th></tr></thead><tbody>';
				(client.tasks || []).forEach(function (task) {
					html +=
						'<tr><td><a href="' +
						esc(task.workroom_url) +
						'" target="_blank" rel="noopener">#' +
						esc(task.task_id) +
						'</a></td><td>' +
						esc(task.title) +
						'</td><td>' +
						esc(task.state) +
						'</td><td>' +
						esc(task.flag || '—') +
						'</td><td>' +
						money(task) +
						'</td></tr>';
				});
				html += '</tbody></table>';
				html += '<h3>' + esc('Opportunity cues') + '</h3>' + renderSuggestions(parsed.data.suggestions);
				html += '<p class="description">' + esc(cfg.i18n.suggested) + '</p>';
				html += renderEvidence(client.evidence);
				html += '<h3>' + esc('Outreach draft — review only') + '</h3>' + renderDraft(parsed.data.draft);
				html += renderUnsupported(parsed.data.unsupported);
				$body.html(html);
				$root.find('.wpcable-workspace-layout').addClass('wpcable-drawer-open');
				$root.find('#wpcable-client-drawer').prop('hidden', false);
			});
		}

		$root.on('click', '.wpcable-client-filter', function () {
			$root.find('.wpcable-client-filter').removeClass('is-active button-primary');
			$(this).addClass('is-active button-primary');
			state.filter = $(this).data('filter') || 'all';
			state.page = 1;
			load();
		});

		$('#wpcable-client-search').on('keyup search', function () {
			var val = $(this).val();
			clearTimeout(searchTimer);
			searchTimer = setTimeout(function () {
				state.search = val;
				state.page = 1;
				load();
			}, 250);
		});

		$root.on('click', '.wpcable-row-link', function () {
			openClient($(this).data('client-id'));
		});

		$root.on('click', '.wpcable-drawer__close', function () {
			$root.find('.wpcable-workspace-layout').removeClass('wpcable-drawer-open');
			$root.find('#wpcable-client-drawer').prop('hidden', true);
		});

		var bootId = (window.location.search.match(/client_id=(\d+)/) || [])[1];
		load();
		if (bootId) {
			openClient(bootId);
		}
	}

	$(function () {
		tasksWorkspace();
		clientsWorkspace();
	});
})(jQuery);
