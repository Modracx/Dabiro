'use strict';
/**
 * Dabiro - Professional Edition
 * A single-file database management system with modern, responsive UI
 * Version: 1.0.2
 * Kenneth D'silva (Modracx), Copyright (c) March 2026
 * Licensed under the MIT License – https://opensource.org/licenses/MIT
 */

const express      = require('express');
const session      = require('express-session');
const cookieParser = require('cookie-parser');
const multer       = require('multer');
const crypto       = require('crypto');

// Optional DB drivers – installed via npm
let mysql2, pg, Sqlite3;
try { mysql2   = require('mysql2/promise'); }   catch (_) {}
try { pg       = require('pg');             }   catch (_) {}
try { Sqlite3  = require('sqlite3').verbose(); } catch (_) {}

// ─── Config ───────────────────────────────────────────────────────────────────
const VERSION          = '1.0.2';
const SESSION_TIMEOUT  = 3_600_000; // 1 h in ms
const PORT             = process.env.PORT || 5050;
const SESSION_SECRET   = process.env.SESSION_SECRET || crypto.randomBytes(32).toString('hex');

const THEME_OPTIONS = { light:'Light', dark:'Dark', blue:'Blue', green:'Green', purple:'Purple', sunset:'Sunset', slate:'Slate' };
const LANG_OPTIONS  = { en:'English', es:'Español', fr:'Français', de:'Deutsch', pt:'Português', zh:'中文', ja:'日本語', ar:'العربية', it:'Italiano', ru:'Русский', tr:'Türkçe', hi:'हिन्दी', ko:'한국어' };
const ALLOWED_COL_TYPES = ['INT','TINYINT','SMALLINT','MEDIUMINT','BIGINT','FLOAT','DOUBLE','DECIMAL','NUMERIC','REAL','CHAR','VARCHAR','TINYTEXT','TEXT','MEDIUMTEXT','LONGTEXT','DATE','DATETIME','TIMESTAMP','TIME','YEAR','BOOLEAN','BOOL','BLOB','TINYBLOB','MEDIUMBLOB','LONGBLOB','JSON','UUID','SERIAL','BIGSERIAL','SMALLSERIAL','BYTEA','CIDR','INET','MACADDR'];

// ─── App setup ────────────────────────────────────────────────────────────────
const app = express();
app.use(express.urlencoded({ extended: true, limit: '50mb' }));
app.use(express.json({ limit: '50mb' }));
app.use(cookieParser());
app.use(session({
    secret: SESSION_SECRET, resave: false, saveUninitialized: false,
    cookie: { httpOnly: true, maxAge: SESSION_TIMEOUT }
}));
const upload = multer({ storage: multer.memoryStorage(), limits: { fileSize: 10 * 1024 * 1024 } });

// ─── Helpers ──────────────────────────────────────────────────────────────────
const h = v => v == null ? '' : String(v).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#x27;');

function csrfToken(req) {
    if (!req.session.csrf_token) req.session.csrf_token = crypto.randomBytes(32).toString('hex');
    return req.session.csrf_token;
}
function validateCsrf(req, token) {
    if (!token || !req.session.csrf_token) return false;
    try {
        const a = Buffer.from(String(req.session.csrf_token));
        const b = Buffer.from(String(token));
        return a.length === b.length && crypto.timingSafeEqual(a, b);
    } catch { return false; }
}
function gp(body, key, def = '') { return body?.[key] ?? def; }
function gg(query, key, def = '') { return query?.[key] ?? def; }
function getLang(req)  { const v = req.cookies.dbadmin_lang  || 'en';  return LANG_OPTIONS[v]  ? v : 'en'; }
function getTheme(req) { const v = req.cookies.dbadmin_theme || 'light'; return THEME_OPTIONS[v] ? v : 'light'; }
function setCookieYear(res, name, value) { res.cookie(name, value, { maxAge: 365*24*3600*1000, path: '/' }); }

// ─── DbConnection ─────────────────────────────────────────────────────────────
class DbConnection {
    constructor() { this.conn = null; this.type = ''; }

    async connect(type, host, user, pass, dbname = '') {
        this.type = type;
        try {
            if (type === 'mysql') {
                if (!mysql2) return 'mysql2 not installed. Run: npm install mysql2';
                const [h, p = '3306'] = host.includes(':') ? host.split(':') : [host, '3306'];
                this.conn = await mysql2.createConnection({ host: h, port: +p, user, password: pass, database: dbname || undefined, multipleStatements: true });
            } else if (type === 'pgsql') {
                if (!pg) return 'pg not installed. Run: npm install pg';
                const [h, p = '5432'] = host.includes(':') ? host.split(':') : [host, '5432'];
                this.conn = new pg.Client({ host: h, port: +p, user, password: pass, database: dbname || 'postgres' });
                await this.conn.connect();
            } else if (type === 'sqlite') {
                if (!Sqlite3) return 'sqlite3 not installed. Run: npm install sqlite3';
                await new Promise((resolve, reject) => {
                    this.conn = new Sqlite3.Database(host, err => err ? reject(err) : resolve());
                });
            } else { return 'Unsupported database type'; }
            return true;
        } catch (e) { return e.message; }
    }

    async close() {
        if (!this.conn) return;
        try { if (this.type === 'mysql') await this.conn.end(); else if (this.type === 'pgsql') await this.conn.end(); else if (this.type === 'sqlite') await new Promise(r => this.conn.close(r)); } catch (_) {}
        this.conn = null;
    }

    quoteId(name) { return this.type === 'pgsql' ? '"' + String(name).replace(/"/g,'""') + '"' : '`' + String(name).replace(/`/g,'``') + '`'; }
    quoteVal(v)   { if (v == null) return 'NULL'; const s = String(v); return this.type === 'pgsql' ? "'" + s.replace(/'/g,"''") + "'" : "'" + s.replace(/\\/g,'\\\\').replace(/'/g,"\\'") + "'"; }
    fieldName(col){ return col.Field ?? col.column_name ?? col.name ?? ''; }

    async run(sql, params = []) {
        if (this.type === 'mysql') { const [rows] = await this.conn.query(sql, params); return Array.isArray(rows) ? rows : []; }
        if (this.type === 'pgsql') { const r = await this.conn.query(sql, params); return r.rows; }
        if (this.type === 'sqlite') {
            if (/^\s*(SELECT|PRAGMA|WITH)/i.test(sql)) {
                return await new Promise((res, rej) => this.conn.all(sql, params, (err, rows) => err ? rej(err) : res(rows || [])));
            } else {
                return await new Promise((res, rej) => this.conn.run(sql, params, err => err ? rej(err) : res([])));
            }
        }
        return [];
    }
    async exec(sql) {
        if (this.type === 'mysql') await this.conn.query(sql);
        else if (this.type === 'pgsql') await this.conn.query(sql);
        else if (this.type === 'sqlite') await new Promise((res, rej) => this.conn.exec(sql, err => err ? rej(err) : res()));
    }
    async getDatabases() {
        if (this.type === 'mysql') return (await this.run('SHOW DATABASES')).map(r => Object.values(r)[0]);
        if (this.type === 'pgsql') return (await this.run('SELECT datname FROM pg_database WHERE datistemplate=false ORDER BY datname')).map(r => r.datname);
        return ['main'];
    }
    async getTables(database) {
        if (this.type === 'mysql') { if (database) await this.run(`USE ${this.quoteId(database)}`); return (await this.run('SHOW TABLES')).map(r => Object.values(r)[0]); }
        if (this.type === 'pgsql') return (await this.run("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename")).map(r => r.tablename);
        if (this.type === 'sqlite') return (await this.run("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")).map(r => r.name);
        return [];
    }
    async getColumns(table) {
        if (this.type === 'mysql') return await this.run(`SHOW COLUMNS FROM ${this.quoteId(table)}`);
        if (this.type === 'pgsql') return await this.run(`SELECT column_name as "Field", data_type as "Type", is_nullable as "Null", column_default as "Default" FROM information_schema.columns WHERE table_name=$1 ORDER BY ordinal_position`, [table]);
        if (this.type === 'sqlite') return await this.run(`PRAGMA table_info(${this.quoteId(table)})`);
        return [];
    }
}

// ─── Auth ─────────────────────────────────────────────────────────────────────
function isLoggedIn(req) { return req.session.logged_in === true; }

async function getDb(req) {
    if (!isLoggedIn(req)) return null;
    if (Date.now() - req.session.last_activity > SESSION_TIMEOUT) { req.session.destroy(() => {}); return null; }
    req.session.last_activity = Date.now();
    const db = new DbConnection();
    const ok = await db.connect(req.session.db_type, req.session.db_host, req.session.db_user, req.session.db_pass, req.session.db_name || '');
    return ok === true ? db : null;
}

