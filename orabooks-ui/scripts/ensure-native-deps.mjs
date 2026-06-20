import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import path from 'node:path';

const root = path.resolve(fileURLToPath(new URL('..', import.meta.url)));
const viteBin = path.join(root, 'node_modules', 'vite', 'bin', 'vite.js');

function checkVite() {
  return spawnSync(process.execPath, [viteBin, '--version'], {
    cwd: root,
    encoding: 'utf8',
  });
}

let result = checkVite();
if (result.status === 0) {
  process.exit(0);
}

const output = `${result.stdout || ''}${result.stderr || ''}`;
const nativeBindingMissing = /Cannot find native binding|MODULE_NOT_FOUND|binding.*\.node/i.test(output);

if (!nativeBindingMissing) {
  process.stderr.write(output);
  process.exit(result.status || 1);
}

console.log('Missing native optional npm dependencies detected. Repairing local install...');
const install = spawnSync('npm', ['install', '--include=optional', '--package-lock=false'], {
  cwd: root,
  stdio: 'inherit',
  shell: process.platform === 'win32',
});

if (install.status !== 0) {
  process.exit(install.status || 1);
}

result = checkVite();
if (result.status !== 0) {
  process.stderr.write(`${result.stdout || ''}${result.stderr || ''}`);
  process.exit(result.status || 1);
}
