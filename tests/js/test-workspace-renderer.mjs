/**
 * Production-JS integration: wpcable-workspace.js against a WP {success,data} envelope.
 * No Codeable network. jQuery 3 Deferred exceptionHook is in play, matching live WP admin.
 */
import { JSDOM } from 'jsdom';
import { createRequire } from 'module';
import { readFileSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const require = createRequire(import.meta.url);
const jqueryFactory = require('jquery');
const root = join(dirname(fileURLToPath(import.meta.url)), '../..');
const htmlPath = join(root, 'tests/fixtures/integration/tasks-renderer.html');
const mockPath = join(root, 'tests/fixtures/integration/tasks-ajax-mock.js');
const jsPath = join(root, 'assets/js/wpcable-workspace.js');

function tick(ms) {
	return new Promise((resolve) => setTimeout(resolve, ms));
}

function assert(cond, msg) {
	if (!cond) {
		throw new Error(msg);
	}
	console.log('  PASS  ' + msg);
}

function boot() {
	const html = readFileSync(htmlPath, 'utf8').replace(
		/<script[\s\S]*?<\/script>/g,
		''
	);
	const dom = new JSDOM(html, {
		url: 'https://example.test/wp-admin/admin.php?page=codeable_tasks',
		pretendToBeVisual: true,
		runScripts: 'outside-only'
	});
	const { window } = dom;
	const $ = jqueryFactory(window);
	window.jQuery = window.$ = $;
	window.console = console;
	window.eval(readFileSync(mockPath, 'utf8'));
	window.eval(readFileSync(jsPath, 'utf8'));
	return { window, $ };
}

async function run() {
	let failed = 0;
	function check(cond, msg) {
		try {
			assert(cond, msg);
		} catch (err) {
			failed += 1;
			console.log('  FAIL  ' + err.message);
		}
	}

	console.log('7b) JS: production Tasks renderer against WP ajax envelope');

	{
		const { window, $ } = boot();
		await tick(30);
		const rows = window.document.querySelectorAll('#wpcable-task-rows tr.wpcable-row-link');
		const loading = window.document.querySelector('#wpcable-task-rows').textContent;
		const pager = window.document.querySelector('#wpcable-task-pagination').textContent;
		const imported = window.document.querySelector('[data-kpi="imported"]').textContent;
		check(rows.length === 25, 'success envelope renders 25 item rows (not a Loading placeholder)');
		check(loading.indexOf('Loading') === -1, 'tbody is not stuck on Loading');
		check(pager.indexOf('Showing 1') !== -1 && pager.indexOf('of 30') !== -1, 'pagination shows Showing 1–25 of 30');
		check(imported === '30', 'applyCounts updates imported KPI from filter_counts.all');
		check(
			window.__wpcableAjaxLog.some((e) => e.action === 'wpcable_query_tasks'),
			'load() POSTs wpcable_query_tasks'
		);

		window.__wpcableMockAjax({ action: 'codeable_process_queue' });
		await tick(20);
		const rowsAfterSync = window.document.querySelectorAll('#wpcable-task-rows tr.wpcable-row-link');
		check(
			rowsAfterSync.length === 25,
			'unrelated sync poll {success:false,state:FINISHED} does not clear task rows'
		);

		$('#wpcable-task-search').val('Alpha').trigger('keyup');
		await tick(300);
		const searchRows = window.document.querySelectorAll('#wpcable-task-rows tr.wpcable-row-link');
		check(searchRows.length === 2, 'search Alpha returns 2 matching rows');

		$('#wpcable-task-search').val('').trigger('keyup');
		await tick(300);
		window.document.querySelector('#wpcable-task-pagination .wpcable-page-buttons .button').click();
		await tick(30);
		const page2 = window.document.querySelectorAll('#wpcable-task-rows tr.wpcable-row-link');
		const pager2 = window.document.querySelector('#wpcable-task-pagination').textContent;
		check(page2.length === 5, 'page 2 of 25/30 shows remaining 5 rows');
		check(pager2.indexOf('Showing 26') !== -1, 'page 2 pagination label');

		$('#wpcable-per-page').val('50').trigger('change');
		await tick(30);
		const fifty = window.document.querySelectorAll('#wpcable-task-rows tr.wpcable-row-link');
		check(fifty.length === 30, 'per_page 50 renders all 30 rows');
	}

	{
		const { window } = boot();
		await tick(30);
		window.__wpcableForceError = true;
		window.jQuery(window.document).trigger('codeable-reload-tasks');
		await tick(30);
		const text = window.document.querySelector('#wpcable-task-rows').textContent;
		check(text.indexOf('Loading') === -1, 'success:false envelope does not leave Loading');
		check(text.indexOf('No matching projects') !== -1 || text.indexOf('forced_error') !== -1, 'success:false shows an error/empty row');
	}

	{
		const { window } = boot();
		await tick(30);
		window.__wpcableForceNetworkFail = true;
		window.jQuery(window.document).trigger('codeable-reload-tasks');
		await tick(30);
		const text = window.document.querySelector('#wpcable-task-rows').textContent;
		check(text.indexOf('Unavailable') !== -1, 'ajax .fail replaces Loading with Unavailable');
	}

	if (failed) {
		console.log('JS renderer failures: ' + failed);
		process.exit(1);
	}
}

run().catch((err) => {
	console.error(err);
	process.exit(1);
});
