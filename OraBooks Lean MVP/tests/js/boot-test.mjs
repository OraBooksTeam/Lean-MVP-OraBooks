import { JSDOM } from 'jsdom';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const js = fs.readFileSync(path.join(__dirname, '../../assets/react/frontend.js'), 'utf8');

const dom = new JSDOM(
  `<!DOCTYPE html><html><body>
    <div id="orabooks-app-root" class="orabooks-app-root" data-initial-route="/register">
      <p class="orabooks-app-root-loading">Loading</p>
    </div>
  </body></html>`,
  { url: 'https://fundsme.xyz/register/', runScripts: 'outside-only' }
);

const { window } = dom;
window.orabooks_ajax = {
  ajax_url: '/wp-admin/admin-ajax.php',
  nonce: 'test',
  current_user_id: '0',
};

try {
  window.eval(js);
} catch (e) {
  console.error('BOOT ERROR:', e);
  process.exit(1);
}

await new Promise((r) => setTimeout(r, 200));

const root = window.document.getElementById('orabooks-app-root');
console.log('mounted:', window.orabooksReactMounted);
console.log('is-mounted:', root?.classList.contains('is-mounted'));
console.log('hash:', window.location.hash);
console.log('root text sample:', root?.textContent?.slice(0, 120));