// ─── Main route ───────────────────────────────────────────────────────────────
app.all('/', upload.single('sql_file'), async (req, res) => {
    const body  = req.body  || {};
    const query = req.query || {};
    let error = '', success = '', errorMsg = '', successMsg = '';

    // ── Preferences (theme / lang) ──
    if (query.set_theme && THEME_OPTIONS[query.set_theme]) { setCookieYear(res, 'dbadmin_theme', query.set_theme); }
    if (query.set_lang  && LANG_OPTIONS[query.set_lang])   { setCookieYear(res, 'dbadmin_lang',  query.set_lang);  }

    // ── Login ──
    if (body.login) {
        if (!validateCsrf(req, body.csrf_token)) {
            error = 'Security token validation failed. Please refresh and try again.';
        } else {
            const db = new DbConnection();
            const result = await db.connect(body.db_type, body.db_host, body.db_user, body.db_pass, body.db_name || '');
            if (result === true) {
                await db.close();
                req.session.regenerate(err => {});
                req.session.logged_in    = true;
                req.session.db_type      = body.db_type;
                req.session.db_host      = body.db_host;
                req.session.db_user      = body.db_user;
                req.session.db_pass      = body.db_pass;
                req.session.db_name      = body.db_name || '';
                req.session.last_activity = Date.now();
                const dest = body.db_name ? `/?page=tables&db=${encodeURIComponent(body.db_name)}` : '/?page=databases';
                return res.redirect(dest);
            } else { error = result; }
        }
    }

    // ── Logout ──
    if ((body.logout && validateCsrf(req, body.csrf_token)) || (query.action === 'logout' && validateCsrf(req, query.csrf_token))) {
        req.session.destroy(() => res.redirect('/'));
        return;
    }

    // ── AJAX: get_tables ──
    if (query.action === 'get_tables' && query.db) {
        if (!isLoggedIn(req)) return res.json([]);
        const db = await getDb(req);
        if (!db) return res.json([]);
        try { const t = await db.getTables(query.db); await db.close(); res.json(t); } catch (_) { res.json([]); }
        return;
    }

    // ── Require login for all below ──
    if (!isLoggedIn(req)) {
        const ctx = { csrf: csrfToken(req), error, theme: getTheme(req), lang: getLang(req), req };
        return res.send(renderShell(ctx, renderLoginForm(ctx), true));
    }

    const db = await getDb(req);
    if (!db) { req.session.destroy(() => {}); return res.redirect('/'); }

    // ── POST actions ──
    if (req.method === 'POST') {

        // Create table
        if (body.create_table) {
            if (!validateCsrf(req, body.csrf_token)) { errorMsg = 'Security token validation failed.'; }
            else {
                try {
                    if (db.type !== 'sqlite' && body.create_table_db) await db.run(`USE ${db.quoteId(body.create_table_db)}`);
                    await db.exec(body.create_table_sql);
                    successMsg = 'Table created successfully.';
                } catch (e) { errorMsg = 'Create table failed: ' + e.message; }
            }
        }

        // Add column
        if (body.add_column) {
            if (!validateCsrf(req, body.csrf_token)) { errorMsg = 'Security token validation failed.'; }
            else {
                const tbl = body.add_column_table, colName = body.new_col_name, colTypeRaw = body.new_col_type;
                const colTypeUpper = (colTypeRaw || '').toUpperCase().trim();
                if (!ALLOWED_COL_TYPES.includes(colTypeUpper)) { errorMsg = `Invalid column type '${h(colTypeRaw)}'.`; }
                else if (tbl && colName && colTypeUpper) {
                    try {
                        let sql = `ALTER TABLE ${db.quoteId(tbl)} ADD COLUMN ${db.quoteId(colName)} ${colTypeUpper}`;
                        if (body.new_col_length && /^\d+(,\d+)?$/.test(body.new_col_length)) sql += `(${body.new_col_length})`;
                        sql += body.new_col_null ? ' NULL' : ' NOT NULL';
                        if (body.new_col_default !== '') {
                            sql += body.new_col_default?.toUpperCase() === 'NULL' ? ' DEFAULT NULL' : ` DEFAULT ${db.quoteVal(body.new_col_default)}`;
                        }
                        if (body.new_col_extra === 'AUTO_INCREMENT') sql += ' AUTO_INCREMENT';
                        await db.exec(sql);
                        successMsg = `Column '${h(colName)}' added to '${h(tbl)}' successfully.`;
                    } catch (e) { errorMsg = e.message; }
                }
            }
        }

        // Execute SQL
        if (body.execute_sql) {
            if (!validateCsrf(req, body.csrf_token)) { errorMsg = 'Security token validation failed.'; }
            else if (body.sql) {
                try {
                    const t0 = Date.now();
                    const rows = await db.run(body.sql);
                    req._sqlResult = rows;
                    req._sqlTime   = Date.now() - t0;
                } catch (e) { req._sqlError = e.message; }
            }
        }

        // Export query as file
        if (body.export_query && validateCsrf(req, body.csrf_token) && body.sql) {
            await db.close();
            res.setHeader('Content-Type', 'text/plain');
            res.setHeader('Content-Disposition', `attachment; filename="query_${dateStamp()}.sql"`);
            return res.send(`-- SQL Query Export\n-- Generated: ${new Date().toISOString()}\n-- Database: ${req.session.db_name || 'N/A'}\n\n${body.sql}`);
        }

        // Export database
        if (body.export_database && validateCsrf(req, body.csrf_token) && body.export_db_name) {
            const fmt = body.export_db_format || 'sql';
            const dbName = body.export_db_name;
            if (db.type !== 'sqlite') await db.run(`USE ${db.quoteId(dbName)}`);
            const tables = await db.getTables(dbName);
            const stamp  = dateStamp();
            if (fmt === 'sql') {
                res.setHeader('Content-Type', 'text/plain; charset=utf-8');
                res.setHeader('Content-Disposition', `attachment; filename="${dbName}_${stamp}.sql"`);
                let out = `-- Database Export: ${dbName}\n-- Generated: ${new Date().toISOString()}\n-- Server: ${req.session.db_host}\n\n`;
                for (const t of tables) {
                    out += `-- Table: ${t}\n`;
                    if (db.type === 'mysql') { try { const r = await db.run(`SHOW CREATE TABLE ${db.quoteId(t)}`); out += r[0]['Create Table'] + ';\n\n'; } catch (_) {} }
                    const rows = await db.run(`SELECT * FROM ${db.quoteId(t)}`);
                    for (const row of rows) { out += `INSERT INTO ${db.quoteId(t)} VALUES (${Object.values(row).map(v => db.quoteVal(v)).join(', ')});\n`; }
                    out += '\n';
                }
                await db.close(); return res.send(out);
            } else {
                const exportData = { database: dbName, exported_at: new Date().toISOString(), tables: {} };
                for (const t of tables) exportData.tables[t] = await db.run(`SELECT * FROM ${db.quoteId(t)}`);
                res.setHeader('Content-Type', 'application/json; charset=utf-8');
                res.setHeader('Content-Disposition', `attachment; filename="${dbName}_${stamp}.json"`);
                await db.close(); return res.send(JSON.stringify(exportData, null, 2));
            }
        }

        // Export table
        if (body.export_table_download && validateCsrf(req, body.csrf_token) && body.export_table) {
            const fmt = body.export_format, tbl = body.export_table, edb = body.export_database;
            if (db.type !== 'sqlite' && edb) await db.run(`USE ${db.quoteId(edb)}`);
            const rows  = await db.run(`SELECT * FROM ${db.quoteId(tbl)}`);
            const stamp = dateStamp();
            if (fmt === 'csv') {
                res.setHeader('Content-Type', 'text/csv; charset=utf-8');
                res.setHeader('Content-Disposition', `attachment; filename="${edb}_${tbl}_${stamp}.csv"`);
                const cols = rows.length ? Object.keys(rows[0]) : [];
                let out = cols.map(csvEsc).join(',') + '\n';
                for (const r of rows) out += cols.map(c => csvEsc(r[c] ?? '')).join(',') + '\n';
                await db.close(); return res.send('\ufeff' + out);
            } else if (fmt === 'json') {
                res.setHeader('Content-Type', 'application/json; charset=utf-8');
                res.setHeader('Content-Disposition', `attachment; filename="${edb}_${tbl}_${stamp}.json"`);
                await db.close(); return res.send(JSON.stringify({ database: edb, table: tbl, exported_at: new Date().toISOString(), total_records: rows.length, data: rows }, null, 2));
            } else if (fmt === 'xml') {
                res.setHeader('Content-Type', 'application/xml; charset=utf-8');
                res.setHeader('Content-Disposition', `attachment; filename="${edb}_${tbl}_${stamp}.xml"`);
                let out = `<?xml version="1.0" encoding="UTF-8"?>\n<export>\n  <database>${h(edb)}</database>\n  <table>${h(tbl)}</table>\n  <records>\n`;
                for (const row of rows) { out += '    <record>\n'; for (const [k,v] of Object.entries(row)) out += `      <${h(k)}>${h(v)}</${h(k)}>\n`; out += '    </record>\n'; }
                out += '  </records>\n</export>';
                await db.close(); return res.send(out);
            } else { // sql
                res.setHeader('Content-Type', 'text/plain; charset=utf-8');
                res.setHeader('Content-Disposition', `attachment; filename="${edb}_${tbl}_${stamp}.sql"`);
                let out = `-- Table Export: ${edb}.${tbl}\n-- Generated: ${new Date().toISOString()}\n-- Records: ${rows.length}\n\n`;
                if (db.type === 'mysql') { try { const r = await db.run(`SHOW CREATE TABLE ${db.quoteId(tbl)}`); out += r[0]['Create Table'] + ';\n\n'; } catch (_) {} }
                for (const row of rows) out += `INSERT INTO ${db.quoteId(tbl)} VALUES (${Object.values(row).map(v => db.quoteVal(v)).join(', ')});\n`;
                await db.close(); return res.send(out);
            }
        }

        // Import SQL
        if (body.import_sql && req.file) {
            if (!validateCsrf(req, body.csrf_token)) { errorMsg = 'Security token validation failed.'; }
            else {
                const orig = req.file.originalname || '';
                if (!orig.toLowerCase().endsWith('.sql')) { errorMsg = 'Invalid file type. Only .sql files are allowed.'; }
                else {
                    try { await db.exec(req.file.buffer.toString('utf8')); successMsg = 'SQL file imported successfully!'; }
                    catch (e) { errorMsg = e.message; }
                }
            }
        }

        // Delete record
        if (query.action === 'delete' && query.table && validateCsrf(req, query.csrf_token)) {
            const conditions = [];
            for (const [k, v] of Object.entries(query)) {
                if (k.startsWith('where_')) conditions.push(`${db.quoteId(k.slice(6))} = ${db.quoteVal(v)}`);
            }
            if (conditions.length) {
                try { await db.run(`DELETE FROM ${db.quoteId(query.table)} WHERE ${conditions.join(' AND ')}`); successMsg = 'Record deleted.'; }
                catch (e) { errorMsg = e.message; }
            }
        }

        // Save record (insert / update)
        if (body.save_record) {
            if (!validateCsrf(req, body.csrf_token)) { errorMsg = 'Security token validation failed.'; }
            else {
                const tbl   = body.table;
                const isEdit = body.is_edit === '1';
                const fields = body.field || {};
                try {
                    if (isEdit) {
                        const sets = [], where = [];
                        for (const [name, val] of Object.entries(fields)) {
                            if (name.startsWith('old_')) where.push(`${db.quoteId(name.slice(4))} = ${db.quoteVal(val)}`);
                            else sets.push(`${db.quoteId(name)} = ${db.quoteVal(val)}`);
                        }
                        await db.run(`UPDATE ${db.quoteId(tbl)} SET ${sets.join(', ')} WHERE ${where.join(' AND ')}`);
                    } else {
                        const cols = [], vals = [];
                        for (const [name, val] of Object.entries(fields)) { cols.push(db.quoteId(name)); vals.push(db.quoteVal(val)); }
                        await db.run(`INSERT INTO ${db.quoteId(tbl)} (${cols.join(', ')}) VALUES (${vals.join(', ')})`);
                    }
                    successMsg = isEdit ? 'Record updated successfully.' : 'Record inserted successfully.';
                } catch (e) { errorMsg = e.message; }
            }
        }

        // Create database
        if (body.create_database) {
            if (!validateCsrf(req, body.csrf_token)) { errorMsg = 'Security token validation failed.'; }
            else try { await db.run(`CREATE DATABASE ${db.quoteId(body.new_db_name)}`); successMsg = `Database '${h(body.new_db_name)}' created.`; }
            catch (e) { errorMsg = e.message; }
        }

        // Bulk actions (drop database/table, truncate table)
        if (body.bulk_action && validateCsrf(req, body.csrf_token)) {
            const items   = Array.isArray(body['selected[]']) ? body['selected[]'] : [body['selected[]']].filter(Boolean);
            const action  = body.bulk_action;
            const ctx_db  = query.db || '';
            let ok = 0;
            for (const item of items) {
                try {
                    if (action === 'drop' && !ctx_db)  { await db.run(`DROP DATABASE ${db.quoteId(item)}`); ok++; }
                    if (action === 'drop' && ctx_db)   { await db.run(`DROP TABLE ${db.quoteId(item)}`); ok++; }
                    if (action === 'truncate' && ctx_db){ await db.run(`TRUNCATE TABLE ${db.quoteId(item)}`); ok++; }
                } catch (e) { errorMsg += e.message + '; '; }
            }
            if (ok) successMsg = `${action} completed on ${ok} item(s).`;
        }

        // Table operations (rename / copy / move)
        if (body.rename_table && validateCsrf(req, body.csrf_token)) {
            try {
                if (db.type === 'mysql') await db.run(`RENAME TABLE ${db.quoteId(body.old_table_name)} TO ${db.quoteId(body.new_table_name)}`);
                else await db.run(`ALTER TABLE ${db.quoteId(body.old_table_name)} RENAME TO ${db.quoteId(body.new_table_name)}`);
                successMsg = 'Table renamed successfully.';
            } catch (e) { errorMsg = e.message; }
        }
        if (body.copy_table && validateCsrf(req, body.csrf_token)) {
            try {
                if (db.type === 'mysql') await db.run(`CREATE TABLE ${db.quoteId(body.target_db)}.${db.quoteId(body.target_table)} LIKE ${db.quoteId(body.source_db)}.${db.quoteId(body.source_table)}`);
                else await db.run(`CREATE TABLE ${db.quoteId(body.target_table)} AS SELECT * FROM ${db.quoteId(body.source_table)} WHERE 1=0`);
                if (body.copy_data) await db.run(`INSERT INTO ${db.quoteId(body.target_table)} SELECT * FROM ${db.quoteId(body.source_table)}`);
                successMsg = 'Table copied successfully.';
            } catch (e) { errorMsg = e.message; }
        }
    }

    // ── Global search (POST) ──
    let searchResults = {};
    if (body.global_search && body.search_term && body.search_database) {
        if (db.type !== 'sqlite') await db.run(`USE ${db.quoteId(body.search_database)}`);
        let tables = Array.isArray(body['search_tables[]']) ? body['search_tables[]'] : (body['search_tables[]'] ? [body['search_tables[]']] : []);
        if (!tables.length) tables = await db.getTables(body.search_database);
        for (const t of tables) {
            try {
                const cols = await db.getColumns(t);
                const clauses = cols.filter(c => /char|text|enum|set/i.test(c.Type || c.type || '')).map(c => `${db.quoteId(db.fieldName(c))} LIKE ${db.quoteVal('%' + body.search_term + '%')}`);
                if (!clauses.length) continue;
                const rows = await db.run(`SELECT * FROM ${db.quoteId(t)} WHERE ${clauses.join(' OR ')} LIMIT 100`);
                if (rows.length) searchResults[t] = rows;
            } catch (_) {}
        }
    }

    // ── Build page context ──
    const page  = query.page || (isLoggedIn(req) ? 'databases' : 'login');
    const theme = getTheme(req);
    const lang  = getLang(req);
    const csrf  = csrfToken(req);

    const ctx = { page, theme, lang, csrf, error, success, errorMsg, successMsg, req, db, query, body, searchResults };
    if (req._sqlResult !== undefined) { ctx.sqlResult = req._sqlResult; ctx.sqlTime = req._sqlTime; }
    if (req._sqlError  !== undefined) ctx.sqlError = req._sqlError;

    const html = await buildPage(ctx);
    await db.close();
    res.send(html);
});

// ─── Page builders ────────────────────────────────────────────────────────────
async function buildPage(ctx) {
    const { page, db, query, body, req } = ctx;

    if (!isLoggedIn(req)) return renderShell(ctx, renderLoginForm(ctx), true);

    // Fetch tables for sidebar nav-section when a DB is selected
    if (query.db && page !== 'databases') {
        try { ctx.navTables = await db.getTables(query.db); } catch (_) { ctx.navTables = []; }
    } else { ctx.navTables = []; }

    let content = '';
    try {
        switch (page) {
            case 'databases':      content = await pageDatabases(ctx);   break;
            case 'tables':         content = await pageTables(ctx);       break;
            case 'browse':         content = await pageBrowse(ctx);       break;
            case 'structure':      content = await pageStructure(ctx);    break;
            case 'insert': case 'edit': content = await pageInsertEdit(ctx); break;
            case 'sql':            content = await pageSql(ctx);          break;
            case 'import':         content = await pageImport(ctx);       break;
            case 'export':         content = await pageExport(ctx);       break;
            case 'search':         content = await pageSearch(ctx);       break;
            case 'table_operations': content = await pageTableOps(ctx);   break;
            default:               content = await pageDatabases(ctx);
        }
    } catch (e) { content = `<div class="alert alert-error"><span>Error: ${h(e.message)}</span></div>`; }

    return renderShell(ctx, content, false);
}

