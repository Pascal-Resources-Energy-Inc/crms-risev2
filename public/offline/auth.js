/* Own-account offline password verification, prepared only after server sign-in. */
(function (root, factory) {
    'use strict';
    if (typeof module === 'object' && module.exports) module.exports = factory;
    else root.OfflineAuth = factory(root);
})(typeof window !== 'undefined' ? window : this, function (env, adapter) {
    'use strict';
    const ITERATIONS = 600000;
    const store = adapter || browserStore();
    const normalizeRole = value => String(value || '').trim().toLowerCase().replace(/[\s_-]+/g, '');
    const allowed = new Set(['dealer', 'megadealer', 'centerchief', 'client', 'customer']);
    const hex = bytes => Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
    const unhex = value => new Uint8Array(value.match(/.{2}/g).map(byte => parseInt(byte, 16)));
    function error(code, message) { const result = new Error(message); result.code = code; return result; }
    function available() { return !!(env.crypto && env.crypto.subtle && env.crypto.getRandomValues && (env.TextEncoder || typeof TextEncoder !== 'undefined')); }
    function requireCrypto() {
        if (!available()) throw error('CRYPTO_UNAVAILABLE', 'Offline password verification needs a supported browser at localhost or HTTPS. Sign in online to continue.');
    }
    function safeUser(user) {
        const id = Number((user || {}).id);
        if (!Number.isSafeInteger(id) || id < 1 || !allowed.has(normalizeRole((user || {}).role))) throw error('INVALID_ACCOUNT', 'This account is not available for offline sign-in.');
        const safe = { id, name: String(user.name || 'User'), email: String(user.email || ''), role: String(user.role) };
        if (user.offlineExpiresAt) safe.offlineExpiresAt = user.offlineExpiresAt;
        return safe;
    }
    function normalizeIdentifier(value) {
        const text = String(value || '').trim().toLowerCase();
        if (text.includes('@') || !/^[+\d\s()-]+$/.test(text)) return text;
        let digits = text.replace(/\D/g, '');
        if (/^639\d{9}$/.test(digits)) digits = '0' + digits.slice(2);
        else if (/^9\d{9}$/.test(digits)) digits = '0' + digits;
        return digits;
    }
    function roleMatches(selected, actual) {
        const selection = normalizeRole(selected), role = normalizeRole(actual);
        if (!selection) return true;
        return selection === 'dealer' ? ['dealer', 'megadealer', 'centerchief'].includes(role) : selection === role;
    }
    function browserStore() {
        function run(mode, action) {
            return new Promise((resolve, reject) => {
                if (!env.indexedDB) return reject(error('STORAGE_UNAVAILABLE', 'This browser cannot save offline sign-in. Allow device storage and sign in online again.'));
                const opening = env.indexedDB.open('GazLiteOfflineAuth', 1);
                let settled = false, db;
                const timer = env.setTimeout(() => finish(error('STORAGE_UNAVAILABLE', 'Offline sign-in storage did not respond. Close other Gaz Lite tabs and try again.')), 10000);
                function finish(failure, value) {
                    if (settled) return;
                    settled = true; env.clearTimeout(timer);
                    if (db) db.close();
                    failure ? reject(failure) : resolve(value);
                }
                opening.onupgradeneeded = () => opening.result.createObjectStore('credentials', { keyPath: 'user_id' });
                opening.onerror = opening.onblocked = () => finish(error('STORAGE_UNAVAILABLE', 'Unable to open offline sign-in storage. Sign in online and retry.'));
                opening.onsuccess = () => {
                    db = opening.result;
                    if (settled) { db.close(); return; }
                    let result;
                    try {
                        const tx = db.transaction(['credentials'], mode);
                        tx.oncomplete = () => finish(null, result);
                        tx.onabort = tx.onerror = () => finish(error('STORAGE_UNAVAILABLE', 'Unable to save offline sign-in on this device. Your saved transactions are kept.'));
                        const request = action(tx.objectStore('credentials'));
                        request.onsuccess = () => { result = request.result; };
                    } catch (_) { finish(error('STORAGE_UNAVAILABLE', 'Offline sign-in storage is unavailable. Your saved transactions are kept.')); }
                };
            });
        }
        return { get: id => run('readonly', store => store.get(id)), list: () => run('readonly', store => store.getAll()),
            put: row => run('readwrite', store => store.put(row)), remove: id => run('readwrite', store => store.delete(id)) };
    }
    async function derive(password, salt) {
        requireCrypto();
        const Encoder = env.TextEncoder || TextEncoder;
        const key = await env.crypto.subtle.importKey('raw', new Encoder().encode(password), 'PBKDF2', false, ['deriveBits']);
        return new Uint8Array(await env.crypto.subtle.deriveBits({ name: 'PBKDF2', hash: 'SHA-256', iterations: ITERATIONS, salt }, key, 256));
    }
    function validatePassword(password) {
        if (typeof password !== 'string' || !password || password.length > 4096) throw error('INVALID_CREDENTIALS', 'Enter your email or phone number and the correct password.');
    }
    function validRecord(record) {
        try {
            return !!record && record.schema_version === 1 && record.algorithm === 'PBKDF2-SHA256' && record.iterations === ITERATIONS &&
                /^[a-f0-9]{64}$/.test(record.salt) && /^[a-f0-9]{64}$/.test(record.verifier) &&
                Number(record.user_id) === safeUser(record.user).id && typeof record.credential_version === 'string' && !!record.credential_version &&
                Array.isArray(record.identifiers) && record.identifiers.length > 0 && Number.isFinite(Date.parse(record.expires_at));
        } catch (_) { return false; }
    }
    async function enroll(grant, password, identifier) {
        requireCrypto(); validatePassword(password);
        if (!grant || typeof grant.credential_version !== 'string' || !grant.credential_version || grant.credential_version.length > 256 ||
            !Number.isFinite(Date.parse(grant.expires_at)) || Date.parse(grant.expires_at) <= Date.now()) {
            throw error('INVALID_GRANT', 'Online sign-in must finish before this account can be prepared offline.');
        }
        const user = safeUser(grant.user);
        const identifiers = [...new Set([user.email, identifier, ...(Array.isArray(grant.identifiers) ? grant.identifiers : [])].map(normalizeIdentifier).filter(Boolean))];
        if (!identifiers.length) throw error('INVALID_GRANT', 'The online account has no sign-in identifier.');
        const salt = env.crypto.getRandomValues(new Uint8Array(32));
        const verifier = await derive(password, salt);
        if (Date.parse(grant.expires_at) <= Date.now()) throw error('EXPIRED_GRANT', 'Offline preparation expired. Sign in online again.');
        await store.put({ schema_version: 1, user_id: user.id, user, identifiers, algorithm: 'PBKDF2-SHA256', iterations: ITERATIONS,
            salt: hex(salt), verifier: hex(verifier), credential_version: grant.credential_version, expires_at: grant.expires_at, prepared_at: new Date().toISOString() });
        return { ready: true, user: Object.assign({}, user, { offlineExpiresAt: grant.expires_at }), expires_at: grant.expires_at };
    }
    async function authenticate(identifier, password, selectedRole) {
        requireCrypto(); validatePassword(password);
        const normalized = normalizeIdentifier(identifier);
        if (!normalized) throw error('INVALID_CREDENTIALS', 'Enter your email or phone number and the correct password.');
        const matching = (await store.list()).filter(record => validRecord(record) && record.identifiers.includes(normalized));
        if (!matching.length) throw error('NOT_PREPARED', 'This account is not prepared in this browser yet. Connect and sign in online once, then wait for “Offline sign-in ready”.');
        const eligible = matching.filter(record => roleMatches(selectedRole, record.user.role));
        if (!eligible.length) throw error('ROLE_MISMATCH', 'Choose the account type used for your online sign-in.');
        const current = eligible.filter(record => Date.parse(record.expires_at) > Date.now());
        if (!current.length) throw error('EXPIRED_GRANT', 'Offline sign-in needs to be renewed. Connect and sign in online once to prepare it again.');
        if (current.length > 3) throw error('AMBIGUOUS_IDENTIFIER', 'Use your account email to sign in offline.');
        for (const record of current) {
            const actual = await derive(password, unhex(record.salt)), expected = unhex(record.verifier);
            let difference = actual.length ^ expected.length;
            for (let index = 0; index < expected.length; index++) difference |= actual[index] ^ expected[index];
            if (difference !== 0) continue;
            // A password or role update in another tab must invalidate an in-flight check.
            const latest = await store.get(record.user_id);
            if (!validRecord(latest) || latest.verifier !== record.verifier || latest.credential_version !== record.credential_version ||
                latest.expires_at !== record.expires_at || normalizeRole(latest.user.role) !== normalizeRole(record.user.role) || Date.parse(latest.expires_at) <= Date.now()) {
                throw error('ACCOUNT_CHANGED', 'This offline account changed. Sign in again with its current password.');
            }
            return Object.assign({}, safeUser(record.user), { offlineExpiresAt: record.expires_at });
        }
        throw error('INVALID_CREDENTIALS', 'The email or phone number and password do not match this saved account.');
    }
    async function forget(id) {
        const number = Number(id);
        if (Number.isSafeInteger(number) && number > 0) await store.remove(number);
    }
    async function reconcile(grant, id) {
        if (grant === null && id) { await forget(id); return; }
        if (!grant || !grant.user) return;
        const number = Number(grant.user.id), record = await store.get(number);
        if (record && (!validRecord(record) || record.credential_version !== grant.credential_version || normalizeRole(record.user.role) !== normalizeRole(grant.user.role))) await forget(number);
    }
    async function status(id) {
        if (!available()) return { ready: false };
        const record = await store.get(Number(id));
        return validRecord(record) && Date.parse(record.expires_at) > Date.now()
            ? { ready: true, expires_at: record.expires_at, user: safeUser(record.user) } : { ready: false };
    }
    function startSession(value) {
        const user = safeUser(value);
        if (user.offlineExpiresAt && (!Number.isFinite(Date.parse(user.offlineExpiresAt)) || Date.parse(user.offlineExpiresAt) <= Date.now())) throw error('EXPIRED_GRANT', 'Offline sign-in needs to be renewed online.');
        const session = Object.assign({}, user, { loginTime: new Date().toISOString() });
        const changes = { offlineUser: JSON.stringify(session), current_user_id: String(user.id), current_user_name: user.name, current_user_email: user.email, current_user_role: user.role };
        const previous = {};
        try {
            Object.keys(changes).forEach(key => { previous[key] = env.localStorage.getItem(key); });
            Object.entries(changes).forEach(([key, next]) => env.localStorage.setItem(key, next));
        } catch (_) {
            Object.entries(previous).forEach(([key, before]) => { try { before === null ? env.localStorage.removeItem(key) : env.localStorage.setItem(key, before); } catch (_) {} });
            throw error('STORAGE_UNAVAILABLE', 'This browser cannot save your offline session. Allow device storage and try again.');
        }
        return session;
    }
    return { enroll, authenticate, reconcile, forget, status, startSession, normalizeIdentifier, roleMatches };
});
