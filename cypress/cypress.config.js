const { defineConfig } = require("cypress");

const artifactsPath = process.env.CYPRESS_ARTIFACTS_PATH || ".";

module.exports = defineConfig({
  e2e: {
    baseUrl: process.env.CYPRESS_BASE_URL || "http://frappe:8000",
    env: {
      FRAPPE_USER: process.env.CYPRESS_FRAPPE_USER || "Administrator",
      FRAPPE_PASSWORD: process.env.FRAPPE_PASSWORD,
    },
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
    screenshotsFolder: `${artifactsPath}/screenshots`,
    videosFolder: `${artifactsPath}/videos`,
    downloadsFolder: `${artifactsPath}/downloads`,
  },
});
