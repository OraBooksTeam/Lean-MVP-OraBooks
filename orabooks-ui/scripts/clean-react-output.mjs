import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(fileURLToPath(new URL('.', import.meta.url)), '..');
const outDir = path.resolve(root, '../OraBooks Lean MVP/assets/react');

if (fs.existsSync(outDir)) {
  for (const name of fs.readdirSync(outDir)) {
    if (name === 'deploy-manifest.json') {
      continue;
    }

    fs.rmSync(path.join(outDir, name), { recursive: true, force: true });
  }
}
