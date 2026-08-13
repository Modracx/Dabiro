<?php
/**
 * Dabiro - Professional Database Management System
 * A single-file, zero-dependency database manager with a modern, responsive interface.
 * Version: 1.2.1
 * Kenneth D'silva (Modracx), Copyright (c) November 2025
 * Licensed under the MIT License – https://opensource.org/licenses/MIT
 */

// ─── Configuration & Session ──────────────────────────────────────────────────
define('DB_ADMIN_VERSION', '1.2.1');
define('SESSION_TIMEOUT', 3600);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ─── Helper Functions ─────────────────────────────────────────────────────────
function h($str)
{
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirect($url)
{
    header("Location: $url");
    exit;
}

function get_post($key, $default = '')
{
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

function get_get($key, $default = '')
{
    return isset($_GET[$key]) ? $_GET[$key] : $default;
}

function format_bytes($bytes, $precision = 2)
{
    $bytes = (float)$bytes;
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $pow = floor(log($bytes) / log(1024));
    $pow = min((int)$pow, count($units) - 1);
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}

// ─── CSRF Protection ──────────────────────────────────────────────────────────
function generate_csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token)
{
    return !empty($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function get_csrf_token()
{
    return generate_csrf_token();
}

// ─── Translations System ──────────────────────────────────────────────────────
$TRANSLATIONS = [
    'en' => [
        'app_name' => 'Dabiro',
        'app_tagline' => 'Professional Database Management Interface',
        'database_type_label' => 'Database Type',
        'host_label' => 'Host / File Path',
        'port_label' => 'Port',
        'username_label' => 'Username',
        'password_label' => 'Password',
        'database_name_label' => 'Database Name (optional)',
        'ssl_label' => 'Require SSL / TLS Encryption (Remote Database)',
        'connect_button' => 'Connect to Database',
        'connect_uri_label' => 'Or Paste Connection URL (e.g. postgres://... or mysql://...)',
        'saved_connections' => 'Saved Connections',
        'logout' => 'Logout',
        'databases' => 'Databases',
        'tables' => 'Tables',
        'browse' => 'Browse',
        'structure' => 'Structure',
        'sql_console' => 'SQL Console',
        'import_data' => 'Import Data',
        'export_data' => 'Export Data',
        'global_search' => 'Global Search',
        'operations' => 'Operations',
        'create_database' => 'Create Database',
        'create_table' => 'Create Table',
        'table_name' => 'Table Name',
        'columns' => 'Columns',
        'add_column' => 'Add Column',
        'add_condition' => 'Add Condition',
        'rename_table' => 'Rename Table',
        'copy_table' => 'Copy Table',
        'move_table' => 'Move Table',
        'drop' => 'Drop',
        'truncate' => 'Truncate',
        'drop_selected' => 'Drop Selected',
        'truncate_selected' => 'Truncate Selected',
        'select_all' => 'Select All',
        'insert_record' => 'Insert Record',
        'edit_record' => 'Edit Record',
        'delete' => 'Delete',
        'edit' => 'Edit',
        'clone' => 'Clone',
        'save' => 'Save Record',
        'cancel' => 'Cancel',
        'search' => 'Search',
        'filter' => 'Filter',
        'operator' => 'Operator',
        'value' => 'Value',
        'sort_by' => 'Sort By',
        'rows_per_page' => 'Rows Per Page',
        'back_to_databases' => 'Back to Databases',
        'back_to_tables' => 'Back to Tables',
        'back_to_table' => 'Back to Table',
        'download_database' => 'Download Database',
        'download_table' => 'Download Table',
        'export_entire_database' => 'Export Entire Database',
        'export_single_table' => 'Export Single Table',
        'export_format' => 'Export Format',
        'select_database' => 'Select Database',
        'select_table' => 'Select Table',
        'execute_query' => 'Execute Query',
        'export_query' => 'Export Query as SQL',
        'recent_queries' => 'Recent Queries',
        'clear' => 'Clear',
        'theme' => 'Theme',
        'language' => 'Language',
        'total_databases' => 'Total Databases',
        'total_tables' => 'Total Tables',
        'total_records' => 'Total Records',
        'total_size' => 'Total Size',
        'data_size' => 'Data Size',
        'index_size' => 'Index Size',
        'overhead' => 'Overhead / Free',
        'engine' => 'Engine',
        'collation' => 'Collation',
        'actions' => 'Actions',
        'query_results' => 'Query Results',
        'execution_time' => 'Execution Time',
        'rows' => 'Rows',
        'records' => 'Records',
        'server' => 'Server'
    ],
    'es' => [
        'app_name' => 'Dabiro',
        'app_tagline' => 'Interfaz profesional de gestión de bases de datos',
        'database_type_label' => 'Tipo de Base de Datos',
        'host_label' => 'Servidor / Ruta de Archivo',
        'port_label' => 'Puerto',
        'username_label' => 'Usuario',
        'password_label' => 'Contraseña',
        'database_name_label' => 'Nombre de Base de Datos (opcional)',
        'ssl_label' => 'Requerir cifrado SSL / TLS (Remoto)',
        'connect_button' => 'Conectar a la Base de Datos',
        'connect_uri_label' => 'O Pegar URL de Conexión',
        'saved_connections' => 'Conexiones Guardadas',
        'logout' => 'Cerrar Sesión',
        'databases' => 'Bases de Datos',
        'tables' => 'Tablas',
        'browse' => 'Explorar',
        'structure' => 'Estructura',
        'sql_console' => 'Consola SQL',
        'import_data' => 'Importar Datos',
        'export_data' => 'Exportar Datos',
        'global_search' => 'Búsqueda Global',
        'total_size' => 'Tamaño Total',
        'data_size' => 'Tamaño de Datos',
        'index_size' => 'Tamaño de Índices',
        'engine' => 'Motor',
        'collation' => 'Cotejamiento',
        'actions' => 'Acciones'
    ],
    'zh' => [
        'app_name' => 'Dabiro',
        'app_tagline' => '专业的现代化数据库管理界面',
        'database_type_label' => '数据库类型',
        'host_label' => '主机 / 文件路径',
        'port_label' => '端口',
        'username_label' => '用户名',
        'password_label' => '密码',
        'database_name_label' => '数据库名称 (可选)',
        'ssl_label' => '启用 SSL / TLS 加密 (远程连接)',
        'connect_button' => '连接数据库',
        'connect_uri_label' => '或粘贴连接字符串 (例如 postgres://... 或 mysql://...)',
        'saved_connections' => '已存连接配置',
        'logout' => '退出登录',
        'databases' => '数据库',
        'tables' => '数据表',
        'browse' => '浏览数据',
        'structure' => '表结构',
        'sql_console' => 'SQL 控制台',
        'import_data' => '导入数据',
        'export_data' => '导出数据',
        'global_search' => '全局搜索',
        'total_size' => '总容量大小',
        'data_size' => '数据大小',
        'index_size' => '索引大小',
        'overhead' => '空间碎片',
        'engine' => '存储引擎',
        'collation' => '字符集校对',
        'actions' => '操作'
    ],
    'ar' => [
        'app_name' => 'Dabiro',
        'app_tagline' => 'واجهة احترافية متقدمة لإدارة قواعد البيانات',
        'database_type_label' => 'نوع قاعدة البيانات',
        'host_label' => 'المضيف / مسار الملف',
        'port_label' => 'المنفذ',
        'username_label' => 'اسم المستخدم',
        'password_label' => 'كلمة المرور',
        'database_name_label' => 'اسم القاعدة (اختياري)',
        'ssl_label' => 'تشفير SSL / TLS (اتصال عن بعد)',
        'connect_button' => 'الاتصال بقاعدة البيانات',
        'connect_uri_label' => 'أو الصق رابط الاتصال المباشر',
        'saved_connections' => 'الاتصالات المحفوظة',
        'logout' => 'تسجيل الخروج',
        'databases' => 'قواعد البيانات',
        'tables' => 'الجداول',
        'browse' => 'تصفح',
        'structure' => 'الهيكل',
        'sql_console' => 'وحدة SQL',
        'total_size' => 'الحجم الإجمالي',
        'data_size' => 'حجم البيانات',
        'index_size' => 'حجم الفهارس',
        'engine' => 'المحرك',
        'collation' => 'الترميز',
        'actions' => 'الإجراءات'
    ]
];

$SUPPORTED_LANGS = [
    'en' => 'English', 'es' => 'Español', 'fr' => 'Français', 'de' => 'Deutsch',
    'pt' => 'Português', 'zh' => '中文', 'ja' => '日本語', 'ar' => 'العربية',
    'it' => 'Italiano', 'ru' => 'Русский', 'tr' => 'Türkçe', 'hi' => 'हिन्दी', 'ko' => '한국어'
];

$THEMES = [
    'light' => 'Light', 'dark' => 'Dark', 'slate' => 'Slate',
    'blue' => 'Blue', 'green' => 'Green', 'purple' => 'Purple', 'sunset' => 'Sunset'
];

$current_lang = get_get('set_lang', $_COOKIE['dbadmin_lang'] ?? 'en');
if (!array_key_exists($current_lang, $SUPPORTED_LANGS)) $current_lang = 'en';
if (isset($_GET['set_lang'])) setcookie('dbadmin_lang', $current_lang, time() + (365 * 86400), '/');

$current_theme = get_get('set_theme', $_COOKIE['dbadmin_theme'] ?? 'light');
if (!array_key_exists($current_theme, $THEMES)) $current_theme = 'light';
if (isset($_GET['set_theme'])) setcookie('dbadmin_theme', $current_theme, time() + (365 * 86400), '/');

function __($key, $default = null)
{
    global $TRANSLATIONS, $current_lang;
    if (isset($TRANSLATIONS[$current_lang][$key])) return $TRANSLATIONS[$current_lang][$key];
    if (isset($TRANSLATIONS['en'][$key])) return $TRANSLATIONS['en'][$key];
    return $default !== null ? $default : $key;
}

// ─── Database Connection Class ────────────────────────────────────────────────
class DbConnection
{
    private $pdo = null;
    private $type = '';

    public function connect($type, $host, $user, $pass, $dbname = '', $port = '', $ssl = false)
    {
        $this->type = $type;
        try {
            switch ($type) {
                case 'mysql':
                    $p = $port ?: '3306';
                    if (strpos($host, ':') !== false) {
                        list($h, $prt) = explode(':', $host, 2);
                        $host = $h;
                        $p = $prt;
                    }
                    $dsn = "mysql:host=$host;port=$p" . ($dbname ? ";dbname=$dbname" : '') . ";charset=utf8mb4";
                    $opts = [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ];
                    if ($ssl) {
                        $opts[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
                    }
                    $this->pdo = new PDO($dsn, $user, $pass, $opts);
                    break;

                case 'pgsql':
                    $p = $port ?: '5432';
                    if (strpos($host, ':') !== false) {
                        list($h, $prt) = explode(':', $host, 2);
                        $host = $h;
                        $p = $prt;
                    }
                    $dsn = "pgsql:host=$host;port=$p" . ($dbname ? ";dbname=$dbname" : ';dbname=postgres');
                    if ($ssl) {
                        $dsn .= ";sslmode=require";
                    }
                    $this->pdo = new PDO($dsn, $user, $pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    ]);
                    break;

                case 'sqlite':
                    $this->pdo = new PDO("sqlite:$host", null, null, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    ]);
                    break;
            }
            return true;
        } catch (PDOException $e) {
            return $e->getMessage();
        }
    }

    public function getPdo() { return $this->pdo; }
    public function getType() { return $this->type; }
    public function query($sql) { return $this->pdo ? $this->pdo->query($sql) : null; }
    public function prepare($sql) { return $this->pdo ? $this->pdo->prepare($sql) : null; }

    public function quoteIdentifier($name)
    {
        if ($this->type === 'pgsql') {
            return '"' . str_replace('"', '""', $name) . '"';
        }
        return '`' . str_replace('`', '``', $name) . '`';
    }

    public function getDatabasesWithStats()
    {
        if (!$this->pdo) return [];
        $list = [];
        if ($this->type === 'mysql') {
            try {
                $sql = "SELECT table_schema AS db_name, 
                               COUNT(table_name) AS table_count, 
                               COALESCE(SUM(data_length + index_length), 0) AS total_size,
                               COALESCE(SUM(data_length), 0) AS data_size,
                               COALESCE(SUM(index_length), 0) AS index_size
                        FROM information_schema.TABLES
                        GROUP BY table_schema
                        ORDER BY table_schema";
                $rows = $this->query($sql)->fetchAll();
                foreach ($rows as $r) {
                    $list[$r['db_name']] = [
                        'name' => $r['db_name'],
                        'tables' => (int)$r['table_count'],
                        'size' => (float)$r['total_size'],
                        'data_size' => (float)$r['data_size'],
                        'index_size' => (float)$r['index_size']
                    ];
                }
            } catch (Exception $e) {}
            if (empty($list)) {
                $dbs = $this->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($dbs as $d) { $list[$d] = ['name' => $d, 'tables' => 0, 'size' => 0, 'data_size' => 0, 'index_size' => 0]; }
            }
        } elseif ($this->type === 'pgsql') {
            try {
                $sql = "SELECT datname AS db_name, pg_database_size(datname) AS total_size FROM pg_database WHERE datistemplate = false ORDER BY datname";
                $rows = $this->query($sql)->fetchAll();
                foreach ($rows as $r) {
                    $list[$r['db_name']] = [
                        'name' => $r['db_name'],
                        'tables' => 0,
                        'size' => (float)$r['total_size'],
                        'data_size' => (float)$r['total_size'],
                        'index_size' => 0
                    ];
                }
            } catch (Exception $e) {}
        } elseif ($this->type === 'sqlite') {
            $sz = 0;
            if (!empty($_SESSION['db_host']) && file_exists($_SESSION['db_host'])) {
                $sz = filesize($_SESSION['db_host']);
            }
            $list['main'] = ['name' => 'main', 'tables' => count($this->getTables('main')), 'size' => $sz, 'data_size' => $sz, 'index_size' => 0];
        }
        return $list;
    }

    public function getDatabases()
    {
        return array_keys($this->getDatabasesWithStats());
    }

    public function getTables($database = null)
    {
        if (!$this->pdo) return [];
        if ($database && $this->type != 'sqlite') {
            $this->pdo->exec("USE " . $this->quoteIdentifier($database));
        }
        switch ($this->type) {
            case 'mysql':
                return $this->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            case 'pgsql':
                return $this->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename")->fetchAll(PDO::FETCH_COLUMN);
            case 'sqlite':
                return $this->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        }
        return [];
    }

    public function getTablesWithStats($database = null)
    {
        if (!$this->pdo) return [];
        if ($database && $this->type != 'sqlite') {
            $this->pdo->exec("USE " . $this->quoteIdentifier($database));
        }
        if ($this->type === 'mysql') {
            try {
                $stmt = $this->prepare("SELECT table_name AS Name, engine AS Engine, table_rows AS Rows, data_length AS Data_length, index_length AS Index_length, (data_length + index_length) AS Total_length, data_free AS Data_free, auto_increment AS Auto_increment, table_collation AS Collation, table_comment AS Comment FROM information_schema.TABLES WHERE table_schema = ? ORDER BY table_name");
                $stmt->execute([$database]);
                return $stmt->fetchAll();
            } catch (Exception $e) {}
        } elseif ($this->type === 'pgsql') {
            try {
                $sql = "SELECT c.relname AS \"Name\", 'heap' AS \"Engine\", c.reltuples::bigint AS \"Rows\", pg_relation_size(c.oid) AS \"Data_length\", (pg_total_relation_size(c.oid) - pg_relation_size(c.oid)) AS \"Index_length\", pg_total_relation_size(c.oid) AS \"Total_length\", 0 AS \"Data_free\", NULL AS \"Auto_increment\", 'UTF8' AS \"Collation\", '' AS \"Comment\" FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace WHERE n.nspname = 'public' AND c.relkind = 'r' ORDER BY c.relname";
                return $this->query($sql)->fetchAll();
            } catch (Exception $e) {}
        }
        $stats = [];
        $tbls = $this->getTables($database);
        foreach ($tbls as $t) {
            $cnt = $this->getRowCount($t);
            $stats[] = [
                'Name' => $t, 'Engine' => 'SQLite', 'Rows' => $cnt,
                'Data_length' => 0, 'Index_length' => 0, 'Total_length' => 0,
                'Data_free' => 0, 'Auto_increment' => null, 'Collation' => 'BINARY', 'Comment' => ''
            ];
        }
        return $stats;
    }

    public function getColumns($table)
    {
        if (!$this->pdo) return [];
        switch ($this->type) {
            case 'mysql':
                return $this->query("SHOW COLUMNS FROM " . $this->quoteIdentifier($table))->fetchAll();
            case 'pgsql':
                $stmt = $this->pdo->prepare("SELECT column_name as \"Field\", data_type as \"Type\", is_nullable as \"Null\", column_default as \"Default\" FROM information_schema.columns WHERE table_name = ? AND (table_schema = current_schema() OR table_schema = 'public') ORDER BY ordinal_position");
                $stmt->execute([$table]);
                return $stmt->fetchAll();
            case 'sqlite':
                return $this->query("PRAGMA table_info(" . $this->quoteIdentifier($table) . ")")->fetchAll();
        }
        return [];
    }

    public function getRowCount($table)
    {
        if (!$this->pdo) return 0;
        try {
            $sql = "SELECT COUNT(*) as cnt FROM " . $this->quoteIdentifier($table);
            $res = $this->query($sql)->fetch();
            return (int)($res['cnt'] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }
}

// ─── Authentication & Session ─────────────────────────────────────────────────
function is_logged_in()
{
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function login($type, $host, $user, $pass, $dbname = '', $port = '', $ssl = false)
{
    $db = new DbConnection();
    $res = $db->connect($type, $host, $user, $pass, $dbname, $port, $ssl);
    if ($res === true) {
        session_regenerate_id(true);
        $_SESSION['logged_in'] = true;
        $_SESSION['db_type'] = $type;
        $_SESSION['db_host'] = $host;
        $_SESSION['db_port'] = $port;
        $_SESSION['db_ssl']  = $ssl;
        $_SESSION['db_user'] = $user;
        $_SESSION['db_pass'] = $pass;
        $_SESSION['db_name'] = $dbname;
        $_SESSION['last_activity'] = time();
        return true;
    }
    return $res;
}

function logout()
{
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    redirect('?');
}

function get_connection()
{
    if (!is_logged_in()) return null;
    if (time() - ($_SESSION['last_activity'] ?? 0) > SESSION_TIMEOUT) {
        logout();
    }
    $_SESSION['last_activity'] = time();
    $db = new DbConnection();
    $res = $db->connect(
        $_SESSION['db_type'] ?? 'mysql',
        $_SESSION['db_host'] ?? 'localhost',
        $_SESSION['db_user'] ?? '',
        $_SESSION['db_pass'] ?? '',
        $_SESSION['db_name'] ?? '',
        $_SESSION['db_port'] ?? '',
        $_SESSION['db_ssl'] ?? false
    );
    if ($res !== true) {
        return null;
    }
    return $db;
}

// ─── Action Handlers ──────────────────────────────────────────────────────────
$error_message = null;
$success_message = null;
$sql_result = null;
$sql_error = null;
$sql_time = 0;
$sql_affected = 0;

// Login Form Submit
if (isset($_POST['login'])) {
    if (!validate_csrf_token(get_post('csrf_token'))) {
        $error_message = 'Security token validation failed. Please refresh.';
    } else {
        $type = get_post('db_type');
        $host = get_post('db_host');
        $port = get_post('db_port');
        $user = get_post('db_user');
        $pass = get_post('db_pass');
        $dbname = get_post('db_name');
        $ssl = (bool)get_post('db_ssl');

        $res = login($type, $host, $user, $pass, $dbname, $port, $ssl);
        if ($res === true) {
            redirect($dbname ? ('?page=tables&db=' . urlencode($dbname)) : '?page=databases');
        } else {
            $error_message = $res;
        }
    }
}

// Logout
if (isset($_POST['logout']) && validate_csrf_token(get_post('csrf_token'))) {
    logout();
}

// AJAX get_tables
if (get_get('action') === 'get_tables' && get_get('db')) {
    header('Content-Type: application/json; charset=utf-8');
    if (!is_logged_in()) { echo json_encode([]); exit; }
    $db = get_connection();
    try {
        echo json_encode($db ? $db->getTables(get_get('db')) : []);
    } catch (Exception $e) { echo json_encode([]); }
    exit;
}

// Create Database
if (isset($_POST['create_database']) && validate_csrf_token(get_post('csrf_token'))) {
    $db = get_connection();
    $new_db = trim(get_post('db_name'));
    if ($new_db && $db) {
        try {
            $db->query("CREATE DATABASE " . $db->quoteIdentifier($new_db));
            $success_message = "Database `$new_db` created successfully.";
        } catch (Exception $e) { $error_message = $e->getMessage(); }
    }
}

// Create Table
if (isset($_POST['create_table']) && validate_csrf_token(get_post('csrf_token'))) {
    $db = get_connection();
    $sql = get_post('create_table_sql');
    $db_name = get_post('create_table_db');
    if ($sql && $db) {
        try {
            if ($db_name && $db->getType() !== 'sqlite') {
                $db->getPdo()->exec("USE " . $db->quoteIdentifier($db_name));
            }
            $db->getPdo()->exec($sql);
            $success_message = "Table created successfully.";
        } catch (Exception $e) { $error_message = $e->getMessage(); }
    }
}

// Bulk Actions (Drop / Truncate)
if (isset($_POST['bulk_action']) && validate_csrf_token(get_post('csrf_token'))) {
    $db = get_connection();
    $action = get_post('bulk_action');
    $items = (array)get_post('selected', []);
    $cur_db = get_post('database', get_get('db', ''));
    if ($db && !empty($items)) {
        try {
            if ($cur_db && $db->getType() !== 'sqlite') {
                $db->getPdo()->exec("USE " . $db->quoteIdentifier($cur_db));
            }
            foreach ($items as $item) {
                if ($action === 'drop') {
                    $db->query("DROP TABLE " . $db->quoteIdentifier($item));
                } elseif ($action === 'truncate') {
                    if ($db->getType() === 'sqlite') {
                        $db->query("DELETE FROM " . $db->quoteIdentifier($item));
                    } else {
                        $db->query("TRUNCATE TABLE " . $db->quoteIdentifier($item));
                    }
                }
            }
            $success_message = count($items) . " table(s) processed successfully.";
        } catch (Exception $e) { $error_message = $e->getMessage(); }
    }
}

// Table Operations (Rename, Copy, Truncate, Drop, Move)
if (isset($_POST['operation_action']) && validate_csrf_token(get_post('csrf_token'))) {
    $db = get_connection();
    $op = get_post('operation_action');
    $tbl = get_post('table');
    $cur_db = get_get('db', '');
    if ($db && $tbl) {
        if ($cur_db && $db->getType() !== 'sqlite') $db->getPdo()->exec("USE " . $db->quoteIdentifier($cur_db));
        try {
            if ($op === 'rename_table') {
                $new_name = trim(get_post('new_table_name'));
                if ($new_name) {
                    $db->query("ALTER TABLE " . $db->quoteIdentifier($tbl) . " RENAME TO " . $db->quoteIdentifier($new_name));
                    $success_message = "Table renamed to `$new_name`.";
                    redirect("?page=browse&db=" . urlencode($cur_db) . "&table=" . urlencode($new_name));
                }
            } elseif ($op === 'copy_table') {
                $target_name = trim(get_post('copy_table_name'));
                $with_data = get_post('copy_data') === '1';
                if ($target_name) {
                    if ($db->getType() === 'mysql') {
                        $db->query("CREATE TABLE " . $db->quoteIdentifier($target_name) . " LIKE " . $db->quoteIdentifier($tbl));
                        if ($with_data) {
                            $db->query("INSERT INTO " . $db->quoteIdentifier($target_name) . " SELECT * FROM " . $db->quoteIdentifier($tbl));
                        }
                    } elseif ($db->getType() === 'pgsql') {
                        $db->query("CREATE TABLE " . $db->quoteIdentifier($target_name) . " (LIKE " . $db->quoteIdentifier($tbl) . " INCLUDING ALL)");
                        if ($with_data) {
                            $db->query("INSERT INTO " . $db->quoteIdentifier($target_name) . " SELECT * FROM " . $db->quoteIdentifier($tbl));
                        }
                    } elseif ($db->getType() === 'sqlite') {
                        $db->query("CREATE TABLE " . $db->quoteIdentifier($target_name) . " AS SELECT * FROM " . $db->quoteIdentifier($tbl) . ($with_data ? "" : " WHERE 1=0"));
                    }
                    $success_message = "Table copied to `$target_name`.";
                }
            } elseif ($op === 'truncate_table') {
                if ($db->getType() === 'sqlite') $db->query("DELETE FROM " . $db->quoteIdentifier($tbl));
                else $db->query("TRUNCATE TABLE " . $db->quoteIdentifier($tbl));
                $success_message = "Table `$tbl` emptied.";
            } elseif ($op === 'drop_table') {
                $db->query("DROP TABLE " . $db->quoteIdentifier($tbl));
                redirect("?page=tables&db=" . urlencode($cur_db));
            }
        } catch (Exception $e) { $error_message = $e->getMessage(); }
    }
}

// Delete Record
if (get_get('action') === 'delete' && get_get('table') && validate_csrf_token(get_get('csrf_token'))) {
    $db = get_connection();
    $table = get_get('table');
    $cur_db = get_get('db', '');
    $conds = [];
    if ($db) {
        if ($cur_db && $db->getType() !== 'sqlite') $db->getPdo()->exec("USE " . $db->quoteIdentifier($cur_db));
        foreach ($_GET as $k => $v) {
            if (strpos($k, 'where_') === 0) {
                $conds[] = $db->quoteIdentifier(substr($k, 6)) . " = " . $db->getPdo()->quote($v);
            }
        }
        if (!empty($conds)) {
            try {
                $db->query("DELETE FROM " . $db->quoteIdentifier($table) . " WHERE " . implode(' AND ', $conds) . " LIMIT 1");
                $success_message = "Record deleted successfully.";
            } catch (Exception $e) { $error_message = $e->getMessage(); }
        }
    }
}

// Save Record (Insert / Edit)
if (isset($_POST['save_record']) && validate_csrf_token(get_post('csrf_token'))) {
    $db = get_connection();
    $table = get_post('table');
    $is_edit = get_post('is_edit') === '1';
    $fields = (array)get_post('field', []);
    $cur_db = get_get('db', '');

    if ($db && $table && !empty($fields)) {
        if ($cur_db && $db->getType() !== 'sqlite') $db->getPdo()->exec("USE " . $db->quoteIdentifier($cur_db));
        try {
            if ($is_edit) {
                $sets = []; $where = [];
                foreach ($fields as $k => $v) {
                    if (strpos($k, 'old_') === 0) {
                        $where[] = $db->quoteIdentifier(substr($k, 4)) . " = " . $db->getPdo()->quote($v);
                    } else {
                        $sets[] = $db->quoteIdentifier($k) . " = " . ($v === '' ? "NULL" : $db->getPdo()->quote($v));
                    }
                }
                $sql = "UPDATE " . $db->quoteIdentifier($table) . " SET " . implode(', ', $sets) . " WHERE " . implode(' AND ', $where) . " LIMIT 1";
                $db->query($sql);
                $success_message = "Record updated successfully.";
            } else {
                $cols = []; $vals = [];
                foreach ($fields as $k => $v) {
                    if (strpos($k, 'old_') !== 0) {
                        $cols[] = $db->quoteIdentifier($k);
                        $vals[] = ($v === '' ? "NULL" : $db->getPdo()->quote($v));
                    }
                }
                $sql = "INSERT INTO " . $db->quoteIdentifier($table) . " (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";
                $db->query($sql);
                $success_message = "Record inserted successfully.";
            }
        } catch (Exception $e) { $error_message = $e->getMessage(); }
    }
}

// Execute SQL / Export Query
if (isset($_POST['execute_sql']) || isset($_POST['export_query'])) {
    if (!validate_csrf_token(get_post('csrf_token'))) {
        $sql_error = 'Security token validation failed.';
    } else {
        $db = get_connection();
        $sql = trim(get_post('sql'));
        $cur_db = get_get('db', $_SESSION['db_name'] ?? '');
        if ($db && $cur_db && $db->getType() !== 'sqlite') {
            $db->getPdo()->exec("USE " . $db->quoteIdentifier($cur_db));
        }

        if (isset($_POST['export_query'])) {
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="query_' . date('Ymd_His') . '.sql"');
            echo "-- Dabiro Query Export\n\n" . $sql . ";\n";
            exit;
        }

        if ($db && $sql) {
            $t_start = microtime(true);
            try {
                $stmt = $db->query($sql);
                $sql_time = round((microtime(true) - $t_start) * 1000, 2);
                if ($stmt->columnCount() > 0) {
                    $sql_result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $sql_affected = $stmt->rowCount();
                    $sql_result = [];
                }
            } catch (Exception $e) {
                $sql_error = $e->getMessage();
            }
        }
    }
}

// Export DB / Table Streaming
if (isset($_POST['export_database']) && validate_csrf_token(get_post('csrf_token'))) {
    $db = get_connection();
    $exp_db = get_post('export_db_name');
    $exp_fmt = get_post('export_db_format', 'sql');
    if ($db && $exp_db) {
        if ($db->getType() !== 'sqlite') $db->getPdo()->exec("USE " . $db->quoteIdentifier($exp_db));
        $tables = $db->getTables($exp_db);
        $stamp = date('Y-m-d_H-i-s');
        if ($exp_fmt === 'sql') {
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $exp_db . '_' . $stamp . '.sql"');
            echo "-- Dabiro Database Dump: $exp_db\n\n";
            foreach ($tables as $tbl) {
                echo "-- Table: $tbl\n";
                if ($db->getType() === 'mysql') {
                    try {
                        $create = $db->query("SHOW CREATE TABLE " . $db->quoteIdentifier($tbl))->fetch();
                        echo ($create['Create Table'] ?? $create['create table'] ?? '') . ";\n\n";
                    } catch (Exception $e) {}
                }
                $st = $db->query("SELECT * FROM " . $db->quoteIdentifier($tbl));
                while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                    $vals = array_map(function($v) use ($db) { return $v === null ? 'NULL' : $db->getPdo()->quote($v); }, array_values($row));
                    echo "INSERT INTO " . $db->quoteIdentifier($tbl) . " VALUES (" . implode(', ', $vals) . ");\n";
                }
                echo "\n";
            }
            exit;
        }
    }
}

// SQL Import
if (isset($_POST['import_sql']) && isset($_FILES['sql_file']) && validate_csrf_token(get_post('csrf_token'))) {
    $db = get_connection();
    if ($db && $_FILES['sql_file']['error'] === UPLOAD_ERR_OK) {
        $cur_db = get_get('db', $_SESSION['db_name'] ?? '');
        if ($cur_db && $db->getType() !== 'sqlite') $db->getPdo()->exec("USE " . $db->quoteIdentifier($cur_db));
        try {
            $sql_content = file_get_contents($_FILES['sql_file']['tmp_name']);
            $db->getPdo()->exec($sql_content);
            $success_message = "SQL file imported successfully.";
        } catch (Exception $e) { $error_message = $e->getMessage(); }
    }
}

// ─── Routing Context ──────────────────────────────────────────────────────────
$page = get_get('page', is_logged_in() ? 'databases' : 'login');
$db = is_logged_in() ? get_connection() : null;
$is_rtl = ($current_lang === 'ar');
$selected_db = get_get('db', $_SESSION['db_name'] ?? '');
$selected_table = get_get('table', '');

$nav_tables = [];
if ($db && $selected_db) {
    try { $nav_tables = $db->getTables($selected_db); } catch (Exception $e) {}
}

?><!DOCTYPE html>
<html lang="<?php echo h($current_lang); ?>" dir="<?php echo $is_rtl ? 'rtl' : 'ltr'; ?>" data-theme="<?php echo h($current_theme); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h(__('app_name')); ?> v<?php echo DB_ADMIN_VERSION; ?></title>
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
        [data-theme="blue"] {
            --primary: #0284c7; --primary-hover: #0369a1; --primary-light: #e0f2fe;
            --bg-body: #f0f9ff; --bg-card: #ffffff; --bg-sidebar: #ffffff; --bg-header: #ffffff;
            --text-main: #0c4a6e; --text-muted: #38bdf8; --border: #bae6fd; --table-hover: #e0f2fe;
            --scrollbar-thumb: #7dd3fc;
        }
        [data-theme="green"] {
            --primary: #059669; --primary-hover: #047857; --primary-light: #d1fae5;
            --bg-body: #f0fdf4; --bg-card: #ffffff; --bg-sidebar: #ffffff; --bg-header: #ffffff;
            --text-main: #064e3b; --text-muted: #34d399; --border: #a7f3d0; --table-hover: #d1fae5;
            --scrollbar-thumb: #6ee7b7;
        }
        [data-theme="purple"] {
            --primary: #7c3aed; --primary-hover: #6d28d9; --primary-light: #ede9fe;
            --bg-body: #faf5ff; --bg-card: #ffffff; --bg-sidebar: #ffffff; --bg-header: #ffffff;
            --text-main: #4c1d95; --text-muted: #a78bfa; --border: #ddd6fe; --table-hover: #ede9fe;
            --scrollbar-thumb: #c4b5fd;
        }
        [data-theme="sunset"] {
            --primary: #ea580c; --primary-hover: #c2410c; --primary-light: #ffedd5;
            --bg-body: #fff7ed; --bg-card: #ffffff; --bg-sidebar: #ffffff; --bg-header: #ffffff;
            --text-main: #7c2d12; --text-muted: #fb923c; --border: #fed7aa; --table-hover: #ffedd5;
            --scrollbar-thumb: #fdba74;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Inter", sans-serif; background: var(--bg-body); color: var(--text-main); line-height: 1.5; font-size: 14px; min-height: 100vh; }
        a { color: var(--primary); text-decoration: none; }
        a:hover { text-decoration: underline; }

        /* Sleek scrollbar system across whole UI */
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
        .form-control, input, select, textarea { width: 100%; padding: 8px 12px; border: 1px solid var(--border); border-radius: var(--radius-sm); font-size: 13px; background: var(--bg-card); color: var(--text-main); font-family: inherit; }
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
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(2px); display: none; align-items: center; justify-content: center; z-index: 1000; padding: 20px; }
        .modal-overlay.active { display: flex; }
        .modal-box { background: var(--bg-card); border-radius: var(--radius-lg); width: 100%; max-width: 650px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.2); border: 1px solid var(--border); }
        .modal-header { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .modal-body { padding: 20px; }
        .modal-footer { padding: 14px 20px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; background: var(--table-hover); }
        .login-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; background: var(--bg-body); }
        .login-card { width: 100%; max-width: 480px; background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius-lg); padding: 30px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .mobile-toggle { display: none; background: transparent; border: none; font-size: 20px; cursor: pointer; color: var(--text-main); }
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            [dir="rtl"] .sidebar { transform: translateX(100%); }
            .sidebar.open { transform: translateX(0); }
            .main-wrapper { margin-left: 0 !important; margin-right: 0 !important; }
            .mobile-toggle { display: block; }
        }
    </style>
</head>
<body>

<?php if (!is_logged_in() || !$db): ?>
    <!-- ─── Login Screen with Remote DB & URI Support ─── -->
    <div class="login-wrap">
        <div class="login-card">
            <div style="text-align:center; margin-bottom:20px;">
                <svg viewBox="0 0 24 24" style="width:48px; height:48px; fill:var(--primary); margin-bottom:8px;"><path d="M12 3C6.48 3 2 4.79 2 7v10c0 2.21 4.48 4 10 4s10-1.79 10-4V7c0-2.21-4.48-4-10-4zm0 2c4.97 0 8 1.5 8 2s-3.03 2-8 2-8-1.5-8-2 3.03-2 8-2zm0 6c-4.97 0-8-1.5-8-2v3.13c1.78 1.13 4.73 1.87 8 1.87s6.22-.74 8-1.87V9c0 .5-3.03 2-8 2zm0 6c-4.97 0-8-1.5-8-2v3.13c1.78 1.13 4.73 1.87 8 1.87s6.22-.74 8-1.87V15c0 .5-3.03 2-8 2z"/></svg>
                <h1 style="font-size:22px; font-weight:800;"><?php echo h(__('app_name')); ?></h1>
                <p style="color:var(--text-muted); font-size:13px;"><?php echo h(__('app_tagline')); ?></p>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-error"><?php echo h($error_message); ?></div>
            <?php endif; ?>

            <!-- Quick Connection String Parser -->
            <div class="form-group" style="background:var(--table-hover); padding:10px; border-radius:var(--radius-sm);">
                <label style="font-size:12px; color:var(--text-muted);"><?php echo h(__('connect_uri_label')); ?></label>
                <input type="text" id="connUriInput" class="form-control" placeholder="postgres://user:pass@host:5432/db or mysql://..." oninput="parseConnectionUri(this.value)">
            </div>

            <!-- Saved Connection Profiles -->
            <div id="savedProfilesSection" style="display:none; margin-bottom:14px;">
                <label style="font-size:12px; font-weight:700; margin-bottom:4px; display:block;"><?php echo h(__('saved_connections')); ?></label>
                <select id="savedProfilesSelect" class="form-control" onchange="loadSavedProfile(this.value)">
                    <option value="">-- Choose a saved profile --</option>
                </select>
            </div>

            <form method="post" id="loginForm" onsubmit="saveConnectionProfile();">
                <input type="hidden" name="csrf_token" value="<?php echo h(get_csrf_token()); ?>">
                
                <div class="form-group">
                    <label><?php echo h(__('database_type_label')); ?></label>
                    <select name="db_type" id="dbTypeInput" class="form-control" onchange="togglePortField(this.value)">
                        <option value="mysql">MySQL / MariaDB</option>
                        <option value="pgsql">PostgreSQL / Supabase / Neon</option>
                        <option value="sqlite">SQLite 3</option>
                    </select>
                </div>

                <div style="display:flex; gap:10px;">
                    <div class="form-group" style="flex:3;">
                        <label><?php echo h(__('host_label')); ?></label>
                        <input type="text" name="db_host" id="dbHostInput" class="form-control" value="localhost" placeholder="localhost or db.example.com" required>
                    </div>
                    <div class="form-group" id="portFieldGroup" style="flex:1;">
                        <label><?php echo h(__('port_label')); ?></label>
                        <input type="number" name="db_port" id="dbPortInput" class="form-control" placeholder="3306">
                    </div>
                </div>

                <div style="display:flex; gap:10px;">
                    <div class="form-group" style="flex:1;">
                        <label><?php echo h(__('username_label')); ?></label>
                        <input type="text" name="db_user" id="dbUserInput" class="form-control" value="root">
                    </div>
                    <div class="form-group" style="flex:1;">
                        <label><?php echo h(__('password_label')); ?></label>
                        <input type="password" name="db_pass" id="dbPassInput" class="form-control" placeholder="••••••••">
                    </div>
                </div>

                <div class="form-group">
                    <label><?php echo h(__('database_name_label')); ?></label>
                    <input type="text" name="db_name" id="dbNameInput" class="form-control" placeholder="Leave blank to explore all databases">
                </div>

                <div class="form-group" style="margin-top:8px;">
                    <label style="display:flex; align-items:center; gap:8px; font-weight:normal; cursor:pointer; font-size:12px;">
                        <input type="checkbox" name="db_ssl" id="dbSslInput" value="1">
                        <span>🔒 <?php echo h(__('ssl_label')); ?></span>
                    </label>
                </div>

                <button type="submit" name="login" class="btn btn-primary" style="width:100%; padding:10px; margin-top:6px;"><?php echo h(__('connect_button')); ?></button>
            </form>

            <div style="display:flex; justify-content:space-between; margin-top:20px; padding-top:14px; border-top:1px solid var(--border); font-size:12px;">
                <div>
                    <span style="color:var(--text-muted);"><?php echo h(__('theme')); ?>:</span>
                    <select onchange="location.href='?set_theme='+this.value" style="padding:2px 6px; font-size:12px; width:auto; display:inline-block;">
                        <?php foreach ($THEMES as $t_k => $t_v): ?>
                            <option value="<?php echo $t_k; ?>" <?php echo $current_theme === $t_k ? 'selected' : ''; ?>><?php echo $t_v; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <span style="color:var(--text-muted);"><?php echo h(__('language')); ?>:</span>
                    <select onchange="location.href='?set_lang='+this.value" style="padding:2px 6px; font-size:12px; width:auto; display:inline-block;">
                        <?php foreach ($SUPPORTED_LANGS as $l_k => $l_v): ?>
                            <option value="<?php echo $l_k; ?>" <?php echo $current_lang === $l_k ? 'selected' : ''; ?>><?php echo $l_v; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- ─── Authenticated Workspace ─── -->
    <div class="app-layout">
        <!-- Sidebar Navigation -->
        <aside class="sidebar" id="appSidebar">
            <div class="sidebar-brand">
                <svg viewBox="0 0 24 24"><path d="M12 3C6.48 3 2 4.79 2 7v10c0 2.21 4.48 4 10 4s10-1.79 10-4V7c0-2.21-4.48-4-10-4zm0 2c4.97 0 8 1.5 8 2s-3.03 2-8 2-8-1.5-8-2 3.03-2 8-2zm0 6c-4.97 0-8-1.5-8-2v3.13c1.78 1.13 4.73 1.87 8 1.87s6.22-.74 8-1.87V9c0 .5-3.03 2-8 2zm0 6c-4.97 0-8-1.5-8-2v3.13c1.78 1.13 4.73 1.87 8 1.87s6.22-.74 8-1.87V15c0 .5-3.03 2-8 2z"/></svg>
                <span><?php echo h(__('app_name')); ?></span>
                <span class="badge badge-type" style="margin-left:auto; font-size:10px;"><?php echo h($_SESSION['db_type'] ?? ''); ?></span>
            </div>
            <nav class="sidebar-nav">
                <a href="?page=databases" class="nav-link <?php echo $page === 'databases' ? 'active' : ''; ?>">
                    <span>📁</span> <span><?php echo h(__('databases')); ?></span>
                </a>
                <a href="?page=sql<?php echo $selected_db ? '&db=' . urlencode($selected_db) : ''; ?>" class="nav-link <?php echo $page === 'sql' ? 'active' : ''; ?>">
                    <span>⚡</span> <span><?php echo h(__('sql_console')); ?></span>
                </a>
                <a href="?page=search<?php echo $selected_db ? '&db=' . urlencode($selected_db) : ''; ?>" class="nav-link <?php echo $page === 'search' ? 'active' : ''; ?>">
                    <span>🔍</span> <span><?php echo h(__('global_search')); ?></span>
                </a>
                <a href="?page=import<?php echo $selected_db ? '&db=' . urlencode($selected_db) : ''; ?>" class="nav-link <?php echo $page === 'import' ? 'active' : ''; ?>">
                    <span>📥</span> <span><?php echo h(__('import_data')); ?></span>
                </a>
                <a href="?page=export<?php echo $selected_db ? '&db=' . urlencode($selected_db) : ''; ?>" class="nav-link <?php echo $page === 'export' ? 'active' : ''; ?>">
                    <span>📤</span> <span><?php echo h(__('export_data')); ?></span>
                </a>

                <?php if ($selected_db): ?>
                    <div class="nav-section">
                        <div class="nav-section-title"><?php echo h($selected_db); ?> (<span id="sidebarTableCount"><?php echo count($nav_tables); ?></span>)</div>
                        
                        <?php if (count($nav_tables) > 8): ?>
                            <!-- Instant Table Filter for Large DBs -->
                            <div style="padding:4px 8px 8px 8px;">
                                <input type="text" id="sidebarTableFilter" class="form-control" placeholder="Filter tables..." oninput="filterSidebarTables(this.value)" style="height:28px; font-size:11px; padding:2px 8px; border-radius:var(--radius-sm);">
                            </div>
                        <?php endif; ?>

                        <div id="sidebarTableList">
                            <?php foreach ($nav_tables as $tbl): ?>
                                <a href="?page=browse&db=<?php echo urlencode($selected_db); ?>&table=<?php echo urlencode($tbl); ?>" class="table-tree-item <?php echo $selected_table === $tbl ? 'active' : ''; ?>" data-table-name="<?php echo h($tbl); ?>" title="<?php echo h($tbl); ?>">
                                    <svg class="tbl-icon" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                                    <span class="tbl-text"><?php echo h($tbl); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </nav>
            <div class="sidebar-footer">
                <div style="color:var(--text-muted); margin-bottom:6px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    👤 <?php echo h($_SESSION['db_user'] ?? 'user'); ?> @ <?php echo h($_SESSION['db_host'] ?? 'localhost'); ?>
                </div>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo h(get_csrf_token()); ?>">
                    <button type="submit" name="logout" class="btn btn-danger btn-sm" style="width:100%;">🚪 <?php echo h(__('logout')); ?></button>
                </form>
            </div>
        </aside>

        <!-- Main Wrapper -->
        <div class="main-wrapper">
            <header class="topbar">
                <div style="display:flex; align-items:center; gap:12px;">
                    <button class="mobile-toggle" onclick="document.getElementById('appSidebar').classList.toggle('open')">☰</button>
                    <div class="breadcrumbs">
                        <a href="?page=databases">🏠 <?php echo h(__('databases')); ?></a>
                        <?php if ($selected_db): ?>
                            <span>/</span> <a href="?page=tables&db=<?php echo urlencode($selected_db); ?>"><?php echo h($selected_db); ?></a>
                        <?php endif; ?>
                        <?php if ($selected_table): ?>
                            <span>/</span> <strong><?php echo h($selected_table); ?></strong>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display:flex; gap:10px;">
                    <select onchange="location.href='?set_theme='+this.value+'&page=<?php echo urlencode($page); ?>&db=<?php echo urlencode($selected_db); ?>&table=<?php echo urlencode($selected_table); ?>'" class="form-control" style="width:auto; height:30px; padding:2px 8px; font-size:12px;">
                        <?php foreach ($THEMES as $t_k => $t_v): ?>
                            <option value="<?php echo $t_k; ?>" <?php echo $current_theme === $t_k ? 'selected' : ''; ?>><?php echo $t_v; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select onchange="location.href='?set_lang='+this.value+'&page=<?php echo urlencode($page); ?>&db=<?php echo urlencode($selected_db); ?>&table=<?php echo urlencode($selected_table); ?>'" class="form-control" style="width:auto; height:30px; padding:2px 8px; font-size:12px;">
                        <?php foreach ($SUPPORTED_LANGS as $l_k => $l_v): ?>
                            <option value="<?php echo $l_k; ?>" <?php echo $current_lang === $l_k ? 'selected' : ''; ?>><?php echo $l_v; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </header>

            <main class="content-body">
                <?php if ($success_message): ?>
                    <div class="alert alert-success">✓ <?php echo h($success_message); ?></div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div class="alert alert-error">✕ <?php echo h($error_message); ?></div>
                <?php endif; ?>

                <?php if ($page === 'databases'): ?>
                    <!-- ─── Databases View with Sizes ─── -->
                    <?php
                    $db_stats = $db ? $db->getDatabasesWithStats() : [];
                    $total_server_size = 0;
                    foreach ($db_stats as $ds) { $total_server_size += $ds['size']; }
                    ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
                        <div>
                            <h2 style="font-size:20px; font-weight:800;"><?php echo h(__('databases')); ?></h2>
                            <p style="color:var(--text-muted); font-size:13px;">
                                <?php echo count($db_stats); ?> <?php echo h(__('databases')); ?> &bull; 
                                <strong><?php echo format_bytes($total_server_size); ?></strong> <?php echo h(__('total_size')); ?>
                            </p>
                        </div>
                        <button class="btn btn-primary" onclick="document.getElementById('modalCreateDb').classList.add('active')">+ <?php echo h(__('create_database')); ?></button>
                    </div>

                    <div class="card">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th><?php echo h(__('database_name_label')); ?></th>
                                        <th><?php echo h(__('tables')); ?></th>
                                        <th><?php echo h(__('total_size')); ?></th>
                                        <th><?php echo h(__('data_size')); ?></th>
                                        <th><?php echo h(__('index_size')); ?></th>
                                        <th style="text-align:right;"><?php echo h(__('actions')); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($db_stats as $dbname => $stat): ?>
                                        <tr>
                                            <td>
                                                <a href="?page=tables&db=<?php echo urlencode($dbname); ?>" style="font-weight:600; font-size:14px;">📁 <?php echo h($dbname); ?></a>
                                            </td>
                                            <td><span class="badge badge-type"><?php echo number_format($stat['tables']); ?></span></td>
                                            <td><strong><?php echo format_bytes($stat['size']); ?></strong></td>
                                            <td style="color:var(--text-muted);"><?php echo format_bytes($stat['data_size']); ?></td>
                                            <td style="color:var(--text-muted);"><?php echo format_bytes($stat['index_size']); ?></td>
                                            <td style="text-align:right; white-space:nowrap;">
                                                <a href="?page=tables&db=<?php echo urlencode($dbname); ?>" class="btn btn-secondary btn-sm"><?php echo h(__('tables')); ?></a>
                                                <a href="?page=sql&db=<?php echo urlencode($dbname); ?>" class="btn btn-secondary btn-sm">SQL</a>
                                                <a href="?page=export&db=<?php echo urlencode($dbname); ?>" class="btn btn-secondary btn-sm"><?php echo h(__('export_data')); ?></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Create DB Modal -->
                    <div class="modal-overlay" id="modalCreateDb">
                        <div class="modal-box">
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo h(get_csrf_token()); ?>">
                                <div class="modal-header">
                                    <h3 class="card-title"><?php echo h(__('create_database')); ?></h3>
                                    <button type="button" onclick="document.getElementById('modalCreateDb').classList.remove('active')" style="background:none;border:none;font-size:18px;cursor:pointer;">✕</button>
                                </div>
                                <div class="modal-body">
                                    <div class="form-group">
                                        <label><?php echo h(__('database_name_label')); ?></label>
                                        <input type="text" name="db_name" class="form-control" required placeholder="my_new_database">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalCreateDb').classList.remove('active')"><?php echo h(__('cancel')); ?></button>
                                    <button type="submit" name="create_database" class="btn btn-primary"><?php echo h(__('create_database')); ?></button>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php elseif ($page === 'tables'): ?>
                    <!-- ─── Tables View with phpMyAdmin-Style Size Metrics ─── -->
                    <?php
                    $table_stats = $db && $selected_db ? $db->getTablesWithStats($selected_db) : [];
                    $total_tbl_rows = 0;
                    $total_tbl_data = 0;
                    $total_tbl_idx  = 0;
                    $total_tbl_size = 0;
                    $total_tbl_free = 0;

                    foreach ($table_stats as $ts) {
                        $total_tbl_rows += (int)($ts['Rows'] ?? 0);
                        $total_tbl_data += (float)($ts['Data_length'] ?? 0);
                        $total_tbl_idx  += (float)($ts['Index_length'] ?? 0);
                        $total_tbl_size += (float)($ts['Total_length'] ?? 0);
                        $total_tbl_free += (float)($ts['Data_free'] ?? 0);
                    }
                    ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
                        <div>
                            <h2 style="font-size:20px; font-weight:800;"><?php echo h(__('tables')); ?>: <?php echo h($selected_db); ?></h2>
                            <p style="color:var(--text-muted); font-size:13px;">
                                <?php echo count($table_stats); ?> <?php echo h(__('tables')); ?> &bull; 
                                <?php echo number_format($total_tbl_rows); ?> <?php echo h(__('records')); ?> &bull; 
                                <strong><?php echo format_bytes($total_tbl_size); ?></strong>
                            </p>
                        </div>
                        <div style="display:flex; gap:8px;">
                            <button class="btn btn-primary" onclick="document.getElementById('modalCreateTable').classList.add('active')">+ <?php echo h(__('create_table')); ?></button>
                            <a href="?page=sql&db=<?php echo urlencode($selected_db); ?>" class="btn btn-secondary">⚡ SQL</a>
                            <a href="?page=export&db=<?php echo urlencode($selected_db); ?>" class="btn btn-secondary">📤 <?php echo h(__('export_data')); ?></a>
                        </div>
                    </div>

                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?php echo h(get_csrf_token()); ?>">
                        <input type="hidden" name="database" value="<?php echo h($selected_db); ?>">
                        <div class="card">
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th style="width:30px;"><input type="checkbox" onclick="document.querySelectorAll('.table-select-cb').forEach(c=>c.checked=this.checked);"></th>
                                            <th><?php echo h(__('table_name')); ?></th>
                                            <th><?php echo h(__('engine')); ?></th>
                                            <th><?php echo h(__('collation')); ?></th>
                                            <th><?php echo h(__('records')); ?></th>
                                            <th><?php echo h(__('data_size')); ?></th>
                                            <th><?php echo h(__('index_size')); ?></th>
                                            <th><?php echo h(__('total_size')); ?></th>
                                            <th><?php echo h(__('overhead')); ?></th>
                                            <th style="text-align:right;"><?php echo h(__('actions')); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($table_stats)): ?>
                                            <tr><td colspan="10" style="text-align:center; padding:24px; color:var(--text-muted);">No tables found in this database.</td></tr>
                                        <?php endif; ?>
                                        <?php foreach ($table_stats as $ts): ?>
                                            <tr>
                                                <td><input type="checkbox" name="selected[]" value="<?php echo h($ts['Name']); ?>" class="table-select-cb"></td>
                                                <td><a href="?page=browse&db=<?php echo urlencode($selected_db); ?>&table=<?php echo urlencode($ts['Name']); ?>" style="font-weight:600;">📄 <?php echo h($ts['Name']); ?></a></td>
                                                <td><span class="badge badge-type"><?php echo h($ts['Engine'] ?? 'N/A'); ?></span></td>
                                                <td style="font-size:12px; color:var(--text-muted);"><?php echo h($ts['Collation'] ?? 'N/A'); ?></td>
                                                <td><strong><?php echo number_format($ts['Rows'] ?? 0); ?></strong></td>
                                                <td><?php echo format_bytes($ts['Data_length'] ?? 0); ?></td>
                                                <td><?php echo format_bytes($ts['Index_length'] ?? 0); ?></td>
                                                <td><strong><?php echo format_bytes($ts['Total_length'] ?? 0); ?></strong></td>
                                                <td><?php echo ($ts['Data_free'] ?? 0) > 0 ? ('<span style="color:var(--warning);">' . format_bytes($ts['Data_free']) . '</span>') : '-'; ?></td>
                                                <td style="text-align:right; white-space:nowrap;">
                                                    <a href="?page=browse&db=<?php echo urlencode($selected_db); ?>&table=<?php echo urlencode($ts['Name']); ?>" class="btn btn-secondary btn-sm"><?php echo h(__('browse')); ?></a>
                                                    <a href="?page=structure&db=<?php echo urlencode($selected_db); ?>&table=<?php echo urlencode($ts['Name']); ?>" class="btn btn-secondary btn-sm"><?php echo h(__('structure')); ?></a>
                                                    <a href="?page=operations&db=<?php echo urlencode($selected_db); ?>&table=<?php echo urlencode($ts['Name']); ?>" class="btn btn-secondary btn-sm">⚙</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <!-- phpMyAdmin Summary Footer Row -->
                                    <?php if (!empty($table_stats)): ?>
                                        <tfoot style="background:var(--table-hover); font-weight:700;">
                                            <tr>
                                                <td></td>
                                                <td><?php echo count($table_stats); ?> <?php echo h(__('tables')); ?></td>
                                                <td>-</td>
                                                <td>-</td>
                                                <td><?php echo number_format($total_tbl_rows); ?></td>
                                                <td><?php echo format_bytes($total_tbl_data); ?></td>
                                                <td><?php echo format_bytes($total_tbl_idx); ?></td>
                                                <td><?php echo format_bytes($total_tbl_size); ?></td>
                                                <td><?php echo format_bytes($total_tbl_free); ?></td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    <?php endif; ?>
                                </table>
                            </div>
                            <?php if (!empty($table_stats)): ?>
                                <div style="padding:12px 18px; display:flex; gap:8px; align-items:center; background:var(--table-hover);">
                                    <button type="submit" name="bulk_action" value="drop" class="btn btn-danger btn-sm" onclick="return confirm('Drop selected tables?');"><?php echo h(__('drop_selected')); ?></button>
                                    <button type="submit" name="bulk_action" value="truncate" class="btn btn-secondary btn-sm" onclick="return confirm('Empty selected tables?');"><?php echo h(__('truncate_selected')); ?></button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </form>

                    <!-- Create Table Modal -->
                    <div class="modal-overlay" id="modalCreateTable">
                        <div class="modal-box">
                            <form method="post" onsubmit="return buildTableSql();">
                                <input type="hidden" name="csrf_token" value="<?php echo h(get_csrf_token()); ?>">
                                <input type="hidden" name="create_table_db" value="<?php echo h($selected_db); ?>">
                                <input type="hidden" name="create_table_sql" id="createTableSql">
                                <div class="modal-header">
                                    <h3 class="card-title"><?php echo h(__('create_table')); ?></h3>
                                    <button type="button" onclick="document.getElementById('modalCreateTable').classList.remove('active')" style="background:none;border:none;font-size:18px;cursor:pointer;">✕</button>
                                </div>
                                <div class="modal-body">
                                    <div class="form-group">
                                        <label><?php echo h(__('table_name')); ?></label>
                                        <input type="text" id="newTableName" class="form-control" required placeholder="users">
                                    </div>
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin:14px 0 8px;">
                                        <strong><?php echo h(__('columns')); ?></strong>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="addTableColumnRow()">+ <?php echo h(__('add_column')); ?></button>
                                    </div>
                                    <div id="colBuilderList"></div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalCreateTable').classList.remove('active')"><?php echo h(__('cancel')); ?></button>
                                    <button type="submit" name="create_table" class="btn btn-primary"><?php echo h(__('create_table')); ?></button>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php elseif ($page === 'browse'): ?>
                    <!-- ─── Data Browser with Adminer-Style Multi-Condition Filter ─── -->
                    <?php
                    $cols = $db->getColumns($selected_table);
                    $limit = (int)get_get('limit', 50);
                    $cur_p = max(1, (int)get_get('p', 1));
                    $offset = ($cur_p - 1) * $limit;
                    $sort_col = get_get('sort', '');
                    $sort_dir = strtoupper(get_get('dir', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

                    // Parse Adminer-style search conditions: where[0][col], where[0][op], where[0][val]
                    $where_params = (array)get_get('where', []);
                    $where_clauses = [];
                    foreach ($where_params as $w) {
                        $c = $w['col'] ?? '';
                        $op = $w['op'] ?? '=';
                        $val = $w['val'] ?? '';
                        if (!$c) continue;

                        $q_col = $db->quoteIdentifier($c);
                        if ($op === 'IS NULL') {
                            $where_clauses[] = "$q_col IS NULL";
                        } elseif ($op === 'IS NOT NULL') {
                            $where_clauses[] = "$q_col IS NOT NULL";
                        } elseif ($op === 'LIKE %...%') {
                            $where_clauses[] = "$q_col LIKE " . $db->getPdo()->quote("%$val%");
                        } elseif ($op === 'STARTS WITH') {
                            $where_clauses[] = "$q_col LIKE " . $db->getPdo()->quote("$val%");
                        } elseif ($op === 'ENDS WITH') {
                            $where_clauses[] = "$q_col LIKE " . $db->getPdo()->quote("%$val");
                        } elseif ($op === 'NOT LIKE') {
                            $where_clauses[] = "$q_col NOT LIKE " . $db->getPdo()->quote("%$val%");
                        } elseif ($op === 'IN (...)') {
                            $in_vals = array_map(function($v) use ($db) { return $db->getPdo()->quote(trim($v)); }, explode(',', $val));
                            $where_clauses[] = "$q_col IN (" . implode(',', $in_vals) . ")";
                        } elseif (in_array($op, ['=', '!=', '>', '<', '>=', '<='])) {
                            $where_clauses[] = "$q_col $op " . $db->getPdo()->quote($val);
                        }
                    }

                    // Fallback to simple search
                    $simple_q = trim(get_get('search', ''));
                    $simple_f = get_get('search_field', '');
                    if ($simple_q !== '' && $simple_f !== '' && empty($where_clauses)) {
                        $where_clauses[] = $db->quoteIdentifier($simple_f) . " LIKE " . $db->getPdo()->quote("%$simple_q%");
                    }

                    $where_sql = !empty($where_clauses) ? (" WHERE " . implode(' AND ', $where_clauses)) : "";
                    $order_sql = $sort_col ? (" ORDER BY " . $db->quoteIdentifier($sort_col) . " $sort_dir") : "";
                    $data_sql = "SELECT * FROM " . $db->quoteIdentifier($selected_table) . $where_sql . $order_sql . " LIMIT $limit OFFSET $offset";
                    
                    $rows = [];
                    try {
                        $rows = $db->query($data_sql)->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Exception $e) { $error_message = $e->getMessage(); }

                    $total_rows = $db->getRowCount($selected_table);
                    $total_pages = max(1, ceil($total_rows / $limit));
                    ?>

                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
                        <div>
                            <h2 style="font-size:20px; font-weight:800;"><?php echo h($selected_table); ?></h2>
                            <p style="color:var(--text-muted); font-size:13px;"><?php echo number_format($total_rows); ?> <?php echo h(__('total_records')); ?> (<?php echo h(__('page')); ?> <?php echo $cur_p; ?>/<?php echo $total_pages; ?>)</p>
                        </div>
                        <div style="display:flex; gap:8px;">
                            <a href="?page=insert&db=<?php echo urlencode($selected_db); ?>&table=<?php echo urlencode($selected_table); ?>" class="btn btn-primary">+ <?php echo h(__('insert_record')); ?></a>
                            <button class="btn btn-secondary" onclick="document.getElementById('advancedFilterBox').style.display=document.getElementById('advancedFilterBox').style.display==='none'?'block':'none'">🔍 <?php echo h(__('filter')); ?></button>
                            <a href="?page=structure&db=<?php echo urlencode($selected_db); ?>&table=<?php echo urlencode($selected_table); ?>" class="btn btn-secondary"><?php echo h(__('structure')); ?></a>
                            <a href="?page=operations&db=<?php echo urlencode($selected_db); ?>&table=<?php echo urlencode($selected_table); ?>" class="btn btn-secondary">⚙ <?php echo h(__('operations')); ?></a>
                        </div>
                    </div>

                    <!-- Adminer-Style Advanced Multi-Condition Filter Box -->
                    <div class="card" id="advancedFilterBox" style="<?php echo empty($where_params) ? 'display:none;' : ''; ?> margin-bottom:14px;">
                        <div class="card-header">
                            <strong style="font-size:13px;">🔍 Filter & Search (Adminer Style)</strong>
                        </div>
                        <form method="get" style="padding:14px;">
                            <input type="hidden" name="page" value="browse">
                            <input type="hidden" name="db" value="<?php echo h($selected_db); ?>">
                            <input type="hidden" name="table" value="<?php echo h($selected_table); ?>">
                            
                            <div id="filterConditionRows">
                                <?php if (!empty($where_params)): ?>
                                    <?php foreach ($where_params as $idx => $w): ?>
                                        <div class="filter-row" style="display:flex; gap:8px; margin-bottom:8px; align-items:center;">
                                            <select name="where[<?php echo $idx; ?>][col]" class="form-control" style="flex:2;">
                                                <?php foreach ($cols as $col): ?>
                                                    <?php $fn = $col['Field'] ?? $col['name'] ?? $col['column_name']; ?>
                                                    <option value="<?php echo h($fn); ?>" <?php echo ($w['col'] ?? '') === $fn ? 'selected' : ''; ?>><?php echo h($fn); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <select name="where[<?php echo $idx; ?>][op]" class="form-control" style="flex:1.5;">
                                                <option <?php echo ($w['op'] ?? '') === '=' ? 'selected' : ''; ?>>=</option>
                                                <option <?php echo ($w['op'] ?? '') === 'LIKE %...%' ? 'selected' : ''; ?>>LIKE %...%</option>
                                                <option <?php echo ($w['op'] ?? '') === 'STARTS WITH' ? 'selected' : ''; ?>>STARTS WITH</option>
                                                <option <?php echo ($w['op'] ?? '') === 'ENDS WITH' ? 'selected' : ''; ?>>ENDS WITH</option>
                                                <option <?php echo ($w['op'] ?? '') === 'NOT LIKE' ? 'selected' : ''; ?>>NOT LIKE</option>
                                                <option <?php echo ($w['op'] ?? '') === '>' ? 'selected' : ''; ?>>&gt;</option>
                                                <option <?php echo ($w['op'] ?? '') === '<' ? 'selected' : ''; ?>>&lt;</option>
                                                <option <?php echo ($w['op'] ?? '') === '>=' ? 'selected' : ''; ?>>&gt;=</option>
                                                <option <?php echo ($w['op'] ?? '') === '<=' ? 'selected' : ''; ?>>&lt;=</option>
                                                <option <?php echo ($w['op'] ?? '') === '!=' ? 'selected' : ''; ?>>!=</option>
                                                <option <?php echo ($w['op'] ?? '') === 'IN (...)' ? 'selected' : ''; ?>>IN (...)</option>
                                                <option <?php echo ($w['op'] ?? '') === 'IS NULL' ? 'selected' : ''; ?>>IS NULL</option>
                                                <option <?php echo ($w['op'] ?? '') === 'IS NOT NULL' ? 'selected' : ''; ?>>IS NOT NULL</option>
                                            </select>
                                            <input type="text" name="where[<?php echo $idx; ?>][val]" class="form-control" style="flex:2;" value="<?php echo h($w['val'] ?? ''); ?>" placeholder="Value">
                                            <button type="button" onclick="this.parentElement.remove()" class="btn btn-danger btn-sm">✕</button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px;">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="addFilterRow()">+ <?php echo h(__('add_condition')); ?></button>
                                <div style="display:flex; gap:8px;">
                                    <a href="?page=browse&db=<?php echo urlencode($selected_db); ?>&table=<?php echo urlencode($selected_table); ?>" class="btn btn-secondary btn-sm"><?php echo h(__('clear')); ?></a>
                                    <button type="submit" class="btn btn-primary btn-sm">🔍 Apply Filters</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Data Grid Table -->
                    <div class="card">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th style="text-align:center; width:80px;"><?php echo h(__('actions')); ?></th>
                                        <?php foreach ($cols as $col): ?>
                                            <?php
                                            $fn = $col['Field'] ?? $col['name'] ?? $col['column_name'];
                                            $next_dir = ($sort_col === $fn && $sort_dir === 'ASC') ? 'DESC' : 'ASC';
                                            $sort_url = "?page=browse&db=" . urlencode($selected_db) . "&table=" . urlencode($selected_table) . "&sort=" . urlencode($fn) . "&dir=" . $next_dir;
                                            ?>
                                            <th>
                                                <a href="<?php echo $sort_url; ?>" style="color:inherit; text-decoration:none; display:flex; align-items:center; gap:4px;">
                                                    <span><?php echo h($fn); ?></span>
                                                    <?php if ($sort_col === $fn): ?>
                                                        <span><?php echo $sort_dir === 'ASC' ? '▲' : '▼'; ?></span>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($rows)): ?>
                                        <tr><td colspan="<?php echo count($cols) + 1; ?>" style="text-align:center; padding:30px; color:var(--text-muted);">No records found.</td></tr>
                                    <?php endif; ?>
                                    <?php foreach ($rows as $row): ?>
                                        <?php
                                        $pk_params = [];
                                        foreach ($row as $k => $v) { $pk_params['where_' . $k] = $v; }
                                        $edit_url = "?page=edit&db=" . urlencode($selected_db) . "&table=" . urlencode($selected_table) . "&" . http_build_query($pk_params);
                                        $del_url = "?action=delete&csrf_token=" . urlencode(get_csrf_token()) . "&db=" . urlencode($selected_db) . "&table=" . urlencode($selected_table) . "&" . http_build_query($pk_params);
                                        ?>
                                        <tr>
                                            <td style="white-space:nowrap; text-align:center;">
                                                <a href="<?php echo $edit_url; ?>" class="btn btn-secondary btn-sm" title="Edit">✏</a>
                                                <a href="<?php echo $del_url; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this record?');" title="Delete">🗑</a>
                                            </td>
                                            <?php foreach ($row as $val): ?>
                                                <td>
                                                    <?php if ($val === null): ?>
                                                        <span class="badge badge-null">NULL</span>
                                                    <?php elseif (strlen($val) > 80): ?>
                                                        <span title="<?php echo h($val); ?>"><?php echo h(substr($val, 0, 80)) . '...'; ?></span>
                                                    <?php else: ?>
                                                        <?php echo h($val); ?>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination Footer -->
                        <?php if ($total_pages > 1): ?>
                            <div style="padding:12px 18px; display:flex; justify-content:space-between; align-items:center; background:var(--table-hover); font-size:13px;">
                                <div><?php echo h(__('rows_per_page')); ?>: <strong><?php echo $limit; ?></strong></div>
                                <div style="display:flex; gap:6px;">
                                    <?php if ($cur_p > 1): ?>
                                        <a href="?page=browse&db=<?php echo urlencode($selected_db); ?>&table=<?php echo urlencode($selected_table); ?>&p=<?php echo $cur_p - 1; ?>&sort=<?php echo urlencode($sort_col); ?>&dir=<?php echo $sort_dir; ?>" class="btn btn-secondary btn-sm">&laquo; Prev</a>
                                    <?php endif; ?>
                                    <span style="padding:4px 8px; font-weight:600;"><?php echo $cur_p; ?> / <?php echo $total_pages; ?></span>
                                    <?php if ($cur_p < $total_pages): ?>
                                        <a href="?page=browse&db=<?php echo urlencode($selected_db); ?>&table=<?php echo urlencode($selected_table); ?>&p=<?php echo $cur_p + 1; ?>&sort=<?php echo urlencode($sort_col); ?>&dir=<?php echo $sort_dir; ?>" class="btn btn-secondary btn-sm">Next &raquo;</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php elseif ($page === 'structure'): ?>
                    <!-- ─── Structure View ─── -->
                    <?php
                    $cols = $db->getColumns($selected_table);
                    ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                        <div>
                            <h2 style="font-size:20px; font-weight:800;"><?php echo h(__('structure')); ?>: <?php echo h($selected_table); ?></h2>
                            <p style="color:var(--text-muted); font-size:13px;"><?php echo count($cols); ?> <?php echo h(__('columns')); ?></p>
                        </div>
                        <div style="display:flex; gap:8px;">
                            <a href="?page=browse&db=<?php echo urlencode($selected_db); ?>&table=<?php echo urlencode($selected_table); ?>" class="btn btn-secondary"><?php echo h(__('browse')); ?></a>
                            <a href="?page=insert&db=<?php echo urlencode($selected_db); ?>&table=<?php echo urlencode($selected_table); ?>" class="btn btn-primary">+ <?php echo h(__('insert_record')); ?></a>
                        </div>
                    </div>

                    <div class="card">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th><?php echo h(__('field')); ?></th>
                                        <th><?php echo h(__('type')); ?></th>
                                        <th><?php echo h(__('null')); ?></th>
                                        <th><?php echo h(__('default')); ?></th>
                                        <th><?php echo h(__('extra')); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cols as $idx => $col): ?>
                                        <tr>
                                            <td><?php echo $idx + 1; ?></td>
                                            <td><strong><?php echo h($col['Field'] ?? $col['name'] ?? $col['column_name']); ?></strong></td>
                                            <td><span class="badge badge-type"><?php echo h($col['Type'] ?? $col['type'] ?? $col['data_type']); ?></span></td>
                                            <td><?php echo h($col['Null'] ?? $col['notnull'] ?? 'YES'); ?></td>
                                            <td><?php echo h($col['Default'] ?? $col['dflt_value'] ?? 'NULL'); ?></td>
                                            <td><?php echo h($col['Extra'] ?? ($col['pk'] ? 'PRIMARY KEY' : '')); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                <?php elseif ($page === 'operations'): ?>
                    <!-- ─── Table Operations ─── -->
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                        <h2 style="font-size:20px; font-weight:800;"><?php echo h(__('operations')); ?>: <?php echo h($selected_table); ?></h2>
                        <a href="?page=browse&db=<?php echo urlencode($selected_db); ?>&table=<?php echo urlencode($selected_table); ?>" class="btn btn-secondary"><?php echo h(__('back_to_table')); ?></a>
                    </div>

                    <!-- Rename Table -->
                    <div class="card">
                        <div class="card-header"><h3 class="card-title"><?php echo h(__('rename_table')); ?></h3></div>
                        <form method="post" style="padding:18px;">
                            <input type="hidden" name="csrf_token" value="<?php echo h(get_csrf_token()); ?>">
                            <input type="hidden" name="table" value="<?php echo h($selected_table); ?>">
                            <input type="hidden" name="operation_action" value="rename_table">
                            <div class="form-group">
                                <label>New Table Name</label>
                                <input type="text" name="new_table_name" class="form-control" value="<?php echo h($selected_table); ?>" required>
                            </div>
                            <button type="submit" class="btn btn-primary"><?php echo h(__('rename_table')); ?></button>
                        </form>
                    </div>

                    <!-- Copy Table -->
                    <div class="card">
                        <div class="card-header"><h3 class="card-title"><?php echo h(__('copy_table')); ?></h3></div>
                        <form method="post" style="padding:18px;">
                            <input type="hidden" name="csrf_token" value="<?php echo h(get_csrf_token()); ?>">
                            <input type="hidden" name="table" value="<?php echo h($selected_table); ?>">
                            <input type="hidden" name="operation_action" value="copy_table">
                            <div class="form-group">
                                <label>Target Table Name</label>
                                <input type="text" name="copy_table_name" class="form-control" value="<?php echo h($selected_table . '_copy'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label style="display:flex; align-items:center; gap:8px; font-weight:normal; font-size:13px;">
                                    <input type="checkbox" name="copy_data" value="1" checked> Copy table structure and data
                                </label>
                            </div>
                            <button type="submit" class="btn btn-primary"><?php echo h(__('copy_table')); ?></button>
                        </form>
                    </div>

                    <!-- Dangerous Actions: Truncate / Drop -->
                    <div class="card" style="border-color:var(--danger);">
                        <div class="card-header" style="background:#fee2e2; color:#991b1b;"><h3 class="card-title">Danger Zone</h3></div>
                        <div style="padding:18px; display:flex; gap:12px;">
                            <form method="post" onsubmit="return confirm('Empty all rows in this table?');">
                                <input type="hidden" name="csrf_token" value="<?php echo h(get_csrf_token()); ?>">
                                <input type="hidden" name="table" value="<?php echo h($selected_table); ?>">
                                <input type="hidden" name="operation_action" value="truncate_table">
                                <button type="submit" class="btn btn-danger"><?php echo h(__('truncate')); ?> Table</button>
                            </form>
                            <form method="post" onsubmit="return confirm('Permanently drop this table?');">
                                <input type="hidden" name="csrf_token" value="<?php echo h(get_csrf_token()); ?>">
                                <input type="hidden" name="table" value="<?php echo h($selected_table); ?>">
                                <input type="hidden" name="operation_action" value="drop_table">
                                <button type="submit" class="btn btn-danger"><?php echo h(__('drop')); ?> Table</button>
                            </form>
                        </div>
                    </div>

                <?php elseif ($page === 'insert' || $page === 'edit'): ?>
                    <!-- ─── Insert / Edit Form ─── -->
                    <?php
                    $is_edit = ($page === 'edit');
                    $cols = $db->getColumns($selected_table);
                    $edit_vals = [];
                    if ($is_edit) {
                        foreach ($_GET as $k => $v) {
                            if (strpos($k, 'where_') === 0) $edit_vals[substr($k, 6)] = $v;
                        }
                    }
                    ?>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                        <h2 style="font-size:20px; font-weight:800;"><?php echo h($is_edit ? __('edit_record') : __('insert_record')); ?>: <?php echo h($selected_table); ?></h2>
                        <a href="?page=browse&db=<?php echo urlencode($selected_db); ?>&table=<?php echo urlencode($selected_table); ?>" class="btn btn-secondary"><?php echo h(__('back_to_table')); ?></a>
                    </div>
                    <div class="card">
                        <form method="post" style="padding:18px;">
                            <input type="hidden" name="csrf_token" value="<?php echo h(get_csrf_token()); ?>">
                            <input type="hidden" name="table" value="<?php echo h($selected_table); ?>">
                            <input type="hidden" name="is_edit" value="<?php echo $is_edit ? '1' : '0'; ?>">
                            <?php foreach ($cols as $col): ?>
                                <?php
                                $fn = $col['Field'] ?? $col['name'] ?? $col['column_name'];
                                $val = $is_edit ? ($edit_vals[$fn] ?? '') : '';
                                ?>
                                <div class="form-group">
                                    <label><?php echo h($fn); ?> <span style="font-size:11px; color:var(--text-muted);">(<?php echo h($col['Type'] ?? $col['type'] ?? ''); ?>)</span></label>
                                    <input type="text" name="field[<?php echo h($fn); ?>]" class="form-control" value="<?php echo h($val); ?>">
                                    <?php if ($is_edit): ?>
                                        <input type="hidden" name="field[old_<?php echo h($fn); ?>]" value="<?php echo h($val); ?>">
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <div style="display:flex; gap:10px; margin-top:16px;">
                                <button type="submit" name="save_record" class="btn btn-primary"><?php echo h(__('save')); ?></button>
                                <a href="?page=browse&db=<?php echo urlencode($selected_db); ?>&table=<?php echo urlencode($selected_table); ?>" class="btn btn-secondary"><?php echo h(__('cancel')); ?></a>
                            </div>
                        </form>
                    </div>

                <?php elseif ($page === 'sql'): ?>
                    <!-- ─── SQL Console ─── -->
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                        <div>
                            <h2 style="font-size:20px; font-weight:800;"><?php echo h(__('sql_console')); ?></h2>
                            <p style="color:var(--text-muted); font-size:13px;"><?php echo $selected_db ? ("Database: " . h($selected_db)) : "Execute raw queries"; ?></p>
                        </div>
                    </div>

                    <?php if ($sql_error): ?>
                        <div class="alert alert-error">✕ <?php echo h($sql_error); ?></div>
                    <?php endif; ?>

                    <div class="card">
                        <form method="post" id="sqlConsoleForm" style="padding:18px;">
                            <input type="hidden" name="csrf_token" value="<?php echo h(get_csrf_token()); ?>">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                                <div style="display:flex; gap:6px;">
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="setSqlQuery('SELECT * FROM <?php echo h($selected_table ?: 'table_name'); ?> LIMIT 50;')">SELECT *</button>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="setSqlQuery('SELECT COUNT(*) FROM <?php echo h($selected_table ?: 'table_name'); ?>;')">COUNT(*)</button>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="setSqlQuery('SHOW TABLES;')">SHOW TABLES</button>
                                </div>
                                <div style="display:flex; gap:6px; align-items:center;">
                                    <select id="sqlHistorySelect" onchange="if(this.value){document.getElementById('sqlQueryTextarea').value=this.value;}" class="form-control" style="height:28px; padding:2px 8px; font-size:12px; width:180px;">
                                        <option value="">-- <?php echo h(__('recent_queries')); ?> --</option>
                                    </select>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="clearSqlHistory()"><?php echo h(__('clear')); ?></button>
                                </div>
                            </div>
                            <div class="form-group">
                                <textarea name="sql" id="sqlQueryTextarea" rows="8" class="form-control" style="font-family:monospace; font-size:13px;" placeholder="SELECT * FROM table LIMIT 10;"><?php echo h(get_post('sql', get_get('pre_sql', ''))); ?></textarea>
                            </div>
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <div style="display:flex; gap:8px;">
                                    <button type="submit" name="execute_sql" class="btn btn-primary">⚡ <?php echo h(__('execute_query')); ?> (Ctrl+Enter)</button>
                                    <button type="submit" name="export_query" class="btn btn-secondary">💾 <?php echo h(__('export_query')); ?></button>
                                </div>
                                <span style="font-size:12px; color:var(--text-muted);">Shortcut: <strong>Ctrl+Enter</strong> / <strong>Cmd+Enter</strong></span>
                            </div>
                        </form>
                    </div>

                    <?php if ($sql_result !== null): ?>
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title"><?php echo h(__('query_results')); ?></h3>
                                <span style="font-size:12px; color:var(--text-muted);"><?php echo $sql_time; ?>ms &bull; <?php echo count($sql_result); ?> <?php echo h(__('rows')); ?></span>
                            </div>
                            <?php if (!empty($sql_result)): ?>
                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <?php foreach (array_keys($sql_result[0]) as $k): ?>
                                                    <th><?php echo h($k); ?></th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sql_result as $r): ?>
                                                <tr>
                                                    <?php foreach ($r as $v): ?>
                                                        <td><?php echo $v === null ? '<span class="badge badge-null">NULL</span>' : h($v); ?></td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div style="padding:18px; color:var(--success); font-weight:600;">✓ Query executed successfully. <?php echo $sql_affected; ?> row(s) affected.</div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                <?php elseif ($page === 'export'): ?>
                    <!-- ─── Export View ─── -->
                    <?php $all_dbs = $db ? $db->getDatabases() : []; ?>
                    <h2 style="font-size:20px; font-weight:800; margin-bottom:14px;"><?php echo h(__('export_data')); ?></h2>
                    <div class="card">
                        <div class="card-header"><h3 class="card-title"><?php echo h(__('export_entire_database')); ?></h3></div>
                        <form method="post" style="padding:18px;">
                            <input type="hidden" name="csrf_token" value="<?php echo h(get_csrf_token()); ?>">
                            <div class="form-group">
                                <label><?php echo h(__('select_database')); ?></label>
                                <select name="export_db_name" class="form-control" required>
                                    <?php foreach ($all_dbs as $d): ?>
                                        <option value="<?php echo h($d); ?>" <?php echo $d === $selected_db ? 'selected' : ''; ?>><?php echo h($d); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label><?php echo h(__('export_format')); ?></label>
                                <select name="export_db_format" class="form-control">
                                    <option value="sql">SQL Dump (.sql)</option>
                                </select>
                            </div>
                            <button type="submit" name="export_database" class="btn btn-primary">⬇ <?php echo h(__('download_database')); ?></button>
                        </form>
                    </div>

                <?php elseif ($page === 'import'): ?>
                    <!-- ─── Import View ─── -->
                    <h2 style="font-size:20px; font-weight:800; margin-bottom:14px;"><?php echo h(__('import_data')); ?></h2>
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">SQL File Upload</h3></div>
                        <form method="post" enctype="multipart/form-data" style="padding:18px;">
                            <input type="hidden" name="csrf_token" value="<?php echo h(get_csrf_token()); ?>">
                            <div class="form-group">
                                <label>Select .sql File (Max 25MB)</label>
                                <input type="file" name="sql_file" accept=".sql" class="form-control" required>
                            </div>
                            <button type="submit" name="import_sql" class="btn btn-primary">📥 Import SQL File</button>
                        </form>
                    </div>

                <?php elseif ($page === 'search'): ?>
                    <!-- ─── Global Search ─── -->
                    <?php
                    $all_dbs = $db ? $db->getDatabases() : [];
                    $sterm = trim(get_post('search_term', ''));
                    $sdb = get_post('search_database', $selected_db ?: ($all_dbs[0] ?? ''));
                    $results = [];
                    if ($sterm && $sdb && $db) {
                        if ($db->getType() !== 'sqlite') $db->getPdo()->exec("USE " . $db->quoteIdentifier($sdb));
                        $stables = $db->getTables($sdb);
                        foreach ($stables as $stbl) {
                            try {
                                $scols = $db->getColumns($stbl);
                                $w_clauses = [];
                                foreach ($scols as $sc) {
                                    $tp = strtolower($sc['Type'] ?? $sc['type'] ?? '');
                                    $cn = $sc['Field'] ?? $sc['name'] ?? '';
                                    if ($cn && preg_match('~char|text|varchar|enum|blob~i', $tp)) {
                                        $w_clauses[] = $db->quoteIdentifier($cn) . " LIKE " . $db->getPdo()->quote("%$sterm%");
                                    }
                                }
                                if (!empty($w_clauses)) {
                                    $sq = "SELECT * FROM " . $db->quoteIdentifier($stbl) . " WHERE " . implode(' OR ', $w_clauses) . " LIMIT 10";
                                    $found = $db->query($sq)->fetchAll(PDO::FETCH_ASSOC);
                                    if (!empty($found)) {
                                        $results[$stbl] = $found;
                                    }
                                }
                            } catch (Exception $e) {}
                        }
                    }
                    ?>
                    <h2 style="font-size:20px; font-weight:800; margin-bottom:14px;"><?php echo h(__('global_search')); ?></h2>
                    <div class="card">
                        <form method="post" style="padding:18px;">
                            <input type="hidden" name="csrf_token" value="<?php echo h(get_csrf_token()); ?>">
                            <div class="form-group">
                                <label><?php echo h(__('search')); ?> Term</label>
                                <input type="text" name="search_term" class="form-control" value="<?php echo h($sterm); ?>" placeholder="Keyword to find..." required autofocus>
                            </div>
                            <div class="form-group">
                                <label><?php echo h(__('select_database')); ?></label>
                                <select name="search_database" class="form-control">
                                    <?php foreach ($all_dbs as $d): ?>
                                        <option value="<?php echo h($d); ?>" <?php echo $d === $sdb ? 'selected' : ''; ?>><?php echo h($d); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">🔍 <?php echo h(__('search')); ?></button>
                        </form>
                    </div>

                    <?php if ($sterm): ?>
                        <div style="margin-top:20px;">
                            <h3>Results for "<?php echo h($sterm); ?>" (<?php echo count($results); ?> tables matched)</h3>
                            <?php foreach ($results as $tbl_name => $found_rows): ?>
                                <div class="card" style="margin-top:14px;">
                                    <div class="card-header">
                                        <strong>📄 <?php echo h($tbl_name); ?></strong> (<?php echo count($found_rows); ?> matches preview)
                                        <a href="?page=browse&db=<?php echo urlencode($sdb); ?>&table=<?php echo urlencode($tbl_name); ?>&search=<?php echo urlencode($sterm); ?>" class="btn btn-secondary btn-sm"><?php echo h(__('browse')); ?> Table</a>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="data-table">
                                            <thead>
                                                <tr>
                                                    <?php foreach (array_keys($found_rows[0]) as $h_k): ?>
                                                        <th><?php echo h($h_k); ?></th>
                                                    <?php endforeach; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($found_rows as $fr): ?>
                                                    <tr>
                                                        <?php foreach ($fr as $fv): ?>
                                                            <td><?php echo $fv === null ? '<span class="badge badge-null">NULL</span>' : h($fv); ?></td>
                                                        <?php endforeach; ?>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                <?php endif; ?>
            </main>
        </div>
    </div>
