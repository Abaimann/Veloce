<?php

declare(strict_types=1);

test('halaman root mengarahkan pengguna tamu ke login', function (): void {
    $this->get('/')->assertRedirect('/login');
});
