import crypto from 'node:crypto';

/**
 * The six-digit code an authenticator app shows for a base32 secret now (RFC 6238: 30-second steps, HMAC-SHA1), the
 * same as the app checks with Google2FA. Used only to answer the run's own moderators' two-factor challenge.
 */
export function totpCode(secret: string, at: number = Date.now()): string {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = '';
    for (const char of secret.replace(/=+$/, '').toUpperCase()) {
        bits += alphabet.indexOf(char).toString(2).padStart(5, '0');
    }
    const key = Buffer.from(bits.match(/.{8}/g)!.map((byte) => parseInt(byte, 2)));

    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(Math.floor(at / 1000 / 30)));
    const hmac = crypto.createHmac('sha1', key).update(counter).digest();
    const offset = hmac[hmac.length - 1] & 0x0f;
    const value = (hmac.readUInt32BE(offset) & 0x7fffffff) % 1_000_000;

    return String(value).padStart(6, '0');
}

/** The account's own secret, made from the run's secret and its address exactly as support/e2e.php makes it. */
export function secretFor(runSecret: string, email: string): string {
    const hex = crypto.createHash('sha256').update(`${runSecret}|${email.toLowerCase()}`).digest('hex').slice(0, 32);

    return hex.replace(/[0-9a-f]/g, (c) => 'ABCDEFGHIJKLMNOP'[parseInt(c, 16)]);
}
