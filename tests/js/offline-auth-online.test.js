const assert = require('node:assert/strict');
const test = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '../..');
const login = fs.readFileSync(path.join(root, 'resources/views/auth/login.blade.php'), 'utf8');
const block = login.slice(login.indexOf('    // Online sign-in prepares only'), login.indexOf('    // End online sign-in preparation.'))
    .replace("@json(asset('offline/home.html'))", JSON.stringify('https://example.test/team/offline/home.html'))
    .replace('{{ route("home") }}', 'https://example.test/team/home');
const user = { id: 42, name: 'Example', email: 'example@example.invalid', role: 'Dealer' };
const grant = { user, identifiers: [user.email], credential_version: 'example-version', expires_at: '2026-12-01T00:00:00Z' };
const response = (body, status = 200) => ({ ok: status >= 200 && status < 300, status, headers: { get: () => 'application/json' }, json: async () => structuredClone(body) });
const flush = () => new Promise(resolve => setImmediate(resolve));

function harness({ online = true, fetcher, auth = {}, data = {} } = {}) {
    const calls = [], alerts = [], formValues = new Map([['email', ' example@example.invalid '], ['password', 'example-password'], ['selected_role', 'dealer']]);
    let submit;
    const form = { action: 'https://example.test/team/login', addEventListener(name, handler) { if (name === 'submit') submit = handler; } };
    const nodes = {
        loginForm: form, signinButton: { disabled: false, innerHTML: 'Sign in' }, password: { value: 'example-password' }, offlineSetupStatus: { textContent: '' }
    };
    const context = vm.createContext({
        navigator: { onLine: online }, AbortController, setTimeout, clearTimeout,
        document: { getElementById: id => nodes[id] },
        FormData: class { get(key) { return formValues.get(key); } delete(key) { formValues.delete(key); } },
        async fetch(url, options) { calls.push({ type: 'fetch', url, method: options.method }); return fetcher ? fetcher(url, options) : response({ success: true, user, offline_login: grant, redirect: '/dashboard' }); },
        Swal: { async fire(value) { alerts.push(value); } },
        window: {
            location: { href: '' }, OfflineDataConfig: { headless: true },
            OfflineAuth: {
                async authenticate(identifier, password, role) { calls.push({ type: 'authenticate', identifier, password, role }); return structuredClone(user); },
                async startSession(value) { calls.push({ type: 'session', user: value }); },
                async enroll(value, password, identifier) { calls.push({ type: 'enroll', grant: value, password, identifier }); return { user: { ...user, offlineExpiresAt: grant.expires_at } }; },
                async forget(id) { calls.push({ type: 'forget', id }); }, ...auth
            },
            OfflineData: {
                async refresh() { calls.push({ type: 'refresh' }); return { user, offline_login: structuredClone(grant) }; },
                async refreshAssets() { calls.push({ type: 'files' }); return { success: true }; }, ...data
            }
        }
    });
    vm.runInContext(block, context);
    return { context, calls, alerts, nodes, formValues, submit: () => submit.call(form, { preventDefault() {} }) };
}

test('online success prepares account data and files before enrolling the authenticated password', async () => {
    const h = harness(); await h.submit();
    const kinds = h.calls.map(call => call.type);
    assert.ok(kinds.indexOf('enroll') > kinds.indexOf('refresh'));
    assert.ok(kinds.indexOf('enroll') > kinds.indexOf('files'));
    assert.equal(h.calls.find(call => call.type === 'enroll').identifier, user.email);
    assert.equal(h.calls.find(call => call.type === 'enroll').grant.credential_version, grant.credential_version);
    assert.equal(h.context.window.location.href, '/dashboard');
    assert.match(h.alerts[0].text, /Offline sign-in.*ready/);
    assert.equal(h.nodes.password.value, '');
    assert.equal(h.formValues.has('password'), false);
    assert.equal(h.calls.filter(call => call.type === 'session').at(-1).user.offlineExpiresAt, grant.expires_at);
});

test('success never redirects before durable local enrollment completes', async () => {
    let finish;
    const h = harness({ auth: { enroll: () => new Promise(resolve => { finish = () => resolve({ user }); }) } });
    const pending = h.submit(); await flush();
    assert.equal(h.context.window.location.href, ''); assert.equal(h.alerts.length, 0);
    finish(); await pending; assert.equal(h.context.window.location.href, '/dashboard');
});

test('already-open online form signs in offline without making a network request', async () => {
    const h = harness({ online: false }); await h.submit();
    assert.deepEqual(h.calls.map(call => call.type), ['authenticate', 'session']);
    assert.equal(h.calls[0].role, 'dealer');
    assert.equal(h.context.window.location.href, 'https://example.test/team/offline/home.html');
});

test('lost connection falls back only after local password verification', async () => {
    const h = harness({ fetcher: async () => { throw new TypeError('network'); } }); await h.submit();
    assert.deepEqual(h.calls.map(call => call.type), ['fetch', 'authenticate', 'session']);
    assert.match(h.alerts[0].title, /offline/);
});

test('server authentication and role rejections never use a cached offline credential', async () => {
    for (const status of [401, 403, 419, 422]) {
        const h = harness({ fetcher: async () => response({ success: false, message: 'Rejected' }, status) });
        await h.submit();
        assert.deepEqual(h.calls.map(call => call.type), ['fetch']);
        assert.equal(h.context.window.location.href, '');
    }
});

test('wrong offline password does not create a session or redirect', async () => {
    const h = harness({ online: false, auth: { authenticate: async () => { throw new Error('Incorrect password.'); } } });
    await h.submit(); assert.equal(h.calls.length, 0);
    assert.equal(h.context.window.location.href, '');
    assert.match(h.alerts[0].text, /Incorrect password/);
    assert.equal(h.nodes.signinButton.disabled, false);
});

test('password rotation during preparation cannot enroll the earlier password', async () => {
    const h = harness({ data: { refresh: async () => ({ offline_login: { ...grant, credential_version: 'changed-version' } }) } });
    await h.submit(); assert.equal(h.calls.some(call => call.type === 'enroll'), false);
    assert.equal(h.context.window.location.href, '/dashboard');
    assert.equal(h.alerts[0].icon, 'warning');
    assert.match(h.alerts[0].text, /credentials changed/);
});

test('setup failure remains an online success with a truthful warning', async () => {
    const h = harness({ data: { refreshAssets: async () => { throw new Error('Offline files could not be saved.'); } } });
    await h.submit(); assert.equal(h.context.window.location.href, '/dashboard');
    assert.equal(h.calls.some(call => call.type === 'enroll'), false);
    assert.equal(h.alerts[0].icon, 'warning');
    assert.equal(h.alerts[0].showConfirmButton, true);
    assert.match(h.alerts[0].text, /could not be saved/);
});

test('an explicit server revocation removes the old verifier for this user only', async () => {
    const h = harness({ fetcher: async () => response({ success: true, user, offline_login: null, redirect: '/dashboard' }) });
    await h.submit(); assert.equal(h.calls.find(call => call.type === 'forget').id, 42);
    assert.equal(h.calls.some(call => call.type === 'enroll'), false);
    assert.equal(h.context.window.location.href, '/dashboard');
});

test('both page layouts load the local verifier before the account data runtime', () => {
    for (const file of ['app.blade.php', 'header.blade.php']) {
        const html = fs.readFileSync(path.join(root, 'resources/views/layouts', file), 'utf8');
        assert.ok(html.indexOf("asset('offline/auth.js')") < html.indexOf("asset('offline/data.js')"));
    }
});
