/**
 * Production Estimate page JS (fee_type=client) — sequential input+change+blur.
 * Proves 1/1/1 and 2/4/10; does not change the formula.
 */
import { JSDOM } from 'jsdom';
import { createRequire } from 'module';
import { readFileSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const require = createRequire(import.meta.url);
const jqueryFactory = require('jquery');
const root = join(dirname(fileURLToPath(import.meta.url)), '../..');
const htmlPath = join(root, 'tests/fixtures/integration/estimate-client-fee.html');

function tick(ms) {
	return new Promise((resolve) => setTimeout(resolve, ms));
}

function assert(cond, msg) {
	if (!cond) {
		throw new Error(msg);
	}
	console.log('  PASS  ' + msg);
}

async function run() {
	console.log('7c) JS: Estimate formulas (fee_type=client) sequential fill');
	const raw = readFileSync(htmlPath, 'utf8');
	const html = raw.replace(/<script[\s\S]*?<\/script>/g, '');
	const dom = new JSDOM(html, {
		url: 'https://example.test/wp-admin/admin.php?page=codeable_estimate',
		runScripts: 'outside-only',
		pretendToBeVisual: true
	});
	const { window } = dom;
	window.jQuery = window.$ = jqueryFactory(window);
	const inline = raw.match(/<script>([\s\S]*?)<\/script>/)[1];
	window.eval(inline);
	await tick(20);

	const $ = window.jQuery;
	assert($('#estimate_hours_standard').val() === '1:00', 'defaults 1/1/1 hours 1:00');
	assert($('#estimate').val() === '100.00', 'defaults 1/1/1 estimate $100.00');
	assert($('#earnings').val() === '90.00', 'defaults 1/1/1 earnings $90.00');

	window.__wpcableSetHours(2, 4, 10);
	await tick(20);
	assert($('#optimistic_estimate').val() === '2', 'sequential O=2');
	assert($('#likely_estimate').val() === '4', 'sequential M=4 (cascade did not clamp)');
	assert($('#pessimistic_estimate').val() === '10', 'sequential P=10');
	assert($('#estimate_hours_standard').val() === '4:40', '2/4/10 standard 4:40');
	assert($('#estimate').val() === '466.67', '2/4/10 standard estimate $466.67');
	assert($('#earnings').val() === '420.00', '2/4/10 standard earnings $420.00');
	assert($('#estimate_hours_pessimistic').val() === '6:40', '2/4/10 cautious 6:40');
	assert($('#estimate_pessimistic').val() === '666.67', '2/4/10 cautious estimate $666.67');

	window.__wpcableSetHours(1, 1, 1);
	await tick(20);
	assert($('#estimate_hours_standard').val() === '1:00', 'restore 1/1/1 hours 1:00');
	assert($('#estimate').val() === '100.00', 'restore 1/1/1 estimate $100.00');
}

run().catch((err) => {
	console.error(err);
	process.exit(1);
});
