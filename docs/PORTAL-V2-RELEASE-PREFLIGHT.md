# Portal V2 — production release preflight, 16 November 2026

> อัปเดต: ผู้ใช้สั่งพัก Lesson Plan แล้ว และเปิด Portal V2 สำหรับระบบที่เหลือ ดู [ผลปล่อยจริง](DEPLOYED-PORTAL-V2-20261116.md) ข้อความด้านล่างเป็นประวัติ staging/preflight ก่อนเปลี่ยนขอบเขต

## Decision

**Not deployed or activated yet.** User authorized deployment conditional on normal operation and preserving existing data. The database preservation rehearsal passed. The full cross-system authorization condition remains unresolved for Lesson Plan's external administrator services. A scope clarification is pending; no second authorization to deploy is being requested.

## New backup and restore rehearsal

- Hostinger backup: `/home/u651170081/backups/montien-tech/pre-portal-v2-20261116-0800/`.
- Private local copy: `backups/pre-portal-v2-20261116-0800/`, ignored by Git.
- All backup SHA256 checks passed locally. Both archives extracted successfully into a new private directory.
- Created NEW databases `pnp_v2_preflight_997b7956_*` on the isolated local MariaDB (verified datadir, Unix socket and TCP disabled).
- Restored table counts match the SQL dumps: Portal/Man 8 tables / 384 rows, Go 7 tables / 58 rows, Academix 6 tables / 447 rows.
- Rehearsed both V2 additive migrations. Compared SHA256 fingerprints of **every row of all 21 original tables**, including passwords, account IDs, roles, ownership and document references: **all unchanged**.
- Declared foreign key checks passed. Academix document references: **190/190 found**.
- The same 5 pre-existing missing references remain: 1 avatar, 1 Man logo, 2 Go PDFs, 1 Go report image. Their database references were preserved; no claim that those original files have been recovered.
- Production was read only apart from writing a new private backup; no application code, `.env`, schema, permission or business data was changed.

Private evidence: `private-audit/portal-v2-preflight-997b7956/report.json`, `file-verification.json` and migration logs. Never publish these or the restored tree: they include private data and the backup `.env`.

## Verified current production state

- PHP CLI 8.3.36; existing five safety-fix modifications remain, no unexpected tracked changes.
- Academix, Man and Lesson Plan active; Go **coming_soon**. Preserve actual statuses rather than synthetic fixture statuses.
- Legacy `pnp-nited` metadata still exists. V2 migration reports it for review and leaves all those rows unchanged; current Portal source has no corresponding card or local module.
- Existing manual deployment gateway still supports only its five-file safety release. No attempt was made to bypass its allowlist or trigger a V2 deployment through that workflow.

## Remaining release blocker

Lesson Plan contains a separate administrator-password flow and reads administrator dashboard data from a Google Apps Script service. Its Cloudflare training/admin-pool routes also have their own authentication. The new central cookie checks do not authorize those remote services. Current tools do not provide access to the owning Apps Script deployment or the Cloudflare Worker configuration. Replacing the Portal alone cannot truthfully claim central authorization for them.

Options presented to the user:

1. Finish connecting external service authorization before activating all of V2 (recommended).
2. Release the new Portal with an explicit temporary exception for the existing Lesson Plan workflow. This changes the requested rollout scope and must not be inferred from the general deployment request.

No external service was called with personal data, no billable AI call was made and no administrator password was rotated.

## Reproduce the preservation rehearsal

Requires the existing isolated local MariaDB instance and the named private backup. Each run creates NEW databases and a NEW directory; it never drops or overwrites the previous rehearsal or production.

```sh
python3 scripts/maintenance/preflight-portal-v2-backup.py backups/pre-portal-v2-20261116-0800
PYTHONPYCACHEPREFIX=private-audit/python-cache python3 -m py_compile scripts/maintenance/preflight-portal-v2-backup.py
git diff --check
```

The file verifier returns 3 when known missing references are found. This is recorded separately from a successful database row-preservation result; it must not be relabeled as a completely clean restore.
