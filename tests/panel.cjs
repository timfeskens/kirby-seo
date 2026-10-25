const { chromium } = require('@playwright/test');
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const { spawn } = require('node:child_process');
const os = require('node:os');

const root = path.resolve(__dirname, '..');
const server = http.createServer((req, res) => {
  const files = {
    '/vue.js': 'kirby/panel/dist/js/vue.esm.browser.js',
    '/index.js': 'index.js',
    '/index.css': 'index.css',
    '/plugins.js': 'kirby/panel/dist/js/plugins.js',
  };
  if (files[req.url]) {
    res.setHeader('Content-Type', req.url.endsWith('.css') ? 'text/css' : 'application/javascript');
    return res.end(fs.readFileSync(path.join(root, files[req.url])));
  }
  res.setHeader('Content-Type', 'text/html');
  res.end(`<!doctype html><html lang="nl"><head><link rel="stylesheet" href="/index.css"></head><body><div id="app"></div>
    <script type="importmap">{"imports":{"vue":"/vue.js"}}</script><script type="module">
    import Vue from '/vue.js';
    await import('/plugins.js');
    await import('/index.js');
    for (const [name, component] of Object.entries(window.panel.plugins.components)) {
      if (!component.extends) Vue.component(name, component);
    }
    Vue.component('k-field', {render(h) { return h('div', this.$slots.default); }});
    Vue.component('k-button', {props: ['disabled'], render(h) {return h('button', {attrs: {disabled: this.disabled}, on: {click: () => this.$emit('click')}}, this.$slots.default); }});
    window.calls = [];
    Vue.prototype.$panel = {translation: {code: 'nl'}};
    Vue.prototype.$api = {async post(url, data) {
      window.calls.push({url, data});
      if (url.endsWith('/suggest')) return {title: 'Suggestion', description: 'Review me'};
      const title = data.content.metatitle || 'Default';
       return {title, canonical: 'https://example.test/page', tags: {'og:title': title, 'og:site_name': 'Example', 'og:description': 'Facebook description', 'twitter:title': 'Twitter title', 'twitter:description': 'Twitter description', 'twitter:card': 'summary', description: data.content.metadescription, robots: 'noindex'}};
    }};
    window.app = new Vue({el: '#app', data: {versions: {changes: {}}, content: {metatitle: 'First', metadescription: 'Description'}},
      render(h) { return h('k-timfeskens-seo-preview-field', {props: {label: 'SEO', ai: true, endpoints: {field: 'pages/test/fields/seopreview'}, preview: {title: 'Initial', canonical: 'https://example.test/page', tags: {description: 'Initial description', robots: 'noindex'}}}}); }
    });
    </script></body></html>`);
});

(async () => {
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  const browser = await chromium.launch();
  try {
    const page = await browser.newPage({viewport: {width: 390, height: 844}});
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(`http://127.0.0.1:${server.address().port}`);
    await page.getByRole('region', {name: 'Google', exact: true}).waitFor();
    for (const name of ['Google', 'Facebook', 'Twitter / X']) assert.equal(await page.getByRole('region', {name, exact: true}).count(), 1, `${name} preview`);
    await page.evaluate(() => {window.app.content.metatitle = 'Changed <script>window.pwned=true</script>';});
    await page.getByText('Changed <script>window.pwned=true</script>', {exact: true}).first().waitFor();
    assert.equal(await page.evaluate(() => window.pwned), undefined, 'Preview renders escaped text');
    await page.getByRole('region', {name: 'Twitter / X', exact: true}).getByText('Twitter title', {exact: true}).waitFor();
    await page.getByRole('region', {name: 'Facebook', exact: true}).getByText('Facebook description', {exact: true}).waitFor();
    assert.equal(await page.evaluate(() => window.calls[0].url), 'pages/test/fields/seopreview/preview', 'Field endpoint');
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'No mobile overflow');
    page.on('dialog', dialog => dialog.accept());
    await page.getByRole('button', {name: 'AI-suggestie maken'}).click();
    await page.getByText('Review me', {exact: true}).waitFor();
    assert.equal(await page.evaluate(() => window.app.content.metatitle), 'Changed <script>window.pwned=true</script>', 'Suggestions never overwrite fields');
    assert.deepEqual(errors, [], 'No browser errors');
    await realPanel(browser);
    console.log('Passed browser checks: real Kirby Panel, responsive previews, safe text, manual AI review, styled sitemap and empty/multilingual views.');
  } finally {
    await browser.close();
    server.close();
  }
})().catch(error => { console.error(error); server.close(); process.exitCode = 1; });

