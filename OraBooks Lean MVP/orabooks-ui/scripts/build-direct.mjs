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
    return false;
  }
  return true;
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
    return false;
  }

  if (!fs.existsSync(pluginBundle) || fs.statSync(wrongBundle).mtimeMs > fs.statSync(pluginBundle).mtimeMs) {
    console.log('\n>> sync misplaced build output to plugin assets/react');
    if (fs.existsSync(outDir)) {
      fs.rmSync(outDir, { recursive: true, force: true });
    }
    copyRecursive(wrongDir, outDir);
    fs.rmSync(wrongDir, { recursive: true, force: true });
    return true;
  }

  return fs.existsSync(pluginBundle);
}

function ensurePluginAssets() {
  const pluginBundle = path.join(outDir, 'frontend.js');
  const wrongDir = path.join(root, 'assets/react');
  const wrongBundle = path.join(wrongDir, 'frontend.js');

  if (syncMisplacedBuildOutput()) {
    return true;
  }

  if (!fs.existsSync(pluginBundle) && fs.existsSync(wrongBundle)) {
    console.log('\n>> restore plugin assets from orabooks-ui/assets/react');
    copyRecursive(wrongDir, outDir);
    return true;
  }

  if (fs.existsSync(wrongBundle) && fs.existsSync(pluginBundle)) {
    const wrongTime = fs.statSync(wrongBundle).mtimeMs;
    const pluginTime = fs.statSync(pluginBundle).mtimeMs;
    if (wrongTime > pluginTime) {
      console.log('\n>> deploy newer UI build to plugin assets/react');
      copyRecursive(wrongDir, outDir);
      return true;
    }
  }

  return fs.existsSync(pluginBundle);
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
      return true;
    }
  }

  return false;
}

function unmapDrive() {
  if (drive) {
    spawnSync('subst', [`${drive} /D`], { shell: false, stdio: 'ignore' });
  }
}

console.log('Build root:', root);

const useSubst = process.platform === 'win32';
const mappedDrive = useSubst && mapDrive();
const buildRoot = mappedDrive ? `${drive}\\` : root;
if (useSubst && !mappedDrive) {
  console.log('Drive mapping unavailable; building from UNC path.');
}

let buildOk = true;
try {
  buildOk = run('clean', node, ['scripts/clean-react-output.mjs'], { cwd: buildRoot, env: buildEnv() }) && buildOk;
  buildOk = run('admin', node, [vite, 'build', '--config', 'vite.admin.config.ts'], { cwd: buildRoot, env: buildEnv() }) && buildOk;
  buildOk = run('frontend', node, [vite, 'build', '--config', 'vite.frontend.config.ts'], { cwd: buildRoot, env: buildEnv() }) && buildOk;
} finally {
  if (mappedDrive) {
    unmapDrive();
  }
}

if (!ensurePluginAssets()) {
  console.error('\nERROR: frontend.js is missing from OraBooks Lean MVP/assets/react/.');
  console.error('Run: node scripts/copy-build-to-plugin.mjs');
  process.exit(1);
}
writeManifest();

const bundlePath = path.join(outDir, 'frontend.js');
const bundle = fs.readFileSync(bundlePath, 'utf8');
console.log('\nBundle checks:');
console.log('  valid line item:', bundle.includes('Vendor and at least one valid line item'));
console.log('  old subtotal msg:', bundle.includes('Vendor and subtotal are required'));
console.log('  AP aging link:', bundle.includes('Full AP aging report'));

if (!buildOk) {
  console.error('\nBuild failed, but plugin assets were restored from the last successful UI output.');
  process.exit(1);
}
