/**
 * Lobster 폰트로 "g."를 그려 path로 변환한 뒤 favicon-G.svg 생성.
 * 실행: node generate-G-lobster.js && node generate-g.js
 */
const opentype = require('opentype.js');
const fs = require('fs');
const path = require('path');

const LOBSTER_TTF_URL = 'https://github.com/google/fonts/raw/main/ofl/lobster/Lobster-Regular.ttf';
const OUT_DIR = path.resolve(__dirname, '..', 'public');
const FONTS_DIR = path.resolve(__dirname, 'fonts');
const LOCAL_FONT = path.join(FONTS_DIR, 'Lobster-Regular.ttf');
const SVG_PATH = path.join(OUT_DIR, 'favicon-G.svg');
const VIEW_SIZE = 100;
const FONT_SIZE = 82;

async function getFontBuffer() {
  if (fs.existsSync(LOCAL_FONT)) {
    return fs.readFileSync(LOCAL_FONT);
  }
  fs.mkdirSync(FONTS_DIR, { recursive: true });
  const res = await fetch(LOBSTER_TTF_URL, { redirect: 'follow' });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  const ab = await res.arrayBuffer();
  const buf = Buffer.from(ab);
  fs.writeFileSync(LOCAL_FONT, buf);
  return buf;
}

async function main() {
  const fontBuffer = await getFontBuffer();
  const font = opentype.parse(fontBuffer.buffer);

  // opentype.js: y축이 위로 증가 (baseline=0, ascender 양수, descender 음수)
  // SVG: y축이 아래로 증가
  // baseline을 y=FONT_SIZE 위치에 놓으면 정상 방향으로 렌더링됨 (반전 불필요)
  const glyphPath = font.getPath('g.', 0, FONT_SIZE, FONT_SIZE);
  const bbox = glyphPath.getBoundingBox();
  const pathData = glyphPath.toPathData(2);

  const glyphW = bbox.x2 - bbox.x1;
  const glyphH = bbox.y2 - bbox.y1;
  const tx = (VIEW_SIZE - glyphW) / 2 - bbox.x1;
  const ty = (VIEW_SIZE - glyphH) / 2 - bbox.y1;

  const svg = `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${VIEW_SIZE} ${VIEW_SIZE}">
  <rect width="${VIEW_SIZE}" height="${VIEW_SIZE}" fill="#ffffff"/>
  <g transform="translate(${tx}, ${ty})">
    <path d="${pathData}" fill="#000000"/>
  </g>
</svg>
`;

  fs.writeFileSync(SVG_PATH, svg, 'utf8');
  console.log('Created:', SVG_PATH);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
