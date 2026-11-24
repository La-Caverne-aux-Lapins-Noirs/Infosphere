// Run after INFOSPHERE_KEEP_VIDEO_FIXTURES=1 php tests/support_video_tracks.php:
// node tests/support_video_tracks_browser.mjs /tmp/infosphere_video_...
// Requires Playwright and Chromium; no live Infosphère/database is contacted.
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import { resolve, dirname, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';
import { spawnSync } from 'node:child_process';
const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const fixtures = resolve(process.argv[2] || 'missing-fixture-directory');
const php = process.env.INFOSPHERE_TEST_PHP || 'php';
const rendered = {};
for (const name of ['multipanel', 'send_ajax']) {
    const result = spawnSync(php, ['-n', '-r',
        '$Dictionnary=["DownloadFile"=>"Télécharger","ServerReturnErrorStatus"=>"Erreur serveur"]; $User=["id"=>1]; $Position="support"; include $argv[1];',
        resolve(root, 'script', `${name}.js`)], {encoding: 'utf8'});
    assert.equal(result.status, 0, result.stderr);
    rendered[`/script/${name}.js`] = result.stdout;
}
const html = `<!doctype html><html lang="fr"><meta charset="utf-8">
<link rel="stylesheet" href="/style/structure/support.css">
<style>body{background:#222;color:white;font:16px sans-serif}.support_screen{width:900px;height:520px;background:black}video{width:100%;height:100%}</style>
<div id="screen" class="support_screen"><video class="screen_media" controls preload="metadata" data-support-can-upload="1"><source></video></div>
<script>function set_error_div(message){window.lastError=message}</script>
<script src="/script/ext/hls.min.js"></script><script src="/script/send_ajax.js"></script>
<script src="/script/multipanel.js"></script><script src="/script/support_video_tracks.js"></script>
<p><button onclick="testMovie('mkv')">MKV</button> <button onclick="testMovie('mp4')">MP4</button>
<button onclick="testMovie('silent')">Vidéo sans audio</button> <button onclick="testMovie('mkv','/legacy/')">Ancien HLS</button>
<button onclick="testMovie('mkv','/slow/');testMovie('silent')">Changement rapide</button>
<button onclick="support_video_play(document.querySelector('video'),'/assets/mp4.mp4','')">MP4 direct</button>
<button onclick="send_ajax('POST','/too-large',null)">Test HTTP 413</button>
<button onclick="let v=document.querySelector('video');v.currentTime=2;v.pause()">Pause à 2 s</button></p>
<pre id="test_status"></pre><script>
function testMovie(name,prefix='/assets/'){support_video_play(document.querySelector('video'),'/assets/'+name+'.mp4',prefix+name+'.hls/index.m3u8')}
setInterval(function(){let v=document.querySelector('video'),h=v._infosphere_hls;document.getElementById('test_status').textContent=JSON.stringify({time:v.currentTime,audio:h?h.audioTrack:null,audioCount:h?h.audioTracks.length:0,tracks:Array.from(v.textTracks).map(t=>({label:t.label,mode:t.mode,cues:Array.from(t.activeCues||[]).map(c=>c.text)})),controls:document.querySelectorAll('.support_video_controls').length,error:window.lastError||'',warnings:document.querySelector('.support_video_track_warning')?.textContent||''},null,2)},200)
</script></html>`;
const server = createServer(async (request, response) => {
    try {
        let path = decodeURIComponent(new URL(request.url, 'http://localhost').pathname);
        if (path == '/') {
            response.setHeader('Content-Type', 'text/html; charset=utf-8');
            return response.end(html);
        }
        if (path == '/too-large') {
            response.writeHead(413, {'Content-Type': 'text/html'});
            return response.end('<html>Request too large</html>');
        }
        if (rendered[path]) {
            response.setHeader('Content-Type', 'application/javascript; charset=utf-8');
            return response.end(rendered[path]);
        }
        if (path.startsWith('/legacy/')) {
            if (path.endsWith('/tracks.json')) {
                response.writeHead(404);
                return response.end();
            }
            path = path.replace('/legacy/', '/assets/');
        }
        if (path.startsWith('/slow/')) {
            if (path.endsWith('/tracks.json'))
                await new Promise(resolve => setTimeout(resolve, 600));
            path = path.replace('/slow/', '/assets/');
        }
        const base = path.startsWith('/assets/') ? fixtures : root;
        const file = resolve(base, '.' + path);
        if (!file.startsWith(base + sep))
            throw new Error('invalid path');
        const content = await readFile(file);
        const types = {m3u8: 'application/vnd.apple.mpegurl', m4s: 'video/iso.segment', mp4: 'video/mp4',
            vtt: 'text/vtt; charset=utf-8', json: 'application/json', js: 'application/javascript', css: 'text/css'};
        response.setHeader('Content-Type', types[file.split('.').pop()] || 'application/octet-stream');
        response.setHeader('Accept-Ranges', 'bytes');
        const range = /^bytes=(\d+)-(\d*)$/.exec(request.headers.range || '');
        if (range) {
            const start = Number(range[1]);
            const end = range[2] ? Math.min(Number(range[2]), content.length - 1) : content.length - 1;
            response.writeHead(206, {'Content-Range': `bytes ${start}-${end}/${content.length}`,
                'Content-Length': end - start + 1});
            return response.end(content.subarray(start, end + 1));
        }
        response.setHeader('Content-Length', content.length);
        response.end(content);
    } catch {
        response.writeHead(404);
        response.end();
    }
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
if (process.env.INFOSPHERE_VIDEO_SERVER_ONLY) {
    console.log(`Browser test server: http://127.0.0.1:${server.address().port}/`);
    await new Promise(() => {});
}
const browser = await chromium.launch({headless: true, executablePath: process.env.CHROMIUM || '/usr/bin/chromium',
    args: ['--no-sandbox', '--autoplay-policy=no-user-gesture-required']});
const page = await browser.newPage({viewport: {width: 1100, height: 720}});
const errors = [];
page.on('pageerror', error => errors.push(error.message));
try {
    await page.goto(`http://127.0.0.1:${server.address().port}/`);
    const play = async (name, prefix = '/assets/') => {
        await page.evaluate(({name, prefix}) => support_video_play(document.querySelector('video'),
            `/assets/${name}.mp4`, `${prefix}${name}.hls/index.m3u8`), {name, prefix});
    };
    await play('mkv');
    await page.waitForFunction(() => document.querySelector('[aria-label="Piste audio"]').options.length == 2);
    await page.waitForFunction(() => document.querySelector('[aria-label="Sous-titres"]').options.length == 3);
    await page.waitForFunction(() => document.querySelector('video').currentTime > 0.5);
    await page.evaluate(() => {const video = document.querySelector('video'); video.pause(); video.currentTime = 2;});
    await page.locator('[aria-label="Piste audio"]').selectOption('1');
    await page.waitForFunction(() => document.querySelector('video')._infosphere_hls.audioTrack == 1);
    assert.ok(await page.evaluate(() => document.querySelector('video').currentTime > 1.8), 'Audio switch reset playback position');
    await page.locator('[aria-label="Sous-titres"]').selectOption('1');
    await page.waitForFunction(() => Array.from(document.querySelector('video').textTracks).some(track =>
        track.mode == 'showing' && Array.from(track.activeCues || []).some(cue => cue.text.includes('Hello captain'))));
    await page.locator('[aria-label="Sous-titres"]').selectOption('-1');
    assert.ok(await page.evaluate(() => Array.from(document.querySelector('video').textTracks).every(track => track.mode != 'showing')));
    await page.locator('input[type="file"]').setInputFiles({name: 'local.srt', mimeType: 'application/x-subrip',
        buffer: Buffer.from('1\n00:00:00,000 --> 00:00:05,500\nSous-titres locaux\n\n')});
    await page.waitForFunction(() => document.querySelector('[aria-label="Sous-titres"]').selectedOptions[0].text == 'local.srt');
    await play('mp4');
    await page.waitForFunction(() => document.querySelector('[aria-label="Sous-titres"]').options.length == 3);
    assert.equal(await page.locator('.support_video_controls').count(), 1);
    assert.ok(!(await page.locator('[aria-label="Sous-titres"]').innerText()).includes('local.srt'));
    await play('mkv', '/legacy/');
    await page.waitForFunction(() => document.querySelector('video').currentTime > 0.2);
    assert.equal(await page.locator('[aria-label="Sous-titres"] option').count(), 1);
    await play('mkv', '/slow/');
    await play('silent');
    await page.waitForFunction(() => document.querySelector('video').currentTime > 0.2);
    await page.waitForTimeout(800);
    assert.equal(await page.locator('[aria-label="Sous-titres"] option').count(), 1, 'Old request populated the next movie');
    assert.equal(await page.locator('[aria-label="Piste audio"]').isVisible(), false);
    await page.evaluate(() => support_video_play(document.querySelector('video'), '/assets/mp4.mp4', ''));
    await page.waitForFunction(() => document.querySelector('video').currentTime > 0.2);
    await page.evaluate(() => send_ajax('POST', '/too-large', null));
    await page.waitForFunction(() => window.lastError && window.lastError.includes('taille maximale'));
    await page.evaluate(() => document.querySelector('video').setAttribute('data-support-can-upload', '0'));
    await play('mkv');
    await page.waitForFunction(() => document.querySelector('[aria-label="Sous-titres"]').options.length == 3);
    assert.equal(await page.locator('input[type="file"]').count(), 0, 'Readers must not see the subtitle loader');
    assert.equal(await page.locator('[aria-label="Piste audio"] option').count(), 2);
    await page.evaluate(() => document.querySelector('video').setAttribute('data-support-can-upload', '1'));
    await play('mkv');
    await page.waitForFunction(() => document.querySelector('[aria-label="Sous-titres"]').options.length == 3);
    if (process.env.INFOSPHERE_VIDEO_SCREENSHOT)
        await page.screenshot({path: process.env.INFOSPHERE_VIDEO_SCREENSHOT});
    assert.deepEqual(errors, [], 'Browser JavaScript errors');
    console.log('OK: HLS playback, audio switching, visible subtitle cues, subtitles off, local SRT, asset switching, legacy HLS, stale requests, silent video, MP4 fallback, HTTP 413 and subtitle-loader permissions.');
} finally {
    await browser.close();
    server.closeAllConnections();
    await new Promise(resolve => server.close(resolve));
}