function renderLoginForm({ csrf, error, theme, lang }) {
    return `<div class="login-container"><div class="login-box">
<div class="logo"><div class="logo-icon" style="width:56px;height:56px;margin:0 auto 8px;display:flex;align-items:center;justify-content:center;">${logoSvg(48)}</div>
<h1 data-i18n="app_name">Dabiro</h1><p data-i18n="app_tagline">Professional database management interface</p></div>
${error ? `<div class="alert alert-error"><span>${h(error)}</span></div>` : ''}
<form method="post"><input type="hidden" name="csrf_token" value="${h(csrf)}">
<div class="form-group"><label data-i18n="database_type_label">Database Type</label>
<select name="db_type" required><option value="mysql">MySQL / MariaDB</option><option value="pgsql">PostgreSQL</option><option value="sqlite">SQLite</option></select></div>
<div class="form-group"><label data-i18n="host_label">Host / File Path</label><input type="text" name="db_host" value="localhost" required placeholder="localhost"></div>
<div class="form-group"><label data-i18n="username_label">Username</label><input type="text" name="db_user" value="root" placeholder="root"></div>
<div class="form-group"><label data-i18n="password_label">Password</label><input type="password" name="db_pass" placeholder="Enter password"></div>
<div class="form-group"><label data-i18n="database_name_label">Database Name <small style="font-weight:400;color:var(--text-light)">(optional)</small></label><input type="text" name="db_name" placeholder="Leave empty to see all databases"></div>
<button type="submit" name="login" value="1" class="btn btn-primary" style="width:100%;padding:12px">${iconSvg('plug')} <span data-i18n="connect_button">Connect to Database</span></button></form>
<div style="display:flex;gap:10px;margin-top:12px;padding-top:12px;border-top:1px solid var(--border)">
${dropdownHtml('Theme', Object.entries(THEME_OPTIONS).map(([v,l]) => `<a href="/?set_theme=${v}" class="dropdown-item" data-theme-option="${v}">${l}</a>`).join(''), 'flex:1')}
${dropdownHtml('Language', Object.entries(LANG_OPTIONS).map(([v,l]) => `<a href="/?set_lang=${v}" class="dropdown-item" data-language-option="${v}">${l}</a>`).join(''), 'flex:1')}
</div></div></div>`;
}

async function pageDatabases({ db, errorMsg, successMsg, csrf, req, query }) {
    const databases = await db.getDatabases();
    let dbInfo = 'N/A';
    try { if (db.type === 'mysql') dbInfo = (await db.run('SELECT VERSION()'))[0]['VERSION()'] || 'N/A'; else if (db.type === 'pgsql') dbInfo = (await db.run('SELECT version()'))[0].version || 'N/A'; else if (db.type === 'sqlite') dbInfo = (await db.run('SELECT sqlite_version()'))[0]['sqlite_version()'] || 'N/A'; } catch(_) {}
    return `
${alertHtml(successMsg, errorMsg)}
<div class="page-header-row"><div class="page-header"><h1 data-i18n="databases">Databases</h1><p data-i18n="databases_subtitle">Select a database to view its tables and data</p></div>
<div class="view-toggle"><button class="view-toggle-btn active" data-view="grid" onclick="toggleView('grid')"><svg viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/></svg> <span>Grid</span></button><button class="view-toggle-btn" data-view="list" onclick="toggleView('list')"><svg viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="5" width="18" height="3" rx="1.5"/><rect x="3" y="10.5" width="18" height="3" rx="1.5"/><rect x="3" y="16" width="18" height="3" rx="1.5"/></svg> <span>List</span></button></div></div>
<div class="stats-grid">
<div class="stat-card"><div class="stat-value">${databases.length}</div><div class="stat-label" data-i18n="total_databases">Total Databases</div></div>
<div class="stat-card"><div class="stat-value">${h(db.type)}</div><div class="stat-label" data-i18n="database_type_stat">Database Type</div></div>
<div class="stat-card"><div class="stat-value" style="font-size:14px">${h(req.session.db_host)}</div><div class="stat-label" data-i18n="server_host">Server Host</div></div>
<div class="stat-card"><div class="stat-value" style="font-size:14px">${h(dbInfo.split(' ')[0])}</div><div class="stat-label">Version</div></div>
</div>
<form id="bulk-action-form" method="post">
<input type="hidden" name="bulk_action" id="bulk-action"><input type="hidden" name="csrf_token" value="${h(csrf)}">
<div class="btn-group" style="flex-wrap:wrap">
<label style="display:flex;align-items:center;gap:8px;padding:6px 12px;background:var(--card-bg);border:2px solid var(--border);border-radius:var(--radius-sm);cursor:pointer;height:30px"><input type="checkbox" onchange="selectAll(this)" style="width:auto;margin:0"><span style="font-weight:600;font-size:13px">Select All</span></label>
<input type="text" placeholder="Search databases..." onkeyup="searchItems(this)" style="flex:1;padding:6px 12px;border:2px solid var(--border);border-radius:var(--radius-sm);font-size:13px;height:30px">
${db.type !== 'sqlite' ? `<button type="button" class="btn btn-success" onclick="openModal('createDatabaseModal')">Create Database</button>
<div class="dropdown"><button type="button" class="dropdown-toggle action-dropdown-toggle" disabled><span>Actions</span><span class="selection-count"></span></button><div class="dropdown-menu"><a href="javascript:void(0)" onclick="performAction('drop')" class="dropdown-item danger">Drop Selected</a></div></div>` : ''}
</div>
<div id="databasesContainer" class="grid" style="margin-top:14px">
${databases.map(dbName => `<a href="/?page=tables&db=${encodeURIComponent(dbName)}" class="grid-card">
<label onclick="event.preventDefault()" style="cursor:pointer;flex-shrink:0"><input type="checkbox" name="selected[]" value="${h(dbName)}" onchange="updateActionButton()" style="width:auto"></label>
<div class="card-icon" style="width:36px;height:36px;background:linear-gradient(135deg,var(--primary),var(--secondary));border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;flex-shrink:0">
<svg width="18" height="18" fill="white" viewBox="0 0 24 24"><ellipse cx="12" cy="6" rx="8" ry="3"/><path d="M4 6v4c0 1.66 3.58 3 8 3s8-1.34 8-3V6"/><path d="M4 10v4c0 1.66 3.58 3 8 3s8-1.34 8-3v-4"/><path d="M4 14v4c0 1.66 3.58 3 8 3s8-1.34 8-3v-4"/></svg>
</div><div><h3>${h(dbName)}</h3><p>Database</p></div></a>`).join('')}
</div></form>
${db.type !== 'sqlite' ? `<div id="createDatabaseModal" class="modal"><div class="modal-content"><div class="modal-header"><h3>Create New Database</h3><button class="modal-close" onclick="closeModal('createDatabaseModal')">&times;</button></div>
<form method="post"><input type="hidden" name="csrf_token" value="${h(csrf)}"><div class="modal-body"><div class="form-group"><label>Database Name</label><input type="text" name="new_db_name" required placeholder="my_database"></div></div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('createDatabaseModal')">Cancel</button><button type="submit" name="create_database" value="1" class="btn btn-success">Create</button></div></form></div></div>` : ''}`;
}

async function pageTables({ db, errorMsg, successMsg, csrf, query }) {
    const database = query.db || '';
    const tables   = await db.getTables(database);
    return `
${alertHtml(successMsg, errorMsg)}
<div class="page-header-row"><div class="page-header"><h1>Tables in <em>${h(database)}</em></h1><p>${tables.length} tables found</p></div>
<div class="view-toggle"><button class="view-toggle-btn active" data-view="grid" onclick="toggleView('grid')"><svg viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/></svg> <span>Grid</span></button><button class="view-toggle-btn" data-view="list" onclick="toggleView('list')"><svg viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="5" width="18" height="3" rx="1.5"/><rect x="3" y="10.5" width="18" height="3" rx="1.5"/><rect x="3" y="16" width="18" height="3" rx="1.5"/></svg> <span>List</span></button></div></div>
<form id="bulk-action-form" method="post">
<input type="hidden" name="bulk_action" id="bulk-action"><input type="hidden" name="csrf_token" value="${h(csrf)}">
<div class="btn-group" style="flex-wrap:wrap">
<label style="display:flex;align-items:center;gap:8px;padding:6px 12px;background:var(--card-bg);border:2px solid var(--border);border-radius:var(--radius-sm);cursor:pointer;height:30px"><input type="checkbox" onchange="selectAll(this)" style="width:auto;margin:0"><span style="font-weight:600;font-size:13px">Select All</span></label>
<input type="text" placeholder="Search tables..." onkeyup="searchItems(this)" style="flex:1;padding:6px 12px;border:2px solid var(--border);border-radius:var(--radius-sm);font-size:13px;height:30px">
${db.type !== 'sqlite' ? `<button type="button" class="btn btn-success" onclick="openModal('createTableModal')">Create Table</button>` : ''}
<div class="dropdown"><button type="button" class="dropdown-toggle action-dropdown-toggle" disabled><span>Actions</span><span class="selection-count"></span></button><div class="dropdown-menu"><a href="javascript:void(0)" onclick="performAction('drop')" class="dropdown-item danger">Drop Selected</a><a href="javascript:void(0)" onclick="performAction('truncate')" class="dropdown-item danger">Truncate Selected</a></div></div>
</div>
<div id="tablesContainer" class="grid" style="margin-top:14px">
${tables.map(t => `<div class="grid-card">
<label onclick="event.preventDefault()" style="cursor:pointer;flex-shrink:0"><input type="checkbox" name="selected[]" value="${h(t)}" onchange="updateActionButton()" style="width:auto"></label>
<div style="flex:1;min-width:0">
<h3><a href="/?page=browse&db=${encodeURIComponent(database)}&table=${encodeURIComponent(t)}" style="text-decoration:none;color:inherit">${h(t)}</a></h3>
<div style="display:flex;gap:6px;margin-top:6px;flex-wrap:wrap">
<a href="/?page=browse&db=${encodeURIComponent(database)}&table=${encodeURIComponent(t)}" class="btn btn-sm btn-primary">Browse</a>
<a href="/?page=structure&db=${encodeURIComponent(database)}&table=${encodeURIComponent(t)}" class="btn btn-sm btn-secondary">Structure</a>
<a href="/?page=insert&db=${encodeURIComponent(database)}&table=${encodeURIComponent(t)}" class="btn btn-sm btn-success">Insert</a>
<a href="/?page=table_operations&db=${encodeURIComponent(database)}&table=${encodeURIComponent(t)}" class="btn btn-sm btn-secondary">Operations</a>
</div></div></div>`).join('')}
</div></form>
<div id="createTableModal" class="modal"><div class="modal-content"><div class="modal-header"><h3>Create Table</h3><button class="modal-close" onclick="closeModal('createTableModal')">&times;</button></div>
<form method="post" onsubmit="if(!prepareCreateTable())return false">
<input type="hidden" name="csrf_token" value="${h(csrf)}">
<div class="modal-body">
<div class="form-group"><label>Database</label><select name="create_table_db"><option value="${h(database)}">${h(database)}</option></select></div>
<div class="form-group"><label>Table Name</label><input type="text" id="create_table_name" name="create_table_name" placeholder="table_name" required></div>
<div class="form-group"><label>Columns</label><div id="columnsBuilder" style="display:grid;gap:8px;border:1px solid var(--border);padding:12px;border-radius:8px;background:var(--input-bg)"></div>
<div style="margin-top:8px;display:flex;gap:8px;justify-content:flex-end"><button type="button" class="btn btn-secondary btn-sm" onclick="addColumnRow()">Add Column</button><button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('columnsBuilder').innerHTML='';columnIndex=0;addColumnRow()">Reset</button></div></div>
<input type="hidden" name="create_table_sql" id="create_table_sql">
</div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('createTableModal')">Cancel</button><button type="submit" name="create_table" value="1" class="btn btn-success">Create Table</button></div>
</form></div></div>`;
}

