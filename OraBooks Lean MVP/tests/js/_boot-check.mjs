import { JSDOM } from 'jsdom';
import fs from 'fs';

const js = fs.readFileSync('../../assets/react/frontend.js', 'utf8');
const dom = new JSDOM(
  '<!DOCTYPE html><html><body><div id="orabooks-app-root" data-initial-route="/register"><p>Loading</p></div></body></html>',
  { url: 'https://fundsme.xyz/register/', runScripts: 'outside-only' }
);

dom.window.orabooks_ajax = { ajax_url: '/wp-admin/admin-ajax.php', nonce: 'test', current_user_id: '0' };
dom.window.eval(js);
await new Promise((r) => setTimeout(r, 200));

const root = dom.window.document.getElementById('orabooks-app-root');
console.log('mounted:', dom.window.orabooksReactMounted);
console.log('is-mounted:', root?.classList.contains('is-mounted'));
console.log('text:', root?.textContent?.slice(0, 80));
