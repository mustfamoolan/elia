const fs=require('fs'),path=require('path');
const dir='D:/eyal/_design_ref/captures';
for(const f of fs.readdirSync(dir)){
  if(!f.endsWith('.json'))continue;
  const j=JSON.parse(fs.readFileSync(path.join(dir,f),'utf8'));
  let h=j.html||'';
  h=h.replace(/\sdata-yw(?:-s|-t|-auto)?="[^"]*"/g,'');
  h=h.replace(/>\s+</g,'><');
  h=h.replace(/></g,'>\n<');
  const name=f.replace(/\.json$/,'');
  fs.writeFileSync(path.join(dir,name+'.html'),h);
  fs.writeFileSync(path.join(dir,name+'.txt'),j.text||'');
  console.log(name, h.length, (j.text||'').length);
}
