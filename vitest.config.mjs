/**
 * Unit tests for the editor code. The DDEV WordPress (wordpress/) links back to this
 * folder, so it is excluded, or every test would run twice.
 */
import { defineConfig } from 'vitest/config';

export default defineConfig( {
	test: {
		include: [ 'src/**/*.test.js' ],
		exclude: [ '**/node_modules/**', 'build/**', 'vendor/**', 'wordpress/**' ],
	},
} );
