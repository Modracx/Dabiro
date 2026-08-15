# Dabiro

[![Version](https://img.shields.io/badge/version-2.0.0-blue.svg)](https://github.com/Modracx/Dabiro)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Themes](https://img.shields.io/badge/themes-7-orange.svg)](https://github.com/Modracx/Dabiro)

**A single-file database manager that stays out of your way.** Drop one file on a
server and manage **MySQL/MariaDB**, **PostgreSQL** and **SQLite** from a fast,
keyboard-driven web interface. Available as a zero-dependency **PHP** file or a
**Node.js** server.

---

## Contents

- [Quick start](#quick-start)
- [SSH tunnels](#ssh-tunnels)
- [Features](#features)
- [Keyboard shortcuts](#keyboard-shortcuts)
- [Configuration](#configuration)
- [Security](#security)
- [Supported databases](#supported-databases)
- [Troubleshooting](#troubleshooting)
- [Editions](#editions)
- [License](#license)

---

## Quick start

### PHP

```bash
cp php/dabiro.php /var/www/html/
chmod 644 /var/www/html/dabiro.php
# open https://your-server/dabiro.php
```

Requires **PHP 7.4+** with PDO and the driver for your database
(`pdo_mysql`, `pdo_pgsql`, `pdo_sqlite`). No Composer, no build step, no other
files.

### Node.js

```bash
cd node/
npm install
SESSION_SECRET=$(openssl rand -hex 32) npm start
# open http://localhost:5050
```

Requires **Node 18+**.

---

## SSH tunnels

If your database is only reachable from a bastion, you would normally run:

```bash
ssh -L 5432:localhost:5432 user@bastion.example.com
```

Dabiro does that for you. Pick the **SSH Tunnel** tab on the connect screen, fill
in the bastion details, and enter the database host **as the bastion sees it** —
usually `localhost`.

**Authentication** — private key (pasted or a path on the server), key with a
passphrase, password, or the machine's own SSH agent/config. Nothing extra has to
be installed on either end:

- The **PHP** edition drives the stock `ssh` client and handles passwords through
  `SSH_ASKPASS`, so `sshpass` is *not* required. The secret goes into a
  short-lived `0600` file rather than the command line, where `ps` would leak it.
- The **Node** edition speaks SSH in-process via `ssh2`, so there is no child
  process at all.

**The tunnel is supervised.** Dabiro records the tunnel it owns, checks that it is
actually accepting connections before every query, and silently rebuilds it if it
has dropped — including onto a different local port. Failures are reported in
plain language ("SSH refused the credentials", "the server refused to open the
forward") with the raw `ssh` output underneath, instead of surfacing as a generic
database error.

Host keys are verified on a trust-on-first-use basis and stored in the data
directory, so a swapped host key is caught rather than blindly accepted.

---

## Features

### Databases and tables
- Sizes, row counts, engine and collation at a glance, with per-database totals
- Row counts above 250k use the planner's estimate (shown as `~`) instead of a
  slow `COUNT(*)`; filtered counts are always exact
- Create and drop databases; create, rename, copy, empty, optimise and drop tables
- Bulk drop / empty / optimise across selected tables

### PostgreSQL, properly
- Switching database reconnects (PostgreSQL has no `USE`)
- **Schemas are first-class** — switch between them, create them, and see which
  one you are in. Linking to a table without naming a schema finds the schema
  that actually holds it.

### Browsing and editing
- Multi-condition filters (`LIKE`, `IN`, `IS NULL`, ranges, …) with bound values
- Sort, paginate, and choose page size
- **Double-click any cell to edit it in place**
- Insert/edit forms with explicit `NULL` handling and type hints
- Rows are addressed by primary key, so `NULL`s and float columns no longer make
  a row uneditable

### SQL console
- Syntax highlighting, and autocomplete over your live tables and columns
- Runs multi-statement scripts, one result tab per statement, each timed
- Query history, one-click formatting, `Ctrl`+`Enter` to run

### Import and export
- Export to **SQL, JSON, CSV or XML**, streamed row by row so table size is not
  limited by memory. Pick specific tables, and structure-only if you want.
- Import `.sql` files, split correctly around strings, comments and `DELIMITER`
  blocks, wrapped in a transaction with optional rollback on first error

### Structure
- Columns, indexes and foreign keys, with the `CREATE TABLE` definition
- Add/modify/drop columns and indexes. On PostgreSQL, editing a column applies
  type, nullability and default changes — not just a rename.

### Interface
- 7 themes (Light, Dark, Slate, Blue, Green, Purple, Sunset), respected everywhere
- [Lucide](https://lucide.dev) icons, inlined, with motion written in plain CSS —
  no icon font, no CDN, no JavaScript animation library
- Responsive down to phone width; honours `prefers-reduced-motion`
- Global search across every text column in a database

### Saved connections
Optional, and encrypted. Connections are sealed with AES-256-GCM under a key
derived from a master password (PBKDF2-SHA256, 310k iterations) and written
`0600`. The master password is never stored — lose it and the file is
unrecoverable, by design. Passwords and keys are never sent back to the browser.

Both editions read and write the same vault format.

---

## Keyboard shortcuts

| Key | Action |
|---|---|
| `Ctrl`/`⌘` + `K`, or `/` | Command palette — jump to any database, schema or table |
| `Ctrl`/`⌘` + `Enter` | Run the query in the SQL console |
| `Ctrl`/`⌘` + `Space` | Autocomplete in the SQL console |
| `Esc` | Close the palette or any dialog |
| Double-click | Edit a cell in place |

---

## Configuration

| Variable | Applies to | Purpose |
|---|---|---|
| `DABIRO_DATA_DIR` | both | Where tunnel state, `known_hosts` and the connection vault live. **Point this outside your web root.** Defaults to a `.dabiro` directory in the system temp dir. |
| `SESSION_SECRET` | Node | Signs session cookies. Set it, or sessions are dropped on every restart. |
| `DABIRO_TRUST_PROXY` | both | Set to `1` **only** when Dabiro sits behind a reverse proxy that terminates TLS. It makes Dabiro believe `X-Forwarded-Proto`, which is what lets the session cookie be issued as `Secure`. Leave it unset otherwise - setting it while the browser is on plain HTTP causes a login loop. |
| `PORT` / `HOST` | Node | Listen address. Defaults to `127.0.0.1:5050`. |

Without a writable data directory Dabiro still runs — SSH tunnelling and saved
connections simply switch themselves off and say why.

---

## Security

Dabiro is a tool for handing a database's credentials to a web page. Treat it
accordingly:

- **Always serve it over HTTPS.** Credentials are posted in the clear otherwise.
- **Put it behind another auth layer** (HTTP basic auth, a VPN, an IP allowlist).
  Dabiro has no user accounts of its own — whoever reaches the page can try to
  connect to any database they have credentials for.
- **Do not leave it deployed** on a public host when you are not using it.
- `DABIRO_DATA_DIR` should not be web-servable. Dabiro writes a defensive
  `.htaccess` there, but that only helps on Apache.

Built in: CSRF tokens on every mutating request (compared in constant time),
session regeneration on login, a 1-hour idle timeout, `HttpOnly`/`SameSite`
cookies, `secure` cookies when HTTPS is detected, and typed confirmation before
dropping a table.

User-supplied values are passed as bound parameters. Identifiers are quoted and
validated against the live catalogue, and the few places where an identifier
cannot be bound (storage engine, collation) use a whitelist.

---

## Supported databases

| Database | Minimum | Notes |
|---|---|---|
| MySQL | 5.7+ | |
| MariaDB | 10.0+ | |
| PostgreSQL | 9.4+ | Multi-schema support |
| SQLite | 3.x | Single file; some size metrics are unavailable |

---

## Troubleshooting

**"Nothing is listening on that host and port"** — the database is not reachable
from the machine running Dabiro. If it lives behind a bastion, use the SSH Tunnel
tab.

**SSH tunnel fails with "refused the credentials"** — check the username and the
key/password. With *Use this server's SSH agent*, the agent must be reachable by
the web-server user (PHP-FPM usually has no `SSH_AUTH_SOCK`); choose key or
password auth instead.

**"Host key verification failed"** — the bastion's host key changed. Remove the
stale line from `$DABIRO_DATA_DIR/known_hosts` after confirming why it changed.

**PHP: "the `ssh` client was not found"** — install `openssh-client`. If
`shell_exec` is disabled by your host, SSH tunnelling cannot work; use the Node
edition or an external tunnel.

**Node: SSH tab disabled** — run `npm install ssh2`.

**Stuck in a login loop** - you sign in, and land straight back on the login
screen. Dabiro detects this and explains the cause on the login page itself. The
usual reason is the session cookie being marked `Secure` while the browser is on
plain HTTP, which makes the browser silently discard it: unset
`DABIRO_TRUST_PROXY`, or switch to HTTPS. Other causes it will report are an
unwritable `session.save_path` and blocked cookies.

**Import fails on a large file** — raise `upload_max_filesize` and `post_max_size`
(PHP). The Node edition accepts up to 64 MB.

**Row counts show `~`** — that is a planner estimate, used above 250k rows to keep
the page fast. Filter the table to get an exact count.

---

## Editions

Both editions expose the same features and render the same interface; the
stylesheet, icon sprite and client-side code are generated from one shared source.

| | PHP | Node.js |
|---|---|---|
| Install | copy one file | `npm install` |
| Dependencies | none (PDO only) | express, mysql2, pg, sqlite3, ssh2 |
| SSH tunnel | stock `ssh` client + `SSH_ASKPASS` | in-process (`ssh2`) |
| Best for | shared hosting, cPanel, quick drops | long-running servers, containers |

Language selection is present and 13 languages are listed, but only English
currently ships translated strings — everything else falls back to English. The
string table is a single object; adding a language means adding its keys.

---

## License

MIT. See [LICENSE](LICENSE).

Icons by [Lucide](https://lucide.dev) (ISC).
