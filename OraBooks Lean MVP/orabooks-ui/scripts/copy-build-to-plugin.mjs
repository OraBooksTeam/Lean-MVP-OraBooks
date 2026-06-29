import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(fileURLToPath(new URL('.', import.meta.url)), '..');
const wrongDir = path.join(root, 'assets/react');
const pluginDir = path.resolve(root, '../assets/react');

function walk(dir, base = '') {
  const entries = [];
  if (!fs.existsSync(dir)) {
    return entries;
  }
  for (const name of fs.readdirSync(dir)) {
    const full = path.join(dir, name);
    const rel = path.posix.join(base, name);
    if (fs.statSync(full).isDirectory()) {
      entries.push(...walk(full, rel));
    } else {
      entries.push(rel.replace(/\\/g, '/'));
    }
  }
  return entries;
}

function copyRecursive(src, dest) {
  fs.mkdirSync(dest, { recursive: true });
  for (const name of fs.readdirSync(src)) {
    const from = path.join(src, name);
    const to = path.join(dest, name);
    if (fs.statSync(from).isDirectory()) {
      copyRecursive(from, to);
    } else {
      fs.copyFileSync(from, to);
    }
  }
}

if (!fs.existsSync(wrongDir)) {
  console.error('Source build output not found:', wrongDir);
  process.exit(1);
}

if (fs.existsSync(pluginDir)) {
  fs.rmSync(pluginDir, { recursive: true, force: true });
}
copyRecursive(wrongDir, pluginDir);

const files = walk(pluginDir).sort();
fs.writeFileSync(
  path.join(pluginDir, 'deploy-manifest.json'),
  JSON.stringify(
    {
      generated_at: new Date().toISOString(),
      deploy_path: 'wp-content/plugins/OraBooks Lean MVP/assets/react/',
      files,
    },
    null,
    2
  )
);

const bundle = fs.readFileSync(path.join(pluginDir, 'frontend.js'), 'utf8');
console.log('Copied build to plugin assets/react');
console.log('  valid line item:', bundle.includes('Vendor and at least one valid line item'));
console.log('  AP aging link:', bundle.includes('Full AP aging report'));
console.log('  files:', files.length);
