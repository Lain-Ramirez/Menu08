// Lee el PNG real descargado por CP-CARTA-13.
const fs=require('fs');
const path=require('path');
const modules=fs.existsSync(path.resolve(__dirname,'../node_modules/@zxing/library')) ? path.resolve(__dirname,'../node_modules') : '/tmp/menu08-qa-runtime/newman/node_modules';
const zxing=require(path.join(modules,'@zxing/library'));
const {PNG}=require(path.join(modules,'pngjs'));
const im=PNG.sync.read(fs.readFileSync(process.argv[2]));
const rgba=new Uint8ClampedArray(im.data);
const source=new zxing.RGBLuminanceSource(Int32Array.from({length:im.width*im.height},(_,i)=>(rgba[i*4]<<16)|(rgba[i*4+1]<<8)|rgba[i*4+2]),im.width,im.height);
const bin=new zxing.BinaryBitmap(new zxing.HybridBinarizer(source));
console.log(new zxing.QRCodeReader().decode(bin).getText());
