const assert = require('node:assert/strict');
const test = require('node:test');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../public/offline/offline.js'), 'utf8');
const login = fs.readFileSync(path.join(__dirname, '../../public/offline/login.html'), 'utf8');
const identity = { id: 42, name: 'Example User', role: 'mega_dealer', email: 'example@example.invalid', loginTime: new Date().toISOString() };

function harness(values = {}, page = 'home.html', blocked = false) {
    const saved = new Map(Object.entries(values)), handlers = {}, redirects = [];
    const context = vm.createContext({ URL, Date, confirm: () => true,
        document: { readyState: 'loading', currentScript: { src: 'https://example.test/team/offline/offline.js' }, addEventListener: (name, fn) => { handlers[name] = fn; } },
        localStorage: { getItem(key) { if (blocked) throw new Error('blocked'); return saved.get(key) || null; }, setItem: (key, value) => saved.set(key, value), removeItem: key => saved.delete(key) },
        window: { location: { href: 'https://example.test/team/offline/' + page, pathname: '/team/offline/' + page, replace: value => redirects.push(value) } }
    });
    vm.runInContext(source, context); return { context, saved, handlers, redirects };
}
test('a valid saved session opens immediately without a network request', () => {
    const h = harness({ offlineUser: JSON.stringify(identity) }); h.handlers.DOMContentLoaded();
    assert.equal(h.context.window.getOfflineUser().id, 42); assert.equal(h.redirects.length, 0);
    assert.equal(h.context.window.getOfflinePageUrl('products.html'), 'https://example.test/team/offline/products.html');
});
test('malformed saved identity cannot leave a hidden page or use a stale ID fallback', () => {
    const h = harness({ offlineUser: '{bad', current_user_id: '42', current_user_role: 'Dealer', dealerCartData: 'keep', completedOrders: 'keep' });
    h.handlers.DOMContentLoaded();
    assert.deepEqual(h.redirects, ['https://example.test/team/offline/login.html']);
    assert.equal(h.saved.get('dealerCartData'), 'keep'); assert.equal(h.saved.get('completedOrders'), 'keep');
    assert.equal(h.saved.has('current_user_id'), false);
});
test('expired identity redirects without removing a cart or transactions', () => {
    const h = harness({ offlineUser: JSON.stringify({ ...identity, loginTime: '2000-01-01T00:00:00Z' }), dealerCartData: 'keep' });
    h.handlers.DOMContentLoaded(); assert.equal(h.saved.get('dealerCartData'), 'keep'); assert.equal(h.saved.has('offlineUser'), false);
});

test('an offline authorization expiry is preserved across page opens and enforced independently of the session clock', () => {
    const expires = new Date(Date.now() + 86400000).toISOString();
    const h = harness({ offlineUser: JSON.stringify({ ...identity, offlineExpiresAt: expires }) });
    h.handlers.DOMContentLoaded();
    assert.equal(JSON.parse(h.saved.get('offlineUser')).offlineExpiresAt, expires);
    const expired = harness({ offlineUser: JSON.stringify({ ...identity, offlineExpiresAt: '2000-01-01T00:00:00Z' }), dealerCartData: 'keep' });
    expired.handlers.DOMContentLoaded();
    assert.deepEqual(expired.redirects, ['https://example.test/team/offline/login.html']);
    assert.equal(expired.saved.get('dealerCartData'), 'keep');
});
test('storage restrictions still allow the sign-in page to render', () => {
    const h = harness({}, 'login.html', true); assert.doesNotThrow(() => h.handlers.DOMContentLoaded()); assert.equal(h.redirects.length, 0);
});
test('saved login user navigates once at DOM readiness using the scoped URL', () => {
    const h = harness({ offlineUser: JSON.stringify(identity) }, 'login.html'); h.handlers.DOMContentLoaded(); h.handlers.DOMContentLoaded();
    assert.deepEqual(h.redirects, ['https://example.test/team/offline/home.html']);
});
test('offline login uses its required local verifier and never relies on a legacy password cache or CDN', () => {
    assert.match(login, /src="auth\.js"/);
    assert.match(login, /OfflineAuth\.authenticate\(identifier, password, role\)/);
    assert.doesNotMatch(login, /CRMSDB|dcodeIO|bcryptjs|user\.password/);
});
test('offline login inline scripts parse together and startup is never hidden', () => {
    const inline = [...login.matchAll(/<script\b(?![^>]*src=)[^>]*>([\s\S]*?)<\/script>/g)].map(match => match[1]);
    assert.doesNotThrow(() => new vm.Script(inline.join('\n')));
    assert.doesNotMatch(source + login, /document\.(?:body|documentElement)\.style\.display\s*=\s*['"]none/);
    assert.doesNotMatch(login, /http:\/\/localhost\//);
});
