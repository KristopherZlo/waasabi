import assert from 'node:assert/strict';
import {spawn, spawnSync} from 'node:child_process';
import {copyFile, mkdir, mkdtemp, readFile, rm, writeFile} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {dirname, join} from 'node:path';
import {setTimeout as delay} from 'node:timers/promises';
import {fileURLToPath} from 'node:url';

const root = fileURLToPath(new URL('../', import.meta.url));
const output = process.env.SCREENSHOT_DIR || join(root, 'docs', 'screenshots');
const baseUrl = process.env.WAASABI_URL || 'http://127.0.0.1:8081';
const views = [
    ['/', '.work-card .work-image', 'feed.png'],
    ['/projects/night-bus-photo-essay?tab=discussion', '.discussion .comment', 'work.png'],
    ['/projects/open-source-looper-pedal?tab=updates', '.journal-entry', 'project.png'],
    ['/collaboration', '.job-opening', 'collaborations.png'],
    ['/profile/vera-kim', '.profile-page', 'profile.png'],
];
const defaultChrome = process.platform === 'win32'
    ? 'C:/Program Files/Google/Chrome/Application/chrome.exe'
    : '/usr/bin/google-chrome';
const profile = await mkdtemp(join(tmpdir(), 'waasabi-readme-'));
const browser = spawn(process.env.CHROME_PATH || defaultChrome, [
    '--headless=new', '--disable-gpu', '--no-first-run', '--no-sandbox', '--remote-debugging-port=0',
    `--user-data-dir=${profile}`, 'about:blank',
], {windowsHide: true, stdio: 'ignore'});

let socket;
let sequence = 0;
const pending = new Map();
async function until(check) {
    const deadline = Date.now() + 20000;
    while (Date.now() < deadline) {
        const result = await check();
        if (result) return result;
        await delay(100);
    }
    throw new Error('Browser check timed out');
}
function cdp(method, params = {}) {
    const id = ++sequence;
    return new Promise((resolve, reject) => {
        const timeout = setTimeout(() => {pending.delete(id); reject(new Error(`Timed out: ${method}`));}, 15000);
        pending.set(id, {resolve: value => {clearTimeout(timeout); resolve(value);}, reject: error => {clearTimeout(timeout); reject(error);}});
        socket.send(JSON.stringify({id, method, params}));
    });
}
async function evaluate(expression) {
    const response = await cdp('Runtime.evaluate', {expression, returnByValue: true, awaitPromise: true});
    assert.ok(!response.exceptionDetails, JSON.stringify(response.exceptionDetails));
    return response.result.value;
}
async function capture(path, selector, name) {
    await cdp('Page.navigate', {url: `${baseUrl}${path}`});
    await until(() => evaluate(`document.readyState === 'complete' && !!document.querySelector(${JSON.stringify(selector)})`));
    await evaluate('document.fonts.ready.then(() => true)');
    await evaluate(`(() => {
        scrollTo(0, 0);
        const style = document.createElement('style');
        style.textContent = '* { animation: none !important; transition: none !important; } html { scrollbar-width: none; } ::-webkit-scrollbar { display: none; }';
        document.head.append(style);
        return true;
    })()`);
    await until(() => evaluate(`Array.from(document.images).filter(image => {
        const box = image.getBoundingClientRect();
        return box.width > 0 && box.height > 0 && box.top < innerHeight && box.bottom > 0;
    }).every(image => image.complete && image.naturalWidth > 0)`));
    await delay(300);
    const {data} = await cdp('Page.captureScreenshot', {format: 'png', fromSurface: true});
    await writeFile(join(output, name), Buffer.from(data, 'base64'));
}

try {
    await mkdir(output, {recursive: true});
    await until(async () => {
        try {return (await fetch(baseUrl)).ok;} catch {return false;}
    });
    const port = await until(async () => {
        try {return (await readFile(join(profile, 'DevToolsActivePort'), 'utf8')).split('\n')[0];} catch {return false;}
    });
    const targets = await (await fetch(`http://127.0.0.1:${port}/json`)).json();
    socket = new WebSocket(targets.find(target => target.type === 'page').webSocketDebuggerUrl);
    await new Promise((resolve, reject) => {socket.addEventListener('open', resolve, {once: true}); socket.addEventListener('error', reject, {once: true});});
    socket.addEventListener('message', event => {
        const data = JSON.parse(event.data);
        const request = pending.get(data.id);
        if (request) {pending.delete(data.id); data.error ? request.reject(new Error(data.error.message)) : request.resolve(data.result);}
    });
    await cdp('Emulation.setDeviceMetricsOverride', {width: 1440, height: 1080, deviceScaleFactor: 1, mobile: false});
    for (const [index, [path, selector, name]] of views.entries()) {
        await capture(path, selector, name);
        if (index === 0) {
            assert.equal(await evaluate('document.querySelector(".work-card h2").textContent'), 'Night bus, after dark', 'Seed the README database with ReadmeScreenshotSeeder before capture');
        }
        await copyFile(join(output, name), join(profile, `frame-${String(index).padStart(2, '0')}.png`));
    }
    const gif = spawnSync(process.env.FFMPEG_PATH || 'ffmpeg', [
        '-hide_banner', '-loglevel', 'error', '-y', '-framerate', '1',
        '-i', join(profile, 'frame-%02d.png'),
        '-filter_complex', 'scale=1200:-2:flags=lanczos,split[frames][colors];[colors]palettegen[palette];[frames][palette]paletteuse=dither=bayer:bayer_scale=3',
        '-loop', '0', '-final_delay', '100', join(output, 'overview.gif'),
    ], {windowsHide: true, encoding: 'utf8'});
    if (gif.error) throw new Error('Install FFmpeg or set FFMPEG_PATH to create the README GIF', {cause: gif.error});
    assert.equal(gif.status, 0, gif.stderr);
    console.log(`README screenshots and five-frame GIF saved to ${output}`);
} finally {
    if (socket?.readyState === WebSocket.OPEN) {await cdp('Browser.close').catch(() => {}); socket.close();}
    browser.kill();
    await delay(300);
    assert.equal(dirname(profile), tmpdir());
    await rm(profile, {recursive: true, force: true, maxRetries: 5, retryDelay: 200});
}
