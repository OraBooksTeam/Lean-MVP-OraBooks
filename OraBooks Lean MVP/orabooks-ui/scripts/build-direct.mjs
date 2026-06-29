import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(fileURLToPath(new URL('.', import.meta.url)), '..');

function run(label, cmd, args) {
  console.log(`\n>> ${label}`);
  const result = spawnSync(cmd, args, {
    cwd: root,
    stdio: 'inherit',
    shell: process.platform === 'win32',
    env: process.env,
  });
  if (result.status !== 0) {
    console.error(`${label} failed with exit ${result.status}`);
    process.exit(result.status ?? 1);
  }
}

console.log('Build root:', root);

run('clean', 'node', ['scripts/clean-react-output.mjs']);
run('admin', 'npx', ['vite', 'build', '--config', 'vite.admin.config.ts']);
run('frontend', 'npx', ['vite', 'build', '--config', 'vite.frontend.config.ts']);
run('manifest', 'node', ['scripts/write-deploy-manifest.mjs']);

const bundlePath = path.resolve(root, '../assets/react/frontend.js');
const bundle = fs.readFileSync(bundlePath, 'utf8');
console.log('\nBundle checks:');
console.log('  valid line item:', bundle.includes('Vendor and at least one valid line item'));
console.log('  old subtotal msg:', bundle.includes('Vendor and subtotal are required'));
console.log('  AP aging link:', bundle.includes('Full AP aging report'));
