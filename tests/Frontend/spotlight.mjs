// Run against a populated local site: node --experimental-websocket tests/Frontend/spotlight.mjs
// CHROME_PATH and WAASABI_URL override the local defaults. No browser packages required.
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { mkdtemp, readFile, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { setTimeout as delay } from 'node:timers/promises';

const profile = await mkdtemp(join(tmpdir(), 'waasabi-search-'));
const browser = spawn(process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', [
    '--headless=new', '--disable-gpu', '--no-first-run', '--remote-debugging-port=0', `--user-data-dir=${profile}`, 'about:blank',
], {windowsHide: true, stdio: 'ignore'});
let socket;
let sequence = 0;
const pending = new Map();
async function until(check) {
    const deadline = Date.now() + 15000;
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
        const timeout = setTimeout(() => {pending.delete(id); reject(new Error(`Timed out: ${method}`));}, 10000);
        pending.set(id, {resolve: value => {clearTimeout(timeout); resolve(value);}, reject: error => {clearTimeout(timeout); reject(error);}});
        socket.send(JSON.stringify({id, method, params}));
    });
}
async function evaluate(expression) {
    const response = await cdp('Runtime.evaluate', {expression, returnByValue: true, awaitPromise: true});
    assert.ok(!response.exceptionDetails, JSON.stringify(response.exceptionDetails));
    return response.result.value;
}
async function key(key, modifiers = 0) {
    const windowsVirtualKeyCode = {Escape: 27, Enter: 13, ArrowDown: 40, ArrowUp: 38, k: 75}[key];
    await cdp('Input.dispatchKeyEvent', {type: 'keyDown', key, modifiers, windowsVirtualKeyCode});
    await cdp('Input.dispatchKeyEvent', {type: 'keyUp', key, modifiers, windowsVirtualKeyCode});
}
async function screenshot(name) {
    if (!process.env.QA_SCREENSHOT_DIR) return;
    const {data} = await cdp('Page.captureScreenshot', {format: 'png'});
    await writeFile(join(process.env.QA_SCREENSHOT_DIR, name), Buffer.from(data, 'base64'));
}

try {
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
    await cdp('Emulation.setDeviceMetricsOverride', {width: 1440, height: 1000, deviceScaleFactor: 1, mobile: false});
    await cdp('Page.navigate', {url: process.env.WAASABI_URL || 'http://127.0.0.1:8081/'});
    await until(() => evaluate('!!document.querySelector(".top-search")'));
    await evaluate('document.fonts.ready.then(() => true)');
    assert.match(await evaluate('getComputedStyle(document.body).fontFamily'), /Golos Text Variable/);
    assert.equal(await evaluate('document.fonts.check("16px Golos Text Variable")'), true);
    assert.equal(await evaluate('getComputedStyle(document.documentElement).scrollbarWidth'), 'auto');
    assert.equal(await evaluate('getComputedStyle(document.querySelector(".spotlight-results")).scrollbarWidth'), 'thin');
    assert.equal(await evaluate('!!document.querySelector(".demo-note, .demo-notice")'), false);
    assert.equal(await evaluate('/Local preview| · sample|Local sample profile/.test(document.body.innerText)'), false);
    const term = await evaluate('document.querySelector(".work-card h2").textContent.trim().slice(0, 16)');
    await key('k', 2);
    await until(() => evaluate('document.querySelector("#spotlight").open && document.activeElement.matches(".spotlight-input input")'));
    await screenshot('spotlight-empty.png');
    await cdp('Input.insertText', {text: term});
    await until(() => evaluate('document.querySelectorAll(".spotlight-result").length > 0'));
    const count = await evaluate('document.querySelectorAll(".spotlight-result").length');
    await key('ArrowDown');
    assert.equal(await evaluate('document.querySelector(".spotlight-input input").getAttribute("aria-activedescendant")'), `spotlight-result-${1 % count}`);
    await key('ArrowUp');
    await screenshot('spotlight-desktop.png');
    await key('Enter');
    await until(() => evaluate('location.pathname !== "/" && !!document.querySelector(".work-page")'));
    assert.equal(await evaluate('document.querySelector("#spotlight").open'), false);
    await evaluate('document.querySelector(".top-search").click()');
    await until(() => evaluate('document.querySelector("#spotlight").open'));
    await cdp('Input.insertText', {text: 'q'});
    await delay(300);
    assert.equal(await evaluate('document.querySelectorAll(".spotlight-result").length'), 0);
    await key('Escape');
    await until(() => evaluate('!document.querySelector("#spotlight").open'));
    await key('k', 4); // Cmd+K, even when this test runs on Windows.
    await until(() => evaluate('document.querySelector("#spotlight").open'));
    await cdp('Input.insertText', {text: 'no-results-7eb983e07'});
    await until(() => evaluate('document.querySelector(".spotlight-message")?.textContent === "Try another word or skill."'));
    await key('Escape');
    await cdp('Network.enable');
    await cdp('Network.setBlockedURLs', {urls: ['*/search?*']});
    await evaluate('document.querySelector(".top-search").focus(); document.querySelector(".top-search").click()');
    await cdp('Input.insertText', {text: 'test'});
    await until(() => evaluate('document.querySelector(".spotlight-message")?.textContent === "Search is unavailable. Try again."'));
    await key('Escape');
    await until(() => evaluate('document.activeElement.matches(".top-search")'));
    await cdp('Network.setBlockedURLs', {urls: []});
    for (const width of [390, 320]) {
        await cdp('Emulation.setDeviceMetricsOverride', {width, height: 844, deviceScaleFactor: 1, mobile: true});
        assert.ok(await evaluate('document.documentElement.scrollWidth <= innerWidth'));
        assert.ok(await evaluate('document.querySelector(".top-search").getBoundingClientRect().width > 0'));
    }
    await evaluate('document.documentElement.dataset.theme = "light"; document.querySelector(".top-search").click()');
    await until(() => evaluate('document.querySelector("#spotlight").open && document.activeElement.matches(".spotlight-input input") && document.activeElement.value === ""'));
    await cdp('Input.insertText', {text: term});
    await until(() => evaluate('document.querySelectorAll(".spotlight-result").length > 0'));
    assert.ok(await evaluate('document.querySelector("#spotlight").getBoundingClientRect().right <= innerWidth'));
    await screenshot('spotlight-mobile-light.png');
    console.log('Spotlight passed: shortcuts, focus, results, arrows/Enter, Escape, short query, empty/error states, 390/320px, light theme.');
} finally {
    if (socket?.readyState === WebSocket.OPEN) {await cdp('Browser.close').catch(() => {}); socket.close();}
    browser.kill();
    await delay(500);
    await rm(profile, {recursive: true, force: true, maxRetries: 5, retryDelay: 300});
}
