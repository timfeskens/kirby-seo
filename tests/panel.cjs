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
    console.log('Passed Panel browser checks: real Kirby Panel registration, filled defaults, Google/Facebook/Twitter previews, safe text, mobile layout, manual AI review.');
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
    assert.equal(await page.locator('input[name="usetitletemplate"]').isChecked(), true, 'Page title pattern enabled by default');
    assert.equal(await page.locator('input[name="useogtemplate"]').isChecked(), true, 'Page social pattern enabled by default');
    await page.locator('input[name="metatitle"]').fill('Changed page');
    await page.getByRole('region', {name: 'Google', exact: true}).getByText('Changed page - Example', {exact: true}).waitFor();
    const sitemap = await page.request.get(`http://127.0.0.1:${port}/sitemap.xml`);
    assert.equal(sitemap.status(), 200, 'Automatic sitemap route');
    assert.match(sitemap.headers()['content-type'], /application\/xml/, 'Sitemap XML content type');
    assert.match(await sitemap.text(), /<loc>https:\/\/example.test\/test<\/loc>/, 'Published page in automatic sitemap');
    const alias = await page.request.get(`http://127.0.0.1:${port}/sitemap`, {maxRedirects: 0});
    assert.equal(alias.status(), 301, 'Sitemap alias permanent redirect');
    assert.match(alias.headers().location, /\/sitemap.xml$/, 'Sitemap alias target');
    const robots = await page.request.get(`http://127.0.0.1:${port}/robots.txt`);
    assert.match(await robots.text(), /Sitemap: https:\/\/example.test\/sitemap.xml/, 'Sitemap advertised in robots');
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
