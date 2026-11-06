/**
 * Offline production-template screenshots + page-level overflow check.
 * Wide tables may scroll locally; documentElement.scrollWidth must not exceed clientWidth.
 */
import puppeteer from 'puppeteer-core';
import { mkdirSync, existsSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const root = join(dirname(fileURLToPath(import.meta.url)), '../..');
const htmlDir = join(root, 'tests/fixtures/ui/production');
const shotDir = join(root, 'docs/screenshots');
const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const pages = ['stats', 'tasks', 'clients', 'opportunities', 'estimate', 'settings'];
const widths = [
	{ w: 1920, h: 1200 },
	{ w: 1280, h: 1100 },
	{ w: 390, h: 844 },
];

function assert(cond, msg) {
	if (!cond) {
		throw new Error(msg);
	}
	console.log('  PASS  ' + msg);
}

mkdirSync(shotDir, { recursive: true });

const browser = await puppeteer.launch({
	executablePath: chrome,
	headless: true,
	args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu'],
});

try {
	for (const pageKey of pages) {
		const file = join(htmlDir, `production-${pageKey}.html`);
		assert(existsSync(file), `fixture exists: production-${pageKey}.html`);
		for (const vp of widths) {
			const page = await browser.newPage();
			await page.setViewport({ width: vp.w, height: vp.h, deviceScaleFactor: 1 });
			await page.goto('file://' + file, { waitUntil: 'load', timeout: 30000 });
			await new Promise((resolve) => setTimeout(resolve, 250));

			const metrics = await page.evaluate(() => {
				const de = document.documentElement;
				const app = document.querySelector('.wpcable-app');
				const tables = Array.from(document.querySelectorAll('.wpcable-table-card, .wpcable-table-scroll'));
				return {
					scrollWidth: de.scrollWidth,
					clientWidth: de.clientWidth,
					bodyScroll: document.body.scrollWidth,
					appScroll: app ? app.scrollWidth : 0,
					appClient: app ? app.clientWidth : 0,
					overview: document.querySelectorAll('#wpcable-stats-overview, .wpcable-overview').length,
					profile: document.querySelectorAll('.wpcable-kpi--profile').length,
					health: document.querySelectorAll('#wpcable-import-health').length,
					legacy: document.querySelectorAll('.first-stats-row, .box.user-info').length,
					tableLocal: tables.map((el) => ({
						scrollWidth: el.scrollWidth,
						clientWidth: el.clientWidth,
					})),
				};
			});

			assert(
				metrics.scrollWidth <= metrics.clientWidth + 1,
				`${pageKey}@${vp.w} page scrollWidth ${metrics.scrollWidth} <= clientWidth ${metrics.clientWidth}`
			);
			assert(
				metrics.bodyScroll <= metrics.clientWidth + 1,
				`${pageKey}@${vp.w} body scrollWidth ${metrics.bodyScroll} <= clientWidth ${metrics.clientWidth}`
			);

			if (pageKey === 'stats') {
				assert(metrics.overview === 1, `stats@${vp.w} exactly one overview (${metrics.overview})`);
				assert(metrics.profile === 1, `stats@${vp.w} exactly one profile card (${metrics.profile})`);
				assert(metrics.health === 1, `stats@${vp.w} exactly one import-health (${metrics.health})`);
				assert(metrics.legacy === 0, `stats@${vp.w} no legacy duplicate row`);
			}

			const shot = join(shotDir, `production-${pageKey}-${vp.w}.png`);
			await page.screenshot({ path: shot, fullPage: true });
			console.log('  SHOT  ' + shot.replace(root + '/', ''));
			await page.close();
		}
	}
} finally {
	await browser.close();
}

console.log('  PASS  production screenshots + page-level overflow checks');
