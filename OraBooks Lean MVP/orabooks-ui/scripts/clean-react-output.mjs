import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(fileURLToPath(new URL('.', import.meta.url)), '..');
const outDir = path.resolve(root, '../assets/react');
const wrongDir = path.join(root, 'assets/react');

for (const dir of [outDir, wrongDir]) {
  if (fs.existsSync(dir)) {
    fs.rmSync(dir, { recursive: true, force: true });
  }
}