async function pageBrowse({ db, errorMsg, successMsg, csrf, query }) {
    const database = query.db    || '';
    const table    = query.table || '';
    const limit    = Math.max(1, parseInt(query.limit  || '50'));
    const offset   = Math.max(0, parseInt(query.offset || '0'));
    const sortCol  = query.sort_column || '';
    const sortOrd  = (query.sort_order || 'ASC').toUpperCase() === 'DESC' ? 'DESC' : 'ASC';

    if (db.type !== 'sqlite' && database) await db.run(`USE ${db.quoteId(database)}`);

    // Build WHERE
    const clauses = [];
    if (query.search_column && query.search_value) {
        const cond = query.search_condition || 'like';
        const col  = db.quoteId(query.search_column);
        const val  = query.search_value;
        if (cond === 'exact')    clauses.push(`${col} = ${db.quoteVal(val)}`);
        else if (cond === 'not') clauses.push(`${col} != ${db.quoteVal(val)}`);
        else if (cond === 'like_start') clauses.push(`${col} LIKE ${db.quoteVal(val + '%')}`);
        else if (cond === 'like_end')   clauses.push(`${col} LIKE ${db.quoteVal('%' + val)}`);
        else clauses.push(`${col} LIKE ${db.quoteVal('%' + val + '%')}`);
    }
    const where   = clauses.length ? ' WHERE ' + clauses.join(' AND ') : '';
    const orderBy = sortCol ? ` ORDER BY ${db.quoteId(sortCol)} ${sortOrd}` : '';
    const sql     = `SELECT * FROM ${db.quoteId(table)}${where}${orderBy} LIMIT ${limit} OFFSET ${offset}`;
    const countSql= `SELECT COUNT(*) as cnt FROM ${db.quoteId(table)}${where}`;

    const rows    = await db.run(sql);
    const countR  = await db.run(countSql);
    const total   = parseInt(Object.values(countR[0])[0] || 0);
    const totalPages = Math.ceil(total / limit);
    const curPage    = Math.floor(offset / limit) + 1;
    const cols    = await db.getColumns(table);
    const colNames= cols.map(c => db.fieldName(c));

    const pageUrl = (p) => `/?page=browse&db=${encodeURIComponent(database)}&table=${encodeURIComponent(table)}&limit=${limit}&offset=${(p-1)*limit}${sortCol ? `&sort_column=${encodeURIComponent(sortCol)}&sort_order=${sortOrd}` : ''}`;

    return `
${alertHtml(successMsg, errorMsg)}
<div class="page-header-row"><div class="page-header"><h1>${h(table)}</h1><p>${h(database)} &bull; ${total} rows &bull; Page ${curPage} of ${Math.max(1,totalPages)}</p></div>
<div class="btn-group" style="margin-bottom:0"><a href="/?page=tables&db=${encodeURIComponent(database)}" class="btn btn-secondary">Back</a><a href="/?page=structure&db=${encodeURIComponent(database)}&table=${encodeURIComponent(table)}" class="btn btn-secondary">Structure</a><a href="/?page=insert&db=${encodeURIComponent(database)}&table=${encodeURIComponent(table)}" class="btn btn-success">Insert Row</a></div></div>
<div class="card" style="margin-bottom:10px"><form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">
<input type="hidden" name="page" value="browse"><input type="hidden" name="db" value="${h(database)}"><input type="hidden" name="table" value="${h(table)}">
<div style="flex:1;min-width:150px"><label style="font-size:12px">Column</label><select name="search_column" style="padding:5px 8px;height:30px;font-size:12px">${colNames.map(c=>`<option value="${h(c)}" ${query.search_column===c?'selected':''}>${h(c)}</option>`).join('')}</select></div>
<div><label style="font-size:12px">Condition</label><select name="search_condition" style="padding:5px 8px;height:30px;font-size:12px"><option value="like" ${(query.search_condition||'like')==='like'?'selected':''}>Contains</option><option value="exact" ${query.search_condition==='exact'?'selected':''}>Equals</option><option value="not" ${query.search_condition==='not'?'selected':''}>Not equals</option><option value="like_start" ${query.search_condition==='like_start'?'selected':''}>Starts with</option><option value="like_end" ${query.search_condition==='like_end'?'selected':''}>Ends with</option></select></div>
<div style="flex:2;min-width:150px"><label style="font-size:12px">Value</label><input type="text" name="search_value" value="${h(query.search_value||'')}" style="padding:5px 8px;height:30px;font-size:12px"></div>
<div style="display:flex;gap:6px"><button type="submit" class="btn btn-primary btn-sm" style="margin-top:18px">Search</button>${query.search_value?`<a href="/?page=browse&db=${encodeURIComponent(database)}&table=${encodeURIComponent(table)}" class="btn btn-secondary btn-sm" style="margin-top:18px">Clear</a>`:''}</div>
</form></div>
<div class="browse-table-container">
<table id="data-table"><thead><tr>
${colNames.map((c,i) => { const nextOrd = (sortCol===c && sortOrd==='ASC') ? 'DESC' : 'ASC'; return `<th><a href="/?page=browse&db=${encodeURIComponent(database)}&table=${encodeURIComponent(table)}&limit=${limit}&offset=0&sort_column=${encodeURIComponent(c)}&sort_order=${nextOrd}" style="color:inherit;text-decoration:none">${h(c)}${sortCol===c?' '+(sortOrd==='ASC'?'▲':'▼'):''}</a></th>`; }).join('')}
<th>Actions</th></tr></thead><tbody>
${rows.map(row => {
    const cells = colNames.map(c => `<td>${row[c]==null?'<span class="null-value">NULL</span>':h(String(row[c]))}</td>`).join('');
    const whereParams = colNames.map(c=>`where_${encodeURIComponent(c)}=${encodeURIComponent(row[c]??'')}`).join('&');
    const editParams  = colNames.map(c=>`edit_${encodeURIComponent(c)}=${encodeURIComponent(row[c]??'')}`).join('&');
    return `<tr>${cells}<td><a href="/?page=edit&db=${encodeURIComponent(database)}&table=${encodeURIComponent(table)}&${editParams}" class="btn btn-sm btn-secondary">Edit</a> <a href="/?action=delete&db=${encodeURIComponent(database)}&table=${encodeURIComponent(table)}&${whereParams}&csrf_token=${encodeURIComponent(csrf)}" class="btn btn-sm btn-danger" onclick="return confirm('Delete this record?')">Del</a></td></tr>`;
}).join('')}
</tbody></table></div>
${renderPagination(curPage, totalPages, pageUrl)}`;
}

async function pageStructure({ db, errorMsg, successMsg, csrf, query }) {
    const database = query.db    || '';
    const table    = query.table || '';
    const cols     = await db.getColumns(table);
    return `
${alertHtml(successMsg, errorMsg)}
<div class="page-header-row"><div class="page-header"><h1>Structure: ${h(table)}</h1><p>${h(database)}</p></div>
<div class="btn-group" style="margin-bottom:0"><a href="/?page=browse&db=${encodeURIComponent(database)}&table=${encodeURIComponent(table)}" class="btn btn-secondary">Browse</a>
<button type="button" class="btn btn-primary" onclick="openModal('addColumnModal')">Add Column</button></div></div>
<div class="structure-scroll card">
<table><thead><tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th>${db.type==='mysql'?'<th>Extra</th>':''}</tr></thead><tbody>
${cols.map(c => `<tr><td><strong>${h(db.fieldName(c))}</strong></td><td>${h(c.Type||c.type||c.data_type||'')}</td><td>${h(c.Null||c.is_nullable||'')}</td><td>${c.Default!=null&&c.Default!==''?h(c.Default):(c['Default']===null?'<span class="null-value">NULL</span>':'')}</td>${db.type==='mysql'?`<td>${h(c.Extra||'')}</td>`:''}</tr>`).join('')}
</tbody></table></div>
<div id="addColumnModal" class="modal"><div class="modal-content"><div class="modal-header"><h3>Add Column to ${h(table)}</h3><button class="modal-close" onclick="closeModal('addColumnModal')">&times;</button></div>
<form method="post"><input type="hidden" name="csrf_token" value="${h(csrf)}"><div class="modal-body">
<input type="hidden" name="add_column_table" value="${h(table)}">
<div class="form-group"><label>Column Name</label><input type="text" name="new_col_name" required></div>
<div class="form-group"><label>Type</label><select name="new_col_type"><option>INT</option><option>VARCHAR</option><option>TEXT</option><option>DATE</option><option>DATETIME</option><option>DECIMAL</option><option>BOOLEAN</option><option>BIGINT</option><option>FLOAT</option><option>JSON</option></select></div>
<div class="form-group"><label>Length (optional)</label><input type="text" name="new_col_length" placeholder="e.g. 255"></div>
<div class="form-group"><label><input type="checkbox" name="new_col_null" value="1"> Nullable</label></div>
<div class="form-group"><label>Default (optional)</label><input type="text" name="new_col_default" placeholder="NULL or value"></div>
<div class="form-group"><label>Extra</label><select name="new_col_extra"><option value="">None</option><option value="AUTO_INCREMENT">AUTO_INCREMENT</option></select></div>
</div><div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('addColumnModal')">Cancel</button><button type="submit" name="add_column" value="1" class="btn btn-success">Add Column</button></div></form></div></div>`;
}

async function pageInsertEdit({ db, errorMsg, successMsg, csrf, query, page }) {
    const database = query.db    || '';
    const table    = query.table || '';
    const isEdit   = page === 'edit';
    const cols     = await db.getColumns(table);
    const editData = {};
    if (isEdit) for (const [k,v] of Object.entries(query)) { if (k.startsWith('edit_')) editData[k.slice(5)] = v; }
    return `
${alertHtml(successMsg, errorMsg)}
<div class="page-header"><h1>${isEdit ? 'Edit Record' : 'Insert Record'}</h1><p>${h(table)} in ${h(database)}</p></div>
<div class="btn-group"><a href="/?page=browse&db=${encodeURIComponent(database)}&table=${encodeURIComponent(table)}" class="btn btn-secondary">Back to Table</a></div>
<div class="card"><form method="post"><input type="hidden" name="csrf_token" value="${h(csrf)}">
<input type="hidden" name="table" value="${h(table)}"><input type="hidden" name="is_edit" value="${isEdit?'1':'0'}">
${cols.map(c => { const fn = db.fieldName(c); const val = isEdit ? (editData[fn] ?? '') : ''; return `<div class="form-group"><label>${h(fn)}</label><input type="text" name="field[${h(fn)}]" value="${h(val)}">${isEdit?`<input type="hidden" name="field[old_${h(fn)}]" value="${h(val)}">`:''}</div>`; }).join('')}
<div class="btn-group"><button type="submit" name="save_record" value="1" class="btn btn-success">Save Record</button><a href="/?page=browse&db=${encodeURIComponent(database)}&table=${encodeURIComponent(table)}" class="btn btn-secondary">Cancel</a></div>
</form></div>`;
}

async function pageSql({ db, errorMsg, successMsg, csrf, query, body, sqlResult, sqlTime, sqlError }) {
    const preSql = query.pre_sql || '';
    const lastSql = body?.sql || preSql;
    return `
<div class="page-header"><h1>SQL Console</h1><p>Execute custom SQL queries</p></div>
<div class="btn-group"><a href="/?page=databases" class="btn btn-secondary">Back to Databases</a></div>
${sqlError ? `<div class="alert alert-error"><span>${h(sqlError)}</span></div>` : ''}
<div class="card"><div class="card-header"><div class="card-title">Query Editor</div></div>
<form method="post"><input type="hidden" name="csrf_token" value="${h(csrf)}">
<div class="form-group"><textarea name="sql" id="sql-query" rows="10" placeholder="SELECT * FROM table_name LIMIT 10;">${h(lastSql)}</textarea></div>
<div style="display:flex;gap:8px;flex-wrap:wrap"><button type="submit" name="execute_sql" value="1" class="btn btn-primary">Execute Query</button><button type="submit" name="export_query" value="1" class="btn btn-secondary">Export as SQL</button></div>
</form></div>
${sqlResult ? `<div class="card"><div class="card-header"><div class="card-title">Results</div><div style="color:var(--text-light);font-size:13px">${sqlTime}ms &bull; ${sqlResult.length} rows</div></div>
${sqlResult.length ? `<div class="table-container"><table><thead><tr>${Object.keys(sqlResult[0]).map(c=>`<th>${h(c)}</th>`).join('')}</tr></thead><tbody>${sqlResult.map(row=>`<tr>${Object.values(row).map(v=>`<td>${v==null?'<span class="null-value">NULL</span>':h(String(v))}</td>`).join('')}</tr>`).join('')}</tbody></table></div>` : `<p style="color:var(--success);font-weight:600">Query executed successfully.</p>`}
</div>` : ''}`;
}

async function pageImport({ db, errorMsg, successMsg, csrf }) {
    return `
<div class="page-header"><h1>Import Data</h1><p>Upload and execute SQL files</p></div>
<div class="btn-group"><a href="/?page=databases" class="btn btn-secondary">Back to Databases</a></div>
${alertHtml(successMsg, errorMsg)}
<div class="card"><div class="card-header"><div class="card-title">SQL File Import</div></div>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="${h(csrf)}">
<div class="form-group"><label>Select SQL File (.sql only, max 10 MB)</label><input type="file" name="sql_file" accept=".sql" required></div>
<button type="submit" name="import_sql" value="1" class="btn btn-primary">Import SQL</button></form></div>`;
}

