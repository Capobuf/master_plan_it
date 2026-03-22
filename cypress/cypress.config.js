const { defineConfig } = require("cypress");

module.exports = defineConfig({
  e2e: {
    // Internal Docker network URL (mpit-frontend:8080) — used when running from mpit-backend container.
    // Override with CYPRESS_BASE_URL=http://localhost:9797 when running from the host.
    baseUrl: "http://10.0.5.5:8080",
    viewportWidth: 1280,
    viewportHeight: 800,
    defaultCommandTimeout: 15000,
    requestTimeout: 15000,
    responseTimeout: 30000,
    // Frappe Desk reloads on login; wait for stability
    pageLoadTimeout: 60000,
    retries: { runMode: 1, openMode: 0 },
    specPattern: "e2e/**/*.cy.js",
    supportFile: "support/commands.js",
    video: false,
    screenshotOnRunFailure: true,
    screenshotsFolder: "screenshots",
  },
});
