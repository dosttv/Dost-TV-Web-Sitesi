const puppeteer = require('puppeteer-core');
const fs = require('fs');
const path = require('path');

const sleep = ms => new Promise(r => setTimeout(r, ms));
const ARTIFACT_DIR = process.env.ARTIFACT_DIR || path.join(__dirname, 'output');
if (!fs.existsSync(ARTIFACT_DIR)) {
  fs.mkdirSync(ARTIFACT_DIR, { recursive: true });
}

async function main() {
  console.log('--- INSPECTING CANLI TV ON YENI.DOSTTV.COM ---');

  const browser = await puppeteer.launch({
    executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });

  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 900 });

  const consoleLogs = [];
  page.on('console', msg => {
    consoleLogs.push({ type: msg.type(), text: msg.text() });
    console.log(`[BROWSER CONSOLE ${msg.type().toUpperCase()}]:`, msg.text());
  });

  page.on('pageerror', err => {
    console.error('[BROWSER PAGE ERROR]:', err.toString());
  });

  const networkRequests = [];
  page.on('requestfailed', request => {
    console.log(`[NETWORK FAILED]: ${request.method()} ${request.url()} - ${request.failure()?.errorText}`);
  });

  page.on('response', response => {
    const url = response.url();
    if (url.includes('.m3u8') || url.includes('.ts') || url.includes('live-tv') || url.includes('stream') || response.status() >= 400) {
      console.log(`[NETWORK RESPONSE]: ${response.status()} ${url}`);
    }
  });

  console.log('Navigating to https://yeni.dosttv.com/canli-tv...');
  await page.goto('https://yeni.dosttv.com/canli-tv', { waitUntil: 'networkidle2' });
  await sleep(4000);

  // Inspect video element
  const videoState = await page.evaluate(() => {
    const v = document.getElementById('hls-player');
    const errCont = document.getElementById('hls-error-container');
    const errTxt = document.getElementById('hls-error-text');
    if (!v) return { exists: false };
    return {
      exists: true,
      currentSrc: v.currentSrc,
      src: v.src,
      dataSrc: v.dataset.src,
      dataBackupSrc: v.dataset.backupSrc,
      duration: v.duration,
      currentTime: v.currentTime,
      paused: v.paused,
      muted: v.muted,
      readyState: v.readyState,
      networkState: v.networkState,
      videoWidth: v.videoWidth,
      videoHeight: v.videoHeight,
      error: v.error ? { code: v.error.code, message: v.error.message } : null,
      errorContainerVisible: errCont ? !errCont.classList.contains('hidden') : false,
      errorText: errTxt ? errTxt.innerText : null
    };
  });

  console.log('Video State:', JSON.stringify(videoState, null, 2));

  // Try to play if paused
  console.log('Attempting to trigger play()...');
  const playResult = await page.evaluate(async () => {
    const v = document.getElementById('hls-player');
    if (!v) return 'No video element';
    try {
      v.muted = true;
      await v.play();
      return 'play() succeeded';
    } catch (e) {
      return 'play() error: ' + e.toString();
    }
  });
  console.log('Play Result:', playResult);

  await sleep(3000);

  const updatedVideoState = await page.evaluate(() => {
    const v = document.getElementById('hls-player');
    if (!v) return null;
    return {
      duration: v.duration,
      currentTime: v.currentTime,
      paused: v.paused,
      readyState: v.readyState,
      networkState: v.networkState,
      videoWidth: v.videoWidth,
      videoHeight: v.videoHeight,
    };
  });
  console.log('Updated Video State:', JSON.stringify(updatedVideoState, null, 2));

  const screenshotPath = path.join(ARTIFACT_DIR, 'live_tv_inspect.png');
  await page.screenshot({ path: screenshotPath });
  console.log('Screenshot saved to:', screenshotPath);

  await browser.close();
}

main().catch(err => {
  console.error('Inspect error:', err);
  process.exit(1);
});
