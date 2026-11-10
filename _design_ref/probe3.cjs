const fs=require('fs');
const s=fs.readFileSync('D:/eyal/_design_ref/app.js','utf8');
let i=s.indexOf('path:"/admin",element');
console.log('ROUTES:', s.slice(i, i+1500));
for(const k of ['Zx=','Wx=','function Zx','const Zx']){
  let j=s.indexOf(k);
  console.log('===',k,j);
  if(j>=0) console.log(s.slice(j, j+3000));
}
