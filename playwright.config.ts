import { defineConfig, devices } from '@playwright/test';

// Runs inside the Sail container, where the app listens on port 80.
export default defineConfig({
    testDir: 'tests/e2e',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://localhost',
        trace: 'retain-on-failure',
    },
    projects: [
        { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
        { name: 'mobile', use: { ...devices['Pixel 7'] } },
    ],
});
