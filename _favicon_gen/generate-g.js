const sharp = require('sharp');
const fs = require('fs');
const path = require('path');

const SOURCE = 'C:\\Users\\IBK\\.cursor\\projects\\c-Users-My-project-News\\assets\\favicon-g-master.png';
const OUT = path.resolve(__dirname, '..', 'public');

async function main() {
  const sizes = [16, 32, 192, 512];

  for (const s of sizes) {
    await sharp(SOURCE)
      .resize(s, s, { fit: 'contain', background: { r: 255, g: 255, b: 255, alpha: 1 } })
      .png()
      .toFile(path.join(OUT, `favicon-${s}.png`));
    console.log(`Created: favicon-${s}.png`);
  }

  await sharp(SOURCE)
    .resize(180, 180, { fit: 'contain', background: { r: 255, g: 255, b: 255, alpha: 1 } })
    .png()
    .toFile(path.join(OUT, 'apple-touch-icon.png'));
  console.log('Created: apple-touch-icon.png');

  const buf32 = fs.readFileSync(path.join(OUT, 'favicon-32.png'));
  const buf16 = fs.readFileSync(path.join(OUT, 'favicon-16.png'));
  const icoSizes = [16, 32];
  const numImages = 2;
  const headerSize = 6;
  const entrySize = 16;
  let offset = headerSize + entrySize * numImages;
  const ico = Buffer.alloc(offset + buf16.length + buf32.length);
  ico.writeUInt16LE(0, 0);
  ico.writeUInt16LE(1, 2);
  ico.writeUInt16LE(numImages, 4);

  for (let i = 0; i < numImages; i++) {
    const buf = i === 0 ? buf16 : buf32;
    const pos = headerSize + i * entrySize;
    const s = icoSizes[i];
    ico.writeUInt8(s, pos);
    ico.writeUInt8(s, pos + 1);
    ico.writeUInt8(0, pos + 2);
    ico.writeUInt8(0, pos + 3);
    ico.writeUInt16LE(1, pos + 4);
    ico.writeUInt16LE(32, pos + 6);
    ico.writeUInt32LE(buf.length, pos + 8);
    ico.writeUInt32LE(offset, pos + 12);
    buf.copy(ico, offset);
    offset += buf.length;
  }
  fs.writeFileSync(path.join(OUT, 'favicon.ico'), ico);
  console.log('Created: favicon.ico');

  const masterDest = path.join(OUT, '파비콘.png');
  fs.copyFileSync(SOURCE, masterDest);
  console.log('Created: 파비콘.png (master)');
}

main().catch(e => { console.error(e); process.exit(1); });
