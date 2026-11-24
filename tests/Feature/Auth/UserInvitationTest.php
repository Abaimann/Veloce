<?php

declare(strict_types=1);

use App\Actions\Auth\CreateUserInvitationAction;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('tautan undangan bertanda tangan memungkinkan karyawan mengatur kata sandinya sendiri', function (): void {
    $admin = User::factory()->create();
    $employee = User::factory()->unactivated()->create([
        'name' => 'Siti Aminah',
        'email' => 'siti@veloce.test',
    ]);

    $result = app(CreateUserInvitationAction::class)->execute($employee, $admin);
    $invitation = $result['invitation'];

    expect($employee->password)->toBeNull()
        ->and($invitation->token_hash)->not->toBeEmpty()
        ->and($invitation->used_at)->toBeNull();

    $this->get($result['url'])
        ->assertOk()
        ->assertSee('Aktivasi Akun Karyawan')
        ->assertSee('Siti Aminah');

    $this->post($result['url'], [
        'password' => 'KaryawanBaru123',
        'password_confirmation' => 'KaryawanBaru123',
    ])->assertRedirect(route('dashboard'));

    $employee->refresh();
    $invitation->refresh();

    $this->assertAuthenticatedAs($employee);
    expect(Hash::check('KaryawanBaru123', $employee->password))->toBeTrue()
        ->and($invitation->used_at)->not->toBeNull();
});

test('tautan undangan dengan signature atau token yang dimodifikasi ditolak 403', function (): void {
    $employee = User::factory()->unactivated()->create();
    $result = app(CreateUserInvitationAction::class)->execute($employee);

    $tamperedUrl = str_replace('signature=', 'signature=palsu', $result['url']);

    $this->get($tamperedUrl)->assertForbidden();
    $this->post($tamperedUrl, [
        'password' => 'Rahasia1234',
        'password_confirmation' => 'Rahasia1234',
    ])->assertForbidden();

    expect($employee->fresh()->password)->toBeNull();
});

test('tautan undangan yang sudah kedaluwarsa atau sudah digunakan tidak dapat dipakai lagi', function (): void {
    $employee = User::factory()->unactivated()->create();
    $result = app(CreateUserInvitationAction::class)->execute($employee);

    $result['invitation']->update([
        'expires_at' => now()->subMinute(),
    ]);

    $this->get($result['url'])
        ->assertOk()
        ->assertSee('Undangan Tidak Dapat Digunakan');

    $freshResult = app(CreateUserInvitationAction::class)->execute($employee);
    $freshResult['invitation']->update([
        'used_at' => now(),
    ]);

    $this->get($freshResult['url'])
        ->assertOk()
        ->assertSee('sudah pernah digunakan');
});
