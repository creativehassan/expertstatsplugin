/* global jQuery */
(function () {
	'use strict';

	function makeItem(i) {
		var isAlpha = i <= 2;
		return {
			task_id: i,
			client_id: isAlpha ? 101 : 202,
			title: isAlpha ? 'Alpha project ' + i : 'Fixture task ' + i,
			state: i === 5 ? 'lost' : isAlpha ? 'completed' : 'published',
			kind: 'task',
			value: isAlpha ? 1500 : 0,
			value_known: isAlpha,
			flag: i === 5 ? 'lost' : isAlpha ? 'completed' : '',
			notes: i === 1 ? 'local note' : '',
			has_notes: i === 1,
			client_name: isAlpha ? 'Fixture Client Alpha' : 'Fixture Client Beta',
			avatar: '',
			workroom_url: 'https://app.codeable.io/tasks/' + i + '/workroom',
			activity_valid: true,
			stale_days: isAlpha ? 200 : 1,
			last_activity: 1700000000,
			last_activity_date: '2023-11-14',
			preferred: isAlpha
		};
	}

	var ALL = [];
	var n;
	for (n = 1; n <= 30; n++) {
		ALL.push(makeItem(n));
	}

	window.wpcableWorkspace = {
		ajaxUrl: '/wp-admin/admin-ajax.php',
		nonce: 'test-nonce',
		i18n: {
			noTasks: 'No matching projects',
			loading: 'Loading…',
			unavailable: 'Unavailable',
			notZero: 'Not $0 — this source has not imported.',
			lostHeuristic: 'Lost is a local heuristic',
			notesLocal: 'Local notes only.',
			suggested: 'Suggested from local records.',
			unavailableHistory: 'Unavailable'
		}
	};

	window.__wpcableAjaxLog = [];
	window.__wpcableForceError = false;
	window.__wpcableForceNetworkFail = false;

	function queryTasks(data) {
		data = data || {};
		var page = parseInt(data.page_num, 10) || 1;
		var per = parseInt(data.per_page, 10) || 25;
		var search = (data.search || '').toLowerCase();
		var rows = ALL.slice();
		if (search) {
			rows = rows.filter(function (row) {
				return (
					String(row.title).toLowerCase().indexOf(search) !== -1 ||
					String(row.client_name).toLowerCase().indexOf(search) !== -1 ||
					String(row.task_id) === search
				);
			});
		}
		if (String(data.completed) === '1') {
			rows = rows.filter(function (row) {
				return row.state === 'completed' || row.flag === 'completed';
			});
		}
		if (String(data.lost) === '1') {
			rows = rows.filter(function (row) {
				return row.state === 'lost' || row.flag === 'lost';
			});
		}
		if (data.state && data.state !== 'all') {
			rows = rows.filter(function (row) {
				return row.state === data.state;
			});
		}
		var total = rows.length;
		var start = (page - 1) * per;
		var items = rows.slice(start, start + per);
		return {
			success: true,
			data: {
				items: items,
				total: total,
				page: page,
				per_page: per,
				group_by_client: data.group_by_client ? 1 : 0,
				counts: { imported: 30, published: 26, completed: 2, lost: 1 },
				filter_counts: {
					all: 30,
					published: 26,
					completed: 2,
					lost: 1,
					paid: 2,
					estimated: 0,
					preferred: 2,
					with_notes: 1
				},
				sync: { running: false }
			}
		};
	}

	window.__wpcableMockAjax = function (data) {
		data = data || {};
		window.__wpcableAjaxLog.push({ action: data.action, data: data });
		if (window.__wpcableForceError && data.action === 'wpcable_query_tasks') {
			return { success: false, data: { message: 'forced_error' } };
		}
		if (data.action === 'wpcable_query_tasks') {
			return queryTasks(data);
		}
		if (data.action === 'wpcable_get_task') {
			var id = parseInt(data.task_id, 10);
			var task = ALL.filter(function (row) {
				return row.task_id === id;
			})[0];
			return {
				success: true,
				data: {
					task: task,
					history: {},
					suggestions: [],
					unsupported: []
				}
			};
		}
		if (data.action === 'wpcable_query_clients') {
			return { success: true, data: { items: [], total: 0, page: 1, per_page: 25 } };
		}
		// Sync poll (empty queue) — must not be treated as a tasks failure.
		return { success: false, data: { state: 'FINISHED' } };
	};

	if (typeof jQuery !== 'undefined') {
		jQuery.post = function (url, data) {
			var deferred = jQuery.Deferred();
			if (window.__wpcableForceNetworkFail && data && data.action === 'wpcable_query_tasks') {
				setTimeout(function () {
					deferred.reject({}, 'error', 'forced');
				}, 0);
				return deferred.promise();
			}
			var payload = window.__wpcableMockAjax(data);
			setTimeout(function () {
				deferred.resolve(payload);
			}, 0);
			return deferred.promise();
		};
	}
})();
