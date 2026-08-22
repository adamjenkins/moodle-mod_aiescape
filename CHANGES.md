## [1.1.2] - 2026-08-22

### Fixed

- **Database schema realigned for sites that installed 1.0.0.** The 1.0.0 `db/install.xml` declared `personaname` as `CHAR NOT NULL` with an empty string as its default — a combination XMLDB rejects, silently dropping the default and logging "XMLDB has detected one CHAR NOT NULL column (personaname) with '' (empty string) as DEFAULT value" every time the file is parsed. The XML itself was corrected in 1.0.1, but nothing realigned databases already created from the 1.0.0 definition, so those sites keep a `NOT NULL` column that permanently mismatches `install.xml` under Site administration ▸ Development ▸ Check database schema. An upgrade step now makes the column nullable, matching `install.xml`. Sites that upgraded to 1.0.0 rather than installing it fresh were never affected, and no stored data changes.
