const sharp = require('sharp');
const path = require('path');

const SOURCE = 'C:\\Users\\IBK\\.cursor\\projects\\c-Users-My-project-News\\assets\\c__Users_IBK_AppData_Roaming_Cursor_User_workspaceStorage_29de6e15b72cd38892fa78988709e3f0_images_1000035550-5af2d156-e5a7-4b34-8835-0603a540b1a6.png';
const OUT = path.resolve(__dirname, '..', 'public', 'the-gist-logo.jpg');

sharp(SOURCE)
  .jpeg({ quality: 95 })
  .toFile(OUT)
  .then(() => console.log('Created:', OUT))
  .catch((e) => { console.error(e); process.exit(1); });
