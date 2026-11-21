<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Registrasi penyewa (REQ-AUTH-01, TC-035..TC-038).
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function validInput(array $overrides = []): array
    {
        return array_merge([
            'nik' => '3578012345678901',
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'email' => 'budi@example.com',
            'password' => 'rahasia123',
            'password_confirmation' => 'rahasia123',
        ], $overrides);
    }

    public function test_registration_screen_shows_nik_and_phone_fields(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('name="nik"', false)
            ->assertSee('name="phone"', false);
    }

    /** TC-035: registrasi valid membuat akun penyewa berstatus pending. */
    public function test_tc035_valid_registration_creates_pending_renter(): void
    {
        $response = $this->post('/register', $this->validInput());

        $response->assertRedirect(route('renter.dashboard'));
        $response->assertSessionHas('status');
        $this->assertAuthenticated();

        $user = User::where('email', 'budi@example.com')->firstOrFail();
        $this->assertSame('3578012345678901', $user->nik);
        $this->assertSame('081234567890', $user->phone);
        $this->assertSame(UserRole::PENYEWA, $user->role);
        $this->assertSame(VerificationStatus::PENDING, $user->verification_status);
        $this->assertNull($user->verified_at);
    }

    /** TC-035 (keamanan): role dan status tidak bisa diisi lewat form. */
    public function test_tc035_role_and_status_cannot_be_injected(): void
    {
        $this->post('/register', $this->validInput([
            'role' => UserRole::ADMIN->value,
            'verification_status' => VerificationStatus::APPROVED->value,
        ]));

        $user = User::where('email', 'budi@example.com')->firstOrFail();
        $this->assertSame(UserRole::PENYEWA, $user->role);
        $this->assertSame(VerificationStatus::PENDING, $user->verification_status);
    }

    /** TC-036: NIK atau email yang sudah terdaftar ditolak dengan pesan pada field. */
    public function test_tc036_duplicate_nik_or_email_is_rejected(): void
    {
        User::factory()->create(['nik' => '3578012345678901', 'email' => 'lama@example.com']);

        $this->post('/register', $this->validInput())->assertSessionHasErrors('nik');
        $this->post('/register', $this->validInput([
            'nik' => '3578012345678999',
            'email' => 'lama@example.com',
        ]))->assertSessionHasErrors('email');

        $this->assertSame(1, User::count());
        $this->assertGuest();
    }

    /** TC-037: password disimpan ter-hash. */
    public function test_tc037_password_is_hashed(): void
    {
        $this->post('/register', $this->validInput());

        $stored = User::where('email', 'budi@example.com')->value('password');
        $this->assertNotSame('rahasia123', $stored);
        $this->assertTrue(Hash::check('rahasia123', $stored));
    }

    /** TC-038: NIK harus tepat 16 digit angka. */
    public function test_tc038_nik_must_be_16_digits(): void
    {
        foreach (['357801234567890', '35780123456789012', '35780123456789AB'] as $nik) {
            $this->post('/register', $this->validInput(['nik' => $nik]))->assertSessionHasErrors('nik');
        }

        $this->assertSame(0, User::count());
    }

    public function test_phone_must_be_8_to_15_digits(): void
    {
        foreach (['0812345', '0812345678901234', '0812-3456-789'] as $phone) {
            $this->post('/register', $this->validInput(['phone' => $phone]))->assertSessionHasErrors('phone');
        }

        $this->assertSame(0, User::count());
    }

    public function test_validation_messages_are_in_indonesian(): void
    {
        $this->post('/register', $this->validInput(['nik' => '']))
            ->assertSessionHasErrors(['nik' => 'NIK wajib diisi.']);
    }
}
