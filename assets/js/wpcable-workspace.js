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
		return '<img src="' + esc(url) + '" alt="" />';
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

		function load() {
			var $tbody = $('#wpcable-task-rows');
			$tbody.html('<tr class="wpcable-empty-row"><td colspan="7">' + esc(cfg.i18n.loading) + '</td></tr>');
			ajax('wpcable_query_tasks', params()).done(function (res) {
				if (!res || !res.success) {
					$tbody.html('<tr class="wpcable-empty-row"><td colspan="7">' + esc(cfg.i18n.noTasks) + '</td></tr>');
					return;
				}
				renderRows(res.data);
			});
		}

		function renderRows(data) {
			var items = data.items || [];
			var $tbody = $('#wpcable-task-rows');
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
							esc(task.last_activity_date || '—') +
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
			pagination($('#wpcable-task-pagination'), data.page, data.per_page, data.total, function (p) {
				state.page = p;
				load();
			});
		}

		function openDrawer(taskId) {
			var $layout = $root.find('.wpcable-workspace-layout');
			var $drawer = $('#wpcable-task-drawer');
			var $body = $('#wpcable-task-drawer-body');
			$drawer.prop('hidden', false);
			$layout.addClass('wpcable-drawer-open');
			$body.html('<p>' + esc(cfg.i18n.loading) + '</p>');
			ajax('wpcable_get_task', { task_id: taskId }).done(function (res) {
				if (!res || !res.success) {
					$body.html('<p>' + esc(cfg.i18n.unavailable) + '</p>');
					return;
				}
				var task = res.data.task;
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
					esc(task.last_activity_date || '—') +
					'</p>';
				html +=
					'<p><a class="button" href="' +
					esc(task.workroom_url) +
					'" target="_blank" rel="noopener">' +
					esc('Open workroom (read-only)') +
					'</a> ';
				html +=
					'<button type="button" class="button wpcable-edit-notes">' +
					esc('Edit local notes') +
					'</button></p>';
				html += '<p class="description">' + esc(cfg.i18n.notesLocal) + '</p>';
				if (task.notes) {
					html += '<pre class="wpcable-notes-full">' + esc(task.notes) + '</pre>';
				}
				html += '<h3>' + esc('Suggested next step') + '</h3>' + renderSuggestions(res.data.suggestions);
				html += renderUnsupported(res.data.unsupported);
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

		function openNotes(taskId) {
			ajax('wpcable_get_task', { task_id: taskId }).done(function (res) {
				if (res && res.success) {
					openNotesEditor(res.data.task);
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
			$('#wpcable-client-rows').html(
				'<tr class="wpcable-empty-row"><td colspan="4">' + esc(cfg.i18n.loading) + '</td></tr>'
			);
			ajax('wpcable_query_clients', {
				page_num: state.page,
				per_page: state.per_page,
				search: state.search,
				filter: state.filter
			}).done(function (res) {
				if (!res || !res.success) {
					return;
				}
				render(res.data);
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
						'"><td>' +
						avatar(client.avatar) +
						' ' +
						esc(client.full_name) +
						'<div class="description">' +
						esc(client.role || '') +
						' · ' +
						esc(client.client_id) +
						'</div></td><td>' +
						esc(client.task_stats ? client.task_stats.total : 0) +
						'</td><td>' +
						esc(flags.join(' · ') || '—') +
						'</td><td>' +
						esc(client.stale_days ? client.stale_days + 'd ago' : '—') +
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
				if (!res || !res.success) {
					$body.html('<p>' + esc(cfg.i18n.unavailable) + '</p>');
					return;
				}
				var client = res.data.client;
				var html = '<h2>' + esc(client.full_name) + '</h2>';
				html += '<p class="description">client_id ' + esc(client.client_id) + ' · ' + esc(client.role || '') + '</p>';
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
				html += '<h3>' + esc('Opportunity cues') + '</h3>' + renderSuggestions(res.data.suggestions);
				html += '<p class="description">' + esc(cfg.i18n.suggested) + '</p>';
				html += renderEvidence(client.evidence);
				html += '<h3>' + esc('Outreach draft — review only') + '</h3>' + renderDraft(res.data.draft);
				html += renderUnsupported(res.data.unsupported);
				$body.html(html);
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
