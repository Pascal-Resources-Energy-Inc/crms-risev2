const assert = require('node:assert/strict');
const test = require('node:test');
const { webcrypto } = require('node:crypto');
const createAuth = require('../../public/offline/auth.js');

const PASSWORD = 'Example offline passphrase 42!';
const clone = value => value === undefined ? undefined : structuredClone(value);

function grant(id = 42, overrides = {}) {
    return {
        user: { id, name: 'Example Account ' + id, email: 'account' + id + '@example.invalid', role: 'mega_dealer' },
        identifiers: ['account' + id + '@example.invalid', '09171234567'],
        credential_version: 'version-' + id,
        expires_at: new Date(Date.now() + 30 * 86400000).toISOString(),
        ...overrides
    };
}

function memoryStore(initial = []) {
    const records = new Map(initial.map(row => [Number(row.user_id), clone(row)]));
    return {
        records, failRead: false, failWrite: false,
        async get(id) { if (this.failRead) throw new Error('Offline storage blocked'); return clone(records.get(Number(id))); },
        async list() { if (this.failRead) throw new Error('Offline storage blocked'); return [...records.values()].map(clone); },
        async put(row) {
            if (this.failWrite) throw new Error('Offline storage quota reached');
            records.set(Number(row.user_id), clone(row));
        },
        async remove(id) {
            if (this.failWrite) throw new Error('Offline storage blocked');
            records.delete(Number(id));
        }
    };
}

function harness(store = memoryStore(), overrides = {}) {
    const saved = new Map([
        ['offlineUser', JSON.stringify({ id: 42, role: 'mega_dealer', loginTime: '2000-01-01T00:00:00Z' })],
        ['dealerCartData', 'existing unsent cart'], ['offline-pending-example', 'existing pending transaction']
    ]);
    const mutations = [], network = [], derivations = [];
    const crypto = {
        getRandomValues: bytes => webcrypto.getRandomValues(bytes),
        subtle: new Proxy(webcrypto.subtle, { get(target, property) {
            if (property === 'deriveBits') return (...args) => { derivations.push(clone(args[0])); return target.deriveBits(...args); };
            const value = target[property]; return typeof value === 'function' ? value.bind(target) : value;
        } })
    };
    const env = {
        crypto, TextEncoder, TextDecoder, Date, Uint8Array, ArrayBuffer,
        setTimeout, clearTimeout, isSecureContext: true,
        location: { href: 'https://example.invalid/app/offline/login.html' },
        localStorage: {
            failKey: null,
            getItem: key => saved.get(key) || null,
            setItem(key, value) {
                if (this.failKey === key) throw new Error('Session storage blocked');
                mutations.push({ key, value }); saved.set(key, String(value));
            },
            removeItem: key => { mutations.push({ key }); saved.delete(key); }
        },
        fetch: async (...args) => { network.push(args); throw new Error('No network in this offline authentication test'); },
        ...overrides
    };
    return { api: createAuth(env, store), env, store, saved, mutations, network, derivations };
}

test('online enrollment prepares an account for offline password authentication without storing its password', async () => {
    const h = harness();
    await h.api.enroll(grant(), PASSWORD, 'account42@example.invalid');
    const user = await h.api.authenticate('account42@example.invalid', PASSWORD, 'dealer');
    assert.equal(Number(user.id), 42);
    assert.equal(user.role, 'mega_dealer');
    assert.equal(h.store.records.size, 1);
    assert.ok(!JSON.stringify([...h.store.records.values()]).includes(PASSWORD));
    assert.equal(h.network.length, 0);
    assert.equal(h.mutations.length, 0);
    assert.equal((await h.api.status(42)).ready, true);
});

test('offline verification uses a salted PBKDF2 work factor rather than a fast password hash', async () => {
    const h = harness();
    await h.api.enroll(grant(), PASSWORD, 'account42@example.invalid');
    const first = h.derivations[0];
    assert.equal(first.name, 'PBKDF2');
    assert.equal(typeof first.hash === 'string' ? first.hash : first.hash.name, 'SHA-256');
    assert.ok(first.iterations >= 600000);
    assert.ok(first.salt.byteLength >= 16);
    await h.api.enroll(grant(), PASSWORD, 'account42@example.invalid');
    assert.notDeepEqual(Array.from(h.derivations[1].salt), Array.from(first.salt));
});

