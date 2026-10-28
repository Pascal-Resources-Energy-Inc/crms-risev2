const assert = require('node:assert/strict');
const test = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const html = fs.readFileSync(path.join(__dirname, '../../public/offline/login.html'), 'utf8');
const source = [...html.matchAll(/<script\b(?![^>]*src=)[^>]*>([\s\S]*?)<\/script>/g)]
    .map(match => match[1]).find(script => script.includes('function updateNetworkStatusWithCheck'));

test('login connectivity changes update the badge and resume sync without automatic modals', async () => {
    let reachable = true;
    const modals = [], events = [], nodes = { networkStatus: {}, networkStatusText: {} };
    const context = vm.createContext({
        AbortController, setTimeout, clearTimeout, URL,
        setInterval: () => 1, clearInterval() {}, navigator: { onLine: true },
        document: { getElementById: id => nodes[id] },
        fetch: async () => ({ ok: reachable }),
        CustomEvent: class { constructor(type) { this.type = type; } },
        Swal: { async fire(options) { modals.push(options); return {}; } },
        window: { location: { href: 'https://example.test/team/offline/login.html' }, addEventListener() {}, dispatchEvent: event => events.push(event.type) }
    });
    vm.runInContext(source, context);
    await new Promise(resolve => setImmediate(resolve));
    assert.equal(nodes.networkStatusText.textContent, 'Connected');
    assert.equal(nodes.networkStatus.className, 'network-status online');
    reachable = false; await vm.runInContext('updateNetworkStatusWithCheck()', context);
    assert.equal(nodes.networkStatusText.textContent, 'Offline');
    reachable = true; await vm.runInContext('updateNetworkStatusWithCheck()', context);
    await vm.runInContext('updateNetworkStatusWithCheck()', context);
    assert.equal(nodes.networkStatusText.textContent, 'Connected');
    assert.deepEqual(events, ['offline-connection-restored']);
    assert.equal(modals.length, 0);
    await vm.runInContext('toggleNetworkMode()', context);
    assert.equal(modals.length, 1);
    assert.equal(modals[0].title, 'Switch to Online Mode?');
});

test('automatic updates wait for entered credentials and an in-flight offline login without saving the password', () => {
    const authSource = [...html.matchAll(/<script\b(?![^>]*src=)[^>]*>([\s\S]*?)<\/script>/g)]
        .map(match => match[1]).find(script => script.includes('function handleOfflineLogin'));
    const handlers = {}, nodes = { email: { value: '' }, password: { value: '' } };
    const context = vm.createContext({ document: { getElementById: id => nodes[id] },
        window: { addEventListener: (name, handler) => { handlers[name] = handler; } } });
    vm.runInContext(authSource, context);
    const blocked = () => { let prevented = false; handlers['offline-before-update']({ detail: { automatic: true }, preventDefault() { prevented = true; } }); return prevented; };
    assert.equal(blocked(), false);
    nodes.email.value = 'example@example.invalid'; assert.equal(blocked(), true);
    nodes.email.value = ''; nodes.password.value = 'unsaved-example'; assert.equal(blocked(), true);
    assert.equal(nodes.password.value, 'unsaved-example');
    nodes.password.value = ''; vm.runInContext('offlineLoginPending = true', context); assert.equal(blocked(), true);
    vm.runInContext('offlineLoginPending = false', context); assert.equal(blocked(), false);
});
