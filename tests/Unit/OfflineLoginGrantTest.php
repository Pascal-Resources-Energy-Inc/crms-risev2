<?php

use App\Dealer;
use App\User;
use App\Support\OfflineLoginGrant;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

/** Pure account fixtures; no application database or real credentials. */
class OfflineLoginGrantTest extends TestCase
{
    private $container;

    protected function setUp()
    {
        $this->container = new Container;
        $this->container->instance('config', new Repository(['app' => ['key' => 'synthetic-app-key']]));
        Container::setInstance($this->container);
    }

    protected function tearDown()
    {
        Container::setInstance(null);
    }

    private function user($role = 'Dealer')
    {
        $user = new User;
        $user->setRawAttributes(['id' => 42, 'name' => 'Example', 'email' => 'example@example.invalid', 'role' => $role, 'password' => 'synthetic-server-password-hash']);
        $profile = new Dealer;
        $profile->setRawAttributes(['number' => '09999999999']);
        foreach (['dealer', 'megaDealer', 'centerChief', 'client'] as $relation) $user->setRelation($relation, $profile);
        return $user;
    }

    public function test_grant_contains_only_the_authenticated_identity_aliases_and_opaque_version()
    {
        $user = $this->user();
        $grant = OfflineLoginGrant::forUser($user);
        $this->assertSame(42, $grant['user']['id']);
        $this->assertSame(['example@example.invalid', '09999999999'], $grant['identifiers']);
        $this->assertSame(64, strlen($grant['credential_version']));
        $this->assertNotSame($user->getAuthPassword(), $grant['credential_version']);
        $this->assertStringNotContainsString('synthetic-server-password-hash', json_encode($grant));
        $this->assertArrayNotHasKey('password', $grant['user']);
        $this->assertGreaterThan(time() + 29 * 86400, strtotime($grant['expires_at']));
        $this->assertLessThanOrEqual(time() + 30 * 86400, strtotime($grant['expires_at']));
    }

    public function test_password_change_rotates_version_without_revealing_the_new_hash()
    {
        $user = $this->user('mega_dealer');
        $before = OfflineLoginGrant::forUser($user);
        $user->password = 'synthetic-replacement-hash';
        $after = OfflineLoginGrant::forUser($user);
        $this->assertNotSame($before['credential_version'], $after['credential_version']);
        $this->assertSame('mega_dealer', $after['user']['role']);
        $this->assertStringNotContainsString('synthetic-replacement-hash', json_encode($after));
    }

    public function test_unknown_roles_or_missing_server_credentials_cannot_prepare_offline_login()
    {
        $this->assertNull(OfflineLoginGrant::forUser($this->user('Admin')));
        $this->assertNull(OfflineLoginGrant::forUser($this->user('area distributor')));
        $user = $this->user(); $user->password = null;
        $this->assertNull(OfflineLoginGrant::forUser($user));
        $this->container['config']->set('app.key', '');
        $this->assertNull(OfflineLoginGrant::forUser($this->user()));
    }
}
