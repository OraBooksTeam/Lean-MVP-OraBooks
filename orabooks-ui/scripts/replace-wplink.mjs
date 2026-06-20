import fs from 'fs';
import path from 'path';

const root = path.resolve('src/pages/frontend');

function walk(dir, files = []) {
  for (const ent of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, ent.name);
    if (ent.isDirectory()) walk(p, files);
    else if (ent.name.endsWith('.tsx')) files.push(p);
  }
  return files;
}

for (const file of walk(root)) {
  let src = fs.readFileSync(file, 'utf8');
  if (!src.includes("from 'react-router-dom'") || !src.includes('Link')) continue;
  if (src.includes('useSearchParams')) continue;

  const rel = path.relative(path.dirname(file), path.join(root, 'components/WpLink')).replace(/\\/g, '/');
  const importPath = rel.startsWith('.') ? rel : `./${rel}`;
  src = src.replace(
    /import \{ Link \} from 'react-router-dom';\n/,
    `import WpLink from '${importPath}';\n`
  );
  src = src.replace(/<Link /g, '<WpLink ');
  fs.writeFileSync(file, src);
  console.log('updated', file);
}
