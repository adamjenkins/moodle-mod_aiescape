## [1.1.3] - 2026-09-24

### Changed

- **Releases are now published to Moodle Marketplace automatically.** A new GitHub Actions workflow (`.github/workflows/moodle-release.yml`) submits each tagged version to Moodle Marketplace. This release makes no changes to the plugin's code or database. It re-publishes the 1.1.2 fix below, which never reached Marketplace because the release workflow had been retired before 1.1.2 was tagged.

## Included from [1.1.2] - 2026-08-22

### Fixed

- **Database schema realigned for sites that installed 1.0.0.** The 1.0.0 `db/install.xml` declared `personaname` as `CHAR NOT NULL` with an empty string as its default — a combination XMLDB rejects, silently dropping the default and logging "XMLDB has detected one CHAR NOT NULL column (personaname) with '' (empty string) as DEFAULT value" every time the file is parsed. The XML itself was corrected in 1.0.1, but nothing realigned databases already created from the 1.0.0 definition, so those sites keep a `NOT NULL` column that permanently mismatches `install.xml` under Site administration ▸ Development ▸ Check database schema. An upgrade step now makes the column nullable, matching `install.xml`. Sites that upgraded to 1.0.0 rather than installing it fresh were never affected, and no stored data changes.