async function realPanel(browser) {
  const temporary = fs.mkdtempSync(path.join(os.tmpdir(), 'kirby-seo-panel-'));
  const socket = require('node:net').createServer();
  await new Promise(resolve => socket.listen(0, '127.0.0.1', resolve));
  const port = socket.address().port;
  await new Promise(resolve => socket.close(resolve));
  const php = spawn('php', ['-S', `127.0.0.1:${port}`, path.join(root, 'tests/panel-router.php')], {cwd: root, env: {...process.env, SEO_PANEL_TEST_ROOT: temporary}});
  let logs = '';
  php.stderr.on('data', chunk => {logs += chunk;});
  php.stdout.on('data', chunk => {logs += chunk;});
  const page = await browser.newPage({viewport: {width: 1440, height: 1000}});
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('response', async response => {if (response.status() >= 400) errors.push(`${response.status()} ${response.url()} ${await response.text()}`);});
  try {
    await new Promise((resolve, reject) => {
      php.stderr.on('data', chunk => {if (chunk.toString().includes('Development Server')) resolve();});
      php.on('error', reject);
      php.on('exit', code => reject(new Error(`PHP exited ${code}: ${logs}`)));
    });
    await page.goto(`http://127.0.0.1:${port}/panel/site?tab=seo`);
    await page.getByRole('region', {name: 'Google', exact: true}).waitFor();
    assert.equal(await page.getByText(/Het veldtype .* bestaat niet/).count(), 0, 'No unknown field error in real Panel');
    await page.getByRole('heading', {name: 'Zoekmachines', exact: true}).waitFor();
    await page.getByRole('heading', {name: 'Delen op sociale media', exact: true}).waitFor();
    await page.getByRole('heading', {name: 'Robotsinstellingen', exact: true}).waitFor();
    await page.getByRole('heading', {name: 'Sociale profielen', exact: true}).waitFor();
    assert.equal(await page.getByText(/Overnemen/).count(), 0, 'Global settings have no inheritance option');
    for (const name of ['robotsindex', 'robotsfollow', 'robotsimageindex', 'robotssnippet']) {
      assert.equal(await page.locator(`select[name="${name}"] option[value="default"]`).innerText(), ' Automatisch ', 'Global robots automatic label');
    }
    await responsiveLayout(page, 'site');
    for (const [name, expected] of [['metatemplate', '{{ title }} - {{ site.title }}'], ['ogtemplate', '{{ title }}'], ['ogsitename', '{{ site.title }}']]) {
      const input = page.locator(`input[name="${name}"]`);
      assert.equal(await input.inputValue(), expected, `${name} default in real Panel`);
      assert.equal(await input.getAttribute('placeholder'), expected, `${name} placeholder in real Panel`);
    }
    assert.equal(await page.locator('select[name="twittercardtype"]').inputValue(), 'default', 'Automatic card selection filled');
    assert.equal(await page.locator('select[name="robotsindex"]').inputValue(), 'default', 'Automatic indexing selection filled');
    assert.equal(await page.locator('.k-label-invalid:visible').count(), 0, 'Defaults produce no visible validation errors');
    await page.locator('input[name="metatemplate"]').fill('{{ title }} | {{ site.title }}');
    await page.getByRole('region', {name: 'Google', exact: true}).getByText('Home | Example', {exact: true}).waitFor();
    await page.locator('input[name="ogtemplate"]').fill('{{ title }} social');
    await page.getByRole('region', {name: 'Facebook', exact: true}).getByText('Home social', {exact: true}).waitFor();
    await page.getByRole('region', {name: 'Twitter / X', exact: true}).getByText('Home social', {exact: true}).waitFor();
    await page.locator('textarea[name="metadescription"]').fill('An unsaved description');
    await page.getByRole('region', {name: 'Google', exact: true}).getByText('An unsaved description', {exact: true}).waitFor();
    await page.setViewportSize({width: 390, height: 844});
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'Real Panel mobile overflow');
    await page.goto(`http://127.0.0.1:${port}/panel/pages/test?tab=seo`);
    await page.getByRole('region', {name: 'Google', exact: true}).getByText('Test page - Example', {exact: true}).waitFor();
    assert.match(await page.locator('select[name="robotsindex"] option[value="default"]').innerText(), /Overnemen/, 'Page robots retain inheritance');
    assert.equal(await page.getByRole('heading', {name: 'Sociale profielen', exact: true}).count(), 0, 'Profiles only on site');
    await page.getByRole('heading', {name: 'Geavanceerd en overerving', exact: true}).waitFor();
    await responsiveLayout(page, 'page');
    assert.equal(await page.locator('input[name="usetitletemplate"]').isChecked(), true, 'Page title pattern enabled by default');
    assert.equal(await page.locator('input[name="useogtemplate"]').isChecked(), true, 'Page social pattern enabled by default');
    await page.locator('input[name="metatitle"]').fill('Changed page');
    await page.getByRole('region', {name: 'Google', exact: true}).getByText('Changed page - Example', {exact: true}).waitFor();
    const longTitle = 'LongUnbrokenTitle'.repeat(20);
    await page.locator('input[name="metatitle"]').fill(longTitle);
    await page.getByRole('region', {name: 'Google', exact: true}).getByText(`${longTitle} - Example`, {exact: true}).waitFor();
    await responsiveLayout(page, 'page-long-title');
    const sitemap = await page.request.get(`http://127.0.0.1:${port}/sitemap.xml`);
    assert.equal(sitemap.status(), 200, 'Automatic sitemap route');
    assert.match(sitemap.headers()['content-type'], /application\/xml/, 'Sitemap XML content type');
    assert.match(await sitemap.text(), /<loc>https:\/\/example.test\/test<\/loc>/, 'Published page in automatic sitemap');
    const alias = await page.request.get(`http://127.0.0.1:${port}/sitemap`, {maxRedirects: 0});
    assert.equal(alias.status(), 301, 'Sitemap alias permanent redirect');
    assert.match(alias.headers().location, /\/sitemap.xml$/, 'Sitemap alias target');
    const robots = await page.request.get(`http://127.0.0.1:${port}/robots.txt`);
    assert.match(await robots.text(), /Sitemap: https:\/\/example.test\/sitemap.xml/, 'Sitemap advertised in robots');
    const stylesheet = await page.request.get(`http://127.0.0.1:${port}/sitemap.xsl`);
    assert.equal(stylesheet.status(), 200, 'Stylesheet route available');
    assert.match(stylesheet.headers()['content-type'], /text\/xsl/, 'Stylesheet content type');
    await page.goto(`http://127.0.0.1:${port}/sitemap.xml`);
    await page.getByRole('heading', {name: 'XML Sitemap', exact: true}).waitFor();
    assert.equal(await page.locator('tbody tr').count(), 2, 'Browser renders sitemap entries');
    assert.equal(await page.locator('.count').innerText(), '2 URLs', 'Sitemap displays entry count');
    assert.equal(await page.getByRole('link', {name: 'https://example.test/test', exact: true}).getAttribute('href'), 'https://example.test/test', 'Sitemap URLs clickable');
    for (const width of [1440, 390, 375]) {
      await page.setViewportSize({width, height: 900});
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `Sitemap ${width}px has no horizontal overflow`);
      if (process.env.SEO_PANEL_SCREENSHOTS) {
        fs.mkdirSync(process.env.SEO_PANEL_SCREENSHOTS, {recursive: true});
        await page.screenshot({path: path.join(process.env.SEO_PANEL_SCREENSHOTS, `sitemap-${width}.png`), fullPage: true});
      }
    }
    // Exercise empty/multilingual views and escaping without changing persistent content.
    const rendered = await page.evaluate(async () => {
      const processor = new XSLTProcessor();
      const parser = new DOMParser();
      const xsl = await (await fetch('/sitemap.xsl')).text();
      processor.importStylesheet(parser.parseFromString(xsl, 'application/xml'));
      const render = xml => processor.transformToDocument(parser.parseFromString(xml, 'application/xml'));
      const empty = render('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>');
      const multilingual = render('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml"><url><loc>https://example.test/&lt;script&gt;</loc><xhtml:link rel="alternate" hreflang="nl" href="https://example.nl/"/></url></urlset>');
      return {empty: empty.querySelector('.empty').textContent, count: empty.querySelector('.count').textContent, language: multilingual.querySelector('.alternates a').getAttribute('hreflang'), scripts: multilingual.querySelectorAll('script').length, missing: multilingual.querySelector('tbody td:last-child').textContent.trim()};
    });
    assert.match(rendered.empty, /No indexable URLs/);
    assert.equal(rendered.count, '0 URLs');
    assert.equal(rendered.language, 'nl');
    assert.equal(rendered.scripts, 0, 'Stylesheet escapes URL text');
    assert.equal(rendered.missing, 'Not available', 'Missing dates not fabricated');
    assert.deepEqual(errors, [], 'No real Panel browser errors');
  } catch (error) {
    console.error(errors);
    console.error(await page.locator('body').innerText());
    console.error(logs);
    throw error;
  } finally {
    await page.close();
    php.kill();
    await new Promise(resolve => php.once('exit', resolve));
    fs.rmSync(temporary, {recursive: true, force: true});
  }
}