test('saved offline credentials work after the old 24-hour page session has expired', async () => {
    const h = harness();
    const original = new Map(h.saved);
    await h.api.enroll(grant(), PASSWORD, 'account42@example.invalid');
    const user = await h.api.authenticate('account42@example.invalid', PASSWORD, 'dealer');
    assert.equal(Number(user.id), 42);
    assert.deepEqual(h.saved, original);
    assert.equal(h.mutations.length, 0);
});

test('email case and Philippine phone variants match the account enrolled online', async () => {
    const h = harness();
    await h.api.enroll(grant(), PASSWORD, 'ACCOUNT42@EXAMPLE.INVALID');
    for (const identifier of [' ACCOUNT42@EXAMPLE.INVALID ', '+63 917 123 4567', '639171234567', '09171234567']) {
        assert.equal(Number((await h.api.authenticate(identifier, PASSWORD, 'Mega Dealer')).id), 42);
    }
});

test('a wrong password cannot create a session or change saved work', async () => {
    const h = harness();
    await h.api.enroll(grant(), PASSWORD, 'account42@example.invalid');
    const records = clone([...h.store.records.values()]), saved = new Map(h.saved);
    await assert.rejects(h.api.authenticate('account42@example.invalid', 'wrong password', 'dealer'));
    assert.deepEqual([...h.store.records.values()], records);
    assert.deepEqual(h.saved, saved);
    assert.equal(h.mutations.length, 0);
});

test('password encoding preserves Unicode and significant leading or trailing spaces', async () => {
    const h = harness(), password = '  Héllo 🔥 offline passphrase  ';
    await h.api.enroll(grant(), password, 'account42@example.invalid');
    assert.equal(Number((await h.api.authenticate('account42@example.invalid', password, 'dealer')).id), 42);
    await assert.rejects(h.api.authenticate('account42@example.invalid', password.trim(), 'dealer'));
});

test('an account cannot sign in through the wrong role option', async () => {
    const h = harness();
    await h.api.enroll(grant(), PASSWORD, 'account42@example.invalid');
    await assert.rejects(h.api.authenticate('account42@example.invalid', PASSWORD, 'client'));
    assert.equal(Number((await h.api.authenticate('account42@example.invalid', PASSWORD, 'dealer')).id), 42);
});

test('a client is authenticated with its own stored role and cannot use Dealer sign-in', async () => {
    const h = harness(), client = grant(43);
    client.user.role = 'Client';
    await h.api.enroll(client, PASSWORD, 'account43@example.invalid');
    assert.equal((await h.api.authenticate('account43@example.invalid', PASSWORD, 'client')).role, 'Client');
    await assert.rejects(h.api.authenticate('account43@example.invalid', PASSWORD, 'dealer'));
});

test('unprepared identifiers fail without falling back to identity fields or making a network request', async () => {
    const h = harness();
    await assert.rejects(h.api.authenticate('account42@example.invalid', PASSWORD, 'dealer'));
    assert.equal(h.mutations.length, 0);
    assert.equal(h.network.length, 0);
    assert.equal((await h.api.status(42)).ready, false);
});

test('the authenticated user object excludes password hashes and unrelated grant fields', async () => {
    const h = harness(), value = grant();
    value.user.password = 'server-password-hash-not-for-offline-storage';
    value.user.remember_token = 'private-server-token';
    await h.api.enroll(value, PASSWORD, 'account42@example.invalid');
    const user = await h.api.authenticate('account42@example.invalid', PASSWORD, 'dealer');
    assert.equal(user.password, undefined);
    assert.equal(user.remember_token, undefined);
    const serialized = JSON.stringify([...h.store.records.values()]);
    assert.ok(!serialized.includes('server-password-hash-not-for-offline-storage'));
    assert.ok(!serialized.includes('private-server-token'));
});

test('renewed online enrollment replaces the old password verifier for that account', async () => {
    const h = harness();
    await h.api.enroll(grant(), PASSWORD, 'account42@example.invalid');
    const next = grant(42, { credential_version: 'rotated-password-version' });
    await h.api.enroll(next, 'New example password 43!', 'account42@example.invalid');
    await assert.rejects(h.api.authenticate('account42@example.invalid', PASSWORD, 'dealer'));
    assert.equal(Number((await h.api.authenticate('account42@example.invalid', 'New example password 43!', 'dealer')).id), 42);
    assert.equal(h.store.records.size, 1);
});

