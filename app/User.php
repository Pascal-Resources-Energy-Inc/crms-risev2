<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'serial', 'read_notifications',
    ];

    protected $hidden = [
        'password', 'remember_token',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'read_notifications' => 'array',
    ];

    public function dealer()
    {
        return $this->hasOne(Dealer::class, 'user_id');
    }

    /** Mega Dealer profile stored with the authenticated users in admin_crms. */
    public function megaDealer()
    {
        return $this->hasOne(MegaDealer::class, 'user_id');
    }

    public function client()
    {
        return $this->hasOne(Client::class, 'user_id');
    }

    public function centerChief()
    {
        return $this->hasOne(CenterChief::class, 'user_id');
    }

    /** Dealer-facing roles use the dealer workspace and operational permissions. */
    public function isDealerLike()
    {
        return in_array($this->normalizedRole(), ['dealer', 'megadealer', 'centerchief'], true);
    }

    /** Center Chiefs must always transact at the suggested retail price. */
    public function usesSrpPricing()
    {
        return $this->normalizedRole() === 'centerchief';
    }

    /** Mega Dealers receive the dedicated Mega Dealer product price. */
    public function usesMegaDealerPricing()
    {
        return $this->normalizedRole() === 'megadealer';
    }

    private function normalizedRole()
    {
        return strtolower(preg_replace('/[\s_-]+/', '', trim((string) $this->role)));
    }
    
    public function redeemedHistory()
    {
        return $this->hasMany(RedeemedHistory::class);
    }

    
}
