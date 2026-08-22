/**
 * Shared browser launcher.
 *
 * This repository's development container ships Chromium at a fixed path
 * (PLAYWRIGHT_BROWSERS_PATH=/opt/pw-browsers) and blocks the postinstall
 * download, so the suites cannot assume Playwright's own browser directory.
 * CI has no such path and uses `npx playwright install` instead. Resolving here
 * keeps that difference in one file rather than in every suite.
 */

import { existsSync } from 'node:fs';
import { chromium } from 'playwright';

const CANDIDATES = [
	process.env.BHC_CHROMIUM,
	process.env.PLAYWRIGHT_BROWSERS_PATH
		? `${ process.env.PLAYWRIGHT_BROWSERS_PATH }/chromium`
		: null,
].filter( Boolean );

/**
 * Launches Chromium, using an explicit binary only when one is actually there.
 *
 * @param {object} options Extra launch options.
 * @return {Promise<import('playwright').Browser>} Browser instance.
 */
export async function launchBrowser( options = {} ) {
	const executablePath = CANDIDATES.find( ( path ) => existsSync( path ) );

	return chromium.launch( {
		args: [ '--no-sandbox' ],
		...( executablePath ? { executablePath } : {} ),
		...options,
	} );
}

export const BASE_URL = process.env.BHC_BASE_URL || 'http://localhost:8088';
