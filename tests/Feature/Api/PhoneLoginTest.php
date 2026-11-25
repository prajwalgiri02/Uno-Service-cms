<?php

namespace Tests\Feature\Api;

use App\Contracts\SmsGateway;
use App\Models\Otp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Testing\TestResponse;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\Fakes\FakeSmsGateway;
use Tests\TestCase;

class PhoneLoginTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+9779841234567';

    private FakeSmsGateway $sms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sms = new FakeSmsGateway;
        $this->app->instance(SmsGateway::class, $this->sms);
    }

    public function test_send_otp_rejects_numbers_that_are_not_nepali_mobiles(): void
    {
        $this->postJson('/api/v1/auth/otp/send', ['phone' => '01-4412345'])
            ->assertStatus(422)
            ->assertJsonPath('status', 422)
            ->assertJsonValidationErrors('phone');

        $this->assertSame([], $this->sms->sent);
    }

    public function test_send_otp_normalizes_the_number_and_sends_a_code(): void
    {
        $this->postJson('/api/v1/auth/otp/send', ['phone' => '98412 34567'])
            ->assertOk()
            ->assertJsonPath('data.phone', self::PHONE)
            ->assertJsonPath('data.resend_in', 60)
            ->assertJsonMissingPath('data.code');

        $this->assertMatchesRegularExpression('/^\d{4}$/', $this->sms->lastCodeFor(self::PHONE));
    }

    public function test_send_otp_enforces_the_resend_cooldown(): void
    {
        $this->sendOtp();

        $this->postJson('/api/v1/auth/otp/send', ['phone' => self::PHONE])
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone')
            ->assertJsonPath('retry_after', 60);

        $this->travel(61)->seconds();

        $this->sendOtp()->assertOk();
        $this->assertCount(2, $this->sms->sent);
    }

    public function test_verify_creates_a_customer_and_returns_a_token_pair(): void
    {
        $this->sendOtp();

        $response = $this->verify($this->sms->lastCodeFor(self::PHONE))
            ->assertOk()
            ->assertJsonPath('data.is_new_user', true)
            ->assertJsonPath('data.token_type', 'bearer')
            ->assertJsonPath('data.user.phone', self::PHONE)
            ->assertJsonPath('data.user.roles', ['customer']);

        $this->assertNotEmpty($response->json('data.access_token'));
        $this->assertNotEmpty($response->json('data.refresh_token'));
        $this->assertDatabaseCount('otps', 0);
        $this->assertNotNull(User::query()->where('phone', self::PHONE)->value('phone_verified_at'));
    }

    public function test_verify_as_vendor_adds_the_vendor_role_to_an_existing_user(): void
    {
        $user = User::factory()->withPhone(self::PHONE)->create();
        $user->assignRole('customer');

        $this->sendOtp();

        $this->verify($this->sms->lastCodeFor(self::PHONE), ['as' => 'vendor'])
            ->assertOk()
            ->assertJsonPath('data.is_new_user', false)
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.roles', ['customer', 'vendor']);
    }

    public function test_a_wrong_code_counts_an_attempt_and_a_used_code_cannot_be_replayed(): void
    {
        $this->sendOtp();
        $code = $this->sms->lastCodeFor(self::PHONE);
        $wrong = $code === '0000' ? '1111' : '0000';

        $this->verify($wrong)
            ->assertStatus(422)
            ->assertJsonPath('message', 'That code is incorrect. 4 attempts remaining.');

        $this->verify($code)->assertOk();
        $this->verify($code)->assertStatus(422)->assertJsonValidationErrors('code');
    }

    public function test_an_expired_code_is_rejected(): void
    {
        $this->sendOtp();
        $code = $this->sms->lastCodeFor(self::PHONE);

        $this->travel(6)->minutes();

        $this->verify($code)
            ->assertStatus(422)
            ->assertJsonPath('message', 'This code has expired. Request a new one.');
    }

    public function test_too_many_wrong_codes_lock_the_code(): void
    {
        $this->sendOtp();
        $code = $this->sms->lastCodeFor(self::PHONE);
        $wrong = $code === '0000' ? '1111' : '0000';

        foreach (range(1, 5) as $ignored) {
            $this->verify($wrong);
        }

        $this->verify($code)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Too many incorrect attempts. Request a new code.');
        $this->assertSame(5, Otp::query()->value('attempts'));
    }

    public function test_a_blocked_user_cannot_sign_in(): void
    {
        User::factory()->withPhone(self::PHONE)->blocked()->create();
        $this->sendOtp();

        $this->verify($this->sms->lastCodeFor(self::PHONE))
            ->assertForbidden()
            ->assertJsonPath('message', 'Your account has been blocked.');
    }

    public function test_the_access_token_reaches_protected_routes_but_the_refresh_token_does_not(): void
    {
        $tokens = $this->signIn();

        $this->withToken($tokens['access_token'])->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.phone', self::PHONE);

        $this->forgetAuth();

        $this->withToken($tokens['refresh_token'])->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_protected_routes_require_a_token(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_refresh_rotates_the_pair_and_the_old_refresh_token_is_single_use(): void
    {
        $tokens = $this->signIn();

        $refreshed = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $tokens['refresh_token']])
            ->assertOk()
            ->assertJsonPath('data.user.phone', self::PHONE)
            ->json('data');

        $this->assertNotSame($tokens['refresh_token'], $refreshed['refresh_token']);

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $tokens['refresh_token']])
            ->assertUnauthorized();
    }

    public function test_an_access_token_cannot_be_used_as_a_refresh_token(): void
    {
        $tokens = $this->signIn();

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $tokens['access_token']])
            ->assertUnauthorized();
    }

    public function test_logout_revokes_both_tokens(): void
    {
        $tokens = $this->signIn();

        $this->withToken($tokens['access_token'])
            ->postJson('/api/v1/auth/logout', ['refresh_token' => $tokens['refresh_token']])
            ->assertOk();

        $this->forgetAuth();

        $this->withToken($tokens['access_token'])->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $tokens['refresh_token']])->assertUnauthorized();
    }

    public function test_a_user_blocked_after_sign_in_is_locked_out(): void
    {
        $tokens = $this->signIn();
        User::query()->where('phone', self::PHONE)->update(['blocked_at' => now()]);

        $this->withToken($tokens['access_token'])->getJson('/api/v1/auth/me')->assertStatus(423);
    }

    private function sendOtp(): TestResponse
    {
        return $this->postJson('/api/v1/auth/otp/send', ['phone' => self::PHONE]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function verify(?string $code, array $extra = []): TestResponse
    {
        return $this->postJson('/api/v1/auth/otp/verify', ['phone' => self::PHONE, 'code' => $code, ...$extra]);
    }

    /**
     * @return array{access_token: string, refresh_token: string}
     */
    private function signIn(): array
    {
        $this->sendOtp();

        return $this->verify($this->sms->lastCodeFor(self::PHONE))->assertOk()->json('data');
    }

    /**
     * The JWT guard and the scoped JWT instances keep the parsed token for the
     * lifetime of the app, which spans every request in a test.
     */
    private function forgetAuth(): void
    {
        $this->app->forgetScopedInstances();
        JWTAuth::clearResolvedInstances();
        Auth::forgetGuards();
    }
}
