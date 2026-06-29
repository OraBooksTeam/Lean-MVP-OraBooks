import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(fileURLToPath(new URL('.', import.meta.url)), '..');
const wrongDir = path.join(root, 'assets/react');

// Only clear the misplaced UI staging folder. Never delete the plugin deploy
// folder here — that causes a white page if the subsequent build fails.
if (fs.existsSync(wrongDir)) {
  fs.rmSync(wrongDir, { recursive: true, force: true });
}
