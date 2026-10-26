<?php
/**
 * config/session.php
 * -----------------------------------------------------------
 * Helper session & guard. Di-include di setiap halaman yang
 * butuh login (dashboard, balita, kriteria, dst).
 * -----------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';

/**
 * Wajibkan user login. Jika belum, redirect ke halaman login.
 */
function requireLogin(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . 'auth/login.php');
        exit;
    }
}

/**
 * Wajibkan role tertentu (mis. admin).
 */
function requireRole(string $role): void
{
    requireLogin();
    if (($_SESSION['role'] ?? '') !== $role) {
        header('Location: ' . BASE_URL . 'dashboard/index.php?error=forbidden');
        exit;
    }
}

function currentUser(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id'       => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'nama'     => $_SESSION['nama_lengkap'],
        'role'     => $_SESSION['role'],
    ];
}

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

/**
 * Wajibkan role admin. Admin mengelola: data kriteria, LMS WHO, data pengguna.
 */
function requireAdmin(): void
{
    requireRole('admin');
}

/**
 * Wajibkan role kader. Kader mengelola: data balita, data penilaian,
 * perhitungan MOORA — dibatasi pada posyandu yang ia kelola.
 */
function requireKader(): void
{
    requireRole('kader');
}

function isAdmin(): bool
{
    return ($_SESSION['role'] ?? '') === 'admin';
}

function isKader(): bool
{
    return ($_SESSION['role'] ?? '') === 'kader';
}

/**
 * ID posyandu yang dikelola kader yang sedang login (null untuk admin
 * atau jika belum diset).
 */
function currentPosyanduId(): ?int
{
    return isset($_SESSION['posyandu_id']) ? (int) $_SESSION['posyandu_id'] : null;
}

/**
 * Normalisasi input tanggal yang boleh memakai pemisah "-" maupun "/",
 * dalam format dd-mm-yyyy / yyyy-mm-dd / dd/mm/yyyy / yyyy/mm/dd,
 * menjadi format standar database "Y-m-d". Mengembalikan null jika
 * formatnya tidak dikenali / tanggalnya tidak valid.
 */
function normalizeTanggal(?string $input): ?string
{
    $input = trim((string) $input);
    if ($input === '') {
        return null;
    }

    // Seragamkan pemisah "/" menjadi "-" agar cukup satu pola regex
    $input = str_replace('/', '-', $input);

    // yyyy-mm-dd (format asli input type="date" / hasil normalisasi lain)
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $input, $m)) {
        [$_, $y, $mo, $d] = $m;
    // dd-mm-yyyy (format umum yang diketik pengguna Indonesia)
    } elseif (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $input, $m)) {
        [$_, $d, $mo, $y] = $m;
    } else {
        return null;
    }

    if (!checkdate((int) $mo, (int) $d, (int) $y)) {
        return null;
    }

    return sprintf('%04d-%02d-%02d', (int) $y, (int) $mo, (int) $d);
}
