import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { appEnv, browserRoot, dataDir, logFile, repoRoot, runFile } from './env';

/** Run one artisan command against the scratch database and return what it printed. */
export function artisan(args: string[], extraEnv: Record<string, string> = {}): string {
    return execFileSync('php', ['artisan', ...args], {
        cwd: repoRoot,
        env: { ...appEnv(), ...extraEnv },
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'pipe'],
        maxBuffer: 32 * 1024 * 1024,
    });
}

/** What global-setup generated for this run: the password every test account uses, and a tag that keeps names unique. */
export function run(): { password: string; tag: string } {
    return JSON.parse(fs.readFileSync(runFile, 'utf8'));
}

/** One operation of support/e2e.php (make a person, make a business, read rows back). */
export function op<T = any>(operation: Record<string, unknown>): T {
    const out = artisan(['tinker', `--execute=require base_path('tests/Browser/support/e2e.php');`], {
        E2E_OP: JSON.stringify(operation),
        E2E_PASSWORD: fs.existsSync(runFile) ? run().password : '',
    });
    const line = out.split('\n').find((l) => l.startsWith('E2E_RESULT:'));
    if (!line) {
        throw new Error(`e2e.php gave no result for ${operation.op}: ${out}`);
    }

    return JSON.parse(line.slice('E2E_RESULT:'.length));
}

export function rows<T = Record<string, any>>(table: string, where: Record<string, unknown> = {}): T[] {
    return op<T[]>({ op: 'rows', table, where });
}

export type Person = { id: number; name: string; email: string };

let counter = 0;

/** A short tag that is different for every call in every worker, so parallel projects never share a name. */
export function unique(prefix = ''): string {
    counter += 1;
    const stamp = `${Date.now().toString(36)}${process.pid.toString(36)}${counter.toString(36)}`;

    return `${prefix}${stamp}`;
}

export function makeUser(name: string, options: { moderator?: boolean } = {}): Person {
    const email = `${unique('e2e-')}@infodot.test`;

    return op<Person>({ op: 'make-user', name, email, moderator: options.moderator ?? false });
}

export type Business = { id: number; uuid: string; name: string };

export function makeBusiness(owner: Person, name: string, visibility: 'public' | 'members' | 'private', description: string | null = 'A business made for the browser tests.'): Business {
    return op<Business>({ op: 'make-business', owner: owner.email, name, visibility, description });
}

export function addMember(business: Business, person: Person, role = 'editor'): void {
    op({ op: 'add-member', team_id: business.id, email: person.email, role });
}

export function fixture(name: string): string {
    return path.join(dataDir, 'fixtures', name);
}

/**
 * The verification link the app emailed to this address. MAIL_MAILER=log writes the whole message to the log, so the
 * test reads the newest link for the address and opens it, exactly as the person would from their inbox.
 */
export function verificationLink(email: string): string {
    const log = fs.readFileSync(logFile, 'utf8');
    const at = log.lastIndexOf(`To: ${email}`);
    if (at === -1) {
        throw new Error(`No mail to ${email} in ${path.relative(browserRoot, logFile)}`);
    }

    // The message body is quoted-printable: undo the soft line breaks and the =3D escapes before looking for the link.
    const message = log.slice(at, at + 20000).replace(/=\r?\n/g, '').replace(/=3D/g, '=').replace(/&amp;/g, '&');
    const match = message.match(/https?:\/\/[^\s"'<>\]]+\/email\/verify\/\d+\/[a-f0-9]+\?[^\s"'<>\]]+/);
    if (!match) {
        throw new Error(`The mail to ${email} has no verification link`);
    }

    return match[0];
}