test('server credential rotation revokes only that account and preserves other users and pending work', async () => {
    const h = harness();
    await h.api.enroll(grant(), PASSWORD, 'account42@example.invalid');
    await h.api.enroll(grant(43), PASSWORD, 'account43@example.invalid');
    const saved = new Map(h.saved);
    await h.api.reconcile(grant(42, { credential_version: 'changed-on-server' }));
    assert.equal((await h.api.status(42)).ready, false);
    assert.equal((await h.api.status(43)).ready, true);
    await assert.rejects(h.api.authenticate('account42@example.invalid', PASSWORD, 'dealer'));
    assert.equal(Number((await h.api.authenticate('account43@example.invalid', PASSWORD, 'dealer')).id), 43);
    assert.deepEqual(h.saved, saved);
});

test('server role changes revoke the old local role instead of silently upgrading it', async () => {
    const h = harness();
    await h.api.enroll(grant(), PASSWORD, 'account42@example.invalid');
    const next = grant(); next.user.role = 'Client';
    await h.api.reconcile(next);
    assert.equal((await h.api.status(42)).ready, false);
    await assert.rejects(h.api.authenticate('account42@example.invalid', PASSWORD, 'client'));
});

test('reconciliation does not create a usable verifier for an account that has never entered its password', async () => {
    const h = harness();
    await h.api.reconcile(grant());
    assert.equal(h.store.records.size, 0);
    assert.equal((await h.api.status(42)).ready, false);
});

test('failed enrollment storage preserves an older usable verifier and never creates an identity session', async () => {
    const h = harness();
    await h.api.enroll(grant(), PASSWORD, 'account42@example.invalid');
    const before = clone([...h.store.records.values()]);
    h.store.failWrite = true;
    await assert.rejects(h.api.enroll(grant(), 'A replacement password 57!', 'account42@example.invalid'));
    h.store.failWrite = false;
    assert.deepEqual([...h.store.records.values()], before);
    assert.equal(Number((await h.api.authenticate('account42@example.invalid', PASSWORD, 'dealer')).id), 42);
    assert.equal(h.mutations.length, 0);
});

test('unavailable storage fails closed rather than trusting an existing identity', async () => {
    const h = harness(); h.store.failRead = true;
    await assert.rejects(h.api.authenticate('account42@example.invalid', PASSWORD, 'dealer'));
    assert.equal(h.mutations.length, 0);
});

test('a browser without native password derivation cannot enroll or authenticate an account', async () => {
    const h = harness();
    await h.api.enroll(grant(), PASSWORD, 'account42@example.invalid');
    const unsupported = harness(h.store, { crypto: undefined });
    await assert.rejects(unsupported.api.authenticate('account42@example.invalid', PASSWORD, 'dealer'));
    await assert.rejects(unsupported.api.enroll(grant(44), PASSWORD, 'account44@example.invalid'));
    assert.equal(unsupported.mutations.length, 0);
    assert.equal((await unsupported.api.status(42)).ready, false);
});

test('forgetting an account removes only its verifier and leaves offline transactions intact', async () => {
    const h = harness();
    await h.api.enroll(grant(), PASSWORD, 'account42@example.invalid');
    await h.api.enroll(grant(43), PASSWORD, 'account43@example.invalid');
    const saved = new Map(h.saved);
    await h.api.forget(42);
    assert.equal((await h.api.status(42)).ready, false);
    assert.equal((await h.api.status(43)).ready, true);
    assert.deepEqual(h.saved, saved);
});

test('expired credentials require a new online preparation instead of creating an offline session', async () => {
    const h = harness();
    await h.api.enroll(grant(), PASSWORD, 'account42@example.invalid');
    h.store.records.get(42).expires_at = '2000-01-01T00:00:00Z';
    assert.equal((await h.api.status(42)).ready, false);
    await assert.rejects(h.api.authenticate('account42@example.invalid', PASSWORD, 'dealer'));
    assert.equal(h.mutations.length, 0);
});

