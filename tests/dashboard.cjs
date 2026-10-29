const {chromium} = require('@playwright/test');
const fs = require('node:fs');
const path = require('node:path');
const os = require('node:os');
const net = require('node:net');
const assert = require('node:assert/strict');
const {spawn} = require('node:child_process');

(async () => {
  const root = path.resolve(__dirname, '..');
  const temporary = fs.mkdtempSync(path.join(os.tmpdir(), 'seo-dashboard-'));
  const socket = net.createServer();
  await new Promise(resolve => socket.listen(0, '127.0.0.1', resolve));
  const port = socket.address().port;
  await new Promise(resolve => socket.close(resolve));
  const php = spawn('php', ['-S', `127.0.0.1:${port}`, path.join(root, 'tests/dashboard-router.php')], {cwd: root, env: {...process.env, SEO_DASHBOARD_ROOT: temporary}});
  let logs = '';
  php.stderr.on('data', chunk => {logs += chunk;});
  const browser = await chromium.launch();
  const page = await browser.newPage({viewport: {width: 1440, height: 1000}});
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  try {
    await new Promise((resolve, reject) => {
      if (logs.includes('Development Server')) return resolve();
      php.stderr.on('data', chunk => {if (chunk.toString().includes('Development Server')) resolve();});
      php.on('error', reject);
      php.on('exit', code => reject(new Error(`PHP exited ${code}: ${logs}`)));
    });
    const base = `http://127.0.0.1:${port}`;
    await page.goto(`${base}/panel/seo`);
    await page.getByRole('heading', {name: 'Globale SEO-instellingen', exact: true}).waitFor();
    assert.equal(await page.locator('textarea[name="metadescription"]').inputValue(), 'Existing global description');
    await page.getByRole('region', {name: 'Google', exact: true}).waitFor();
    await page.locator('textarea[name="metadescription"]').fill('Updated in sidebar');
    await page.getByRole('region', {name: 'Google', exact: true}).getByText('Updated in sidebar', {exact: true}).waitFor();
    await page.getByRole('button', {name: 'SEO-instellingen opslaan', exact: true}).click();
    await page.getByRole('status').filter({hasText: 'SEO-instellingen opgeslagen'}).waitFor();
    await page.reload();
    assert.equal(await page.locator('textarea[name="metadescription"]').inputValue(), 'Updated in sidebar');
    const arbitrary = await page.evaluate(async () => await window.panel.api.patch('seo/global', {content: {metadescription: 'Updated in sidebar', email: 'attacker@example.test', title: 'Changed'}}));
    assert.match(fs.readFileSync(path.join(temporary, 'content/site.txt'), 'utf8'), /keep@example.test/, 'Non-SEO content preserved');
    assert.doesNotMatch(fs.readFileSync(path.join(temporary, 'content/site.txt'), 'utf8'), /attacker@example.test|Title: Changed/, 'Save endpoint ignores non-SEO keys');
    await page.getByRole('link', {name: 'Verkeer', exact: true}).click();
    await page.getByRole('status').filter({hasText: 'automatisch beschikbaar'}).waitFor();
    assert.equal(await page.getByRole('link', {name: 'Verkeer (deprecated)', exact: true}).count(), 0, 'No traffic dependency');
    await page.request.get(`${base}/fixture/connect`);
    await page.reload();
    await page.getByRole('heading', {name: 'Zoekopdrachten', exact: true}).waitFor();
    assert.equal(await page.locator('.traffic-chart svg').count(), 4);
    assert.equal(await page.evaluate(() => window.pwned), undefined);
    for (const days of [7, 90, 30]) {
      await page.getByRole('link', {name: `Laatste ${days} dagen`, exact: true}).click();
      await page.waitForURL(`**/panel/traffic/${days}`);
      assert.equal(await page.getByRole('region', {name: 'Per dag', exact: true}).locator('tbody tr').count(), days);
    }
    for (const width of [1440, 390, 375]) {
      await page.setViewportSize({width, height: 1000});
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
      for (const title of ['Zoekopdrachten', 'Pagina’s', 'Landen', 'Apparaten', 'Per dag']) {
        const region = page.getByRole('region', {name: title, exact: true});
        await region.evaluate(el => {el.scrollLeft = el.scrollWidth;});
        assert.equal(await region.locator('tbody td').last().innerText(), '3,2');
        await region.evaluate(el => {el.scrollLeft = 0;});
      }
      if (process.env.SEO_SCREENSHOTS) {
        fs.mkdirSync(process.env.SEO_SCREENSHOTS, {recursive: true});
        await page.screenshot({path: path.join(process.env.SEO_SCREENSHOTS, `seo-traffic-${width}.png`), fullPage: true});
      }
    }
    await page.getByRole('button', {name: 'Property kiezen', exact: true}).click();
    await page.getByLabel('Search Console-property', {exact: true}).selectOption('sc-domain:example.test');
    await Promise.all([
      page.waitForNavigation({waitUntil: 'load'}),
      page.getByRole('button', {name: 'Property opslaan', exact: true}).click(),
    ]);
    await page.getByRole('heading', {name: 'Zoekopdrachten', exact: true}).waitFor();
    await page.request.get(`${base}/fixture/error`);
    await page.reload();
    await page.getByRole('status').filter({hasText: 'Google API-limiet'}).waitFor();
    await Promise.all([
      page.waitForNavigation({waitUntil: 'load'}),
      page.getByRole('button', {name: 'Ontkoppelen', exact: true}).click(),
    ]);
    await page.getByRole('button', {name: 'Koppelen met Google', exact: true}).waitFor();
    let authorization;
    await page.route('https://accounts.google.com/**', route => {authorization = route.request().url(); return route.fulfill({body: 'Fictional OAuth'});});
    await page.getByRole('button', {name: 'Koppelen met Google', exact: true}).click();
    await page.waitForURL('https://accounts.google.com/**');
    assert.equal(new URL(authorization).searchParams.get('scope'), 'https://www.googleapis.com/auth/webmasters.readonly');
    assert.deepEqual(errors, []);
    console.log('Sidebar SEO editor and standalone Search Console dashboard passed at 1440/390/375px.');
  } catch (error) {
    console.error(await page.locator('body').innerText());
    console.error(logs);
    throw error;
  } finally {
    await browser.close();
    php.kill();
    await new Promise(resolve => php.exitCode !== null ? resolve() : php.once('exit', resolve));
    fs.rmSync(temporary, {recursive: true, force: true});
    fs.rmSync(temporary + '-google.json', {force: true});
  }
})().catch(error => {console.error(error); process.exitCode = 1;});
