const { chromium } = require('@playwright/test');
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');

const root = path.resolve(__dirname, '..');
const server = http.createServer((req, res) => {
  const files = {
    '/vue.js': 'kirby/panel/dist/js/vue.esm.browser.js',
    '/index.js': 'index.js',
    '/index.css': 'index.css',
  };
  if (files[req.url]) {
    res.setHeader('Content-Type', req.url.endsWith('.css') ? 'text/css' : 'application/javascript');
    return res.end(fs.readFileSync(path.join(root, files[req.url])));
  }
  res.setHeader('Content-Type', 'text/html');
  res.end(`<!doctype html><html lang="nl"><head><link rel="stylesheet" href="/index.css"></head><body><div id="app"></div>
    <script type="module">
    import Vue from '/vue.js';
    window.panel = { plugin(name, plugin) { window.component = plugin.fields.timfeskensSeoPreview; } };
    await import('/index.js');
    Vue.component('k-field', {render(h) { return h('div', this.$slots.default); }});
    Vue.component('k-button', {props: ['disabled'], render(h) {return h('button', {attrs: {disabled: this.disabled}, on: {click: () => this.$emit('click')}}, this.$slots.default); }});
    window.calls = [];
    Vue.prototype.$panel = {translation: {code: 'nl'}};
    Vue.prototype.$api = {async post(url, data) {
      window.calls.push({url, data});
      if (url.endsWith('/suggest')) return {title: 'Suggestion', description: 'Review me'};
      const title = data.content.metatitle || 'Default';
      return {title, canonical: 'https://example.test/page', tags: {'og:title': title, description: data.content.metadescription, robots: 'noindex'}};
    }};
    window.app = new Vue({el: '#app', data: {versions: {changes: {}}, content: {metatitle: 'First', metadescription: 'Description'}},
      render(h) { return h(window.component, {props: {label: 'SEO', ai: true, endpoints: {field: 'pages/test/fields/seopreview'}, preview: {title: 'Initial', canonical: 'https://example.test/page', tags: {description: 'Initial description', robots: 'noindex'}}}}); }
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
    await page.getByText('Initial', {exact: true}).waitFor();
    await page.evaluate(() => {window.app.content.metatitle = 'Changed <script>window.pwned=true</script>';});
    await page.getByText('Changed <script>window.pwned=true</script>', {exact: true}).first().waitFor();
    assert.equal(await page.evaluate(() => window.pwned), undefined, 'Preview renders escaped text');
    assert.equal(await page.evaluate(() => window.calls[0].url), 'pages/test/fields/seopreview/preview', 'Field endpoint');
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'No mobile overflow');
    page.on('dialog', dialog => dialog.accept());
    await page.getByRole('button', {name: 'AI-suggestie maken'}).click();
    await page.getByText('Review me', {exact: true}).waitFor();
    assert.equal(await page.evaluate(() => window.app.content.metatitle), 'Changed <script>window.pwned=true</script>', 'Suggestions never overwrite fields');
    assert.deepEqual(errors, [], 'No browser errors');
    console.log('Passed Panel browser checks: reactive previews, safe text, mobile layout, manual AI review.');
  } finally {
    await browser.close();
    server.close();
  }
})().catch(error => { console.error(error); server.close(); process.exitCode = 1; });
