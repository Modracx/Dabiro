'use strict';
/**
 * Dabiro - Professional Database Management System (Node.js Edition)
 * A single-file Express database manager with remote DB connectivity, size metrics, and advanced search.
 * Version: 1.2.1
 * Kenneth D'silva (Modracx), Copyright (c) March 2026
 * Licensed under the MIT License – https://opensource.org/licenses/MIT
 */

const express      = require('express');
const session      = require('express-session');
const cookieParser = require('cookie-parser');
const multer       = require('multer');
const crypto       = require('crypto');

// Optional DB drivers
let mysql2, pg, Sqlite3;
try { mysql2  = require('mysql2/promise'); }   catch (_) {}
try { pg      = require('pg');             }   catch (_) {}
try { Sqlite3 = require('sqlite3').verbose(); } catch (_) {}

// ─── Configuration ────────────────────────────────────────────────────────────
const VERSION         = '1.2.1';
const SESSION_TIMEOUT = 3_600_000;
const PORT            = process.env.PORT || 5050;
const SESSION_SECRET  = process.env.SESSION_SECRET || crypto.randomBytes(32).toString('hex');

const THEME_OPTIONS = { light: 'Light', dark: 'Dark', slate: 'Slate', blue: 'Blue', green: 'Green', purple: 'Purple', sunset: 'Sunset' };
const LANG_OPTIONS  = { en: 'English', es: 'Español', fr: 'Français', de: 'Deutsch', pt: 'Português', zh: '中文', ja: '日本語', ar: 'العربية', it: 'Italiano', ru: 'Русский', tr: 'Türkçe', hi: 'हिन्दी', ko: '한국어' };

const TRANSLATIONS = {
    en: {
        app_name: 'Dabiro',
        app_tagline: 'Professional Database Management Interface',
        database_type_label: 'Database Type',
        host_label: 'Host / File Path',
        port_label: 'Port',
        username_label: 'Username',
        password_label: 'Password',
        database_name_label: 'Database Name (optional)',
        ssl_label: 'Require SSL / TLS Encryption (Remote Database)',
        connect_button: 'Connect to Database',
        connect_uri_label: 'Or Paste Connection URL (e.g. postgres://... or mysql://...)',
        saved_connections: 'Saved Connections',
        logout: 'Logout',
        databases: 'Databases',
        tables: 'Tables',
        browse: 'Browse',
        structure: 'Structure',
        sql_console: 'SQL Console',
        import_data: 'Import Data',
        export_data: 'Export Data',
        global_search: 'Global Search',
        operations: 'Operations',
        create_database: 'Create Database',
        create_table: 'Create Table',
        table_name: 'Table Name',
        columns: 'Columns',
        add_column: 'Add Column',
        add_condition: 'Add Condition',
        rename_table: 'Rename Table',
        copy_table: 'Copy Table',
        drop_selected: 'Drop Selected',
        truncate_selected: 'Truncate Selected',
        insert_record: 'Insert Record',
        edit_record: 'Edit Record',
        save: 'Save Record',
        cancel: 'Cancel',
        search: 'Search',
        filter: 'Filter',
        clear: 'Clear',
        total_size: 'Total Size',
        data_size: 'Data Size',
        index_size: 'Index Size',
        overhead: 'Overhead / Free',
        engine: 'Engine',
        collation: 'Collation',
        records: 'Records',
        actions: 'Actions',
        execute_query: 'Execute Query',
        export_query: 'Export Query as SQL',
        query_results: 'Query Results',
        rows: 'Rows'
    },
    es: {
        app_name: 'Dabiro',
        app_tagline: 'Interfaz profesional de gestión de bases de datos',
        database_type_label: 'Tipo de Base de Datos',
        host_label: 'Servidor / Ruta de Archivo',
        port_label: 'Puerto',
        username_label: 'Usuario',
        password_label: 'Contraseña',
        database_name_label: 'Nombre de Base de Datos (opcional)',
        ssl_label: 'Requerir cifrado SSL / TLS (Remoto)',
        connect_button: 'Conectar a la Base de Datos',
        connect_uri_label: 'O Pegar URL de Conexión',
        logout: 'Cerrar Sesión',
        databases: 'Bases de Datos',
        tables: 'Tablas',
        browse: 'Explorar',
        structure: 'Estructura',
        sql_console: 'Consola SQL',
        total_size: 'Tamaño Total',
        data_size: 'Tamaño de Datos',
        index_size: 'Tamaño de Índices',
        engine: 'Motor',
        actions: 'Acciones'
    },
    zh: {
        app_name: 'Dabiro',
        app_tagline: '专业的现代化数据库管理界面',
        database_type_label: '数据库类型',
        host_label: '主机 / 文件路径',
        port_label: '端口',
        username_label: '用户名',
        password_label: '密码',
        database_name_label: '数据库名称 (可选)',
        ssl_label: '启用 SSL / TLS 加密 (远程连接)',
        connect_button: '连接数据库',
        connect_uri_label: '或粘贴连接字符串',
        logout: '退出登录',
        databases: '数据库',
        tables: '数据表',
        browse: '浏览数据',
        structure: '表结构',
        sql_console: 'SQL 控制台',
        total_size: '总容量大小',
        data_size: '数据大小',
        index_size: '索引大小',
        overhead: '空间碎片',
        engine: '存储引擎',
        collation: '字符集校对',
        actions: '操作'
    },
    ar: {
        app_name: 'Dabiro',
        app_tagline: 'واجهة احترافية متقدمة لإدارة قواعد البيانات',
        database_type_label: 'نوع قاعدة البيانات',
        host_label: 'المضيف / مسار الملف',
        port_label: 'المنفذ',
        username_label: 'اسم المستخدم',
        password_label: 'كلمة المرور',
        database_name_label: 'اسم القاعدة (اختياري)',
        ssl_label: 'تشفير SSL / TLS (اتصال عن بعد)',
        connect_button: 'الاتصال بقاعدة البيانات',
        connect_uri_label: 'أو الصق رابط الاتصال المباشر',
        logout: 'تسجيل الخروج',
        databases: 'قواعد البيانات',
        tables: 'الجداول',
        browse: 'تصفح',
        structure: 'الهيكل',
        sql_console: 'وحدة SQL',
        total_size: 'الحجم الإجمالي',
        data_size: 'حجم البيانات',
        index_size: 'حجم الفهارس',
        engine: 'المحرك',
        actions: 'الإجراءات'
    }
};

