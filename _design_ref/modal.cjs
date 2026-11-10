const fs=require('fs');
const j=JSON.parse(fs.readFileSync('D:/eyal/_design_ref/captures/modal_student_add.json','utf8'));
let h=j.html.replace(/\sdata-yw(?:-s|-t|-auto)?="[^"]*"/g,'').replace(/>\s+</g,'><').replace(/></g,'>\n<');
fs.writeFileSync('D:/eyal/_design_ref/captures/modal_student_add.html',h);
console.log(h.slice(-3000));
