# Host agreement v1: approval record

| | |
|---|---|
| Version | v1 (InfoDot `SEMINARS_HOST_AGREEMENT_VERSION` and Dot.Billing `BILLING_MARKETPLACE_AGREEMENT_VERSION` both default to `v1`) |
| Text shown to hosts | `resources/markdown/host-agreement.md` (Part A, chargebacks; Part B, removal of a seminar by a moderator) |
| Annotated working draft (archive) | [host-agreement-v1-annotated.md](host-agreement-v1-annotated.md) |
| Approval | The owner told Claude on 2026-10-29 that the owner's adviser has approved the wording **as it is**. Claude did not see the adviser's review. |
| Adviser name and date of sign-off | **[OWNER TO FILL IN]** |
| Where the adviser's written sign-off is kept | **[OWNER TO FILL IN]** |
| Gate | `SEMINARS_HOST_AGREEMENT_APPROVED` stays **off** until the owner runs the `agreement` stage on the host (below). Nothing in this repository turns it on. |

## What was done to the approved wording

"As it is" was applied literally to the terms. The following edits removed annotations and fixed one placeholder; no term was added, dropped or changed.

1. Removed the DRAFT banner, the revision note, every bracketed `[Adviser: ...]` / `[owner: ...]` question, the "Open questions for the adviser" list and the "What the software does" notes. These were written for the adviser and must not be shown to hosts.
2. The introduction was rewritten in plain language (it described the software; it now says what the host is agreeing to).
3. **Placeholder filled: `[contact]` in A8 is now `support@infodot.co.za`**, the contact address the live Terms already use. The owner should confirm that is the right address for disputes about a debt.
4. A7: the draft said a moderator can write off "in whole or in part [the software writes off the whole remaining debt only]". It now says a moderator can write off "what you still owe", which is what the software does.
5. A6: added that a host can see what they owe on the payout page (true since #152).
6. B3: removed the bold sentence "This has not been tested against Paystack" and the bracketed question about who bears Paystack's fee on a refund caused by removal. The remaining sentence says the fee treatment "depends on how Paystack apportions refunds for split payments". **That behaviour is still untested; the Paystack test-mode run in `R13_GO_LIVE.md` should include a refund of a split payment before real tickets are sold.**

If the adviser's signed text differs from `host-agreement.md` in any word, replace the file with the signed text, keep the version label only if no host has accepted anything, and tell Claude.

## Before turning it on (owner, on the host)

1. Confirm nobody has accepted anything, so `v1` can stay `v1` (read-only):

```bash
cd /home/infodotc/public_html/main && php artisan tinker --execute="echo DB::table('seminar_host_agreements')->count();"
```

   (`/home/infodotc/public_html/main` is the InfoDot folder on the host: the one that holds `.env` and `tools/`.)
   If the count is not 0, tell Claude: either the old acceptances need a new version label (`--agreement-version v2`, set the same value in Billing) or they accepted a wording that is now different.
2. Deploy InfoDot first, so the host has the approved text and #158's migration. Dot.Billing needs no deploy for this (its copy is documentation only).
3. Run the agreement stage, preview first (nothing changes without `--apply`):

```bash
tools/r13-enable.sh --infodot /home/infodotc/public_html/main --stage agreement --confirm-agreement-reviewed
```

   then, if the preview is what you expect, the same command with `--apply`. The script flips `SEMINARS_HOST_AGREEMENT_APPROVED` to true and names only the variable it set. Re-run the deploy or `php artisan config:cache` so the value is read.
4. As a host with a payout account, open Seminars > payouts, read both parts and accept. Dot.Billing will then allow tickets for that host.
