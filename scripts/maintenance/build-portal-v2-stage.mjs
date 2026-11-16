import { resolve, join } from 'node:path';
import { pathToFileURL, fileURLToPath } from 'node:url';
import { mkdir, access } from 'node:fs/promises';
const root=resolve(fileURLToPath(new URL('../..',import.meta.url)));
const stage=join(root,'private-audit/portal-v2-stage');
await access(join(stage,'stage.json'));
const envDir=join(stage,'build-env');await mkdir(envDir,{recursive:true});
for(const folder of ['pnpman/frontend','pnp-lessonplan']){
  const project=join(root,folder);
  process.chdir(project);
  const {build}=await import(pathToFileURL(join(project,'node_modules/vite/dist/node/index.js')).href);
  await build({root:project,envDir,build:{outDir:join(stage,'public/pnp-portal',folder,'dist'),emptyOutDir:false}});
}
