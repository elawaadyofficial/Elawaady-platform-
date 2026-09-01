const { chromium } = require('playwright');

/**
 * EXD — every public page at the three widths the spec names.
 *
 * Checks the three things that are invisible in a screenshot and obvious to a
 * person on a phone: a page wider than the screen, a link inside a link, and a
 * request that failed. Run it with the store already serving:
 *
 *   NODE_PATH=/path/to/node_modules node tests/responsive_sweep.js
 *
 * BASE may be set; it defaults to the local development server.
 */
(async () => {
  const browser = await chromium.launch({
    executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome',
    args: ['--no-sandbox'],
  });

  const pages = [
    'index.php', 'categories.php', 'subcategories.php?category_id=1',
    'service.php?id=1', 'search.php?q=%D9%81%D9%8A%D8%B3%D8%A8%D9%88%D9%83',
    'mediation.php', 'page.php?slug=terms', 'contact.php',
    'login.php', 'register.php', 'cart.php', 'order-track.php',
  ];
  const widths = [1440, 768, 390];
  let problems = 0;

  for (const target of pages) {
    const row = [];
    for (const width of widths) {
      const page = await browser.newPage({ viewport: { width, height: 900 } });
      const errors = [];
      page.on('pageerror', e => errors.push(e.message));
      page.on('response', r => { if (r.status() >= 400) errors.push(r.status() + ' ' + r.url().split('/').pop()); });

      await page.goto(`${process.env.BASE || 'http://127.0.0.1:8080'}/${target}`, { waitUntil: 'networkidle' });
      await page.evaluate(() => document.querySelectorAll('img[loading="lazy"]').forEach(i => { i.loading = 'eager'; }));
      await page.waitForTimeout(500);

      const r = await page.evaluate(() => ({
        overflow: document.documentElement.scrollWidth - window.innerWidth,
        nested: document.querySelectorAll('a a').length,
        tiny: [...document.querySelectorAll('a, button')]
          .filter(el => { const b = el.getBoundingClientRect();
                          return b.width > 0 && b.height > 0 && b.height < 28; }).length,
      }));

      const bad = r.overflow > 1 || r.nested > 0 || errors.length > 0;
      if (bad) { problems++; }
      row.push(`${width}:${bad ? 'X' : 'ok'}${r.overflow > 1 ? ` +${r.overflow}px` : ''}` +
               `${r.nested ? ` nested:${r.nested}` : ''}${errors.length ? ` err:${errors[0]}` : ''}`);
      await page.close();
    }
    console.log(`  ${target.padEnd(38)} ${row.join('  ')}`);
  }

  console.log(problems === 0
    ? '\n  Every page is clean at desktop, tablet and phone.'
    : `\n  ${problems} page/width combinations have a problem.`);
  await browser.close();
  process.exit(problems === 0 ? 0 : 1);
})();