async function pageExport({ db, csrf, query }) {
    const allDbs  = await db.getDatabases();
    const selDb   = query.db || '';
    const tables  = selDb ? await db.getTables(selDb) : [];
    return `
<div class="page-header"><h1>Export Data</h1><p>Export databases or tables to various formats</p></div>
<div class="btn-group"><a href="/?page=databases" class="btn btn-secondary">Back to Databases</a></div>
<div class="card"><div class="card-header"><div class="card-title">Export Entire Database</div></div>
<form method="post" style="padding:4px 0"><input type="hidden" name="csrf_token" value="${h(csrf)}">
<div class="form-group"><label>Select Database</label><select name="export_db_name" required>${allDbs.map(d=>`<option value="${h(d)}" ${d===selDb?'selected':''}>${h(d)}</option>`).join('')}</select></div>
<div class="form-group"><label>Format</label><select name="export_db_format"><option value="sql">SQL</option><option value="json">JSON</option></select></div>
<button type="submit" name="export_database" value="1" class="btn btn-primary">Download Database</button></form></div>
<div class="card"><div class="card-header"><div class="card-title">Export Single Table</div></div>
<form method="post" style="padding:4px 0"><input type="hidden" name="csrf_token" value="${h(csrf)}">
<div class="form-group"><label>Select Database</label><select name="export_database" onchange="loadExportTables(this.value)">${allDbs.map(d=>`<option value="${h(d)}" ${d===selDb?'selected':''}>${h(d)}</option>`).join('')}</select></div>
<div class="form-group"><label>Select Table</label><select name="export_table" id="export-table">${tables.map(t=>`<option value="${h(t)}">${h(t)}</option>`).join('')}</select></div>
<div class="form-group"><label>Format</label><select name="export_format"><option value="sql">SQL</option><option value="csv">CSV</option><option value="json">JSON</option><option value="xml">XML</option></select></div>
<button type="submit" name="export_table_download" value="1" class="btn btn-primary">Download Table</button></form></div>`;
}

async function pageSearch({ db, csrf, body, query, searchResults }) {
    const allDbs   = await db.getDatabases();
    const selDb    = body.search_database || query.db || '';
    const tableList= selDb ? await db.getTables(selDb) : [];
    const searched = !!body.global_search;
    return `
<div class="page-header"><h1>Global Search</h1><p>Search across all tables in a database</p></div>
<div class="btn-group"><a href="/?page=databases" class="btn btn-secondary">Back to Databases</a></div>
<div class="card"><div class="card-header"><div class="card-title">Search Configuration</div></div>
<form method="post"><input type="hidden" name="csrf_token" value="${h(csrf)}">
<div class="form-group"><label>Search Term</label><input type="text" name="search_term" value="${h(body.search_term||'')}" required autofocus></div>
<div class="form-group"><label>Database</label><select name="search_database" onchange="this.form.submit()">${allDbs.map(d=>`<option value="${h(d)}" ${d===selDb?'selected':''}>${h(d)}</option>`).join('')}</select></div>
${tableList.length ? `<div class="form-group"><label>Tables (leave empty to search all)</label>
<div style="max-height:180px;overflow-y:auto;border:2px solid var(--border);border-radius:var(--radius-sm);padding:10px">
<label style="display:block;margin-bottom:6px"><input type="checkbox" onclick="toggleAllTables(this)"><strong>Select All</strong></label>
${tableList.map(t=>`<label style="display:block;margin-bottom:4px"><input type="checkbox" name="search_tables[]" value="${h(t)}" class="table-checkbox"> ${h(t)}</label>`).join('')}
</div></div>` : ''}
<button type="submit" name="global_search" value="1" class="btn btn-primary" ${!selDb?'disabled':''}>Search</button></form></div>
${searched && Object.keys(searchResults).length===0 ? `<div class="alert" style="background:#fef3c7;border-color:#f59e0b;color:#92400e"><span>No matches found for "${h(body.search_term)}"</span></div>` : ''}
${Object.entries(searchResults).map(([tname, rows]) => `<div class="card"><div class="card-header"><div class="card-title">${h(tname)} <span style="font-size:12px;font-weight:400;color:var(--text-light)">(${rows.length} matches)</span></div><a href="/?page=browse&db=${encodeURIComponent(selDb)}&table=${encodeURIComponent(tname)}" class="btn btn-sm btn-secondary">View Table</a></div>
<div class="table-container"><table><thead><tr>${rows.length?Object.keys(rows[0]).map(c=>`<th>${h(c)}</th>`).join(''):''}</tr></thead><tbody>${rows.map(row=>`<tr>${Object.values(row).map(v=>`<td>${v==null?'<span class="null-value">NULL</span>':h(String(v))}</td>`).join('')}</tr>`).join('')}</tbody></table></div></div>`).join('')}`;
}

async function pageTableOps({ db, errorMsg, successMsg, csrf, query }) {
    const database = query.db    || '';
    const table    = query.table || '';
    const dbs      = await db.getDatabases();
    return `
${alertHtml(successMsg, errorMsg)}
<div class="page-header"><h1>Table Operations</h1><p>${h(table)} in ${h(database)}</p></div>
<div class="btn-group"><a href="/?page=browse&db=${encodeURIComponent(database)}&table=${encodeURIComponent(table)}" class="btn btn-secondary">Back to Table</a></div>
<div class="card"><div class="card-header"><div class="card-title">Rename Table</div></div>
<form method="post"><input type="hidden" name="csrf_token" value="${h(csrf)}"><input type="hidden" name="old_table_name" value="${h(table)}">
<div class="form-group"><label>New Name</label><input type="text" name="new_table_name" value="${h(table)}" required></div>
<button type="submit" name="rename_table" value="1" class="btn btn-primary">Rename</button></form></div>
<div class="card"><div class="card-header"><div class="card-title">Copy Table</div></div>
<form method="post"><input type="hidden" name="csrf_token" value="${h(csrf)}"><input type="hidden" name="source_db" value="${h(database)}"><input type="hidden" name="source_table" value="${h(table)}">
<div class="form-group"><label>Target Database</label><select name="target_db">${dbs.map(d=>`<option value="${h(d)}" ${d===database?'selected':''}>${h(d)}</option>`).join('')}</select></div>
<div class="form-group"><label>New Table Name</label><input type="text" name="target_table" value="${h(table)}_copy" required></div>
<div class="form-group"><label><input type="checkbox" name="copy_data" value="1" checked style="width:auto"> Copy data</label></div>
<button type="submit" name="copy_table" value="1" class="btn btn-primary">Copy</button></form></div>`;
}

// ─── Layout shell ─────────────────────────────────────────────────────────────
function renderShell(ctx, content, loginPage) {
    const { theme, lang, csrf, req, page, errorMsg, successMsg } = ctx;
    const isRtl = lang === 'ar';
    const user  = req.session.db_user || '';
    const dbType= req.session.db_type || '';
    const dbHost= req.session.db_host || '';
    const currentDb = ctx.query?.db || '';

    const sidebarContent = loginPage ? '' : `
<aside class="sidebar" id="sidebar"><div class="sidebar-header"><div class="sidebar-logo"><div class="sidebar-logo-icon">${logoSvg(24)}</div><div class="sidebar-title"><h2>Dabiro</h2><p>v${VERSION}</p></div></div></div>
<nav class="sidebar-nav">
<a href="/?page=databases" class="nav-item ${page==='databases'?'active':''}" data-i18n="databases">Databases</a>
${currentDb && page !== 'databases' && ctx.navTables && ctx.navTables.length ? `<div class="nav-section"><div class="nav-section-header" onclick="toggleNav(this)"><span class="nav-arrow">▼</span>${h(currentDb)}</div><div class="nav-section-content active">${ctx.navTables.map(tbl=>`<a href="/?page=browse&db=${encodeURIComponent(currentDb)}&table=${encodeURIComponent(tbl)}" class="nav-sub-item ${ctx.query?.table===tbl?'active':''}">${h(tbl)}</a>`).join('')}</div></div>` : ''}
<div class="nav-divider"></div>
<a href="/?page=search" class="nav-item ${page==='search'?'active':''}" data-i18n="global_search">Global Search</a>
<a href="/?page=sql" class="nav-item ${page==='sql'?'active':''}" data-i18n="sql_console">SQL Console</a>
<a href="/?page=import" class="nav-item ${page==='import'?'active':''}" data-i18n="import_data">Import Data</a>
<a href="/?page=export" class="nav-item ${page==='export'?'active':''}" data-i18n="export_data">Export Data</a>
</nav>
<div class="sidebar-footer">
<div style="display:flex;gap:6px;margin-bottom:8px">
${dropdownHtml('Theme', Object.entries(THEME_OPTIONS).map(([v,l])=>`<a href="?${buildQs(ctx.query,{set_theme:v})}" class="dropdown-item" data-theme-option="${v}">${l}</a>`).join(''), 'flex:1')}
${dropdownHtml('Language', Object.entries(LANG_OPTIONS).map(([v,l])=>`<a href="?${buildQs(ctx.query,{set_lang:v})}" class="dropdown-item" data-language-option="${v}">${l}</a>`).join(''), 'flex:1')}
</div>
<div class="user-info"><div class="user-avatar">${h(user.substring(0,2).toUpperCase())}</div><div class="user-details"><div class="name">${h(user)}</div><div class="role">${h(dbType)} &bull; ${h(dbHost)}</div></div></div>
<form method="post" style="width:100%"><input type="hidden" name="logout" value="1"><input type="hidden" name="csrf_token" value="${h(csrf)}"><button type="submit" class="btn btn-danger" style="width:100%" data-i18n="logout">Logout</button></form>
</div></aside>`;

    const topBar = loginPage ? '' : `<div class="top-bar"><div class="breadcrumb">${buildBreadcrumb(ctx)}</div>
<div class="top-bar-preferences">
${dropdownHtml('Theme', Object.entries(THEME_OPTIONS).map(([v,l])=>`<a href="?${buildQs(ctx.query,{set_theme:v})}" class="dropdown-item" data-theme-option="${v}">${l}</a>`).join(''), 'width:160px')}
${dropdownHtml('Language', Object.entries(LANG_OPTIONS).map(([v,l])=>`<a href="?${buildQs(ctx.query,{set_lang:v})}" class="dropdown-item" data-language-option="${v}">${l}</a>`).join(''), 'width:160px')}
</div><div class="system-info"><div class="system-time" id="systemTime"></div></div></div>`;

    return `<!DOCTYPE html><html lang="${h(lang)}" dir="${isRtl?'rtl':'ltr'}" data-theme="${h(theme)}">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Dabiro v${VERSION}</title>${faviconTag()}
<style>${CSS}</style></head>
<body>
<div class="loader-overlay" id="loaderOverlay"><div class="loader-content"><div class="spinner"></div><div class="loader-text" id="loaderText">Processing...</div></div></div>
<div class="toast-container" id="toastContainer"></div>
${loginPage ? content : `
<button class="mobile-menu-toggle" onclick="toggleSidebar()"><span></span><span></span><span></span></button>
<div class="app-container">${sidebarContent}<main class="main-content">${topBar}<div class="content-area">${content}</div></main></div>`}
<script>${CLIENT_JS}</script>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  ${successMsg ? `showToast(${JSON.stringify(successMsg)},'success');` : ''}
  ${errorMsg   ? `showToast(${JSON.stringify(errorMsg)},'error');`   : ''}
});
(function(){
  const themes=${JSON.stringify(Object.keys(THEME_OPTIONS))};
  const langs=${JSON.stringify(Object.keys(LANG_OPTIONS))};
  function getCk(n){const m=document.cookie.match(new RegExp(n+'=([^;]+)'));return m?decodeURIComponent(m[1]):null;}
  function setCk(n,v){document.cookie=n+'='+encodeURIComponent(v)+';path=/;max-age='+(365*86400);}
  function applyTheme(t){if(!themes.includes(t))return;document.documentElement.setAttribute('data-theme',t);setCk('dbadmin_theme',t);document.querySelectorAll('[data-theme-option]').forEach(el=>el.classList.toggle('active',el.dataset.themeOption===t));}
  function applyLang(l){if(!langs.includes(l))return;document.documentElement.setAttribute('lang',l);document.documentElement.setAttribute('dir',l==='ar'?'rtl':'ltr');if(document.body)document.body.style.direction=l==='ar'?'rtl':'ltr';setCk('dbadmin_lang',l);document.querySelectorAll('[data-language-option]').forEach(el=>el.classList.toggle('active',el.dataset.languageOption===l));applyTranslations(l);}
  document.addEventListener('DOMContentLoaded',()=>{
    applyTheme(getCk('dbadmin_theme')||'${theme}');
    applyLang(getCk('dbadmin_lang')||'${lang}');
    bindDropdowns();
    document.querySelectorAll('[data-theme-option]').forEach(el=>el.addEventListener('click',e=>{e.preventDefault();applyTheme(el.dataset.themeOption);}));
    document.querySelectorAll('[data-language-option]').forEach(el=>el.addEventListener('click',e=>{e.preventDefault();applyLang(el.dataset.languageOption);}));
  });
  function bindDropdowns(){document.querySelectorAll('[data-dropdown]').forEach(dd=>{const tgl=dd.querySelector('.dropdown-toggle');if(!tgl)return;tgl.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();const open=dd.classList.contains('open');document.querySelectorAll('[data-dropdown].open').forEach(x=>x.classList.remove('open'));if(!open)dd.classList.add('open');});});document.addEventListener('click',()=>document.querySelectorAll('[data-dropdown].open').forEach(x=>x.classList.remove('open')));}
})();
</script>
</body></html>`;
}

