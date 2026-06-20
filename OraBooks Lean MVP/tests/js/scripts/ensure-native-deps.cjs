const { spawnSync } = require('node:child_process');

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

  return [`@unrs/resolver-binding-linux-x64-${getLinuxLibc()}`];
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
  cwd: process.cwd(),
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
