<?php

/*
 * "Request pertama" untuk ConcurrentRequestTest, dijalankan sebagai proses PHP terpisah.
 *
 * Membuka transaksi, menjalankan perintah SQL yang diberikan (termasuk mengunci baris), mencetak LOCKED,
 * menahan transaksi selama hold_ms milidetik, lalu commit. Selama ditahan, test menjalankan request kedua.
 *
 * Argumen: satu string JSON berisi host, port, database, username, password, hold_ms, statements.
 */

$args = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);

$pdo = new PDO(
    "mysql:host={$args['host']};port={$args['port']};dbname={$args['database']}",
    $args['username'],
    $args['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);

$pdo->beginTransaction();

foreach ($args['statements'] as [$sql, $bindings]) {
    $pdo->prepare($sql)->execute($bindings);
}

echo "LOCKED\n";
fflush(STDOUT);

usleep($args['hold_ms'] * 1000);

$pdo->commit();
echo "COMMITTED\n";