// ─── Small helpers ────────────────────────────────────────────────────────────
function alertHtml(ok, err) {
    return (ok  ? `<div class="alert alert-success"><span>${h(ok)}</span></div>`  : '') +
           (err ? `<div class="alert alert-error"><span>${h(err)}</span></div>` : '');
}
function renderPagination(cur, total, pageUrl) {
    if (total <= 1) return '';
    const pages = [];
    if (cur > 1) pages.push(`<a href="${pageUrl(cur-1)}">&laquo; Prev</a>`);
    for (let p = Math.max(1,cur-2); p <= Math.min(total,cur+2); p++) pages.push(`<a href="${pageUrl(p)}" class="${p===cur?'active':''}">${p}</a>`);
    if (cur < total) pages.push(`<a href="${pageUrl(cur+1)}">Next &raquo;</a>`);
    return `<div class="pagination">${pages.join('')}</div>`;
}
function dropdownHtml(label, items, style='') {
    return `<div class="dropdown" data-dropdown style="${style}"><button class="dropdown-toggle" style="width:100%">${label}</button><div class="dropdown-menu" style="bottom:100%;top:auto;min-width:100%">${items}</div></div>`;
}
function buildBreadcrumb({ page, query }) {
    const db = query?.db || '', table = query?.table || '';
    const crumbs = ['<a href="/?page=databases">Home</a>'];
    if (page === 'tables') crumbs.push(h(db));
    if (['browse','structure','insert','edit','table_operations'].includes(page)) { crumbs.push(`<a href="/?page=tables&db=${encodeURIComponent(db)}">${h(db)}</a>`); crumbs.push(h(table)); }
    if (page === 'sql') crumbs.push('SQL Console');
    if (page === 'import') crumbs.push('Import');
    if (page === 'export') crumbs.push('Export');
    if (page === 'search') crumbs.push('Global Search');
    return crumbs.join(' <span class="separator">&rsaquo;</span> ');
}
function buildQs(existing, overrides) {
    const q = Object.assign({}, existing, overrides);
    return Object.entries(q).filter(([,v])=>v!=null&&v!=='').map(([k,v])=>`${encodeURIComponent(k)}=${encodeURIComponent(v)}`).join('&');
}
function dateStamp() { return new Date().toISOString().replace(/[:.]/g,'-').slice(0,19); }
function csvEsc(v) { const s = String(v ?? ''); return /[,"\n]/.test(s) ? '"' + s.replace(/"/g,'""') + '"' : s; }
function logoSvg(size) { return `<svg width="${size}" height="${size}" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M29.1455 34.6316V41.8158V59.2632L49 47.2895V38.0526L19.9818 21.2895L26.4727 17.5263L56.2545 34.6316V51.0526L29.1455 65.0789L26.4727 67.4737L21.5091 65.0789V36.7432L18.0727 34.4605L14.6364 32.1779V61.3158L7 57.2105V27.1053L11.9636 24.7105L29.1455 34.6316Z" fill="url(#lg1)"/><path d="M39.0727 75L32.9636 71.2368L63.1273 54.8158V29.1579L34.4909 13.0789L39.0727 10L70 27.1053V59.2632L39.0727 75Z" fill="url(#lg2)"/><defs><linearGradient id="lg1" x1="38.5" y1="10" x2="38.5" y2="75" gradientUnits="userSpaceOnUse"><stop offset="0.14" stop-color="#FF0000"/><stop offset="0.89" stop-color="#0015FF"/></linearGradient><linearGradient id="lg2" x1="38.5" y1="10" x2="38.5" y2="75" gradientUnits="userSpaceOnUse"><stop offset="0.14" stop-color="#FF0000"/><stop offset="0.89" stop-color="#0015FF"/></linearGradient></defs></svg>`; }
function iconSvg(name) { if(name==='plug') return `<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22v-4M12 2v4M7 7l-3-3M17 7l3-3M17 17l3 3M7 17l-3 3M2 12h4M18 12h4"/></svg>`; return ''; }
function faviconTag() { return `<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,${encodeURIComponent(logoSvg(32))}">`; }

// ─── Embedded CSS ─────────────────────────────────────────────────────────────
const CSS = `@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');:root,[data-theme="light"]{--primary:#2563eb;--primary-dark:#1d4ed8;--primary-light:#3b82f6;--secondary:#7c3aed;--success:#10b981;--danger:#ef4444;--warning:#f59e0b;--info:#06b6d4;--dark:#0f172a;--dark-light:#1e293b;--dark-lighter:#334155;--light:#f8fafc;--light-dark:#e2e8f0;--border:#cbd5e1;--text:#1e293b;--text-light:#64748b;--text-lighter:#94a3b8;--sidebar-width:220px;--header-height:44px;--shadow-sm:0 1px 2px 0 rgba(0,0,0,0.05);--shadow-md:0 4px 6px -1px rgba(0,0,0,0.1);--shadow-lg:0 10px 15px -3px rgba(0,0,0,0.1);--shadow-xl:0 20px 25px -5px rgba(0,0,0,0.1);--radius:10px;--radius-sm:6px;--transition:all 0.3s cubic-bezier(0.4,0,0.2,1);--bg-gradient:linear-gradient(135deg,#667eea 0%,#764ba2 100%);--card-bg:rgba(255,255,255,0.98);--sidebar-bg:white;--main-bg:#f1f5f9;--input-bg:white;--table-header-bg:var(--light);--table-row-hover:var(--light)}[data-theme="dark"]{--primary:#3b82f6;--primary-dark:#2563eb;--primary-light:#60a5fa;--secondary:#8b5cf6;--success:#22c55e;--danger:#f87171;--warning:#fbbf24;--info:#22d3ee;--dark:#f8fafc;--dark-light:#e2e8f0;--dark-lighter:#cbd5e1;--light:#1e293b;--light-dark:#334155;--border:#475569;--text:#f1f5f9;--text-light:#cbd5e1;--text-lighter:#94a3b8;--shadow-sm:0 1px 2px 0 rgba(0,0,0,0.3);--shadow-md:0 4px 6px -1px rgba(0,0,0,0.4);--shadow-lg:0 10px 15px -3px rgba(0,0,0,0.4);--shadow-xl:0 20px 25px -5px rgba(0,0,0,0.4);--bg-gradient:linear-gradient(135deg,#1e293b 0%,#0f172a 100%);--card-bg:#1e293b;--sidebar-bg:#0f172a;--main-bg:#0f172a;--input-bg:#334155;--table-header-bg:#334155;--table-row-hover:#334155}[data-theme="blue"]{--primary:#0ea5e9;--primary-dark:#0284c7;--primary-light:#38bdf8;--secondary:#6366f1;--bg-gradient:linear-gradient(135deg,#0ea5e9 0%,#6366f1 100%);--card-bg:rgba(255,255,255,0.98);--sidebar-bg:#f0f9ff;--main-bg:#e0f2fe;--input-bg:#ffffff;--table-header-bg:#e0f2fe;--table-row-hover:#bae6fd}[data-theme="green"]{--primary:#10b981;--primary-dark:#059669;--primary-light:#34d399;--secondary:#14b8a6;--bg-gradient:linear-gradient(135deg,#10b981 0%,#14b8a6 100%);--card-bg:rgba(255,255,255,0.98);--sidebar-bg:#ecfdf5;--main-bg:#d1fae5;--input-bg:#ffffff;--table-header-bg:#d1fae5;--table-row-hover:#bbf7d0}[data-theme="purple"]{--primary:#a855f7;--primary-dark:#9333ea;--primary-light:#c084fc;--secondary:#ec4899;--bg-gradient:linear-gradient(135deg,#a855f7 0%,#ec4899 100%);--card-bg:rgba(255,255,255,0.98);--sidebar-bg:#fdf4ff;--main-bg:#f5e6ff;--input-bg:#ffffff;--table-header-bg:#f3e8ff;--table-row-hover:#e9d5ff}[data-theme="sunset"]{--primary:#fb923c;--primary-dark:#ea580c;--primary-light:#fdba74;--secondary:#f87171;--bg-gradient:linear-gradient(135deg,#fb923c 0%,#f43f5e 100%);--card-bg:rgba(255,255,255,0.98);--sidebar-bg:#fff7ed;--main-bg:#ffe4d6;--input-bg:#ffffff;--table-header-bg:#ffe7d6;--table-row-hover:#ffd4b8}[data-theme="slate"]{--primary:#475569;--primary-dark:#334155;--primary-light:#94a3b8;--secondary:#0ea5e9;--bg-gradient:linear-gradient(135deg,#475569 0%,#0f172a 100%);--card-bg:#1f2937;--sidebar-bg:#111827;--main-bg:#0f172a;--input-bg:#273449;--text:#e2e8f0;--text-light:#cbd5f5;--text-lighter:#94a3b8;--border:#2f3b52;--table-header-bg:#2b374a;--table-row-hover:#1c2533}*{margin:0;padding:0;box-sizing:border-box}body{font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg-gradient);color:var(--text);line-height:1.5;min-height:100vh;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}.hidden{display:none !important}.column-option-disabled{opacity:0.5}.login-container{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}.login-box{background:var(--card-bg);backdrop-filter:blur(20px);padding:20px;border-radius:var(--radius);box-shadow:var(--shadow-xl);max-width:380px;width:100%;animation:slideUp 0.5s ease;border:1px solid var(--border)}@keyframes slideUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}.logo{text-align:center;margin-bottom:16px;display:flex;flex-direction:column;align-items:center;justify-content:center}.logo-icon{width:56px;height:56px;margin:0 auto 8px;background:linear-gradient(135deg,var(--primary),var(--secondary));border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;position:relative;box-shadow:var(--shadow-lg)}.logo h1{font-size:18px;font-weight:800;color:var(--dark);margin-bottom:4px;letter-spacing:-0.5px}.logo p{color:var(--text-light);font-size:12px;font-weight:500}.app-container{display:flex;min-height:100vh}.mobile-menu-toggle{display:none;position:fixed;top:20px;left:20px;z-index:1000;background:var(--card-bg);border:1px solid var(--border);padding:8px;border-radius:var(--radius-sm);box-shadow:var(--shadow-md);cursor:pointer}.mobile-menu-toggle span{display:block;width:20px;height:2px;background:var(--text);margin:4px 0;transition:var(--transition)}.sidebar{width:var(--sidebar-width);background:var(--sidebar-bg);position:fixed;height:100vh;box-shadow:var(--shadow-lg);z-index:100;border-right:1px solid var(--border);transition:var(--transition);display:flex;flex-direction:column;overflow:hidden}.sidebar-header{padding:10px;border-bottom:1px solid var(--light-dark);flex-shrink:0}.sidebar-logo{display:flex;align-items:center;gap:8px}.sidebar-logo-icon{width:32px;height:32px;background:linear-gradient(135deg,var(--primary),var(--secondary));border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;position:relative}.sidebar-title h2{font-size:15px;font-weight:700;color:var(--dark);line-height:1.2}.sidebar-title p{font-size:12px;color:var(--text-light);margin-top:2px}.sidebar-nav{padding:6px 0;flex:1;overflow-y:auto;overflow-x:hidden}.sidebar-nav::-webkit-scrollbar{width:6px}.sidebar-nav::-webkit-scrollbar-track{background:var(--light)}.sidebar-nav::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px}.sidebar-nav::-webkit-scrollbar-thumb:hover{background:var(--text-light)}.nav-item{display:flex;align-items:center;padding:7px 14px;color:var(--text);text-decoration:none;transition:var(--transition);border-left:3px solid transparent;font-weight:500;font-size:12px}.nav-item:hover{background:var(--light);color:var(--primary);border-left-color:var(--primary)}.nav-item.active{background:linear-gradient(90deg,rgba(37,99,235,0.1),transparent);color:var(--primary);border-left-color:var(--primary);font-weight:600}.nav-divider{height:1px;background:var(--light-dark);margin:6px 0}.nav-section{margin:4px 0}.nav-section-header{display:flex;align-items:center;padding:6px 14px;color:var(--dark);font-weight:600;font-size:12px;cursor:pointer;transition:var(--transition);gap:8px}.nav-section-header:hover{background:var(--light)}.nav-arrow{transition:transform 0.3s ease;font-size:10px;color:var(--text-light)}.nav-section-header.collapsed .nav-arrow{transform:rotate(-90deg)}.nav-section-content{max-height:0;overflow:hidden;transition:max-height 0.3s ease}.nav-section-content.active{max-height:500px;overflow-y:auto;overflow-x:hidden}.nav-section-content::-webkit-scrollbar{width:4px}.nav-section-content::-webkit-scrollbar-track{background:transparent}.nav-section-content::-webkit-scrollbar-thumb{background:var(--border);border-radius:2px}.nav-section-content::-webkit-scrollbar-thumb:hover{background:var(--text-light)}.nav-sub-item{display:flex;align-items:center;padding:5px 14px 5px 28px;color:var(--text);text-decoration:none;transition:var(--transition);border-left:3px solid transparent;font-size:12px}.nav-sub-item:hover{background:var(--light);color:var(--primary)}.nav-sub-item.active{background:linear-gradient(90deg,rgba(37,99,235,0.08),transparent);color:var(--primary);border-left-color:var(--primary);font-weight:500}.sidebar-footer{padding:8px 12px;border-top:1px solid var(--light-dark);background:var(--sidebar-bg);flex-shrink:0}.user-info{display:flex;align-items:center;padding:8px;background:var(--light);border-radius:var(--radius-sm);margin-bottom:8px}.user-avatar{width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));display:flex;align-items:center;justify-content:center;font-weight:700;color:white;margin-right:10px;font-size:13px}.user-details{flex:1;min-width:0}.user-details .name{font-size:13px;font-weight:600;color:var(--dark);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.user-details .role{font-size:12px;color:var(--text-light);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.main-content{flex:1;margin-left:var(--sidebar-width);background:var(--main-bg);min-height:100vh;transition:var(--transition)}.top-bar{background:var(--card-bg);height:var(--header-height);display:flex;align-items:center;justify-content:space-between;padding:0 20px;box-shadow:var(--shadow-sm);position:sticky;top:0;z-index:50;border-bottom:1px solid var(--border)}.breadcrumb{display:flex;align-items:center;gap:6px;color:var(--text-light);font-size:13px;font-weight:500}.breadcrumb a{color:var(--primary);text-decoration:none;transition:var(--transition)}.breadcrumb a:hover{color:var(--primary-dark)}.breadcrumb .separator{color:var(--border)}.system-info{display:flex;align-items:center;gap:12px}.top-bar-preferences{display:flex;align-items:center;gap:8px}.system-time{color:var(--text-light);font-size:13px;font-weight:500;font-variant-numeric:tabular-nums}.content-area{padding:14px}.page-header{margin-bottom:10px}.page-header h1{font-size:18px;font-weight:800;color:var(--dark);margin-bottom:4px;letter-spacing:-0.5px}.page-header p{color:var(--text-light);font-size:14px;margin:0}.page-header-row{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;gap:16px}.page-header-row .page-header{margin-bottom:0;flex:1}.card{background:var(--card-bg);border-radius:var(--radius);box-shadow:var(--shadow-sm);padding:12px;margin-bottom:10px;transition:var(--transition);border:1px solid var(--border)}.card:hover{box-shadow:var(--shadow-md)}.card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;padding-bottom:8px;border-bottom:2px solid var(--light-dark)}.card-title{font-size:14px;font-weight:700;color:var(--dark);margin:0}.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px}.grid-card{background:var(--card-bg);border-radius:var(--radius);padding:10px;box-shadow:var(--shadow-sm);text-decoration:none;color:var(--text);transition:var(--transition);border:2px solid var(--border);position:relative;overflow:hidden;display:flex;align-items:center;gap:12px}.grid-card::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,var(--primary),var(--secondary));transform:scaleX(0);transition:var(--transition)}.grid-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-lg);border-color:var(--primary)}.grid-card:hover::before{transform:scaleX(1)}.grid-card h3{font-size:15px;font-weight:700;color:var(--dark);margin-bottom:5px}.grid-card p{font-size:13px;color:var(--text-light)}.list-view{display:flex;flex-direction:column;gap:12px}.list-view .grid-card{padding:10px 16px;transform:none}.list-view .grid-card:hover{transform:translateX(4px)}.list-view .grid-card h3{margin-bottom:0;font-size:14px}.list-view .grid-card p{display:none}.view-toggle{display:flex;gap:6px;background:var(--card-bg);padding:4px;border-radius:var(--radius-sm);border:2px solid var(--border)}.view-toggle-btn{padding:6px 10px;border:none;background:transparent;color:var(--text-light);cursor:pointer;border-radius:6px;transition:var(--transition);font-weight:600;font-size:13px;display:flex;align-items:center;gap:8px}.view-toggle-btn svg{width:16px;height:16px}.view-toggle-btn:hover{color:var(--primary);background:var(--table-row-hover)}.view-toggle-btn.active{background:var(--primary);color:white}.form-group{margin-bottom:12px}label{display:block;margin-bottom:6px;font-weight:600;color:var(--text);font-size:13px}input[type="text"],input[type="password"],input[type="number"],input[type="file"],select,textarea{width:100%;padding:9px 12px;border:2px solid var(--border);border-radius:var(--radius-sm);font-size:13px;font-family:inherit;transition:var(--transition);background:var(--input-bg);color:var(--text)}input:focus,select:focus,textarea:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 4px rgba(37,99,235,0.1)}textarea{font-family:'Courier New',monospace;resize:vertical;min-height:140px}.btn{display:inline-flex;align-items:center;justify-content:center;padding:5px 12px;border:none;border-radius:var(--radius-sm);font-size:12px;font-weight:600;text-decoration:none;cursor:pointer;transition:var(--transition);gap:6px;font-family:inherit;height:30px;line-height:1;white-space:nowrap}.btn-primary{background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:white;box-shadow:0 4px 14px 0 rgba(37,99,235,0.3)}.btn-primary:hover{transform:translateY(-2px);box-shadow:0 6px 20px 0 rgba(37,99,235,0.4)}.btn-success{background:linear-gradient(135deg,var(--success),#059669);color:white;box-shadow:0 4px 14px 0 rgba(16,185,129,0.3)}.btn-success:hover{transform:translateY(-2px);box-shadow:0 6px 20px 0 rgba(16,185,129,0.4)}.btn-danger{background:linear-gradient(135deg,var(--danger),#dc2626);color:white;box-shadow:0 4px 14px 0 rgba(239,68,68,0.3)}.btn-danger:hover{transform:translateY(-2px);box-shadow:0 6px 20px 0 rgba(239,68,68,0.4)}.btn-secondary{background:var(--card-bg);color:var(--text);border:2px solid var(--border)}.btn-secondary:hover{background:var(--table-row-hover);border-color:var(--primary);color:var(--primary)}.btn-sm{padding:3px 8px;font-size:11px;height:24px}.btn-group{display:flex;gap:6px;margin-bottom:10px;flex-wrap:wrap;align-items:center}.alert{padding:10px 16px;border-radius:var(--radius-sm);margin-bottom:14px;display:flex;align-items:flex-start;gap:12px;animation:slideDown 0.3s ease;font-weight:500}@keyframes slideDown{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}.alert-success{background:#d1fae5;color:#065f46;border-left:4px solid var(--success)}.alert-error{background:#fee2e2;color:#991b1b;border-left:4px solid var(--danger)}.table-container{background:var(--card-bg);border-radius:var(--radius);box-shadow:var(--shadow-sm);overflow:hidden;border:1px solid var(--border);margin-bottom:24px;width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}.table-container::-webkit-scrollbar{height:6px}.table-container::-webkit-scrollbar-track{background:var(--light);border-radius:3px}.table-container::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px}.table-container::-webkit-scrollbar-thumb:hover{background:var(--text-light)}table{width:100%;border-collapse:collapse;min-width:600px}thead{background:var(--table-header-bg);color:var(--text)}th{padding:9px 12px;text-align:left;font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:0.5px;border:1px solid var(--border)}td{padding:8px 12px;border:1px solid var(--light-dark);font-size:13px}tbody tr{transition:var(--transition)}tbody tr:hover{background:var(--table-row-hover)}.null-value{color:#9ca3af;font-style:italic;font-size:13px}.empty-value{color:#d1d5db;font-style:italic;font-size:13px}.pagination{display:flex;justify-content:center;align-items:center;gap:6px;margin-top:20px;flex-wrap:wrap}.pagination a{padding:7px 12px;background:var(--card-bg);color:var(--text);text-decoration:none;border-radius:var(--radius-sm);border:2px solid var(--border);font-weight:600;transition:var(--transition);font-size:13px}.pagination a:hover{border-color:var(--primary);color:var(--primary);background:var(--table-row-hover)}.pagination a.active{background:var(--primary);color:white;border-color:var(--primary)}.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px;margin-bottom:12px}.stat-card{background:var(--card-bg);border-radius:var(--radius);padding:12px;box-shadow:var(--shadow-sm);position:relative;overflow:hidden;border:1px solid var(--border)}.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,var(--primary),var(--secondary))}.stat-card .stat-value{font-size:22px;font-weight:800;color:var(--dark);margin-bottom:4px;letter-spacing:-1px}.stat-card .stat-label{font-size:12px;color:var(--text-light);font-weight:600;text-transform:uppercase;letter-spacing:0.5px}.loader-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.4);display:none;align-items:center;justify-content:center;z-index:9999;backdrop-filter:blur(2px)}.loader-overlay.active{display:flex}.loader-content{display:flex;flex-direction:column;align-items:center;gap:12px}.spinner{width:36px;height:36px;border:3px solid rgba(255,255,255,0.3);border-top:3px solid white;border-radius:50%;animation:spin 1s linear infinite}@keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}.loader-text{color:white;font-weight:600;font-size:14px;animation:pulse 1.5s ease-in-out infinite}@keyframes pulse{0%,100%{opacity:1}50%{opacity:0.6}}.toast-container{position:fixed;top:20px;right:20px;z-index:10000;pointer-events:none}@media (max-width:640px){.toast-container{left:20px;right:20px;top:10px}}.toast{background:white;border-radius:var(--radius-sm);padding:10px 16px;margin-bottom:12px;box-shadow:var(--shadow-lg);display:flex;align-items:center;gap:12px;min-width:260px;animation:slideIn 0.3s ease;pointer-events:auto;border-left:4px solid}@media (max-width:640px){.toast{min-width:auto;width:100%}}@keyframes slideIn{from{opacity:0;transform:translateX(400px)}to{opacity:1;transform:translateX(0)}}@keyframes slideOut{from{opacity:1;transform:translateX(0)}to{opacity:0;transform:translateX(400px)}}.toast.removing{animation:slideOut 0.3s ease}.toast-success{border-left-color:var(--success);background:#d1fae5;color:#065f46}.toast-error{border-left-color:var(--danger);background:#fee2e2;color:#991b1b}.toast-info{border-left-color:var(--info);background:#cffafe;color:#164e63}.toast-warning{border-left-color:var(--warning);background:#fef3c7;color:#78350f}.toast-icon{font-weight:700;font-size:20px;flex-shrink:0}.toast-message{flex:1;font-weight:500}.toast-close{background:none;border:none;font-size:20px;cursor:pointer;opacity:0.7;transition:opacity 0.2s;padding:0;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:inherit}.toast-close:hover{opacity:1}.dropdown{position:relative;display:inline-block}.dropdown-toggle{background:var(--input-bg);color:var(--text);border:2px solid var(--border);padding:5px 12px;border-radius:var(--radius-sm);cursor:pointer;font-weight:600;font-size:12px;display:inline-flex;align-items:center;justify-content:center;gap:6px;transition:var(--transition);height:30px;line-height:1;white-space:nowrap}.dropdown-toggle:hover{border-color:var(--primary);color:var(--primary);background:var(--table-row-hover)}.dropdown-toggle::after{content:'▼';font-size:10px}.dropdown-menu{position:absolute;top:80%;right:0;background:var(--card-bg);border:2px solid var(--border);border-radius:var(--radius-sm);box-shadow:var(--shadow-lg);min-width:200px;z-index:1000;display:none;margin-top:8px;overflow:hidden}.dropdown.open .dropdown-menu{display:block}.dropdown:hover .dropdown-menu,.dropdown-menu:hover{display:block}.dropdown-item{display:block;padding:8px 14px;color:var(--text);text-decoration:none;transition:var(--transition);border-bottom:1px solid var(--light-dark);font-size:13px;font-weight:500;background:var(--card-bg)}.dropdown-item:last-child{border-bottom:none}.dropdown-item:hover{background:var(--table-row-hover);color:var(--primary)}.dropdown-item.danger:hover{background:#fee2e2;color:var(--danger)}.dropdown-item.active{background:var(--primary);color:white}.dropdown-item.active:hover{background:var(--primary-dark);color:white}.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);z-index:2000;align-items:center;justify-content:center;padding:20px}.modal.active{display:flex}.modal-content{background:var(--card-bg);border-radius:var(--radius);max-width:560px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:var(--shadow-xl);animation:modalSlideUp 0.3s ease}@keyframes modalSlideUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}.modal-header{padding:10px 14px;border-bottom:2px solid var(--light-dark);display:flex;align-items:center;justify-content:space-between}.modal-header h3{font-size:15px;font-weight:700;color:var(--dark);margin:0}.modal-close{background:none;border:none;font-size:28px;color:var(--text-light);cursor:pointer;line-height:1;padding:0;width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:50%;transition:var(--transition)}.modal-close:hover{background:var(--light);color:var(--danger)}.modal-body{padding:12px}.modal-footer{padding:8px 12px;border-top:2px solid var(--light-dark);display:flex;gap:12px;justify-content:flex-end}.structure-scroll{max-height:calc(100vh - 200px);overflow:auto}.browse-table-container{display:block;overflow-x:auto;-webkit-overflow-scrolling:touch;width:84.5vw;max-width:84.5vw;overflow-y:visible}#data-table{width:100%;min-width:600px;border-collapse:collapse}.text-center{text-align:center}.mb-0{margin-bottom:0}.mb-2{margin-bottom:10px}.mb-3{margin-bottom:16px}.mt-2{margin-top:10px}.mt-3{margin-top:16px}@media(max-width:1024px){:root{--sidebar-width:260px}.content-area{padding:20px}.top-bar{flex-wrap:wrap;gap:10px}.top-bar-preferences{width:100%;justify-content:flex-start}}@media(max-width:768px){.mobile-menu-toggle{display:block}.sidebar{transform:translateX(-100%)}.sidebar.active{transform:translateX(0)}.main-content{margin-left:0}.top-bar{padding:0 20px 0 70px}.login-box{padding:40px 30px}.grid{grid-template-columns:1fr}.stats-grid{grid-template-columns:1fr}.btn-group{flex-direction:column}.btn-group .btn{width:100%}.page-header h1{font-size:24px}.breadcrumb{font-size:12px}.system-info{gap:10px}.system-time{font-size:12px}.page-header-row{flex-direction:column;align-items:stretch}.view-toggle{width:100%}.view-toggle-btn{flex:1;justify-content:center}}@media(max-width:480px){.content-area{padding:15px}.card{padding:20px}.table-container{font-size:13px}th,td{padding:12px 8px}}.table-container::-webkit-scrollbar{height:6px}.table-container::-webkit-scrollbar-track{background:var(--light);border-radius:3px}.table-container::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px}.table-container::-webkit-scrollbar-thumb:hover{background:var(--text-light)}[dir="rtl"] .sidebar{left:auto;right:0;border-right:none;border-left:1px solid var(--border)}[dir="rtl"] .main-content{margin-left:0;margin-right:var(--sidebar-width)}[dir="rtl"] .mobile-menu-toggle{left:auto;right:20px}[dir="rtl"] .top-bar{padding:0 20px 0 30px}@media (max-width:768px){[dir="rtl"] .top-bar{padding:0 70px 0 20px}}[dir="rtl"] .nav-item{border-left:none;border-right:3px solid transparent;text-align:right}[dir="rtl"] .nav-item:hover{border-left-color:transparent;border-right-color:var(--primary)}[dir="rtl"] .nav-item.active{border-left-color:transparent;border-right-color:var(--primary);background:linear-gradient(270deg,rgba(37,99,235,0.1),transparent)}[dir="rtl"] .nav-section-header{text-align:right;flex-direction:row-reverse}[dir="rtl"] .nav-sub-item{padding:5px 28px 5px 14px;border-left:none;border-right:3px solid transparent;text-align:right}[dir="rtl"] .nav-sub-item:hover{border-right-color:var(--primary)}[dir="rtl"] .nav-sub-item.active{border-left-color:transparent;border-right-color:var(--primary);background:linear-gradient(270deg,rgba(37,99,235,0.08),transparent)}[dir="rtl"] .user-avatar{margin-right:0;margin-left:10px}[dir="rtl"] .alert{border-left:none;border-right:4px solid}[dir="rtl"] .alert-success{border-right-color:var(--success)}[dir="rtl"] .alert-error{border-right-color:var(--danger)}[dir="rtl"] .toast{border-left:none;border-right:4px solid;animation:slideInRTL 0.3s ease}[dir="rtl"] .toast.removing{animation:slideOutRTL 0.3s ease}@keyframes slideInRTL{from{opacity:0;transform:translateX(-400px)}to{opacity:1;transform:translateX(0)}}@keyframes slideOutRTL{from{opacity:1;transform:translateX(0)}to{opacity:0;transform:translateX(-400px)}}[dir="rtl"] .stat-card::before,[dir="rtl"] .grid-card::before{background:linear-gradient(270deg,var(--primary),var(--secondary))}[dir="rtl"] .dropdown-menu{right:auto;left:0}[dir="rtl"] .breadcrumb{flex-direction:row-reverse}[dir="rtl"] .card-header{flex-direction:row-reverse}[dir="rtl"] .page-header-row{flex-direction:row-reverse}[dir="rtl"] .btn-group{flex-direction:row-reverse}[dir="rtl"] th,[dir="rtl"] td{text-align:right}[dir="rtl"] .list-view .grid-card:hover{transform:translateX(-4px)}`;

// ─── Embedded client-side JS ──────────────────────────────────────────────────
const CLIENT_JS = `
function showLoader(m){document.getElementById('loaderText').textContent=m||'Processing...';document.getElementById('loaderOverlay').classList.add('active');}
function hideLoader(){document.getElementById('loaderOverlay').classList.remove('active');}
function showToast(msg,type,dur){type=type||'info';dur=dur===undefined?4000:dur;const c=document.getElementById('toastContainer');const t=document.createElement('div');t.className='toast toast-'+type;const ic={'success':'✓','error':'✕','info':'ⓘ','warning':'⚠'};t.innerHTML='<span class="toast-icon">'+(ic[type]||'•')+'</span><span class="toast-message">'+msg+'</span><button class="toast-close" onclick="this.parentElement.remove()">×</button>';c.appendChild(t);if(dur>0)setTimeout(()=>{t.classList.add('removing');setTimeout(()=>t.remove(),300);},dur);return t;}
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('active');}
function updateTime(){const el=document.getElementById('systemTime');if(!el)return;const n=new Date();el.textContent=String(n.getHours()).padStart(2,'0')+':'+String(n.getMinutes()).padStart(2,'0')+':'+String(n.getSeconds()).padStart(2,'0');}
updateTime();setInterval(updateTime,1000);
function toggleView(v){const c=document.getElementById('databasesContainer')||document.getElementById('tablesContainer');if(!c)return;c.className=v==='list'?'list-view':'grid';document.querySelectorAll('.view-toggle-btn').forEach(b=>b.classList.toggle('active',b.dataset.view===v));localStorage.setItem('dbadmin_view',v);}
document.addEventListener('DOMContentLoaded',()=>{const sv=localStorage.getItem('dbadmin_view')||'grid';if(document.getElementById('databasesContainer')||document.getElementById('tablesContainer'))toggleView(sv);});
function openModal(id){const m=document.getElementById(id);if(m)m.classList.add('active');}
function closeModal(id){const m=document.getElementById(id);if(m)m.classList.remove('active');}
document.addEventListener('click',e=>{if(e.target.classList.contains('modal'))e.target.classList.remove('active');});
document.addEventListener('keydown',e=>{if(e.key==='Escape'){const m=document.querySelector('.modal.active');if(m)m.classList.remove('active');}});
function selectAll(cb){document.querySelectorAll('input[name="selected[]"]').forEach(c=>c.checked=cb.checked);updateActionButton();}
function updateActionButton(){const n=document.querySelectorAll('input[name="selected[]"]:checked').length;const b=document.querySelector('.action-dropdown-toggle');if(b){b.disabled=n===0;const s=document.querySelector('.selection-count');if(s)s.textContent=n>0?' ('+n+')':'';};}
function performAction(action){const items=Array.from(document.querySelectorAll('input[name="selected[]"]:checked')).map(c=>c.value);if(!items.length){showToast('Select at least one item','warning');return;}if(!confirm(action+' '+items.join(', ')+'? This cannot be undone!'))return;showLoader('Processing '+action+'...');document.getElementById('bulk-action').value=action;document.getElementById('bulk-action-form').submit();}
function searchItems(inp){const t=inp.value.toLowerCase();(document.getElementById('databasesContainer')||document.getElementById('tablesContainer'))?.querySelectorAll('.grid-card').forEach(c=>c.style.display=c.textContent.toLowerCase().includes(t)?'':'none');}
function toggleAllTables(cb){document.querySelectorAll('.table-checkbox').forEach(c=>c.checked=cb.checked);}
function loadExportTables(db){const s=document.getElementById('export-table');if(!s||!db)return;s.innerHTML='<option>Loading...</option>';s.disabled=true;fetch('/?action=get_tables&db='+encodeURIComponent(db)).then(r=>r.json()).then(t=>{s.innerHTML=t.map(n=>'<option value="'+n+'">'+n+'</option>').join('');s.disabled=false;}).catch(()=>{s.innerHTML='<option>Error</option>';s.disabled=false;});}
let columnIndex=0;
function addColumnRow(data){data=data||{};const c=document.getElementById('columnsBuilder');const r=document.createElement('div');r.style.cssText='display:flex;gap:6px;align-items:center;flex-wrap:wrap';r.innerHTML='<input class="col-name" placeholder="name" style="flex:1;min-width:80px;padding:6px;border:2px solid var(--border);border-radius:5px"><select class="col-type" style="width:110px;padding:6px;border:2px solid var(--border);border-radius:5px"><option>INT</option><option>VARCHAR</option><option>TEXT</option><option>DATE</option><option>DATETIME</option><option>DECIMAL</option><option>BOOLEAN</option><option>BIGINT</option></select><input class="col-length" placeholder="len" style="width:70px;padding:6px;border:2px solid var(--border);border-radius:5px"><label style="display:flex;align-items:center;gap:4px;font-size:12px"><input type="checkbox" class="col-null"> NULL</label><button type="button" onclick="this.parentElement.remove()" class="btn btn-danger btn-sm">✕</button>';if(c)c.appendChild(r);}
addColumnRow();
function toggleNav(h){const s=h.nextElementSibling;h.classList.toggle('collapsed');if(s.classList.contains('active')){s.classList.remove('active');}else{s.classList.add('active');}}
function prepareCreateTable(){const name=document.getElementById('create_table_name')?.value.trim();if(!name){alert('Table name required');return false;}const rows=document.querySelectorAll('#columnsBuilder > div');const cols=[];rows.forEach(r=>{const n=r.querySelector('.col-name')?.value.trim();if(!n)return;const t=r.querySelector('.col-type')?.value;const l=r.querySelector('.col-length')?.value;const nl=r.querySelector('.col-null')?.checked;let d=n+' '+t+(l?'('+l+')':'');d+=nl?' NULL':' NOT NULL';cols.push('\`'+n+'\` '+t+(l?'('+l+')':'')+(nl?' NULL':' NOT NULL'));});if(!cols.length){alert('Add at least one column');return false;}const sql='CREATE TABLE \`'+name+'\` ('+cols.join(', ')+') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';document.getElementById('create_table_sql').value=sql;return true;}
const translations={en:{app_name:"Dabiro",app_tagline:"Professional database management interface",database_type_label:"Database Type",host_label:"Host / File Path",username_label:"Username",password_label:"Password",database_name_label:"Database Name (optional)",connect_button:"Connect to Database",databases:"Databases",tables:"Tables",browse:"Browse",structure:"Structure",sql_console:"SQL Console",import_data:"Import Data",export_data:"Export Data",global_search:"Global Search",logout:"Logout",theme:"Theme",language:"Language",select_all:"Select All"},ar:{app_name:"Dabiro",app_tagline:"واجهة احترافية لإدارة قواعد البيانات",database_type_label:"نوع قاعدة البيانات",host_label:"المضيف/مسار الملف",username_label:"اسم المستخدم",password_label:"كلمة المرور",database_name_label:"اسم القاعدة (اختياري)",connect_button:"الاتصال بقاعدة البيانات",databases:"قواعد البيانات",tables:"الجداول",browse:"تصفح",structure:"الهيكل",sql_console:"وحدة SQL",import_data:"استيراد البيانات",export_data:"تصدير البيانات",global_search:"البحث الشامل",logout:"تسجيل الخروج",theme:"السمة",language:"اللغة",select_all:"تحديد الكل"},es:{app_name:"Dabiro",connect_button:"Conectar a la Base de Datos",databases:"Bases de Datos",logout:"Cerrar Sesión",theme:"Tema",language:"Idioma"},fr:{app_name:"Dabiro",connect_button:"Se Connecter",databases:"Bases de Données",logout:"Déconnexion",theme:"Thème",language:"Langue"},de:{app_name:"Dabiro",connect_button:"Verbinden",databases:"Datenbanken",logout:"Abmelden",theme:"Design",language:"Sprache"},zh:{app_name:"Dabiro",connect_button:"连接数据库",databases:"数据库",logout:"退出",theme:"主题",language:"语言"},ja:{app_name:"Dabiro",connect_button:"接続",databases:"データベース",logout:"ログアウト",theme:"テーマ",language:"言語"},ru:{app_name:"Dabiro",connect_button:"Подключиться",databases:"Базы данных",logout:"Выйти",theme:"Тема",language:"Язык"},it:{app_name:"Dabiro",connect_button:"Connetti",databases:"Database",logout:"Disconnetti",theme:"Tema",language:"Lingua"},tr:{app_name:"Dabiro",connect_button:"Bağlan",databases:"Veritabanları",logout:"Çıkış",theme:"Tema",language:"Dil"},hi:{app_name:"Dabiro",connect_button:"कनेक्ट करें",databases:"डेटाबेस",logout:"लॉगआउट",theme:"थीम",language:"भाषा"},pt:{app_name:"Dabiro",connect_button:"Conectar",databases:"Bancos de Dados",logout:"Sair",theme:"Tema",language:"Idioma"},ko:{app_name:"Dabiro",connect_button:"연결",databases:"데이터베이스",logout:"로그아웃",theme:"테마",language:"언어"}};
function applyTranslations(lang){const pack=translations[lang]||translations.en;document.querySelectorAll('[data-i18n]').forEach(el=>{const k=el.getAttribute('data-i18n');const v=pack[k]||translations.en[k];if(v)el.textContent=v;});document.documentElement.setAttribute('dir',lang==='ar'?'rtl':'ltr');if(document.body)document.body.style.direction=lang==='ar'?'rtl':'ltr';}
`;

// ─── Start ────────────────────────────────────────────────────────────────────
app.listen(PORT, () => {
    console.log(`\nDabiro v${VERSION} (Node.js) running at http://localhost:${PORT}`);
    console.log(`DB drivers loaded: ${[mysql2&&'mysql2',pg&&'pg',Sqlite3&&'sqlite3'].filter(Boolean).join(', ')||'none'}`);
});
