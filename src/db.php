<?php

function db_path(): string {
    return __DIR__ . '/../data/app.sqlite';
}

function getPDO(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'sqlite:' . db_path();
    $pdo = new PDO($dsn, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 5,
        PDO::ATTR_PERSISTENT         => false,
    ]);

    try { $pdo->exec('PRAGMA foreign_keys = ON'); } catch (Throwable $e) {}
    try { $pdo->exec('PRAGMA journal_mode = WAL'); } catch (Throwable $e) {}
    try { $pdo->exec('PRAGMA synchronous = NORMAL'); } catch (Throwable $e) {}
    try { $pdo->exec('PRAGMA busy_timeout = 5000'); } catch (Throwable $e) {} 

    return $pdo;
}
