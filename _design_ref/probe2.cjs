const fs=require('fs');
const s=fs.readFileSync('D:/eyal/_design_ref/app.js','utf8');
for(const k of ['path:"/student"','path:"/parent"','path:"/admin/settings"','path:"/stages"']){
  let i=s.indexOf(k);
  console.log('===',k,i);
  if(i>=0) console.log(s.slice(i-300, i+500));
}
