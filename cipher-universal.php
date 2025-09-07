<?php
/**
 * cipher-universal.php
 * PHP ≥ 7.2, libsodium required
 * ----------------------------------------------------------
 * Двух-ступенчатый KDF:
 *   1. PBKDF2-HMAC-SHA512 (200 000 rounds) – защита от brute-force
 *   2. libsodium pwhash    – память+время
 * Пользователь вводит только ключ-фразу.
 */

declare(strict_types=1);

if (!extension_loaded('sodium')) {
    error_log('Sodium extension not available');
    throw new RuntimeException('Sodium extension required but not available');
}

/* ---------- CONSTANTS ---------- */
const PBKDF2_ROUNDS = 200_000;
const PBKDF2_SALT   = 32;                         // 256-бит
const NONCE_BYTES   = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
const SALT_LEN      = SODIUM_CRYPTO_PWHASH_SALTBYTES;

/* ---------- ВСПОМОГАТЕЛЬНЫЕ ---------- */
// amazonq-ignore-next-line
function pbkdf2_derive(string $pass, string $salt): string
{
    // 32 байта выхода
    return hash_pbkdf2('sha512', $pass, $salt, PBKDF2_ROUNDS, 32, true);
}

function derive_key_final(string $pass, string $salt): string
{
    // libsodium pwhash (Argon2id) – финальный этап
    return sodium_crypto_pwhash(
        32,
        $pass,
        $salt,
        SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
        SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE
    );
}

function build_sbox(string $key32, int $counter): array
{
    $nonce  = str_pad(pack('P', $counter), NONCE_BYTES, "\0", STR_PAD_LEFT);
    $stream = sodium_crypto_stream(256, $nonce, $key32);
    $sbox   = range(0, 255);
    for ($i = 255; $i > 0; --$i) {
        $j = ord($stream[$i]) % ($i + 1);
        [$sbox[$i], $sbox[$j]] = [$sbox[$j], $sbox[$i]];
    }
    return $sbox;
}

function apply_sbox(string $data, array $sbox, bool $reverse = false): string
{
    $out = '';
    $map = $reverse ? array_flip($sbox) : $sbox;
    for ($i = 0, $len = strlen($data); $i < $len; ++$i) {
        $out .= chr($map[ord($data[$i])]);
    }
    return $out;
}

// amazonq-ignore-next-line
function compress(string $in): string   { return gzencode($in, 9); }
function decompress(string $in): string { 
    $result = gzdecode($in);
    if ($result === false) {
        error_log('Decompression failed for data length: ' . strlen($in));
        throw new RuntimeException('Decompression failed');
    }
    return $result;
}

/* ---------- ENCRYPT ---------- */
function encrypt_msg(string $plain, string $pass): string
{
    // 1) PBKDF2 + соль
    $pbkdf2Salt = random_bytes(PBKDF2_SALT);
    $pbkdf2Key  = pbkdf2_derive($pass, $pbkdf2Salt);

    // 2) libsodium pwhash + соль
    $sodiumSalt = random_bytes(SALT_LEN);
    $key        = derive_key_final($pbkdf2Key, $sodiumSalt);

    // 3) S-box + AEAD
    $plain   = compress($plain);
    $counter = random_int(0, 0x7fffffff);
    $cntBin  = pack('P', $counter);

    $sbox = build_sbox($key, $counter);
    $step = apply_sbox($plain, $sbox);

    $nonce  = random_bytes(NONCE_BYTES);
    $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
        $step,
        $cntBin,
        $nonce,
        $key
    );

    // packet: pbkdf2_salt | sodium_salt | counter | nonce | ciphertext
    $packet = $pbkdf2Salt . $sodiumSalt . $cntBin . $nonce . $cipher;
    return base64_encode($packet);
}

/* ---------- DECRYPT ---------- */
function decrypt_msg(string $b64, string $pass): string
{
    $data = base64_decode($b64, true);
    $need = PBKDF2_SALT + SALT_LEN + 8 + NONCE_BYTES + 16;
    if ($data === false || strlen($data) < $need) {
        return '❌ Повреждённые данные';
    }

    $pbkdf2Salt = substr($data, 0, PBKDF2_SALT);
    $sodiumSalt = substr($data, PBKDF2_SALT, SALT_LEN);
    $cntBin     = substr($data, PBKDF2_SALT + SALT_LEN, 8);
    $nonce      = substr($data, PBKDF2_SALT + SALT_LEN + 8, NONCE_BYTES);
    $cipher     = substr($data, PBKDF2_SALT + SALT_LEN + 8 + NONCE_BYTES);

    // 1) PBKDF2
    $pbkdf2Key = pbkdf2_derive($pass, $pbkdf2Salt);
    // 2) libsodium
    $key = derive_key_final($pbkdf2Key, $sodiumSalt);

    // 3) AEAD
    $step = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
        $cipher,
        $cntBin,
        $nonce,
        $key
    );
    if ($step === false) return '❌ Неверный пароль или повреждённые данные';

    // 4) S-box
    $counter = unpack('P', $cntBin)[1];
    $sbox    = build_sbox($key, $counter);
    $plain   = apply_sbox($step, $sbox, true);

    try {
        return decompress($plain);
    } catch (RuntimeException $e) {
        return '❌ Ошибка распаковки';
    }
}

/* ---------- FILE → PASSWORD ---------- */
function file_to_pass(array $f): string
{
    if (!isset($f['tmp_name']) || $f['error'] !== UPLOAD_ERR_OK) return '';
    return hash_file('sha256', $f['tmp_name'], true);
}