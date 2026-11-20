<?php
require_once __DIR__ . '/error_handler.php';

/**
 * Guard autentikasi eksternal.
 *
 * Proyek ini saat ini TIDAK memiliki integrasi LDAP. Tidak ada koneksi,
 * bind, atau respons LDAP nyata yang dibuat di sini. Fungsi di bawah hanya
 * menyediakan titik pengaman: bila suatu hari verifier LDAP didaftarkan,
 * login hanya diterima jika verifier mengembalikan true. Timeout/error
 * verifier ditolak (fail closed) dan dicatat sebagai event.
 */

function obe_register_ldap_verifier(?callable $verifier): void
{
    $GLOBALS['obe_ldap_verifier'] = $verifier;
}

function obe_has_ldap_verifier(): bool
{
    return is_callable($GLOBALS['obe_ldap_verifier'] ?? null);
}

function obe_log_ldap_failure(Throwable $e, string $identity): string
{
    $incidentId = obe_incident_id();
    obe_write_error_log($incidentId, $e, 'ldap_failure');
    security_event('ldap_failure', 'error', [
        'identity'    => $identity,
        'incident_id' => $incidentId,
        'detail'      => obe_exception_summary($e),
    ]);
    return $incidentId;
}

function obe_verify_ldap(string $identity, string $password): bool
{
    if (!obe_has_ldap_verifier()) {
        // Belum ada integrasi LDAP: autentikasi lokal yang berlaku.
        return true;
    }

    try {
        $verified = call_user_func($GLOBALS['obe_ldap_verifier'], $identity, $password);
    } catch (Throwable $e) {
        obe_log_ldap_failure($e, $identity);
        throw $e;
    }

    if ($verified !== true) {
        security_event('ldap_failure', 'failed', [
            'identity' => $identity,
            'detail'   => 'Verifier eksternal menolak autentikasi',
        ]);
        return false;
    }

    security_event('ldap', 'success', ['identity' => $identity]);
    return true;
}
