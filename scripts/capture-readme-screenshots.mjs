import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {mkdir, mkdtemp, readFile, rm, writeFile} from 'node:fs/promises';
import {tmpdir} from 'node:os';
import {join} from 'node:path';
import {setTimeout as delay} from 'node:timers/promises';
import {fileURLToPath} from 'node:url';

const root = fileURLToPath(new URL('../', import.meta.url));
const output = process.env.SCREENSHOT_DIR || join(root, 'docs', 'screenshots');
const baseUrl = process.env.WAASABI_URL || 'http://127.0.0.1:8081';
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
        style.textContent = '* { animation: none !important; transition: none !important; }';
        document.head.append(style);
        return true;
    })()`);
    await delay(500);
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
    await cdp('Emulation.setDeviceMetricsOverride', {width: 1440, height: 960, deviceScaleFactor: 1, mobile: false});
    await capture('/', '.work-card', 'feed.png');
    await capture('/projects/fast-breakdown', '.work-page', 'work.png');
    await capture('/collaboration', '.job-opening', 'collaborations.png');
    await capture('/profile/dasha-n', '.profile-page', 'profile.png');
    console.log(`README screenshots saved to ${output}`);
} finally {
    if (socket?.readyState === WebSocket.OPEN) {await cdp('Browser.close').catch(() => {}); socket.close();}
    browser.kill();
    await delay(300);
    await rm(profile, {recursive: true, force: true, maxRetries: 5, retryDelay: 200});
}
