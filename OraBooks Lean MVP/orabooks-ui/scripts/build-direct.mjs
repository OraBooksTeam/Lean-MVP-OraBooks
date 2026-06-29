import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const root = path.resolve(fileURLToPath(new URL('.', import.meta.url)), '..');
const node = process.execPath;
const vite = 'node_modules/vite/bin/vite.js';
const outDir = path.resolve(root, '../assets/react');
const drive = 'Z:';

function run(label, command, args, options = {}) {
  console.log(`\n>> ${label}`);
  const result = spawnSync(command, args, {
    cwd: options.cwd ?? root,
    stdio: 'inherit',
    shell: false,
    env: process.env,
  });
  if (result.status !== 0) {
    console.error(`${label} failed with exit ${result.status}`);
    if (result.error) {
      console.error(result.error.message);
    }
    process.exit(result.status ?? 1);
  }
}

function cleanOutput() {
  if (fs.existsSync(outDir)) {
    fs.rmSync(outDir, { recursive: true, force: true });
  }
}

function writeManifest() {
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

  const files = walk(outDir).sort();
  fs.writeFileSync(
    path.join(outDir, 'deploy-manifest.json'),
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
  console.log(`Deploy manifest written: ${files.length} files`);
}

function mapDrive() {
  spawnSync('subst', [`${drive} /D`], { shell: false, stdio: 'ignore' });
  const mapped = spawnSync('subst', [drive, root], { shell: false, stdio: 'pipe', encoding: 'utf8' });
  if (mapped.status !== 0) {
    console.error('Failed to map drive Z:');
    console.error(mapped.stderr || mapped.stdout || mapped.error?.message);
    process.exit(1);
  }
}

function unmapDrive() {
  spawnSync('subst', [`${drive} /D`], { shell: false, stdio: 'ignore' });
}

console.log('Build root:', root);

mapDrive();
const buildRoot = `${drive}\\`;

try {
  run('clean', node, ['scripts/clean-react-output.mjs'], { cwd: buildRoot });
  run('admin', node, [vite, 'build', '--config', 'vite.admin.config.ts'], { cwd: buildRoot });
  run('frontend', node, [vite, 'build', '--config', 'vite.frontend.config.ts'], { cwd: buildRoot });
} finally {
  unmapDrive();
}

writeManifest();

const bundlePath = path.join(outDir, 'frontend.js');
const bundle = fs.readFileSync(bundlePath, 'utf8');
console.log('\nBundle checks:');
console.log('  valid line item:', bundle.includes('Vendor and at least one valid line item'));
console.log('  old subtotal msg:', bundle.includes('Vendor and subtotal are required'));
console.log('  AP aging link:', bundle.includes('Full AP aging report'));