<?php endif; ?>

<script>
// ─── Connection URI Parser & Profile Manager ──────────────────────────────────
function parseConnectionUri(uri) {
    if (!uri) return;
    try {
        const u = new URL(uri);
        const protocol = u.protocol.replace(':', '');
        const typeSelect = document.getElementById('dbTypeInput');
        if (protocol.includes('postgres')) typeSelect.value = 'pgsql';
        else if (protocol.includes('mysql') || protocol.includes('mariadb')) typeSelect.value = 'mysql';
        else if (protocol.includes('sqlite')) typeSelect.value = 'sqlite';

        togglePortField(typeSelect.value);

        if (u.hostname) document.getElementById('dbHostInput').value = u.hostname;
        if (u.port) document.getElementById('dbPortInput').value = u.port;
        if (u.username) document.getElementById('dbUserInput').value = decodeURIComponent(u.username);
        if (u.password) document.getElementById('dbPassInput').value = decodeURIComponent(u.password);
        if (u.pathname && u.pathname.length > 1) document.getElementById('dbNameInput').value = decodeURIComponent(u.pathname.replace(/^\//, ''));
        
        if (u.searchParams.get('sslmode') === 'require' || u.searchParams.get('ssl') === 'true') {
            document.getElementById('dbSslInput').checked = true;
        }
    } catch (_) {}
}

function togglePortField(type) {
    const pGroup = document.getElementById('portFieldGroup');
    const pInput = document.getElementById('dbPortInput');
    if (!pGroup || !pInput) return;
    if (type === 'sqlite') {
        pGroup.style.display = 'none';
    } else {
        pGroup.style.display = 'block';
        if (!pInput.value || pInput.value === '3306' || pInput.value === '5432') {
            pInput.value = type === 'pgsql' ? '5432' : '3306';
        }
    }
}

function saveConnectionProfile() {
    const host = document.getElementById('dbHostInput')?.value.trim();
    if (!host) return;
    const type = document.getElementById('dbTypeInput')?.value;
    const port = document.getElementById('dbPortInput')?.value;
    const user = document.getElementById('dbUserInput')?.value;
    const name = document.getElementById('dbNameInput')?.value;
    const ssl  = document.getElementById('dbSslInput')?.checked;

    const profileName = `${type}://${user ? user + '@' : ''}${host}${port ? ':' + port : ''}${name ? '/' + name : ''}`;
    try {
        let profiles = JSON.parse(localStorage.getItem('dabiro_profiles') || '{}');
        profiles[profileName] = { type, host, port, user, name, ssl };
        localStorage.setItem('dabiro_profiles', JSON.stringify(profiles));
    } catch (_) {}
}

function renderSavedProfiles() {
    const sec = document.getElementById('savedProfilesSection');
    const sel = document.getElementById('savedProfilesSelect');
    if (!sec || !sel) return;
    try {
        const profiles = JSON.parse(localStorage.getItem('dabiro_profiles') || '{}');
        const keys = Object.keys(profiles);
        if (keys.length === 0) { sec.style.display = 'none'; return; }
        sec.style.display = 'block';
        sel.innerHTML = '<option value="">-- Choose a saved profile --</option>';
        keys.forEach(k => {
            const opt = document.createElement('option');
            opt.value = k;
            opt.textContent = k;
            sel.appendChild(opt);
        });
    } catch (_) {}
}

function loadSavedProfile(k) {
    if (!k) return;
    try {
        const profiles = JSON.parse(localStorage.getItem('dabiro_profiles') || '{}');
        const p = profiles[k];
        if (!p) return;
        document.getElementById('dbTypeInput').value = p.type;
        togglePortField(p.type);
        document.getElementById('dbHostInput').value = p.host || '';
        document.getElementById('dbPortInput').value = p.port || '';
        document.getElementById('dbUserInput').value = p.user || '';
        document.getElementById('dbNameInput').value = p.name || '';
        document.getElementById('dbSslInput').checked = !!p.ssl;
    } catch (_) {}
}

// ─── Sidebar Table Filter ─────────────────────────────────────────────────────
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

// ─── SQL Shortcuts & Filters ──────────────────────────────────────────────────
let filterIdx = 100;
function addFilterRow() {
    filterIdx++;
    const container = document.getElementById('filterConditionRows');
    if (!container) return;
    const row = document.createElement('div');
    row.className = 'filter-row';
    row.style.cssText = 'display:flex; gap:8px; margin-bottom:8px; align-items:center;';
    
    // Copy options from existing select
    const firstSelect = container.querySelector('select');
    const optionsHtml = firstSelect ? firstSelect.innerHTML : '<option value="id">id</option>';

    row.innerHTML = `
        <select name="where[${filterIdx}][col]" class="form-control" style="flex:2;">${optionsHtml}</select>
        <select name="where[${filterIdx}][op]" class="form-control" style="flex:1.5;">
            <option>=</option><option>LIKE %...%</option><option>STARTS WITH</option><option>ENDS WITH</option>
            <option>NOT LIKE</option><option>&gt;</option><option>&lt;</option><option>&gt;=</option>
            <option>&lt;=</option><option>!=</option><option>IN (...)</option><option>IS NULL</option><option>IS NOT NULL</option>
        </select>
        <input type="text" name="where[${filterIdx}][val]" class="form-control" style="flex:2;" placeholder="Value">
        <button type="button" onclick="this.parentElement.remove()" class="btn btn-danger btn-sm">✕</button>
    `;
    container.appendChild(row);
}

function setSqlQuery(sql) {
    const ta = document.getElementById('sqlQueryTextarea');
    if (ta) { ta.value = sql; ta.focus(); }
}

function initSqlShortcuts() {
    const ta = document.getElementById('sqlQueryTextarea');
    if (!ta) return;
    ta.addEventListener('keydown', function(e) {
        if (e.key === 'Tab') {
            e.preventDefault();
            const s = this.selectionStart, en = this.selectionEnd;
            this.value = this.value.substring(0, s) + '  ' + this.value.substring(en);
            this.selectionStart = this.selectionEnd = s + 2;
        }
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            const form = document.getElementById('sqlConsoleForm');
            if (form) {
                const btn = form.querySelector('button[name="execute_sql"]');
                if (btn) btn.click(); else form.submit();
            }
        }
    });
}

function addTableColumnRow() {
    const list = document.getElementById('colBuilderList');
    if (!list) return;
    const row = document.createElement('div');
    row.style.cssText = 'display:flex; gap:6px; margin-bottom:8px; align-items:center;';
    row.innerHTML = `
        <input type="text" class="c-name form-control" placeholder="column_name" style="flex:2;" required>
        <select class="c-type form-control" style="flex:1.5;">
            <option>INT</option><option>VARCHAR</option><option>TEXT</option><option>DATE</option><option>DATETIME</option><option>DECIMAL</option><option>BOOLEAN</option><option>BIGINT</option>
        </select>
        <input type="text" class="c-len form-control" placeholder="Length" style="flex:1;">
        <label style="display:flex; align-items:center; gap:4px; font-size:12px; white-space:nowrap;"><input type="checkbox" class="c-null"> NULL</label>
        <button type="button" onclick="this.parentElement.remove()" class="btn btn-danger btn-sm">✕</button>
    `;
    list.appendChild(row);
}

function buildTableSql() {
    const name = document.getElementById('newTableName')?.value.trim();
    if (!name) return false;
    const rows = document.querySelectorAll('#colBuilderList > div');
    const cols = [];
    rows.forEach(r => {
        const n = r.querySelector('.c-name')?.value.trim();
        if (!n) return;
        const t = r.querySelector('.c-type')?.value;
        const l = r.querySelector('.c-len')?.value.trim();
        const isNull = r.querySelector('.c-null')?.checked;
        cols.push('`' + n + '` ' + t + (l ? '(' + l + ')' : '') + (isNull ? ' NULL' : ' NOT NULL'));
    });
    if (cols.length === 0) { alert('Please add at least one column.'); return false; }
    document.getElementById('createTableSql').value = 'CREATE TABLE `' + name + '` (' + cols.join(', ') + ')';
    return true;
}

document.addEventListener('DOMContentLoaded', () => {
    renderSavedProfiles();
    initSqlShortcuts();
    if (document.getElementById('colBuilderList')) {
        addTableColumnRow();
    }
    const filterRows = document.getElementById('filterConditionRows');
    if (filterRows && filterRows.children.length === 0) {
        addFilterRow();
    }
});
</script>
</body>
</html>