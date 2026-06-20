import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';
import path from 'node:path';

const root = path.resolve(fileURLToPath(new URL('..', import.meta.url)));
const require = createRequire(import.meta.url);

function getLinuxLibc() {
  const report = process.report && typeof process.report.getReport === 'function'
    ? process.report.getReport()
    : null;
  return report && report.header && report.header.glibcVersionRuntime ? 'gnu' : 'musl';
}

function expectedNativePackages() {
  if (process.platform !== 'linux' || process.arch !== 'x64') {
    return [];
  }

  const libc = getLinuxLibc();
  return [
    `@rolldown/binding-linux-x64-${libc}`,
    `@tailwindcss/oxide-linux-x64-${libc}`,
    `lightningcss-linux-x64-${libc}`,
  ];
}

function missingPackages() {
  return expectedNativePackages().filter((packageName) => {
    try {
      require.resolve(packageName);
      return false;
    } catch (error) {
      if (error && error.code === 'MODULE_NOT_FOUND') {
        return true;
      }
      throw error;
    }
  });
}

let missing = missingPackages();
if (missing.length === 0) {
  process.exit(0);
}

console.log(`Missing native optional npm dependencies detected: ${missing.join(', ')}`);
console.log('Repairing local install...');
const install = spawnSync('npm', ['install', '--include=optional', '--package-lock=false'], {
  cwd: root,
  stdio: 'inherit',
  shell: process.platform === 'win32',
});

if (install.status !== 0) {
  process.exit(install.status || 1);
}

missing = missingPackages();
if (missing.length > 0) {
  console.error(`Native optional dependencies are still missing after npm install: ${missing.join(', ')}`);
  process.exit(1);
}
