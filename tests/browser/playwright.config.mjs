/**
 * The browser tests: Playwright, in the Chrome installed on the machine
 * (`PW_CHANNEL` names another channel, `PW_CHANNEL=chromium` Playwright's
 * own download), against pages built from WordPress's own stylesheets.
 */
import { defineConfig } from '@playwright/test';

export default defineConfig( {
	testDir: '.',
	testMatch: '*.spec.mjs',
	reporter: 'list',
	use: {
		channel: process.env.PW_CHANNEL ?? 'chrome',
		headless: true,
	},
} );
