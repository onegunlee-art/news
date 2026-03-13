/**
 * Lobster 폰트로 "G"를 그려 path로 변환한 뒤 favicon-G.svg 생성.
 * 실행 후 node generate-G.js 로 PNG/ICO 생성.
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
const FONT_SIZE = 92;

async function getFontBuffer() {
  if (fs.existsSync(LOCAL_FONT)) {
    return fs.readFileSync(LOCAL_FONT);
  }
  const res = await fetch(LOBSTER_TTF_URL, { redirect: 'follow' });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  const ab = await res.arrayBuffer();
  return Buffer.from(ab);
}

async function main() {
  const fontBuffer = await getFontBuffer();
  const font = opentype.parse(fontBuffer.buffer);

  // getPath(text, x, y, fontSize) - y는 baseline
  const path = font.getPath('G', 0, 0, FONT_SIZE);
  const bbox = path.getBoundingBox();
  const pathData = path.toPathData(2);

  // SVG는 y가 아래로 증가. opentype은 y가 위로 증가하므로 path를 상하 반전 후 배치
  const w = bbox.x2 - bbox.x1;
  const h = bbox.y2 - bbox.y1;
  const cx = (bbox.x1 + bbox.x2) / 2;
  const cy = (bbox.y1 + bbox.y2) / 2;
  // 중앙을 (VIEW_SIZE/2, VIEW_SIZE/2)에 맞추고, y 반전
  const tx = VIEW_SIZE / 2 - cx;
  const ty = VIEW_SIZE / 2 + cy;

  const svg = `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${VIEW_SIZE} ${VIEW_SIZE}">
  <rect width="${VIEW_SIZE}" height="${VIEW_SIZE}" fill="#ffffff"/>
  <g transform="translate(${tx}, ${ty}) scale(1, -1)">
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
