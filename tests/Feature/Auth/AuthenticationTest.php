<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('halaman login dapat ditampilkan untuk tamu', function (): void {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Masuk ke Akun Anda');
});

test('pengguna aktif dengan kata sandi dapat masuk dan diarahkan ke dashboard', function (): void {
    $user = User::factory()->create([
        'email' => 'budi@veloce.test',
        'password' => Hash::make('Rahasia123!'),
        'is_active' => true,
    ]);

    $response = $this->post(route('login.store'), [
        'email' => 'budi@veloce.test',
        'password' => 'Rahasia123!',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

test('pengguna yang belum aktivasi kata sandi melalui undangan ditolak saat login', function (): void {
    User::factory()->unactivated()->create([
        'email' => 'baru@veloce.test',
    ]);

    $this->post(route('login.store'), [
        'email' => 'baru@veloce.test',
        'password' => 'Sembarang123',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('pengguna nonaktif ditolak saat login dan otomatis keluar bila sesi masih ada', function (): void {
    $user = User::factory()->inactive()->create([
        'email' => 'nonaktif@veloce.test',
        'password' => Hash::make('Rahasia123!'),
    ]);

    $this->post(route('login.store'), [
        'email' => 'nonaktif@veloce.test',
        'password' => 'Rahasia123!',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('login dibatasi oleh rate limiter setelah 5 percobaan gagal berturut-turut', function (): void {
    User::factory()->create([
        'email' => 'limit@veloce.test',
        'password' => Hash::make('Rahasia123!'),
    ]);

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('login.store'), [
            'email' => 'limit@veloce.test',
            'password' => 'Salah123',
        ])->assertSessionHasErrors('email');
    }

    $response = $this->post(route('login.store'), [
        'email' => 'limit@veloce.test',
        'password' => 'Salah123',
    ]);

    $response->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toContain('Terlalu banyak percobaan masuk');
});

test('pengguna dapat logout dengan aman', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
