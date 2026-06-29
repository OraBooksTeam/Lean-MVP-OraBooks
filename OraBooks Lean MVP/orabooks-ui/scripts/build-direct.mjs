import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const root = path.resolve(fileURLToPath(new URL('.', import.meta.url)), '..');
const node = process.execPath;
const vite = 'node_modules/vite/bin/vite.js';
const outDir = path.resolve(root, '../assets/react');
const driveCandidates = ['X:', 'W:', 'V:', 'U:', 'Y:', 'Z:'];
let drive = driveCandidates[0];

function buildEnv() {
  return {
    ...process.env,
    ORABOOKS_UI_ROOT: root,
    ORABOOKS_PLUGIN_ASSETS: outDir,
  };
}

function run(label, command, args, options = {}) {
  console.log(`\n>> ${label}`);
  const result = spawnSync(command, args, {
    cwd: options.cwd ?? root,
    stdio: 'inherit',
    shell: false,
    env: options.env ?? process.env,
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

function syncMisplacedBuildOutput() {
  const wrongDir = path.join(root, 'assets/react');
  const pluginBundle = path.join(outDir, 'frontend.js');
  const wrongBundle = path.join(wrongDir, 'frontend.js');

  if (!fs.existsSync(wrongBundle)) {
    return;
  }

  if (!fs.existsSync(pluginBundle) || fs.statSync(wrongBundle).mtimeMs > fs.statSync(pluginBundle).mtimeMs) {
    console.log('\n>> sync misplaced build output to plugin assets/react');
    if (fs.existsSync(outDir)) {
      fs.rmSync(outDir, { recursive: true, force: true });
    }
    copyRecursive(wrongDir, outDir);
    fs.rmSync(wrongDir, { recursive: true, force: true });
  }
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

  const files = walk(outDir)
    .filter((file) => file !== 'deploy-manifest.json')
    .sort();
  fs.mkdirSync(outDir, { recursive: true });
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
  for (const letter of driveCandidates) {
    spawnSync('subst', [`${letter} /D`], { shell: false, stdio: 'ignore' });
  }

  for (const letter of driveCandidates) {
    const mapped = spawnSync('subst', [letter, root], { shell: false, stdio: 'pipe', encoding: 'utf8' });
    if (mapped.status === 0) {
      drive = letter;
      return;
    }
  }

  console.error('Failed to map a build drive letter');
  process.exit(1);
}

function unmapDrive() {
  if (drive) {
    spawnSync('subst', [`${drive} /D`], { shell: false, stdio: 'ignore' });
  }
}

console.log('Build root:', root);

const useSubst = process.platform === 'win32';
if (useSubst) {
  mapDrive();
}
const buildRoot = useSubst ? `${drive}\\` : root;

try {
  run('clean', node, ['scripts/clean-react-output.mjs'], { cwd: buildRoot, env: buildEnv() });
  run('admin', node, [vite, 'build', '--config', 'vite.admin.config.ts'], { cwd: buildRoot, env: buildEnv() });
  run('frontend', node, [vite, 'build', '--config', 'vite.frontend.config.ts'], { cwd: buildRoot, env: buildEnv() });
} finally {
  if (useSubst) {
    unmapDrive();
  }
}

syncMisplacedBuildOutput();
writeManifest();

const bundlePath = path.join(outDir, 'frontend.js');
const bundle = fs.readFileSync(bundlePath, 'utf8');
console.log('\nBundle checks:');
console.log('  valid line item:', bundle.includes('Vendor and at least one valid line item'));
console.log('  old subtotal msg:', bundle.includes('Vendor and subtotal are required'));
console.log('  AP aging link:', bundle.includes('Full AP aging report'));
