<?php
function temporaryPassword(): string
{
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $password = '';
    for ($index = 0; $index < 10; $index++) {
        $password .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $password;
}