function t(lang, key, def = null) {
    if (TRANSLATIONS[lang] && TRANSLATIONS[lang][key]) return TRANSLATIONS[lang][key];
    if (TRANSLATIONS['en'] && TRANSLATIONS['en'][key]) return TRANSLATIONS['en'][key];
    return def !== null ? def : key;
}

function formatBytes(bytes, precision = 2) {
    bytes = Number(bytes);
    if (!bytes || bytes <= 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    const pow = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    return (bytes / Math.pow(1024, pow)).toFixed(precision) + ' ' + units[pow];
}

// ─── Express App Setup ────────────────────────────────────────────────────────
const app = express();
app.use(express.urlencoded({ extended: true, limit: '50mb' }));
app.use(express.json({ limit: '50mb' }));
app.use(cookieParser());
app.use(session({
    secret: SESSION_SECRET,
    resave: false,
    saveUninitialized: false,
    cookie: { httpOnly: true, maxAge: SESSION_TIMEOUT }
}));
const upload = multer({ storage: multer.memoryStorage(), limits: { fileSize: 25 * 1024 * 1024 } });

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

function getLang(req)  { const v = req.cookies.dbadmin_lang  || 'en';  return LANG_OPTIONS[v]  ? v : 'en'; }
function getTheme(req) { const v = req.cookies.dbadmin_theme || 'light'; return THEME_OPTIONS[v] ? v : 'light'; }
function isLoggedIn(req) { return req.session.logged_in === true; }

// ─── DbConnection Class ───────────────────────────────────────────────────────
class DbConnection {
    constructor() { this.conn = null; this.type = ''; }

    async connect(type, host, user, pass, dbname = '', port = '', ssl = false) {
        this.type = type;
        try {
            if (type === 'mysql') {
                if (!mysql2) return 'mysql2 not installed. Run: npm install mysql2';
                const [h, p = '3306'] = host.includes(':') ? host.split(':') : [host, port || '3306'];
                const opts = { host: h, port: +p, user, password: pass, database: dbname || undefined, multipleStatements: true };
                if (ssl) opts.ssl = { rejectUnauthorized: false };
                this.conn = await mysql2.createConnection(opts);
            } else if (type === 'pgsql') {
                if (!pg) return 'pg not installed. Run: npm install pg';
                const [h, p = '5432'] = host.includes(':') ? host.split(':') : [host, port || '5432'];
                const opts = { host: h, port: +p, user, password: pass, database: dbname || 'postgres' };
                if (ssl) opts.ssl = { rejectUnauthorized: false };
                this.conn = new pg.Client(opts);
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
        try {
            if (this.type === 'mysql') await this.conn.end();
            else if (this.type === 'pgsql') await this.conn.end();
            else if (this.type === 'sqlite') await new Promise(r => this.conn.close(r));
        } catch (_) {}
        this.conn = null;
    }

    quoteId(name) { return this.type === 'pgsql' ? '"' + String(name).replace(/"/g,'""') + '"' : '`' + String(name).replace(/`/g,'``') + '`'; }
    quoteVal(v)   { if (v == null) return 'NULL'; const s = String(v); return this.type === 'pgsql' ? "'" + s.replace(/'/g,"''") + "'" : "'" + s.replace(/\\/g,'\\\\').replace(/'/g,"\\'") + "'"; }

    async run(sql, params = []) {
        if (!this.conn) throw new Error('Not connected');
        if (this.type === 'mysql') {
            const [rows] = await this.conn.query(sql, params);
            return rows;
        } else if (this.type === 'pgsql') {
            const res = await this.conn.query(sql, params);
            return res.rows;
        } else if (this.type === 'sqlite') {
            const trimmed = sql.trim().toUpperCase();
            if (trimmed.startsWith('SELECT') || trimmed.startsWith('PRAGMA') || trimmed.startsWith('EXPLAIN')) {
                return await new Promise((resolve, reject) => {
                    this.conn.all(sql, params, (err, rows) => err ? reject(err) : resolve(rows || []));
                });
            } else {
                return await new Promise((resolve, reject) => {
                    this.conn.run(sql, params, function(err) { err ? reject(err) : resolve({ affectedRows: this.changes, lastID: this.lastID }); });
                });
            }
        }
    }

    async getDatabasesWithStats() {
        const list = {};
        if (this.type === 'mysql') {
            try {
                const rows = await this.run(`SELECT table_schema AS db_name, COUNT(table_name) AS table_count, COALESCE(SUM(data_length + index_length), 0) AS total_size, COALESCE(SUM(data_length), 0) AS data_size, COALESCE(SUM(index_length), 0) AS index_size FROM information_schema.TABLES GROUP BY table_schema ORDER BY table_schema`);
                for (const r of rows) {
                    list[r.db_name] = { name: r.db_name, tables: Number(r.table_count), size: Number(r.total_size), data_size: Number(r.data_size), index_size: Number(r.index_size) };
                }
            } catch (_) {}
        } else if (this.type === 'pgsql') {
            try {
                const rows = await this.run(`SELECT datname AS db_name, pg_database_size(datname) AS total_size FROM pg_database WHERE datistemplate = false ORDER BY datname`);
                for (const r of rows) {
                    list[r.db_name] = { name: r.db_name, tables: 0, size: Number(r.total_size), data_size: Number(r.total_size), index_size: 0 };
                }
            } catch (_) {}
        } else if (this.type === 'sqlite') {
            list['main'] = { name: 'main', tables: (await this.getTables('main')).length, size: 0, data_size: 0, index_size: 0 };
        }
        return list;
    }

    async getTables(database) {
        if (this.type === 'mysql') { if (database) await this.run(`USE ${this.quoteId(database)}`); return (await this.run('SHOW TABLES')).map(r => Object.values(r)[0]); }
        if (this.type === 'pgsql') return (await this.run("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename")).map(r => r.tablename);
        if (this.type === 'sqlite') return (await this.run("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")).map(r => r.name);
        return [];
    }

    async getTablesWithStats(database) {
        if (database && this.type !== 'sqlite') await this.run(`USE ${this.quoteId(database)}`);
        if (this.type === 'mysql') {
            try {
                const rows = await this.run(`SHOW TABLE STATUS` + (database ? ` FROM ${this.quoteId(database)}` : ''));
                if (Array.isArray(rows) && rows.length) {
                    return rows.map(r => {
                        const dLen = Number(r.Data_length ?? r.data_length ?? 0);
                        const iLen = Number(r.Index_length ?? r.index_length ?? 0);
                        return {
                            Name: r.Name ?? r.name ?? '',
                            Engine: r.Engine ?? r.engine ?? 'InnoDB',
                            Rows: Number(r.Rows ?? r.rows ?? 0),
                            Data_length: dLen,
                            Index_length: iLen,
                            Total_length: dLen + iLen,
                            Data_free: Number(r.Data_free ?? r.data_free ?? 0),
                            Auto_increment: r.Auto_increment ?? r.auto_increment ?? null,
                            Collation: r.Collation ?? r.collation ?? ''
                        };
                    });
                }
            } catch (_) {}
        } else if (this.type === 'pgsql') {
            try {
                return await this.run(`SELECT c.relname AS "Name", 'heap' AS "Engine", c.reltuples::bigint AS "Rows", pg_relation_size(c.oid) AS "Data_length", (pg_total_relation_size(c.oid) - pg_relation_size(c.oid)) AS "Index_length", pg_total_relation_size(c.oid) AS "Total_length", 0 AS "Data_free", NULL AS "Auto_increment", 'UTF8' AS "Collation" FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = 'public' AND c.relkind = 'r' ORDER BY c.relname`);
            } catch (_) {}
        }
        const tbls = await this.getTables(database);
        return tbls.map(t => ({ Name: t, Engine: 'SQLite', Rows: 0, Data_length: 0, Index_length: 0, Total_length: 0, Data_free: 0, Collation: 'BINARY' }));
    }

    async getColumns(table) {
        if (this.type === 'mysql') return await this.run(`SHOW COLUMNS FROM ${this.quoteId(table)}`);
        if (this.type === 'pgsql') return await this.run(`SELECT column_name as "Field", data_type as "Type", is_nullable as "Null", column_default as "Default" FROM information_schema.columns WHERE table_name=$1 AND (table_schema=current_schema() OR table_schema='public') ORDER BY ordinal_position`, [table]);
        if (this.type === 'sqlite') return await this.run(`PRAGMA table_info(${this.quoteId(table)})`);
        return [];
    }

    async getRowCount(table) {
        try {
            const res = await this.run(`SELECT COUNT(*) as cnt FROM ${this.quoteId(table)}`);
            return parseInt(res[0]?.cnt ?? res[0]?.count ?? 0, 10);
        } catch (_) { return 0; }
    }
}

async function getDb(req) {
    if (!isLoggedIn(req)) return null;
    const db = new DbConnection();
    const res = await db.connect(req.session.db_type, req.session.db_host, req.session.db_user, req.session.db_pass, req.session.db_name, req.session.db_port, req.session.db_ssl);
    if (res !== true) return null;
    return db;
}

// ─── Main Route Handler ───────────────────────────────────────────────────────
app.all('*', upload.single('sql_file'), async (req, res) => {
    if (req.query.set_lang && LANG_OPTIONS[req.query.set_lang]) res.cookie('dbadmin_lang', req.query.set_lang, { maxAge: 365*86400*1000, path: '/' });
    if (req.query.set_theme && THEME_OPTIONS[req.query.set_theme]) res.cookie('dbadmin_theme', req.query.set_theme, { maxAge: 365*86400*1000, path: '/' });

    const body  = req.body || {};
    const query = req.query || {};
    const lang  = req.query.set_lang || getLang(req);
    const theme = req.query.set_theme || getTheme(req);

    if (query.action === 'get_tables' && query.db) {
        res.setHeader('Content-Type', 'application/json; charset=utf-8');
        if (!isLoggedIn(req)) return res.json([]);
        const db = await getDb(req);
        try {
            const tables = db ? await db.getTables(query.db) : [];
            await db?.close();
            return res.json(tables);
        } catch (_) {
            await db?.close();
            return res.json([]);
        }
    }

    let error = null, errorMsg = null, successMsg = null;

    if (body.login) {
        if (!validateCsrf(req, body.csrf_token)) {
            error = 'Security token validation failed.';
        } else {
            const db = new DbConnection();
            const r = await db.connect(body.db_type, body.db_host, body.db_user, body.db_pass, body.db_name, body.db_port, !!body.db_ssl);
            if (r === true) {
                req.session.logged_in = true;
                req.session.db_type   = body.db_type;
                req.session.db_host   = body.db_host;
                req.session.db_port   = body.db_port;
                req.session.db_ssl    = !!body.db_ssl;
                req.session.db_user   = body.db_user;
                req.session.db_pass   = body.db_pass;
                req.session.db_name   = body.db_name || '';
                await db.close();
                return res.redirect(body.db_name ? `/?page=tables&db=${encodeURIComponent(body.db_name)}` : '/?page=databases');
            } else { error = r; }
            await db.close();
        }
    }

    if (body.logout && validateCsrf(req, body.csrf_token)) {
        req.session.destroy(() => res.redirect('/'));
        return;
    }

    const db = await getDb(req);

    if (db) {
        if (body.create_database && validateCsrf(req, body.csrf_token) && body.db_name) {
            try {
                await db.run(`CREATE DATABASE ${db.quoteId(body.db_name.trim())}`);
                successMsg = `Database ${body.db_name} created successfully.`;
            } catch (e) { errorMsg = e.message; }
        }

        if (body.create_table && validateCsrf(req, body.csrf_token) && body.create_table_sql) {
            try {
                if (body.create_table_db && db.type !== 'sqlite') await db.run(`USE ${db.quoteId(body.create_table_db)}`);
                await db.run(body.create_table_sql);
                successMsg = 'Table created successfully.';
            } catch (e) { errorMsg = e.message; }
        }

        if (body.bulk_action && validateCsrf(req, body.csrf_token)) {
            const items = Array.isArray(body['selected[]']) ? body['selected[]'] : (body['selected[]'] ? [body['selected[]']] : []);
            try {
                if (body.database && db.type !== 'sqlite') await db.run(`USE ${db.quoteId(body.database)}`);
                for (const item of items) {
                    if (body.bulk_action === 'drop') await db.run(`DROP TABLE ${db.quoteId(item)}`);
                    else if (body.bulk_action === 'truncate') await db.run(db.type === 'sqlite' ? `DELETE FROM ${db.quoteId(item)}` : `TRUNCATE TABLE ${db.quoteId(item)}`);
                }
                successMsg = `${items.length} table(s) processed.`;
            } catch (e) { errorMsg = e.message; }
        }

        if (query.action === 'delete' && query.table && validateCsrf(req, query.csrf_token)) {
            try {
                if (query.db && db.type !== 'sqlite') await db.run(`USE ${db.quoteId(query.db)}`);
                const conds = [];
                for (const [k, v] of Object.entries(query)) {
                    if (k.startsWith('where_')) conds.push(`${db.quoteId(k.slice(6))} = ${db.quoteVal(v)}`);
                }
                if (conds.length) {
                    await db.run(`DELETE FROM ${db.quoteId(query.table)} WHERE ${conds.join(' AND ')} LIMIT 1`);
                    successMsg = 'Record deleted successfully.';
                }
            } catch (e) { errorMsg = e.message; }
        }
    }

    let sqlResult = null, sqlTime = 0, sqlError = null, sqlAffected = 0;
    if (db && body.execute_sql) {
        if (!validateCsrf(req, body.csrf_token)) {
            sqlError = 'Security token validation failed.';
        } else {
            const sql = (body.sql || '').trim();
            if (sql) {
                const t0 = Date.now();
                try {
                    if (query.db && db.type !== 'sqlite') await db.run(`USE ${db.quoteId(query.db)}`);
                    const r = await db.run(sql);
                    sqlTime = Date.now() - t0;
                    if (Array.isArray(r)) sqlResult = r;
                    else { sqlAffected = r?.affectedRows ?? 0; sqlResult = []; }
                } catch (e) { sqlError = e.message; }
            }
        }
    }

    const page = query.page || (isLoggedIn(req) ? 'databases' : 'login');
    const csrf = csrfToken(req);
    const selected_db = query.db || req.session.db_name || '';
    const selected_table = query.table || '';

    let nav_tables = [];
    let db_stats = {};
    let table_stats = [];

    if (db) {
        if (selected_db) {
            try { nav_tables = await db.getTables(selected_db); } catch (_) {}
            if (page === 'tables') {
                try { table_stats = await db.getTablesWithStats(selected_db); } catch (_) {}
            }
        }
        if (page === 'databases') {
            try { db_stats = await db.getDatabasesWithStats(); } catch (_) {}
        }
    }

    const html = `<!DOCTYPE html>
<html lang="${h(lang)}" dir="${lang === 'ar' ? 'rtl' : 'ltr'}" data-theme="${h(theme)}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>${h(t(lang, 'app_name'))} v${VERSION}</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%232563eb'><path d='M12 3C6.48 3 2 4.79 2 7v10c0 2.21 4.48 4 10 4s10-1.79 10-4V7c0-2.21-4.48-4-10-4zm0 2c4.97 0 8 1.5 8 2s-3.03 2-8 2-8-1.5-8-2 3.03-2 8-2zm0 6c-4.97 0-8-1.5-8-2v3.13c1.78 1.13 4.73 1.87 8 1.87s6.22-.74 8-1.87V9c0 .5-3.03 2-8 2zm0 6c-4.97 0-8-1.5-8-2v3.13c1.78 1.13 4.73 1.87 8 1.87s6.22-.74 8-1.87V15c0 .5-3.03 2-8 2z'/></svg>">
    <style>
        :root, [data-theme="light"] {
            --primary: #2563eb; --primary-hover: #1d4ed8; --primary-light: #dbeafe;
            --success: #10b981; --danger: #ef4444; --warning: #f59e0b;
            --bg-body: #f8fafc; --bg-card: #ffffff; --bg-sidebar: #ffffff; --bg-header: #ffffff;
            --text-main: #0f172a; --text-muted: #64748b; --border: #e2e8f0; --table-hover: #f1f5f9;
            --scrollbar-thumb: #cbd5e1;
            --radius-sm: 6px; --radius: 8px; --radius-lg: 12px;
        }
        [data-theme="dark"] {
            --primary: #3b82f6; --primary-hover: #60a5fa; --primary-light: #1e3a8a;
            --success: #10b981; --danger: #f87171; --warning: #fbbf24;
            --bg-body: #090d16; --bg-card: #131b2e; --bg-sidebar: #0f172a; --bg-header: #0f172a;
            --text-main: #f8fafc; --text-muted: #94a3b8; --border: #1e293b; --table-hover: #1e293b;
            --scrollbar-thumb: #334155;
        }
        [data-theme="slate"] {
            --primary: #64748b; --primary-hover: #475569; --primary-light: #334155;
            --bg-body: #0b0f19; --bg-card: #151c2c; --bg-sidebar: #0f172a; --bg-header: #0f172a;
            --text-main: #f1f5f9; --text-muted: #94a3b8; --border: #1e293b; --table-hover: #1e293b;
            --scrollbar-thumb: #334155;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Inter", sans-serif; background: var(--bg-body); color: var(--text-main); line-height: 1.5; font-size: 14px; min-height: 100vh; }
        a { color: var(--primary); text-decoration: none; }
        a:hover { text-decoration: underline; }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--scrollbar-thumb); border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }

        .app-layout { display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: var(--bg-sidebar); border-right: 1px solid var(--border); display: flex; flex-direction: column; position: fixed; top: 0; bottom: 0; left: 0; z-index: 100; transition: transform 0.2s ease; overflow: hidden; }
        [dir="rtl"] .sidebar { left: auto; right: 0; border-right: none; border-left: 1px solid var(--border); }
        .sidebar-brand { padding: 14px 16px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid var(--border); font-size: 16px; font-weight: 700; color: var(--text-main); flex-shrink: 0; }
        .sidebar-brand svg { width: 26px; height: 26px; fill: var(--primary); }
        .sidebar-nav { flex: 1; overflow-y: auto; overflow-x: hidden; padding: 10px 8px; scrollbar-width: thin; scrollbar-color: var(--scrollbar-thumb) transparent; }
        .nav-link { display: flex; align-items: center; gap: 10px; padding: 8px 12px; border-radius: var(--radius-sm); color: var(--text-main); font-weight: 500; margin-bottom: 2px; text-decoration: none; white-space: nowrap; }
        .nav-link:hover { background: var(--table-hover); text-decoration: none; }
        .nav-link.active { background: var(--primary); color: #ffffff !important; font-weight: 600; }
        .nav-section { margin-top: 14px; padding-top: 10px; border-top: 1px solid var(--border); }
        .nav-section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); padding: 4px 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; letter-spacing: 0.5px; }
        
        .table-tree-item { display: flex; align-items: center; gap: 8px; padding: 6px 12px 6px 18px; font-size: 12.5px; border-radius: var(--radius-sm); color: var(--text-muted); text-decoration: none; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; transition: background 0.15s ease; }
        [dir="rtl"] .table-tree-item { padding: 6px 18px 6px 12px; }
        .table-tree-item:hover { background: var(--table-hover); color: var(--text-main); text-decoration: none; }
        .table-tree-item.active { font-weight: 600; color: var(--primary); background: var(--primary-light); }
        .table-tree-item .tbl-icon { flex-shrink: 0; width: 14px; height: 14px; opacity: 0.75; fill: currentColor; }
        .table-tree-item .tbl-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; min-width: 0; }

        .sidebar-footer { padding: 12px; border-top: 1px solid var(--border); font-size: 12px; background: var(--bg-card); flex-shrink: 0; }
        .main-wrapper { flex: 1; margin-left: 260px; display: flex; flex-direction: column; min-width: 0; }
        [dir="rtl"] .main-wrapper { margin-left: 0; margin-right: 260px; }
        .topbar { height: 54px; background: var(--bg-header); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 0 20px; position: sticky; top: 0; z-index: 90; }
        .breadcrumbs { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 500; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .content-body { padding: 24px; flex: 1; max-width: 1400px; width: 100%; margin: 0 auto; }
        .card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); margin-bottom: 20px; overflow: hidden; }
        .card-header { padding: 14px 18px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .card-title { font-size: 15px; font-weight: 700; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 6px 14px; border-radius: var(--radius-sm); font-size: 13px; font-weight: 600; cursor: pointer; border: 1px solid transparent; text-decoration: none; }
        .btn-primary { background: var(--primary); color: #ffffff; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-secondary { background: var(--bg-card); color: var(--text-main); border-color: var(--border); }
        .btn-secondary:hover { background: var(--table-hover); }
        .btn-danger { background: var(--danger); color: #ffffff; }
        .btn-sm { padding: 3px 8px; font-size: 11px; }
        .form-group { margin-bottom: 14px; }
        .form-group label { display: block; margin-bottom: 5px; font-size: 13px; font-weight: 600; }
        .form-control, 
        input[type="text"], 
        input[type="password"], 
        input[type="number"], 
        input[type="email"], 
        input[type="search"], 
        select, 
        textarea { 
            width: 100%; 
            padding: 9px 12px; 
            border: 1px solid var(--border); 
            border-radius: var(--radius-sm); 
            font-size: 13px; 
            background: var(--bg-card); 
            color: var(--text-main); 
            font-family: inherit; 
            transition: border-color 0.15s ease, box-shadow 0.15s ease; 
        }
        .form-control:focus, 
        input[type="text"]:focus, 
        input[type="password"]:focus, 
        input[type="number"]:focus, 
        select:focus, 
        textarea:focus { 
            outline: none; 
            border-color: var(--primary); 
            box-shadow: 0 0 0 2px var(--primary-light); 
        }
        input[type="checkbox"], input[type="radio"] { width: auto; height: auto; margin: 0; cursor: pointer; }
        .table-responsive { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; border: 1px solid var(--border); border-radius: var(--radius); }
        .data-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13px; }
        [dir="rtl"] .data-table { text-align: right; }
        .data-table th { background: var(--table-hover); padding: 10px 14px; font-weight: 700; border-bottom: 1px solid var(--border); white-space: nowrap; color: var(--text-muted); }
        .data-table td { padding: 9px 14px; border-bottom: 1px solid var(--border); }
        .data-table tr:hover td { background: var(--table-hover); }
        .badge { display: inline-block; padding: 2px 7px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .badge-null { background: #94a3b8; color: #ffffff; font-style: italic; }
        .badge-type { background: var(--primary-light); color: var(--primary); }
        .alert { padding: 12px 16px; border-radius: var(--radius-sm); margin-bottom: 18px; font-size: 13px; font-weight: 500; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        
        /* ─── Compact Zero-Scroll Login Card ─── */
        .login-wrap { 
            height: 100vh;
            max-height: 100vh;
            display: flex; 
            align-items: center; 
            justify-content: center; 
            padding: 12px 16px; 
            overflow: hidden;
            background: radial-gradient(circle at 50% 15%, var(--primary-light), var(--bg-body) 70%); 
        }
        .login-card { 
            width: 100%; 
            max-width: 440px; 
            background: var(--bg-card); 
            border: 1px solid var(--border); 
            border-radius: var(--radius); 
            padding: 18px 22px; 
            box-shadow: 0 12px 30px -8px rgba(0,0,0,0.1), 0 0 0 1px var(--border); 
        }
        .login-header { text-align: center; margin-bottom: 10px; }
        
        .login-nav-tabs { 
            display: grid; 
            grid-template-columns: repeat(3, 1fr); 
            background: var(--table-hover); 
            border: 1px solid var(--border);
            border-radius: var(--radius-sm); 
            padding: 2px; 
            gap: 2px; 
            margin-bottom: 10px; 
        }
        .login-tab-btn { 
            padding: 5px 2px; 
            font-size: 11px; 
            font-weight: 600; 
            border: none; 
            background: transparent; 
            color: var(--text-muted); 
            border-radius: 4px; 
            cursor: pointer; 
            text-align: center; 
            transition: all 0.15s ease; 
            white-space: nowrap; 
        }
        .login-tab-btn:hover { color: var(--text-main); }
        .login-tab-btn.active { 
            background: var(--bg-card); 
            color: var(--primary); 
            box-shadow: 0 1px 2px rgba(0,0,0,0.06); 
        }

        .login-card .form-group { margin-bottom: 8px; }
        .login-card .form-group label { margin-bottom: 2px; font-size: 11.5px; font-weight: 600; }
        .login-card .form-control { padding: 5px 10px; font-size: 12.5px; height: 32px; }

        .ssl-switch-box {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--table-hover);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 0 10px;
            height: 32px;
            cursor: pointer;
            user-select: none;
        }
        .ssl-switch-box:hover { border-color: var(--primary); }
        .switch-ui {
            position: relative;
            display: inline-block;
            width: 32px;
            height: 18px;
            flex-shrink: 0;
        }
        .switch-ui input { opacity: 0; width: 0; height: 0; position: absolute; }
        .switch-slider {
            position: absolute;
            inset: 0;
            background-color: #cbd5e1;
            border-radius: 18px;
            transition: 0.2s;
        }
        .switch-slider:before {
            position: absolute;
            content: "";
            height: 12px;
            width: 12px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            border-radius: 50%;
            transition: 0.2s;
        }
        .switch-ui input:checked + .switch-slider { background-color: var(--primary); }
        .switch-ui input:checked + .switch-slider:before { transform: translateX(14px); }
    </style>
</head>
<body>
${!isLoggedIn(req) ? `
    <div class="login-wrap">
        <div class="login-card">
            <div class="login-header">
                <div style="display:inline-flex; align-items:center; gap:8px;">
                    <svg viewBox="0 0 24 24" style="width:22px; height:22px; fill:var(--primary);"><path d="M12 3C6.48 3 2 4.79 2 7v10c0 2.21 4.48 4 10 4s10-1.79 10-4V7c0-2.21-4.48-4-10-4zm0 2c4.97 0 8 1.5 8 2s-3.03 2-8 2-8-1.5-8-2 3.03-2 8-2zm0 6c-4.97 0-8-1.5-8-2v3.13c1.78 1.13 4.73 1.87 8 1.87s6.22-.74 8-1.87V9c0 .5-3.03 2-8 2zm0 6c-4.97 0-8-1.5-8-2v3.13c1.78 1.13 4.73 1.87 8 1.87s6.22-.74 8-1.87V15c0 .5-3.03 2-8 2z"/></svg>
                    <h1 style="font-size:18px; font-weight:800; color:var(--text-main); margin:0; letter-spacing:-0.4px;">${h(t(lang, 'app_name'))}</h1>
                </div>
            </div>
            ${error ? `<div class="alert alert-error" style="padding:7px 10px; margin-bottom:8px; font-size:11.5px;">✕ ${h(error)}</div>` : ''}

            <div class="login-nav-tabs">
                <button type="button" class="login-tab-btn active" id="tabBtnDirect" onclick="switchLoginTab('direct')">⚡ Direct</button>
                <button type="button" class="login-tab-btn" id="tabBtnSsh" onclick="switchLoginTab('ssh')">🔒 SSH Tunnel</button>
                <button type="button" class="login-tab-btn" id="tabBtnUri" onclick="switchLoginTab('uri')">🔗 URI String</button>
            </div>

            <!-- Quick Connection String Parser -->
            <div id="loginTabUri" style="display:none; margin-bottom:8px;">
                <div class="form-group" style="background:var(--table-hover); padding:8px 10px; border-radius:var(--radius-sm); border:1px solid var(--border); margin-bottom:0;">
                    <label style="font-size:11px; font-weight:700; color:var(--text-main); margin-bottom:3px;">${h(t(lang, 'connect_uri_label'))}</label>
                    <input type="text" id="connUriInput" class="form-control" placeholder="postgres://user:pass@host:5432/dbname?sslmode=require" oninput="parseConnectionUri(this.value)">
                </div>
            </div>

            <form method="post" id="loginForm">
                <input type="hidden" name="csrf_token" value="${h(csrf)}">
                <input type="hidden" name="use_ssh" id="useSshInput" value="0">
                <div class="form-group"><label>${h(t(lang, 'database_type_label'))}</label><select name="db_type" id="dbTypeInput" class="form-control"><option value="mysql">MySQL / MariaDB</option><option value="pgsql">PostgreSQL / Supabase / Neon</option><option value="sqlite">SQLite 3</option></select></div>
                <div style="display:flex; gap:8px;">
                    <div class="form-group" style="flex:3;"><label>${h(t(lang, 'host_label'))}</label><input type="text" name="db_host" id="dbHostInput" class="form-control" value="localhost" required></div>
                    <div class="form-group" style="flex:1.2;"><label>${h(t(lang, 'port_label'))}</label><input type="number" name="db_port" id="dbPortInput" class="form-control" placeholder="3306"></div>
                </div>
                <div style="display:flex; gap:8px;">
                    <div class="form-group" style="flex:1;"><label>${h(t(lang, 'username_label'))}</label><input type="text" name="db_user" id="dbUserInput" class="form-control" value="root"></div>
                    <div class="form-group" style="flex:1;"><label>${h(t(lang, 'password_label'))}</label><input type="password" name="db_pass" id="dbPassInput" class="form-control"></div>
                </div>
                
                <div style="display:flex; gap:8px; align-items:flex-end;">
                    <div class="form-group" style="flex:2.2; margin-bottom:0;"><label>${h(t(lang, 'database_name_label'))}</label><input type="text" name="db_name" id="dbNameInput" class="form-control" placeholder="Optional"></div>
                    <div class="form-group" style="flex:1.4; margin-bottom:0;">
                        <label>&nbsp;</label>
                        <div class="ssl-switch-box" onclick="const cb=document.getElementById('dbSslInput'); if(cb) cb.checked = !cb.checked;">
                            <span style="font-weight:600; font-size:11.5px; color:var(--text-main);">🔒 SSL</span>
                            <label class="switch-ui" onclick="event.stopPropagation();">
                                <input type="checkbox" name="db_ssl" id="dbSslInput" value="1">
                                <span class="switch-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div id="sshTunnelBox" style="display:none; margin:8px 0; padding:10px; background:var(--table-hover); border-radius:var(--radius-sm); border:1px solid var(--border);">
                    <div style="font-weight:700; font-size:11.5px; margin-bottom:6px;">🔒 SSH Bastion Host</div>
                    <div style="display:flex; gap:6px;">
                        <div class="form-group" style="flex:3; margin-bottom:6px;"><label style="font-size:11px;">SSH Host</label><input type="text" name="ssh_host" id="sshHostInput" class="form-control" placeholder="bastion.example.com"></div>
                        <div class="form-group" style="flex:1.2; margin-bottom:6px;"><label style="font-size:11px;">SSH Port</label><input type="number" name="ssh_port" id="sshPortInput" class="form-control" value="22"></div>
                    </div>
                    <div style="display:flex; gap:6px;">
                        <div class="form-group" style="flex:1; margin-bottom:6px;"><label style="font-size:11px;">SSH Username</label><input type="text" name="ssh_user" id="sshUserInput" class="form-control" placeholder="ubuntu"></div>
                        <div class="form-group" style="flex:1; margin-bottom:6px;"><label style="font-size:11px;">SSH Password</label><input type="password" name="ssh_pass" id="sshPassInput" class="form-control"></div>
                    </div>
                </div>

                <button type="submit" name="login" value="1" class="btn btn-primary" style="width:100%; padding:8px 12px; font-size:13px; margin-top:10px; height:34px;">${h(t(lang, 'connect_button'))} &rarr;</button>
            </form>
        </div>
    </div>
` : `
    <div class="app-layout">
        <aside class="sidebar">
            <div class="sidebar-brand">
                <svg viewBox="0 0 24 24"><path d="M12 3C6.48 3 2 4.79 2 7v10c0 2.21 4.48 4 10 4s10-1.79 10-4V7c0-2.21-4.48-4-10-4zm0 2c4.97 0 8 1.5 8 2s-3.03 2-8 2-8-1.5-8-2 3.03-2 8-2zm0 6c-4.97 0-8-1.5-8-2v3.13c1.78 1.13 4.73 1.87 8 1.87s6.22-.74 8-1.87V9c0 .5-3.03 2-8 2zm0 6c-4.97 0-8-1.5-8-2v3.13c1.78 1.13 4.73 1.87 8 1.87s6.22-.74 8-1.87V15c0 .5-3.03 2-8 2z"/></svg>
                <span>${h(t(lang, 'app_name'))}</span>
                <span class="badge badge-type" style="margin-left:auto; font-size:10px;">${h(req.session.db_type)}</span>
            </div>
            <nav class="sidebar-nav">
                <a href="/?page=databases" class="nav-link ${page==='databases'?'active':''}"><span>📁</span> <span>${h(t(lang, 'databases'))}</span></a>
                <a href="/?page=sql${selected_db?`&db=${encodeURIComponent(selected_db)}`:''}" class="nav-link ${page==='sql'?'active':''}"><span>⚡</span> <span>${h(t(lang, 'sql_console'))}</span></a>
                <a href="/?page=export${selected_db?`&db=${encodeURIComponent(selected_db)}`:''}" class="nav-link ${page==='export'?'active':''}"><span>📤</span> <span>${h(t(lang, 'export_data'))}</span></a>
                ${selected_db ? `
                    <div class="nav-section">
                        <div class="nav-section-title">${h(selected_db)} (<span id="sidebarTableCount">${nav_tables.length}</span>)</div>
                        ${nav_tables.length > 8 ? `
                            <div style="padding:4px 8px 8px 8px;">
                                <input type="text" id="sidebarTableFilter" class="form-control" placeholder="Filter tables..." oninput="filterSidebarTables(this.value)" style="height:28px; font-size:11px; padding:2px 8px; border-radius:var(--radius-sm);">
                            </div>
                        ` : ''}
                        <div id="sidebarTableList">
                            ${nav_tables.map(tbl=>`
                                <a href="/?page=browse&db=${encodeURIComponent(selected_db)}&table=${encodeURIComponent(tbl)}" class="table-tree-item ${selected_table===tbl?'active':''}" data-table-name="${h(tbl)}" title="${h(tbl)}">
                                    <svg class="tbl-icon" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                                    <span class="tbl-text">${h(tbl)}</span>
                                </a>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}
            </nav>
            <div class="sidebar-footer">
                <form method="post"><input type="hidden" name="csrf_token" value="${h(csrf)}"><button type="submit" name="logout" value="1" class="btn btn-danger btn-sm" style="width:100%;">🚪 ${h(t(lang, 'logout'))}</button></form>
            </div>
        </aside>
        <div class="main-wrapper">
            <header class="topbar">
                <div class="breadcrumbs">
                    <a href="/?page=databases">🏠 ${h(t(lang, 'databases'))}</a>
                    ${selected_db ? `<span>/</span> <a href="/?page=tables&db=${encodeURIComponent(selected_db)}">${h(selected_db)}</a>` : ''}
                    ${selected_table ? `<span>/</span> <strong>${h(selected_table)}</strong>` : ''}
                </div>
                <div style="display:flex; gap:10px;">
                    <select onchange="location.href='/?set_theme='+this.value+'&page=${encodeURIComponent(page)}&db=${encodeURIComponent(selected_db)}&table=${encodeURIComponent(selected_table)}'" class="form-control" style="width:auto; height:30px; padding:2px 8px; font-size:12px;">
                        ${Object.entries(THEME_OPTIONS).map(([k,v])=>`<option value="${k}" ${theme===k?'selected':''}>${v}</option>`).join('')}
                    </select>
                    <select onchange="location.href='/?set_lang='+this.value+'&page=${encodeURIComponent(page)}&db=${encodeURIComponent(selected_db)}&table=${encodeURIComponent(selected_table)}'" class="form-control" style="width:auto; height:30px; padding:2px 8px; font-size:12px;">
                        ${Object.entries(LANG_OPTIONS).map(([k,v])=>`<option value="${k}" ${lang===k?'selected':''}>${v}</option>`).join('')}
                    </select>
                </div>
            </header>
            <main class="content-body">
                ${successMsg ? `<div class="alert alert-success">✓ ${h(successMsg)}</div>` : ''}
                ${errorMsg ? `<div class="alert alert-error">✕ ${h(errorMsg)}</div>` : ''}
                ${page === 'databases' ? `
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
                        <h2 style="font-size:20px; font-weight:800;">${h(t(lang, 'databases'))}</h2>
                    </div>
                    <div class="card">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead><tr><th>${h(t(lang, 'database_name_label'))}</th><th>${h(t(lang, 'tables'))}</th><th>${h(t(lang, 'total_size'))}</th><th style="text-align:right;">${h(t(lang, 'actions'))}</th></tr></thead>
                                <tbody>
                                    ${Object.values(db_stats).map(ds=>`
                                        <tr>
                                            <td><a href="/?page=tables&db=${encodeURIComponent(ds.name)}" style="font-weight:600;">📁 ${h(ds.name)}</a></td>
                                            <td><span class="badge badge-type">${ds.tables}</span></td>
                                            <td><strong>${formatBytes(ds.size)}</strong></td>
                                            <td style="text-align:right; white-space:nowrap;">
                                                <a href="/?page=tables&db=${encodeURIComponent(ds.name)}" class="btn btn-secondary btn-sm">${h(t(lang, 'tables'))}</a>
                                                <a href="/?page=sql&db=${encodeURIComponent(ds.name)}" class="btn btn-secondary btn-sm">SQL</a>
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ` : page === 'tables' ? `
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
                        <div>
                            <h2 style="font-size:20px; font-weight:800;">${h(t(lang, 'tables'))}: ${h(selected_db)}</h2>
                            <p style="color:var(--text-muted); font-size:13px;">${table_stats.length} ${h(t(lang, 'tables'))}</p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>${h(t(lang, 'table_name'))}</th>
                                        <th>${h(t(lang, 'engine'))}</th>
                                        <th>${h(t(lang, 'records'))}</th>
                                        <th>${h(t(lang, 'data_size'))}</th>
                                        <th>${h(t(lang, 'index_size'))}</th>
                                        <th>${h(t(lang, 'total_size'))}</th>
                                        <th style="text-align:right;">${h(t(lang, 'actions'))}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${table_stats.map(ts=>`
                                        <tr>
                                            <td><a href="/?page=browse&db=${encodeURIComponent(selected_db)}&table=${encodeURIComponent(ts.Name)}" style="font-weight:600;">📄 ${h(ts.Name)}</a></td>
                                            <td><span class="badge badge-type">${h(ts.Engine || 'N/A')}</span></td>
                                            <td><strong>${Number(ts.Rows || 0).toLocaleString()}</strong></td>
                                            <td>${formatBytes(ts.Data_length || 0)}</td>
                                            <td>${formatBytes(ts.Index_length || 0)}</td>
                                            <td><strong>${formatBytes(ts.Total_length || 0)}</strong></td>
                                            <td style="text-align:right; white-space:nowrap;">
                                                <a href="/?page=browse&db=${encodeURIComponent(selected_db)}&table=${encodeURIComponent(ts.Name)}" class="btn btn-secondary btn-sm">${h(t(lang, 'browse'))}</a>
                                                <a href="/?page=structure&db=${encodeURIComponent(selected_db)}&table=${encodeURIComponent(ts.Name)}" class="btn btn-secondary btn-sm">${h(t(lang, 'structure'))}</a>
                                            </td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        </div>
                    </div>
                ` : `
                    <h2 style="font-size:20px; font-weight:800; margin-bottom:14px;">${h(t(lang, 'sql_console'))}</h2>
                    ${sqlError ? `<div class="alert alert-error">✕ ${h(sqlError)}</div>` : ''}
                    <div class="card">
                        <form method="post" id="sqlConsoleForm" style="padding:18px;">
                            <input type="hidden" name="csrf_token" value="${h(csrf)}">
                            <div class="form-group"><textarea name="sql" rows="8" class="form-control" style="font-family:monospace;">${h(body.sql || '')}</textarea></div>
                            <button type="submit" name="execute_sql" value="1" class="btn btn-primary">⚡ ${h(t(lang, 'execute_query'))}</button>
                        </form>
                    </div>
                    ${sqlResult ? `
                        <div class="card">
                            <div class="card-header"><h3 class="card-title">${h(t(lang, 'query_results'))}</h3><span style="font-size:12px; color:var(--text-muted);">${sqlTime}ms &bull; ${sqlResult.length} ${h(t(lang, 'rows'))}</span></div>
                            ${sqlResult.length ? `
                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead><tr>${Object.keys(sqlResult[0]).map(k=>`<th>${h(k)}</th>`).join('')}</tr></thead>
                                        <tbody>${sqlResult.map(r=>`<tr>${Object.values(r).map(v=>`<td>${v===null?'<span class="badge badge-null">NULL</span>':h(v)}</td>`).join('')}</tr>`).join('')}</tbody>
                                    </table>
                                </div>
                            ` : `<div style="padding:18px; color:var(--success); font-weight:600;">✓ Query executed successfully. ${sqlAffected} row(s) affected.</div>`}
                        </div>
                    ` : ''}
                `}
            </main>
        </div>
    </div>
`}
<script>
function switchLoginTab(tab) {
    document.querySelectorAll('.login-tab-btn').forEach(b => b.classList.remove('active'));
    const uriBox = document.getElementById('loginTabUri');
    const sshBox = document.getElementById('sshTunnelBox');
    const useSsh = document.getElementById('useSshInput');
    if (uriBox) uriBox.style.display = 'none';
    if (sshBox) sshBox.style.display = 'none';
    if (useSsh) useSsh.value = '0';

    if (tab === 'direct') {
        document.getElementById('tabBtnDirect')?.classList.add('active');
    } else if (tab === 'ssh') {
        document.getElementById('tabBtnSsh')?.classList.add('active');
        if (sshBox) sshBox.style.display = 'block';
        if (useSsh) useSsh.value = '1';
    } else if (tab === 'uri') {
        document.getElementById('tabBtnUri')?.classList.add('active');
        if (uriBox) { uriBox.style.display = 'block'; document.getElementById('connUriInput')?.focus(); }
    }
}

function parseConnectionUri(uri) {
    if (!uri) return;
    try {
        const u = new URL(uri);
        const typeSelect = document.getElementById('dbTypeInput');
        if (u.protocol.includes('postgres')) typeSelect.value = 'pgsql';
        else if (u.protocol.includes('mysql')) typeSelect.value = 'mysql';
        else if (u.protocol.includes('sqlite')) typeSelect.value = 'sqlite';
        if (u.hostname) document.getElementById('dbHostInput').value = u.hostname;
        if (u.port) document.getElementById('dbPortInput').value = u.port;
        if (u.username) document.getElementById('dbUserInput').value = decodeURIComponent(u.username);
        if (u.password) document.getElementById('dbPassInput').value = decodeURIComponent(u.password);
        if (u.pathname && u.pathname.length > 1) document.getElementById('dbNameInput').value = decodeURIComponent(u.pathname.replace(/^\\//, ''));
    } catch (_) {}
}

function filterSidebarTables(query) {
    const q = query.toLowerCase().trim();
    let count = 0;
    document.querySelectorAll('.table-tree-item').forEach(el => {
        const name = el.getAttribute('data-table-name') || el.textContent;
        const match = name.toLowerCase().includes(q);
        el.style.display = match ? 'flex' : 'none';
        if (match) count++;
    });
    const badge = document.getElementById('sidebarTableCount');
    if (badge) badge.textContent = count;
}
</script>
</body>
</html>`;

    await db?.close();
    res.send(html);
});

app.listen(PORT, () => {
    console.log(`Dabiro v${VERSION} (Node.js) listening on port ${PORT}`);
});