async function responsiveLayout(page, model) {
  assert.equal(await page.locator('.k-field-name-robotsarchive').count(), 0, 'Obsolete archive control removed');
  assert.doesNotMatch(await page.locator('.tf-seo-google').innerText(), /~60|~160|tekens|characters/, 'No preview character guidance');
  assert.equal(await page.locator('.k-field-name-metatemplate .k-counter').count(), 1, 'Normal input counter retained');
  for (const width of [1920, 1440, 1280, 1024, 768, 390, 375]) {
    await page.setViewportSize({width, height: 1000});
    const layout = await page.evaluate(() => {
      const rect = node => {
        const box = node.getBoundingClientRect();
        return {x: box.x, y: box.y, width: box.width, bottom: box.bottom, right: box.right};
      };
      const settings = document.querySelector('.k-section-name-seoSearch').closest('.k-column');
      const previews = document.querySelector('.tf-seo-previews').closest('.k-section').closest('.k-column');
      return {settings: rect(settings), previews: rect(previews), cards: [...document.querySelectorAll('.tf-seo-previews > section')].map(rect), overflow: document.documentElement.scrollWidth > innerWidth};
    });
    assert.equal(layout.overflow, false, `${model} ${width}px: no document overflow`);
    for (let i = 1; i < layout.cards.length; i++) {
      assert.ok(layout.cards[i].y >= layout.cards[i - 1].bottom, 'Platform previews stacked in order');
      assert.ok(Math.abs(layout.cards[i].x - layout.cards[0].x) < 1, 'Previews share column');
    }
    if (width >= 1280) {
      assert.ok(layout.previews.x >= layout.settings.right, 'Previews to the right of settings');
      assert.ok(Math.abs(layout.previews.y - layout.settings.y) < 1, 'Columns top aligned');
      assert.ok(layout.settings.width / layout.previews.width > 1.9 && layout.settings.width / layout.previews.width < 2.3, 'Approximately 66/33 columns');
    }
    if (width <= 1024) {
      assert.ok(layout.previews.y >= layout.settings.bottom, 'Narrow screens place previews after settings');
      assert.ok(Math.abs(layout.previews.width - layout.settings.width) < 1, 'Narrow columns use full width');
    }
    if (process.env.SEO_PANEL_SCREENSHOTS) {
      await page.screenshot({path: path.join(process.env.SEO_PANEL_SCREENSHOTS, `${model}-${width}.png`), fullPage: true});
    }
  }
  await page.setViewportSize({width: 1440, height: 1000});
}
