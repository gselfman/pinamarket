import fs from 'node:fs/promises';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
import {createHash} from 'node:crypto';
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'..'),source=path.join(root,'prototype'),out=path.join(root,'dist/giftcards');
const names=['app.js','live-app.js','style.css','legacy-ui.mjs','model.mjs','merchant.mjs','contracts.mjs'];
const hash=createHash('sha256');for(const name of [...names,'index.html'])hash.update(await fs.readFile(path.join(source,name)));for(const name of (await fs.readdir(path.join(source,'assets'))).sort())hash.update(await fs.readFile(path.join(source,'assets',name)));
const tag='r-'+hash.digest('hex').slice(0,16),prefix='/giftcards/'+tag,versioned=path.join(out,tag);
await fs.rm(out,{recursive:true,force:true});await fs.mkdir(versioned,{recursive:true});await fs.cp(path.join(source,'assets'),path.join(versioned,'assets'),{recursive:true});
for(const name of names){let body=await fs.readFile(path.join(source,name),'utf8');if(name.endsWith('.js')||name.endsWith('.mjs'))body=body.replace(/\.mjs(['"])/g,'.js$1').replaceAll('./assets/',prefix+'/assets/');await fs.writeFile(path.join(versioned,name.replace(/\.mjs$/,'.js')),body);}
let html=await fs.readFile(path.join(source,'index.html'),'utf8');html=html.replace('<head>','<head>\n  <meta name="pina-runtime" content="live">').replace('<meta name="robots" content="noindex,nofollow">','').replace('Интерактивный прототип.','').replace('Реальные платежи здесь не принимаются.','').replace('Для интерактивного прототипа','Для личного кабинета').replace('  <script src="./assets/jszip.min.js"></script>\n','').replace('src="./app.js"','src="./live-app.js"').replaceAll('./assets/',prefix+'/assets/').replace('href="./style.css"','href="'+prefix+'/style.css"').replace('src="./live-app.js"','src="'+prefix+'/live-app.js"');await fs.writeFile(path.join(out,'index.html'),html);
// Private shell is copied into each immutable PHP release by the existing installer.
await fs.writeFile(path.join(root,'app/giftcards-shell.html'),html);
await fs.writeFile(path.join(root,'app/giftcards-assets.json'),JSON.stringify({prefix,entry:prefix+'/live-app.js',style:prefix+'/style.css'},null,2)+'\n');
console.log('Built production gift-card assets. Node is not required on the hosting server.');
