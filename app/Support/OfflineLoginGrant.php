<?php

namespace App\Support;

/** Metadata for preparing only the account that has authenticated online. */
class OfflineLoginGrant
{
    public static function forUser($user)
    {
        $role = strtolower(preg_replace('/[\s_-]+/', '', trim((string) $user->role)));
        $key = (string) config('app.key');
        $passwordHash = (string) $user->getAuthPassword();
        if (!in_array($role, ['dealer', 'megadealer', 'centerchief', 'client', 'customer'], true) || $key === '' || $passwordHash === '') return null;

        $profile = $user->isDealerLike()
            ? ($user->usesSrpPricing() ? $user->centerChief : ($user->usesMegaDealerPricing() ? $user->megaDealer : $user->dealer))
            : $user->client;
        $identifiers = array_values(array_unique(array_filter([
            (string) $user->email, (string) $user->getAttribute('phone'),
            (string) optional($profile)->number, (string) optional($profile)->contact_number,
        ], function ($value) { return trim($value) !== ''; })));

        return [
            'user' => ['id' => (int) $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role],
            'identifiers' => $identifiers,
            'credential_version' => hash_hmac('sha256', $passwordHash, $key),
            'expires_at' => date(DATE_ATOM, time() + 30 * 86400),
        ];
    }
}
