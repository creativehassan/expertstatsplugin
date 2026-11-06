/* global jQuery */
(function () {
	'use strict';
	window.wpcableWorkspace = window.wpcableWorkspace || {
		ajaxUrl: '/wp-admin/admin-ajax.php',
		nonce: 'test-nonce',
		i18n: {
			noTasks: 'No matching projects',
			loading: 'Loading…',
			unavailable: 'Unavailable',
			notZero: 'Not $0 — this source has not imported.',
			suggested: 'Suggested from local records. Not a confirmed client need.',
			reviewOnly: 'Review only — this plugin never sends outreach.'
		}
	};

	var items = [
		{
			client_id: 101,
			full_name: 'Fixture Client Alpha',
			role: 'client',
			avatar: '',
			preferred: true,
			activity_valid: true,
			stale_days: 200,
			task_stats: { total: 3, completed: 2, lost: 0, preferred: 1 }
		},
		{
			client_id: 202,
			full_name: 'Fixture Client Beta',
			role: 'client',
			avatar: '',
			preferred: false,
			activity_valid: true,
			stale_days: 10,
			task_stats: { total: 27, completed: 0, lost: 1, preferred: 0 }
		}
	];

	var orig = jQuery.ajax;
	jQuery.ajax = function (opts) {
		var data = opts.data || {};
		if (typeof data === 'string') {
			return orig.apply(this, arguments);
		}
		if (data.action === 'wpcable_query_clients') {
			var deferred = jQuery.Deferred();
			setTimeout(function () {
				deferred.resolve({
					success: true,
					data: {
						items: items,
						total: items.length,
						page: 1,
						per_page: 25
					}
				});
			}, 10);
			return deferred.promise();
		}
		if (typeof orig === 'function') {
			return orig.apply(this, arguments);
		}
		return jQuery.Deferred().resolve({ success: false }).promise();
	};
	jQuery.post = function (url, data) {
		return jQuery.ajax({ url: url, data: data, method: 'POST' });
	};
})();
