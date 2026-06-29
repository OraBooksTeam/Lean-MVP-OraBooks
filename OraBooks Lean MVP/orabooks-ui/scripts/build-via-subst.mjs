import { spawnSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(fileURLToPath(new URL('.', import.meta.url)), '..');
const drive = 'Z:';

function run(cmd, args, options = {}) {
  const result = spawnSync(cmd, args, {
    stdio: 'inherit',
    shell: true,
    ...options,
  });
  if (result.status !== 0) {
    process.exit(result.status ?? 1);
  }
}

run('subst', [`${drive} /D`], { stdio: 'ignore' });
run('subst', [drive, root]);

try {
  run('npm', ['run', 'build'], { cwd: `${drive}\\` });
} finally {
  run('subst', [`${drive} /D`], { stdio: 'ignore' });
}
