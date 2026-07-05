const { defineConfig } = require("cypress");

module.exports = defineConfig({
  e2e: {
    setupNodeEvents(on, config) {
      on("before:browser:launch", (browser, launchOptions) => {
        if (browser.family !== "chromium") {
          return launchOptions;
        }
        if (Array.isArray(launchOptions.args)) {
          launchOptions.args.push("--mute-audio", "--disable-audio-output");
        }
        return launchOptions;
      });
      return config;
    },
    baseUrl: process.env.CYPRESS_BASE_URL || "http://frappe:8000",
    env: {
      FRAPPE_USER: process.env.CYPRESS_FRAPPE_USER || "cypress@example.local",
      FRAPPE_PASSWORD: process.env.CYPRESS_FRAPPE_PASSWORD || process.env.FRAPPE_PASSWORD,
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
    screenshotOnRunFailure: false,
  },
});
