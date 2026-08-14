<?php

function generate_uuid() {
    $data = random_bytes(16);
    assert(strlen($data) == 16);

    // Set version to 0100 (UUID v4)
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    // Set bits 6-7 to 10
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
// Contoh penggunaan:
// echo generate_uuid();
// Output: misalnya "a3f2b1c4-5d6e-4f7a-8b9c-0d1e2f3a4b5c"

// Atau untuk menyimpan ke database:
// $uuid = generate_uuid();
// $query = "INSERT INTO users (id, name) VALUES ('$uuid', 'John Doe')";
?>