import { rows } from './app';

export type Stop = { name: string; path: string };

/** The demonstration accounts `php artisan demo:install --with-moderator` creates (database/demo/content.php). */
export const demo = {
    owner: 'naledi@demo.infodot.test',
    colleague: 'sipho@demo.infodot.test',
    outsider: 'lerato@demo.infodot.test',
    moderator: 'thabo@demo.infodot.test',
};

/** The development baseline accounts of DemoSeeder. */
export const seededAccounts = ['demo-owner@infodot.test', 'demo-editor@infodot.test', 'demo-member@infodot.test'];

export const demoCompany = 'Ledger Works (Demonstration)';

/** The pages a visitor reads before signing in. */
export function publicStops(): Stop[] {
    return [
        { name: 'public-home', path: '/' },
        { name: 'public-what-it-does', path: '/features' },
        { name: 'public-about', path: '/about' },
        { name: 'public-questions-people-ask', path: '/faqs' },
        { name: 'public-browse-answers', path: '/solution-results' },
        { name: 'public-businesses', path: '/businesses' },
        { name: 'public-contact', path: '/contact' },
        { name: 'public-security', path: '/security' },
        { name: 'public-sign-in', path: '/login' },
        { name: 'public-create-account', path: '/register' },
    ];
}

/** The signed-in pages the demonstration owner walks through, in order. */
export function ownerStops(): Stop[] {
    const company = rows('teams', { name: demoCompany })[0];
    const owner = rows('users', { email: demo.owner })[0];
    const question = rows('questions', { user_id: owner.id, visibility: 'public' })[0];
    const solution = rows('solutions', { team_id: company.id, visibility: 'public' })[0];

    return [
        { name: 'home', path: '/home' },
        { name: 'knowledge-questions', path: '/questions' },
        { name: 'knowledge-question', path: `/question/view/${question.id}` },
        { name: 'knowledge-ask', path: '/questions/ask' },
        { name: 'knowledge-solutions', path: '/solutions' },
        { name: 'knowledge-solution', path: `/solution/view/${solution.id}` },
        { name: 'knowledge-write-solution', path: '/solution/create' },
        { name: 'knowledge-topics', path: '/topics' },
        { name: 'library', path: '/library' },
        { name: 'businesses', path: '/businesses' },
        { name: 'business-page', path: `/business/${company.uuid}` },
        { name: 'business-profile', path: `/business/${company.uuid}/edit` },
        { name: 'business-inbox', path: `/business/${company.uuid}/inbox` },
        { name: 'seminars', path: '/seminars' },
        { name: 'seminars-new', path: '/seminars/new' },
        { name: 'platforms', path: '/platforms' },
        { name: 'people', path: '/people' },
        { name: 'analytics', path: '/analytics' },
        { name: 'inbox', path: '/inbox' },
        { name: 'notifications', path: '/notifications' },
        { name: 'account-settings', path: '/user/profile/edit' },
        { name: 'account-profile', path: `/user/profile/${owner.id}` },
        { name: 'account-points', path: '/points' },
        { name: 'account-collections', path: '/collections' },
        { name: 'team-settings', path: `/teams/${company.id}` },
        { name: 'team-create', path: '/teams/create' },
    ];
}

/** What a moderator sees on top of that. */
export function moderatorStops(): Stop[] {
    return [
        { name: 'moderation-queue', path: '/moderation' },
        { name: 'moderation-accounts', path: '/moderation/accounts' },
        { name: 'moderation-library', path: '/moderation/library' },
        { name: 'moderation-topics', path: '/moderation/topics' },
        { name: 'moderation-trust', path: '/moderation/trust' },
        { name: 'moderation-verifications', path: '/moderation/verifications' },
        { name: 'moderation-identity', path: '/moderation/identity' },
        { name: 'moderation-seminars', path: '/moderation/seminars' },
        { name: 'moderation-friction', path: '/moderation/friction' },
    ];
}
