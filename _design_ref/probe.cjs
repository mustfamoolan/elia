const fs=require('fs');
const s=fs.readFileSync('D:/eyal/_design_ref/app.js','utf8');
const keys=['لوحة الطالب','مرحباً','studentSection','src/pages/Student','src/pages/student','Qp={','settings:'];
for(const k of keys){
  let i=s.indexOf(k);
  console.log('===',k,i);
  if(i>=0) console.log(s.slice(Math.max(0,i-400), i+1200).replace(/\n/g,' '));
}
