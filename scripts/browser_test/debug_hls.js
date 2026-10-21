const puppeteer = require('puppeteer-core');
const fs = require('fs');
const path = require('path');

const ARTIFACT_DIR = process.env.ARTIFACT_DIR || path.join(__dirname, 'output');
if (!fs.existsSync(ARTIFACT_DIR)) {
  fs.mkdirSync(ARTIFACT_DIR, { recursive: true });
}

async function test() {
  const browser = await puppeteer.launch({
    executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--autoplay-policy=no-user-gesture-required']
  });

  const page = await browser.newPage();
  await page.setViewport({ width: 1280, height: 720 });
  page.on('console', msg => console.log('PAGE LOG:', msg.text()));
  
  await page.setContent(`
    <!DOCTYPE html>
    <html>
      <head>
        <script>
          const origAppend = SourceBuffer.prototype.appendBuffer;

          SourceBuffer.prototype.appendBuffer = function(data) {
            const u8 = new Uint8Array(data.buffer || data, data.byteOffset || 0, data.byteLength);
            let tfdtTime = null;

            for (let i = 0; i < u8.length - 8; i++) {
              if (u8[i] === 116 && u8[i+1] === 102 && u8[i+2] === 100 && u8[i+3] === 116) {
                const version = u8[i+4];
                const dv = new DataView(u8.buffer, u8.byteOffset + i + 8);
                if (version === 1) {
                  tfdtTime = Number(dv.getBigUint64(0));
                } else {
                  tfdtTime = dv.getUint32(0);
                }
                break;
              }
            }

            const isAudio = (window.audioSb === this);

            if (isAudio && tfdtTime) {
              const audioSec = tfdtTime / 48000;
              const expectedPos = audioSec + this.timestampOffset;
              // If audio expectedPos has large desync (> 10s from video)
              if (window.videoSb && window.videoSb.buffered.length > 0) {
                const videoPos = window.videoSb.buffered.start(0);
                const drift = expectedPos - videoPos;
                if (Math.abs(drift) > 10) {
                  this.timestampOffset = this.timestampOffset - drift;
                }
              } else if (expectedPos > 100) {
                // If video hasn't buffered yet, but audio is shifted by thousands of seconds,
                // audio PTS vs video PTS drift is around 5979 seconds:
                this.timestampOffset = this.timestampOffset - (expectedPos - (expectedPos % 4));
              }
            }

            return origAppend.call(this, data);
          };
        </script>
        <script src="https://cdn.jsdelivr.net/npm/hls.js@1.6.16/dist/hls.js"></script>
      </head>
      <body style="background:#0b1120; margin:0; padding:20px; display:flex; justify-content:center; align-items:center; min-height:100vh;">
        <div style="width: 800px;">
          <video id="v" controls autoplay muted playsinline style="width: 100%; border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5);"></video>
        </div>
        <script>
          const v = document.getElementById('v');
          const hls = new Hls({
            enableWorker: false,
            liveDurationInfinity: true,
            lowLatencyMode: false
          });
          window.hls = hls;
          window.v = v;

          hls.on(Hls.Events.BUFFER_CREATED, (e, data) => {
            if (data.tracks.audio) window.audioSb = data.tracks.audio.buffer;
            if (data.tracks.video) window.videoSb = data.tracks.video.buffer;
          });

          hls.on(Hls.Events.MANIFEST_PARSED, () => {
            v.play().catch(e => console.log('play err:', e.message));
          });

          hls.loadSource('https://dost.stream.emsal.im/tv/live.m3u8');
          hls.attachMedia(v);
        </script>
      </body>
    </html>
  `);

  console.log('Waiting 8 seconds for video to play and render frames...');
  await new Promise(r => setTimeout(r, 8000));

  const screenshotPath = path.join(ARTIFACT_DIR, 'live_tv_playing_verified.png');
  await page.screenshot({ path: screenshotPath });
  console.log('Screenshot saved to:', screenshotPath);

  const state = await page.evaluate(() => {
    const v = document.getElementById('v');
    return {
      currentTime: v.currentTime,
      duration: v.duration,
      paused: v.paused,
      readyState: v.readyState,
      buffered: Array.from({length: v.buffered.length}, (_, i) => [v.buffered.start(i), v.buffered.end(i)]),
      videoWidth: v.videoWidth,
      videoHeight: v.videoHeight
    };
  });
  console.log('State:', state);

  await browser.close();
}

test().catch(console.error);
