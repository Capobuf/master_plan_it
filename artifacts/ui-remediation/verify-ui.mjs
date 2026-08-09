import fs from "node:fs";

const selenium = "http://selenium:4444";
const baseUrl = "http://frontend:5173";
const elementKey = "element-6066-11e4-a52e-4f735466cecf";
const outputDirectory = "/var/www/html/artifacts/ui-remediation/after";
const delay = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));
const runToken = Date.now();
let navigationIndex = 0;
let appBooted = false;

function envValue(name) {
  const line = fs.readFileSync("/var/www/html/.env", "utf8").split(/\r?\n/).find((entry) => entry.startsWith(`${name}=`));
  if (!line) throw new Error(`Variabile ${name} non disponibile.`);
  return line.slice(name.length + 1).replace(/^(["'])(.*)\1$/, "$2");
}

async function request(path, method = "GET", body) {
  const response = await fetch(`${selenium}${path}`, {
    method,
    headers: body === undefined ? undefined : { "content-type": "application/json" },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const payload = await response.json();
  if (!response.ok || payload.value?.error) {
    throw new Error(`${method} ${path}: ${payload.value?.message ?? response.statusText}`);
  }
  return payload.value;
}

const sessionResponse = await request("/wd/hub/session", "POST", {
  capabilities: {
    alwaysMatch: {
      browserName: "chrome",
      "goog:chromeOptions": { args: ["--headless=new", "--incognito", "--no-sandbox", "--disable-dev-shm-usage"] },
      "goog:loggingPrefs": { browser: "ALL", performance: "ALL" },
    },
  },
});
const sessionId = sessionResponse.sessionId;
const sessionPath = `/wd/hub/session/${sessionId}`;

async function command(path, method = "GET", body) {
  return request(`${sessionPath}${path}`, method, body);
}

async function setViewport(width, height) {
  await command("/window/rect", "POST", { x: 0, y: 0, width, height });
}

async function go(path) {
  const separator = path.includes("?") ? "&" : "?";
  navigationIndex += 1;
  const target = `${path}${separator}__ui=${runToken}-${navigationIndex}`;
  if (appBooted && await execute("return document.querySelector('#root')?.childElementCount > 0")) {
    await execute("history.pushState({}, '', arguments[0]); window.dispatchEvent(new PopStateEvent('popstate'));", [target]);
  } else {
    await command("/url", "POST", { url: `${baseUrl}${target}` });
  }
  await waitFor(async () => (await execute("return document.readyState")) === "complete");
  try {
    await waitFor(async () => await execute("return document.documentElement.lang === 'it' && document.querySelector('#root')?.childElementCount > 0"));
  } catch (error) {
    await command("/refresh", "POST", {});
    try {
      await waitFor(async () => await execute("return document.documentElement.lang === 'it' && document.querySelector('#root')?.childElementCount > 0"));
    } catch {
      const diagnostic = await execute("return {url: location.href, lang: document.documentElement.lang, title: document.title, root: document.querySelector('#root')?.childElementCount ?? -1, text: document.body.innerText.slice(0, 300)}");
      throw new Error(`${error.message} Navigazione ${path}: ${JSON.stringify(diagnostic)}`);
    }
  }
  appBooted = true;
  await delay(300);
}

async function execute(script, args = []) {
  return command("/execute/sync", "POST", { script, args });
}

async function find(using, value) {
  const result = await command("/element", "POST", { using, value });
  return result[elementKey];
}

async function findMaybe(using, value) {
  try { return await find(using, value); } catch { return null; }
}

async function waitFor(check, timeout = 12000) {
  const deadline = Date.now() + timeout;
  while (Date.now() < deadline) {
    try { if (await check()) return; } catch { /* retry while React settles */ }
    await delay(150);
  }
  throw new Error("Condizione browser non raggiunta entro il timeout.");
}

async function click(element) {
  await command(`/element/${element}/click`, "POST", {});
}

async function clear(element) {
  await command(`/element/${element}/clear`, "POST", {});
}

async function fill(element, value) {
  await execute(`
    const input = arguments[0];
    const setter = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set;
    setter.call(input, arguments[1]);
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
  `, [{ [elementKey]: element }, value]);
}

async function selectValue(element, value) {
  await execute(`
    const select = arguments[0];
    const setter = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value').set;
    setter.call(select, arguments[1]);
    select.dispatchEvent(new Event('change', { bubbles: true }));
  `, [{ [elementKey]: element }, value]);
}

async function clickText(text) {
  let element = null;
  try {
    await waitFor(async () => {
      element = await findMaybe("xpath", `//button[contains(normalize-space(.), ${JSON.stringify(text)})]`);
      return element !== null;
    });
  } catch (error) {
    throw new Error(`${error.message} Pulsante ${text}; percorso ${await currentPath()}; pagina ${(await bodyText()).slice(0, 700)}`);
  }
  await click(element);
}

async function bodyText() {
  return execute("return document.body.innerText");
}

async function screenshot(name) {
  const png = await command("/screenshot");
  fs.writeFileSync(`${outputDirectory}/${name}.png`, Buffer.from(png, "base64"));
}

async function currentPath() {
  return execute("return location.pathname + location.search");
}

async function auditPage(label, viewport) {
  const result = await execute(`
    const root = document.documentElement;
    const body = document.body;
    const text = body.innerText;
    const forbidden = [
      'Authoritative API', 'Authoritative monthly', 'Server totals', 'Server contract resource',
      'Local key', 'Plafond ID', 'Cost center ID', 'Centro di costo (ID)'
    ].filter((value) => text.includes(value));
    return {
      label: arguments[0], viewport: arguments[1], path: location.pathname,
      title: document.title, lang: root.lang,
      pageOverflow: root.scrollWidth > window.innerWidth + 1 || body.scrollWidth > window.innerWidth + 1,
      scrollWidth: Math.max(root.scrollWidth, body.scrollWidth), innerWidth: window.innerWidth,
      sixDecimals: /(?:^|\s)-?\d+[.,]\d{6}(?:\s|$)/m.test(text), forbidden,
      visibleDialogs: [...document.querySelectorAll('[role="dialog"]')].filter((node) => {
        const style = getComputedStyle(node); return style.display !== 'none' && style.visibility !== 'hidden';
      }).length,
    };
  `, [label, viewport]);
  if (result.pageOverflow || result.sixDecimals || result.forbidden.length || result.lang !== "it") {
    throw new Error(`Audit pagina non superato: ${JSON.stringify(result)}`);
  }
  return result;
}

fs.mkdirSync(outputDirectory, { recursive: true });
const report = { routes: [], viewports: [], interactions: [], legacyRedirects: [], console: [], network: [] };

try {
  await setViewport(1440, 900);
  await go("/signin");
  await waitFor(async () => (await currentPath()).startsWith("/accesso"));
  report.legacyRedirects.push({ from: "/signin", to: "/accesso" });
  await go("/accesso");
  await waitFor(async () => (await findMaybe("css selector", "#email")) !== null || !(await currentPath()).startsWith("/accesso"));
  const emailField = await findMaybe("css selector", "#email");
  if (emailField) {
    await screenshot("accesso");
    await fill(emailField, envValue("PLATFORM_ADMIN_EMAIL"));
    await fill(await find("css selector", "#password"), envValue("PLATFORM_ADMIN_PASSWORD"));
    await click(await find("css selector", "form button[type='submit']"));
    await waitFor(async () => !(await currentPath()).startsWith("/accesso"));
  }

  let tenantMenu = null;
  await waitFor(async () => {
    tenantMenu = await findMaybe("css selector", "button[aria-label='Apri il menu Tenant']");
    return tenantMenu !== null;
  });
  if (tenantMenu && !(await bodyText()).includes("Azienda Demo S.r.l.")) {
    await click(tenantMenu);
    let demoTenant = null;
    await waitFor(async () => {
      demoTenant = await findMaybe("xpath", "//button[contains(., 'Azienda Demo S.r.l.')]");
      return demoTenant !== null;
    });
    await click(demoTenant);
    await waitFor(async () => {
      const currentTenantButton = await findMaybe("css selector", "button[aria-label='Apri il menu Tenant']");
      if (!currentTenantButton) return false;
      const currentTenantText = await command(`/element/${currentTenantButton}/text`);
      return currentTenantText.includes("Azienda Demo S.r.l.");
    });
    report.interactions.push("Cambio Tenant tramite menu");
  }

  const apiData = await command("/execute/async", "POST", {
    script: `const done=arguments[arguments.length-1]; Promise.all([
      fetch('/api/v1/expenses?per_page=1').then(r=>r.json()),
      fetch('/api/v1/contracts?per_page=1').then(r=>r.json())
    ]).then(([expenses,contracts])=>done({expenseId:expenses.data?.[0]?.id??null,contractId:contracts.data?.[0]?.id??null})).catch(error=>done({error:String(error)}));`,
    args: [],
  });
  if (apiData.error) throw new Error(apiData.error);

  const pages = [
    ["panoramica", "/", "Panoramica | Master Plan IT"], ["budget", "/budget", "Budget | Master Plan IT"], ["report", "/report", "Report | Master Plan IT"], ["spese", "/spese", "Spese | Master Plan IT"],
    ["editor-spesa", "/spese/nuova", "Nuova Spesa | Master Plan IT"], ["contratti", "/contratti", "Contratti | Master Plan IT"], ["editor-contratto", "/contratti/nuovo", "Nuovo Contratto | Master Plan IT"],
    ["fornitori", "/fornitori", "Fornitori | Master Plan IT"], ["centri-di-costo", "/centri-di-costo", "Centri di Costo | Master Plan IT"],
    ["anni-di-pianificazione", "/anni-di-pianificazione", "Anni di Pianificazione | Master Plan IT"], ["tenant", "/tenant", "Tenant | Master Plan IT"],
    ["utenti", "/utenti", "Utenti | Master Plan IT"], ["ruoli", "/ruoli", "Ruoli | Master Plan IT"],
  ];
  if (apiData.expenseId) pages.splice(5, 0, ["dettaglio-spesa", `/spese/${apiData.expenseId}`, "Dettaglio Spesa | Master Plan IT"], ["modifica-spesa", `/spese/${apiData.expenseId}/modifica`, "Modifica Spesa | Master Plan IT"]);
  if (apiData.contractId) pages.splice(9, 0, ["dettaglio-contratto", `/contratti/${apiData.contractId}`, "Dettaglio Contratto | Master Plan IT"], ["modifica-contratto", `/contratti/${apiData.contractId}/modifica`, "Modifica Contratto | Master Plan IT"]);

  if (!process.env.UI_VERIFY_INTERACTIONS_ONLY) {
  for (const [name, path, expectedTitle] of pages) {
    console.log(`1440x900 ${name}`);
    await go(path);
    await waitFor(async () => (await execute("return document.title")) === expectedTitle);
    await waitFor(async () => !(await bodyText()).includes("Caricamento"));
    const audit = await auditPage(name, "1440x900-chiaro");
    report.routes.push(audit);
    if (["panoramica", "budget", "report", "spese", "editor-spesa", "contratti", "editor-contratto", "fornitori", "anni-di-pianificazione", "tenant"].includes(name)) {
      await screenshot(name);
    }
  }

  for (const [width, height] of [[2048,1152], [1024,768], [390,844]]) {
    await setViewport(width, height);
    for (const [name, path, expectedTitle] of pages) {
      console.log(`${width}x${height} ${name}`);
      await go(path);
      await waitFor(async () => (await execute("return document.title")) === expectedTitle);
      report.viewports.push(await auditPage(name, `${width}x${height}-chiaro`));
    }
  }

  await setViewport(390, 844);
  await go("/");
  await screenshot("panoramica-mobile");

  await execute("localStorage.setItem('theme','dark'); document.documentElement.classList.add('dark');");
  for (const [width, height] of [[2048,1152], [1440,900], [1024,768], [390,844]]) {
    await setViewport(width, height);
    for (const [name, path, expectedTitle] of pages) {
      console.log(`${width}x${height} scuro ${name}`);
      await go(path);
      await waitFor(async () => (await execute("return document.title")) === expectedTitle);
      report.viewports.push(await auditPage(name, `${width}x${height}-scuro`));
      if (name === "panoramica" && width === 1440) await screenshot("panoramica-dark");
      if (name === "panoramica" && width === 390) await screenshot("panoramica-mobile-dark");
    }
  }
  await execute("localStorage.setItem('theme','light'); document.documentElement.classList.remove('dark');");
  }

  await go("/fornitori");
  await clickText("Nuovo Fornitore");
  await waitFor(async () => (await execute("return !!document.querySelector('[role=dialog]')")));
  const focusInDialog = await execute("return !!document.activeElement?.closest('[role=dialog]')");
  if (!focusInDialog) throw new Error("Il focus iniziale non è nel modal.");
  await command("/actions", "POST", { actions: [{ type: "key", id: "keyboard", actions: [{ type: "keyDown", value: "\uE00C" }, { type: "keyUp", value: "\uE00C" }] }] });
  await waitFor(async () => !(await execute("return !!document.querySelector('[role=dialog]')")));
  report.interactions.push("Modal: focus iniziale e chiusura con Escape");

  const testVendor = `Verifica interfaccia ${Date.now()}`;
  const updatedVendor = `${testVendor} aggiornata`;
  await clickText("Nuovo Fornitore");
  await fill(await find("css selector", "#vendor-name"), testVendor);
  await clickText("Crea Fornitore");
  try {
    await waitFor(async () => (await bodyText()).includes(testVendor));
  } catch (error) {
    await screenshot("errore-creazione-fornitore");
    throw new Error(`${error.message} Stato creazione fornitore: ${(await bodyText()).slice(0, 1200)}`);
  }
  await click(await find("css selector", `button[aria-label=${JSON.stringify(`Modifica ${testVendor}`)}]`));
  const vendorName = await find("css selector", "#vendor-name");
  await clear(vendorName); await fill(vendorName, updatedVendor);
  await clickText("Salva Modifiche");
  await waitFor(async () => (await bodyText()).includes(updatedVendor));
  await click(await find("css selector", `button[aria-label=${JSON.stringify(`Elimina ${updatedVendor}`)}]`));
  await clickText("Elimina Fornitore");
  await waitFor(async () => !(await bodyText()).includes(updatedVendor));
  report.interactions.push("Creazione, modifica ed eliminazione di un fornitore di verifica");

  await go("/report");
  const filterButton = await findMaybe("xpath", "//button[contains(., 'Applica Filtri')]");
  if (!filterButton) throw new Error("Filtro Report non trovato.");
  await click(filterButton); await delay(500);
  report.interactions.push("Applicazione filtri Report");

  await go("/spese");
  const kindSelect = await find("css selector", "#expense-kind");
  await selectValue(kindSelect, "plafond");
  await waitFor(async () => (await currentPath()).includes("kind=plafond"));
  report.interactions.push("Filtro Natura del registro Spese");
  const emptyYear = await execute("return [...document.querySelector('#expense-planning-year').options].filter(option => option.text.includes('(inattivo)')).at(-1)?.value ?? null");
  if (emptyYear) {
    await selectValue(await find("css selector", "#expense-planning-year"), emptyYear);
    await waitFor(async () => (await bodyText()).includes("Nessuna spesa"));
    report.interactions.push("Stato vuoto del registro Spese");
  }

  await go("/spese/999999999");
  await waitFor(async () => (await bodyText()).includes("non") || (await bodyText()).includes("Errore"));
  report.interactions.push("Stato di errore gestito nel dettaglio Spesa");

  const legacy = [
    ["/reports", "/report"], ["/expenses", "/spese"],
    ["/contracts", "/contratti"], ["/vendors", "/fornitori"], ["/cost-centers", "/centri-di-costo"],
    ["/planning-years", "/anni-di-pianificazione"], ["/users", "/utenti"], ["/roles", "/ruoli"], ["/tenants", "/tenant"],
  ];
  for (const [from, to] of legacy) {
    await go(from);
    await waitFor(async () => (await currentPath()).startsWith(to));
    report.legacyRedirects.push({ from, to: await currentPath() });
  }
  if (apiData.expenseId) {
    for (const [from, to] of [[`/expenses/${apiData.expenseId}`, `/spese/${apiData.expenseId}`], [`/expenses/${apiData.expenseId}/edit`, `/spese/${apiData.expenseId}/modifica`]]) {
      await go(from); await waitFor(async () => (await currentPath()).startsWith(to)); report.legacyRedirects.push({ from, to: await currentPath() });
    }
  }
  if (apiData.contractId) {
    for (const [from, to] of [[`/contracts/${apiData.contractId}`, `/contratti/${apiData.contractId}`], [`/contracts/${apiData.contractId}/edit`, `/contratti/${apiData.contractId}/modifica`]]) {
      await go(from); await waitFor(async () => (await currentPath()).startsWith(to)); report.legacyRedirects.push({ from, to: await currentPath() });
    }
  }

  await go("/");
  await delay(500);
  try {
    report.console = (await command("/se/log", "POST", { type: "browser" }))
      .filter((entry) => entry.level === "SEVERE")
      .filter((entry) => !entry.message.includes("/api/v1/auth/me") && !entry.message.includes("/api/v1/expenses/999999999"));
  } catch { report.console = ["Log browser non esposto dal driver"]; }
  try {
    const performance = await command("/se/log", "POST", { type: "performance" });
    report.network = performance.map((entry) => JSON.parse(entry.message).message)
      .filter((entry) => entry.method === "Network.responseReceived" && entry.params.response.status >= 400)
      .map((entry) => ({ status: entry.params.response.status, url: entry.params.response.url }))
      .filter((entry) => !(entry.status === 401 && entry.url.includes("/api/v1/auth/me")) && !(entry.status === 404 && entry.url.includes("/api/v1/expenses/999999999")));
  } catch { report.network = ["Log rete non esposto dal driver"]; }

  fs.writeFileSync(`${outputDirectory}/validation-report.json`, JSON.stringify(report, null, 2));
  console.log(JSON.stringify({
    routeAudits: report.routes.length,
    viewportAudits: report.viewports.length,
    interactions: report.interactions,
    legacyRedirects: report.legacyRedirects.length,
    consoleEntries: Array.isArray(report.console) ? report.console.length : null,
    failedResponses: Array.isArray(report.network) ? report.network.length : null,
  }, null, 2));
  if (report.console.length > 0 || report.network.length > 0) {
    throw new Error(`Errori browser inattesi: console=${report.console.length}, rete=${report.network.length}`);
  }
} finally {
  await request(`/wd/hub/session/${sessionId}`, "DELETE").catch(() => undefined);
}