test('corrupt algorithm or lowered password-derivation settings cannot be treated as a ready account', async () => {
    const h = harness();
    await h.api.enroll(grant(), PASSWORD, 'account42@example.invalid');
    const valid = clone(h.store.records.get(42));
    for (const changes of [{ algorithm: 'plain-sha256' }, { iterations: 1 }, { salt: 'not-a-valid-salt' }, { verifier: '' }]) {
        h.store.records.set(42, { ...clone(valid), ...changes });
        assert.equal((await h.api.status(42)).ready, false);
        await assert.rejects(h.api.authenticate('account42@example.invalid', PASSWORD, 'dealer'));
    }
    assert.equal(h.mutations.length, 0);
});

test('expired or incomplete online grants cannot create offline credentials', async () => {
    const h = harness();
    for (const value of [grant(42, { expires_at: '2000-01-01T00:00:00Z' }), grant(42, { credential_version: '' }), grant(42, { user: { id: -1, role: 'dealer' } })]) {
        await assert.rejects(h.api.enroll(value, PASSWORD, 'account42@example.invalid'));
    }
    await assert.rejects(h.api.enroll(grant(), '', 'account42@example.invalid'));
    assert.equal(h.store.records.size, 0);
    assert.equal(h.mutations.length, 0);
});

test('offline session creation occurs explicitly after password verification and preserves pending work', async () => {
    const h = harness();
    await h.api.enroll(grant(), PASSWORD, 'account42@example.invalid');
    const user = await h.api.authenticate('account42@example.invalid', PASSWORD, 'dealer');
    assert.equal(h.mutations.length, 0);
    await h.api.startSession(user);
    const savedUser = JSON.parse(h.saved.get('offlineUser'));
    assert.equal(Number(savedUser.id), 42);
    assert.equal(savedUser.role, 'mega_dealer');
    assert.equal(savedUser.password, undefined);
    assert.ok(Date.parse(savedUser.loginTime) > Date.now() - 60000);
    assert.equal(h.saved.get('dealerCartData'), 'existing unsent cart');
    assert.equal(h.saved.get('offline-pending-example'), 'existing pending transaction');
});

test('partial identity-storage failure restores the previous session without removing pending work', async () => {
    const h = harness();
    await h.api.enroll(grant(), PASSWORD, 'account42@example.invalid');
    const user = await h.api.authenticate('account42@example.invalid', PASSWORD, 'dealer');
    const before = new Map(h.saved);
    h.env.localStorage.failKey = 'current_user_role';
    await assert.rejects(async () => h.api.startSession(user));
    assert.deepEqual(h.saved, before);
});

test('a successful online sign-in with no offline grant can revoke only its previous verifier', async () => {
    const h = harness();
    await h.api.enroll(grant(), PASSWORD, 'account42@example.invalid');
    await h.api.enroll(grant(43), PASSWORD, 'account43@example.invalid');
    await h.api.reconcile(null, 42);
    assert.equal((await h.api.status(42)).ready, false);
    assert.equal((await h.api.status(43)).ready, true);
    assert.equal(h.saved.get('offline-pending-example'), 'existing pending transaction');
});

test('credential revocation during password derivation prevents an in-flight offline sign-in', async () => {
    const h = harness();
    const original = h.env.crypto.subtle.deriveBits.bind(h.env.crypto.subtle);
    let block = false, started, release;
    const computing = new Promise(resolve => { started = resolve; });
    const resumed = new Promise(resolve => { release = resolve; });
    h.env.crypto.subtle = new Proxy(webcrypto.subtle, { get(target, property) {
        if (property === 'deriveBits') return async (...args) => {
            const result = await original(...args);
            if (block) { started(); await resumed; }
            return result;
        };
        const value = target[property]; return typeof value === 'function' ? value.bind(target) : value;
    } });
    await h.api.enroll(grant(), PASSWORD, 'account42@example.invalid');
    block = true;
    const login = h.api.authenticate('account42@example.invalid', PASSWORD, 'dealer');
    const rejected = assert.rejects(login, error => error.code === 'ACCOUNT_CHANGED');
    await computing;
    await h.api.reconcile(grant(42, { credential_version: 'password-changed-during-verification' }));
    release();
    await rejected;
    assert.equal((await h.api.status(42)).ready, false);
    assert.equal(h.mutations.length, 0);
    assert.equal(h.saved.get('offline-pending-example'), 'existing pending transaction');
});
