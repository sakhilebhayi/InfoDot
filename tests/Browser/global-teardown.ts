import { writeAccessibilityReport } from './support/a11y-report';

/** After the run: turn what the accessibility specs recorded into docs/programme/r49/ACCESSIBILITY_SCAN.md. */
export default async function globalTeardown(): Promise<void> {
    writeAccessibilityReport();
}
