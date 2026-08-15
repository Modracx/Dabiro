<?php
/**
 * Dabiro - Professional Database Management System
 * A single-file, zero-dependency database manager with a modern, responsive interface.
 *
 * Version: 2.0.0
 * Kenneth D'silva (Modracx), Copyright (c) 2025
 * Licensed under the MIT License - https://opensource.org/licenses/MIT
 *
 * Icons: Lucide (ISC License) - https://lucide.dev
 */

// ─── Configuration & Session ──────────────────────────────────────────────────
define('DB_ADMIN_VERSION', '2.0.0');
define('SESSION_TIMEOUT', 3600);

// Where tunnel state and the (optional) encrypted connection vault live.
// Override with DABIRO_DATA_DIR to keep this OUTSIDE your web root.
define('DABIRO_DATA_DIR', rtrim(getenv('DABIRO_DATA_DIR') ?: (sys_get_temp_dir() . '/.dabiro'), '/'));

ini_set('display_errors', '0');
mb_internal_encoding('UTF-8');

/**
 * Is the *browser's* connection HTTPS?
 *
 * This decides whether the session cookie gets the Secure flag, and getting it
 * wrong locks people out: a cookie marked Secure is silently discarded by the
 * browser on a plain-HTTP page, so every request starts a fresh session and the
 * login screen reappears forever.
 *
 * Proxy headers are therefore NOT trusted by default - anyone can send
 * X-Forwarded-Proto, and a proxy that sets it while the browser is still on
 * http:// produces exactly that lockout. Set DABIRO_TRUST_PROXY=1 when Dabiro
 * genuinely sits behind a TLS-terminating reverse proxy.
 */
function request_is_https()
{
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') return true;
    if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) return true;
    if (strtolower($_SERVER['REQUEST_SCHEME'] ?? '') === 'https') return true;

    if (getenv('DABIRO_TRUST_PROXY') === '1') {
        // Chained proxies produce "https, http" - the client-facing value is first.
        $proto = strtolower(trim(explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')[0]));
        if ($proto === 'https') return true;
        if (strtolower($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on') return true;
        if (preg_match('/proto=https/i', $_SERVER['HTTP_FORWARDED'] ?? '')) return true;
    }
    return false;
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => request_is_https(),
    ]);
    session_start();
}

/**
 * Detect the "logged in, but bounced straight back to the login screen" loop.
 *
 * A successful login drops a short-lived, never-Secure marker cookie. If the
 * next request arrives without a session but *with* that marker, the session
 * cookie is not surviving the round trip, and we can say why instead of
 * bouncing the user forever.
 */
define('LOGIN_PROBE_COOKIE', 'dabiro_probe');

function login_probe_set()
{
    // Deliberately not Secure: this must come back even when the session cookie
    // cannot, because that mismatch is precisely what we are trying to detect.
    setcookie(LOGIN_PROBE_COOKIE, (string)time(), [
        'expires' => time() + 120, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
    ]);
}

function login_probe_clear()
{
    if (isset($_COOKIE[LOGIN_PROBE_COOKIE])) {
        setcookie(LOGIN_PROBE_COOKIE, '', ['expires' => time() - 3600, 'path' => '/']);
        unset($_COOKIE[LOGIN_PROBE_COOKIE]);
    }
}

/** @return string|null  Explanation of why the session is not sticking. */
function login_loop_diagnosis()
{
    $stamp = (int)($_COOKIE[LOGIN_PROBE_COOKIE] ?? 0);
    if (!$stamp || (time() - $stamp) > 120) return null;

    $reasons = [];

    // By far the most common cause.
    if (request_is_https() && empty($_SERVER['HTTPS']) && (int)($_SERVER['SERVER_PORT'] ?? 0) !== 443) {
        $reasons[] = 'Dabiro thinks this connection is HTTPS (from a proxy header), so the session cookie '
                   . 'is marked Secure - but your browser is on plain HTTP and therefore throws it away. '
                   . 'Either use HTTPS, or unset DABIRO_TRUST_PROXY.';
    } elseif (!request_is_https() && !empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $reasons[] = 'A proxy is forwarding this request. If your browser is on HTTPS, set '
                   . 'DABIRO_TRUST_PROXY=1 so the session cookie is issued correctly.';
    }

    $path = session_save_path();
    if ($path && $path[0] === '/' && !is_writable($path)) {
        $reasons[] = "PHP cannot write sessions to \"$path\", so nothing is remembered between requests. "
                   . 'Fix the permissions or point session.save_path somewhere writable.';
    }

    if (!$reasons) {
        $reasons[] = 'Your browser did not send the session cookie back. Check that cookies are not blocked '
                   . 'for this site, and that you are not switching between hostnames (for example '
                   . 'localhost and 127.0.0.1) between the login and the redirect.';
    }

    return implode(' ', $reasons);
}

// ─── Helper Functions ─────────────────────────────────────────────────────────
function h($str)
{
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

function get_post($key, $default = '')
{
    return $_POST[$key] ?? $default;
}

function get_get($key, $default = '')
{
    return $_GET[$key] ?? $default;
}

function is_ajax()
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
        || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
}

function json_out($data, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function format_bytes($bytes, $precision = 2)
{
    $bytes = (float)$bytes;
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $pow = min((int)floor(log($bytes, 1024)), count($units) - 1);
    $val = $bytes / pow(1024, $pow);
    // Whole bytes never need decimals; large units read better with them.
    return ($pow === 0 ? (string)(int)$val : number_format($val, $precision)) . ' ' . $units[$pow];
}

function format_num($n)
{
    return number_format((float)$n);
}

/** Truncate for display without splitting a multibyte character. */
function truncate_cell($str, $len = 120)
{
    if (mb_strlen($str) <= $len) return ['text' => $str, 'truncated' => false];
    return ['text' => mb_substr($str, 0, $len), 'truncated' => true];
}

function data_dir()
{
    $dir = DABIRO_DATA_DIR;
    if (!is_dir($dir)) {
        @mkdir($dir, 0700, true);
    }
    // Belt and braces: if this ever lands inside a web root, deny direct access.
    if (is_dir($dir) && !file_exists($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
    }
    return is_dir($dir) && is_writable($dir) ? $dir : null;
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
    return !empty($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], (string)$token);
}

function get_csrf_token()
{
    return generate_csrf_token();
}

/** Guard a mutating request; on failure sets $error_message and returns false. */
function require_csrf($token)
{
    global $error_message;
    if (validate_csrf_token($token)) return true;
    $error_message = 'Security token validation failed. Please reload the page and try again.';
    return false;
}

// ─── Translations System ──────────────────────────────────────────────────────
// Only 'en' carries strings today; every other language falls back to English via
// __(). Adding a language means adding its key set here - no other code changes.
$TRANSLATIONS = [
    'en' => [
        'app_name' => 'Dabiro',
        'app_tagline' => 'Professional Database Management Interface',
        'database_type_label' => 'Database Engine',
        'host_label' => 'Host / Server',
        'port_label' => 'Port',
        'username_label' => 'Username',
        'password_label' => 'Password',
        'database_name_label' => 'Database',
        'ssl_label' => 'Require SSL / TLS Encryption',
        'connect_button' => 'Connect',
        'connect_uri_label' => 'Connection URL',
        'saved_connections' => 'Saved Connections',
        'logout' => 'Disconnect',
        'databases' => 'Databases',
        'schemas' => 'Schemas',
        'schema' => 'Schema',
        'tables' => 'Tables',
        'views' => 'Views',
        'browse' => 'Browse',
        'structure' => 'Structure',
        'sql_console' => 'SQL',
        'import_data' => 'Import',
        'export_data' => 'Export',
        'global_search' => 'Search',
        'operations' => 'Operations',
        'create_database' => 'Create Database',
        'create_table' => 'Create Table',
        'table_name' => 'Table',
        'columns' => 'Columns',
        'indexes' => 'Indexes',
        'foreign_keys' => 'Foreign Keys',
        'add_column' => 'Add Column',
        'add_index' => 'Add Index',
        'add_condition' => 'Add Condition',
        'rename_table' => 'Rename Table',
        'copy_table' => 'Copy Table',
        'drop' => 'Drop',
        'truncate' => 'Empty',
        'drop_selected' => 'Drop Selected',
        'truncate_selected' => 'Empty Selected',
        'insert_record' => 'Insert Row',
        'edit_record' => 'Edit Row',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'search' => 'Search',
        'filter' => 'Filter',
        'clear' => 'Clear',
        'total_size' => 'Size',
        'data_size' => 'Data',
        'index_size' => 'Index',
        'overhead' => 'Overhead',
        'engine' => 'Engine',
        'collation' => 'Collation',
        'actions' => 'Actions',
        'query_results' => 'Results',
        'execution_time' => 'Time',
        'rows' => 'rows',
        'records' => 'Rows',
        'server' => 'Server',
        'total_records' => 'rows',
        'page' => 'page',
        'rows_per_page' => 'Per page',
        'recent_queries' => 'History',
        'execute_query' => 'Run',
        'export_query' => 'Save .sql',
        'back_to_table' => 'Back',
        'select_database' => 'Database',
        'export_format' => 'Format',
        'download_database' => 'Download',
        'export_entire_database' => 'Export Database',
    ],
    'es' => [
        'app_name' => 'Dabiro',
        'app_tagline' => 'Interfaz profesional de gestión de bases de datos',
        'database_type_label' => 'Motor de base de datos',
        'host_label' => 'Host / Servidor',
        'port_label' => 'Puerto',
        'username_label' => 'Usuario',
        'password_label' => 'Contraseña',
        'database_name_label' => 'Base de datos',
        'ssl_label' => 'Requerir cifrado SSL / TLS',
        'connect_button' => 'Conectar',
        'connect_uri_label' => 'URL de conexión',
        'saved_connections' => 'Conexiones guardadas',
        'logout' => 'Desconectar',
        'databases' => 'Bases de datos',
        'schemas' => 'Esquemas',
        'schema' => 'Esquema',
        'tables' => 'Tablas',
        'views' => 'Vistas',
        'browse' => 'Explorar',
        'structure' => 'Estructura',
        'sql_console' => 'SQL',
        'import_data' => 'Importar',
        'export_data' => 'Exportar',
        'global_search' => 'Buscar',
        'operations' => 'Operaciones',
        'create_database' => 'Crear base de datos',
        'create_table' => 'Crear tabla',
        'table_name' => 'Tabla',
        'columns' => 'Columnas',
        'indexes' => 'Índices',
        'foreign_keys' => 'Claves foráneas',
        'add_column' => 'Añadir columna',
        'add_index' => 'Añadir índice',
        'add_condition' => 'Añadir condición',
        'rename_table' => 'Renombrar tabla',
        'copy_table' => 'Copiar tabla',
        'drop' => 'Eliminar',
        'truncate' => 'Vaciar',
        'drop_selected' => 'Eliminar seleccionadas',
        'truncate_selected' => 'Vaciar seleccionadas',
        'insert_record' => 'Insertar fila',
        'edit_record' => 'Editar fila',
        'save' => 'Guardar',
        'cancel' => 'Cancelar',
        'search' => 'Buscar',
        'filter' => 'Filtrar',
        'clear' => 'Limpiar',
        'total_size' => 'Tamaño',
        'data_size' => 'Datos',
        'index_size' => 'Índice',
        'overhead' => 'Sobrecarga',
        'engine' => 'Motor',
        'collation' => 'Cotejamiento',
        'actions' => 'Acciones',
        'query_results' => 'Resultados',
        'execution_time' => 'Tiempo',
        'rows' => 'filas',
        'records' => 'Filas',
        'server' => 'Servidor',
        'total_records' => 'filas',
        'page' => 'página',
        'rows_per_page' => 'Por página',
        'recent_queries' => 'Historial',
        'execute_query' => 'Ejecutar',
        'export_query' => 'Guardar .sql',
        'back_to_table' => 'Volver',
        'select_database' => 'Base de datos',
        'export_format' => 'Formato',
        'download_database' => 'Descargar',
        'export_entire_database' => 'Exportar base de datos',
    ],
    'fr' => [
        'app_name' => 'Dabiro',
        'app_tagline' => 'Interface professionnelle de gestion de bases de données',
        'database_type_label' => 'Moteur de base de données',
        'host_label' => 'Hôte / Serveur',
        'port_label' => 'Port',
        'username_label' => 'Utilisateur',
        'password_label' => 'Mot de passe',
        'database_name_label' => 'Base de données',
        'ssl_label' => 'Exiger le chiffrement SSL / TLS',
        'connect_button' => 'Se connecter',
        'connect_uri_label' => 'URL de connexion',
        'saved_connections' => 'Connexions enregistrées',
        'logout' => 'Déconnecter',
        'databases' => 'Bases de données',
        'schemas' => 'Schémas',
        'schema' => 'Schéma',
        'tables' => 'Tables',
        'views' => 'Vues',
        'browse' => 'Parcourir',
        'structure' => 'Structure',
        'sql_console' => 'SQL',
        'import_data' => 'Importer',
        'export_data' => 'Exporter',
        'global_search' => 'Rechercher',
        'operations' => 'Opérations',
        'create_database' => 'Créer une base de données',
        'create_table' => 'Créer une table',
        'table_name' => 'Table',
        'columns' => 'Colonnes',
        'indexes' => 'Index',
        'foreign_keys' => 'Clés étrangères',
        'add_column' => 'Ajouter une colonne',
        'add_index' => 'Ajouter un index',
        'add_condition' => 'Ajouter une condition',
        'rename_table' => 'Renommer la table',
        'copy_table' => 'Copier la table',
        'drop' => 'Supprimer',
        'truncate' => 'Vider',
        'drop_selected' => 'Supprimer la sélection',
        'truncate_selected' => 'Vider la sélection',
        'insert_record' => 'Insérer une ligne',
        'edit_record' => 'Modifier la ligne',
        'save' => 'Enregistrer',
        'cancel' => 'Annuler',
        'search' => 'Rechercher',
        'filter' => 'Filtrer',
        'clear' => 'Effacer',
        'total_size' => 'Taille',
        'data_size' => 'Données',
        'index_size' => 'Index',
        'overhead' => 'Surcharge',
        'engine' => 'Moteur',
        'collation' => 'Interclassement',
        'actions' => 'Actions',
        'query_results' => 'Résultats',
        'execution_time' => 'Temps',
        'rows' => 'lignes',
        'records' => 'Lignes',
        'server' => 'Serveur',
        'total_records' => 'lignes',
        'page' => 'page',
        'rows_per_page' => 'Par page',
        'recent_queries' => 'Historique',
        'execute_query' => 'Exécuter',
        'export_query' => 'Enregistrer .sql',
        'back_to_table' => 'Retour',
        'select_database' => 'Base de données',
        'export_format' => 'Format',
        'download_database' => 'Télécharger',
        'export_entire_database' => 'Exporter la base de données',
    ],
    'de' => [
        'app_name' => 'Dabiro',
        'app_tagline' => 'Professionelle Datenbankverwaltung',
        'database_type_label' => 'Datenbank-Engine',
        'host_label' => 'Host / Server',
        'port_label' => 'Port',
        'username_label' => 'Benutzername',
        'password_label' => 'Passwort',
        'database_name_label' => 'Datenbank',
        'ssl_label' => 'SSL-/TLS-Verschlüsselung erforderlich',
        'connect_button' => 'Verbinden',
        'connect_uri_label' => 'Verbindungs-URL',
        'saved_connections' => 'Gespeicherte Verbindungen',
        'logout' => 'Trennen',
        'databases' => 'Datenbanken',
        'schemas' => 'Schemas',
        'schema' => 'Schema',
        'tables' => 'Tabellen',
        'views' => 'Views',
        'browse' => 'Anzeigen',
        'structure' => 'Struktur',
        'sql_console' => 'SQL',
        'import_data' => 'Importieren',
        'export_data' => 'Exportieren',
        'global_search' => 'Suchen',
        'operations' => 'Operationen',
        'create_database' => 'Datenbank erstellen',
        'create_table' => 'Tabelle erstellen',
        'table_name' => 'Tabelle',
        'columns' => 'Spalten',
        'indexes' => 'Indizes',
        'foreign_keys' => 'Fremdschlüssel',
        'add_column' => 'Spalte hinzufügen',
        'add_index' => 'Index hinzufügen',
        'add_condition' => 'Bedingung hinzufügen',
        'rename_table' => 'Tabelle umbenennen',
        'copy_table' => 'Tabelle kopieren',
        'drop' => 'Löschen',
        'truncate' => 'Leeren',
        'drop_selected' => 'Auswahl löschen',
        'truncate_selected' => 'Auswahl leeren',
        'insert_record' => 'Zeile einfügen',
        'edit_record' => 'Zeile bearbeiten',
        'save' => 'Speichern',
        'cancel' => 'Abbrechen',
        'search' => 'Suchen',
        'filter' => 'Filtern',
        'clear' => 'Zurücksetzen',
        'total_size' => 'Größe',
        'data_size' => 'Daten',
        'index_size' => 'Index',
        'overhead' => 'Overhead',
        'engine' => 'Engine',
        'collation' => 'Kollation',
        'actions' => 'Aktionen',
        'query_results' => 'Ergebnisse',
        'execution_time' => 'Zeit',
        'rows' => 'Zeilen',
        'records' => 'Zeilen',
        'server' => 'Server',
        'total_records' => 'Zeilen',
        'page' => 'Seite',
        'rows_per_page' => 'Pro Seite',
        'recent_queries' => 'Verlauf',
        'execute_query' => 'Ausführen',
        'export_query' => '.sql speichern',
        'back_to_table' => 'Zurück',
        'select_database' => 'Datenbank',
        'export_format' => 'Format',
        'download_database' => 'Herunterladen',
        'export_entire_database' => 'Datenbank exportieren',
    ],
    'pt' => [
        'app_name' => 'Dabiro',
        'app_tagline' => 'Interface profissional de gerenciamento de banco de dados',
        'database_type_label' => 'Motor de banco de dados',
        'host_label' => 'Host / Servidor',
        'port_label' => 'Porta',
        'username_label' => 'Usuário',
        'password_label' => 'Senha',
        'database_name_label' => 'Banco de dados',
        'ssl_label' => 'Exigir criptografia SSL / TLS',
        'connect_button' => 'Conectar',
        'connect_uri_label' => 'URL de conexão',
        'saved_connections' => 'Conexões salvas',
        'logout' => 'Desconectar',
        'databases' => 'Bancos de dados',
        'schemas' => 'Esquemas',
        'schema' => 'Esquema',
        'tables' => 'Tabelas',
        'views' => 'Visões',
        'browse' => 'Navegar',
        'structure' => 'Estrutura',
        'sql_console' => 'SQL',
        'import_data' => 'Importar',
        'export_data' => 'Exportar',
        'global_search' => 'Pesquisar',
        'operations' => 'Operações',
        'create_database' => 'Criar banco de dados',
        'create_table' => 'Criar tabela',
        'table_name' => 'Tabela',
        'columns' => 'Colunas',
        'indexes' => 'Índices',
        'foreign_keys' => 'Chaves estrangeiras',
        'add_column' => 'Adicionar coluna',
        'add_index' => 'Adicionar índice',
        'add_condition' => 'Adicionar condição',
        'rename_table' => 'Renomear tabela',
        'copy_table' => 'Copiar tabela',
        'drop' => 'Excluir',
        'truncate' => 'Esvaziar',
        'drop_selected' => 'Excluir selecionadas',
        'truncate_selected' => 'Esvaziar selecionadas',
        'insert_record' => 'Inserir linha',
        'edit_record' => 'Editar linha',
        'save' => 'Salvar',
        'cancel' => 'Cancelar',
        'search' => 'Pesquisar',
        'filter' => 'Filtrar',
        'clear' => 'Limpar',
        'total_size' => 'Tamanho',
        'data_size' => 'Dados',
        'index_size' => 'Índice',
        'overhead' => 'Sobrecarga',
        'engine' => 'Motor',
        'collation' => 'Collation',
        'actions' => 'Ações',
        'query_results' => 'Resultados',
        'execution_time' => 'Tempo',
        'rows' => 'linhas',
        'records' => 'Linhas',
        'server' => 'Servidor',
        'total_records' => 'linhas',
        'page' => 'página',
        'rows_per_page' => 'Por página',
        'recent_queries' => 'Histórico',
        'execute_query' => 'Executar',
        'export_query' => 'Salvar .sql',
        'back_to_table' => 'Voltar',
        'select_database' => 'Banco de dados',
        'export_format' => 'Formato',
        'download_database' => 'Baixar',
        'export_entire_database' => 'Exportar banco de dados',
    ],
    'zh' => [
        'app_name' => 'Dabiro',
        'app_tagline' => '专业数据库管理界面',
        'database_type_label' => '数据库引擎',
        'host_label' => '主机 / 服务器',
        'port_label' => '端口',
        'username_label' => '用户名',
        'password_label' => '密码',
        'database_name_label' => '数据库',
        'ssl_label' => '要求 SSL / TLS 加密',
        'connect_button' => '连接',
        'connect_uri_label' => '连接 URL',
        'saved_connections' => '已保存的连接',
        'logout' => '断开连接',
        'databases' => '数据库',
        'schemas' => '模式',
        'schema' => '模式',
        'tables' => '表',
        'views' => '视图',
        'browse' => '浏览',
        'structure' => '结构',
        'sql_console' => 'SQL',
        'import_data' => '导入',
        'export_data' => '导出',
        'global_search' => '搜索',
        'operations' => '操作',
        'create_database' => '创建数据库',
        'create_table' => '创建表',
        'table_name' => '表',
        'columns' => '列',
        'indexes' => '索引',
        'foreign_keys' => '外键',
        'add_column' => '添加列',
        'add_index' => '添加索引',
        'add_condition' => '添加条件',
        'rename_table' => '重命名表',
        'copy_table' => '复制表',
        'drop' => '删除',
        'truncate' => '清空',
        'drop_selected' => '删除所选',
        'truncate_selected' => '清空所选',
        'insert_record' => '插入行',
        'edit_record' => '编辑行',
        'save' => '保存',
        'cancel' => '取消',
        'search' => '搜索',
        'filter' => '筛选',
        'clear' => '清除',
        'total_size' => '大小',
        'data_size' => '数据',
        'index_size' => '索引',
        'overhead' => '开销',
        'engine' => '引擎',
        'collation' => '排序规则',
        'actions' => '操作',
        'query_results' => '结果',
        'execution_time' => '耗时',
        'rows' => '行',
        'records' => '行数',
        'server' => '服务器',
        'total_records' => '行',
        'page' => '页',
        'rows_per_page' => '每页',
        'recent_queries' => '历史',
        'execute_query' => '执行',
        'export_query' => '保存 .sql',
        'back_to_table' => '返回',
        'select_database' => '数据库',
        'export_format' => '格式',
        'download_database' => '下载',
        'export_entire_database' => '导出数据库',
    ],
    'ja' => [
        'app_name' => 'Dabiro',
        'app_tagline' => 'プロフェッショナルなデータベース管理インターフェース',
        'database_type_label' => 'データベースエンジン',
        'host_label' => 'ホスト / サーバー',
        'port_label' => 'ポート',
        'username_label' => 'ユーザー名',
        'password_label' => 'パスワード',
        'database_name_label' => 'データベース',
        'ssl_label' => 'SSL / TLS 暗号化を必須にする',
        'connect_button' => '接続',
        'connect_uri_label' => '接続 URL',
        'saved_connections' => '保存された接続',
        'logout' => '切断',
        'databases' => 'データベース',
        'schemas' => 'スキーマ',
        'schema' => 'スキーマ',
        'tables' => 'テーブル',
        'views' => 'ビュー',
        'browse' => '参照',
        'structure' => '構造',
        'sql_console' => 'SQL',
        'import_data' => 'インポート',
        'export_data' => 'エクスポート',
        'global_search' => '検索',
        'operations' => '操作',
        'create_database' => 'データベースを作成',
        'create_table' => 'テーブルを作成',
        'table_name' => 'テーブル',
        'columns' => 'カラム',
        'indexes' => 'インデックス',
        'foreign_keys' => '外部キー',
        'add_column' => 'カラムを追加',
        'add_index' => 'インデックスを追加',
        'add_condition' => '条件を追加',
        'rename_table' => 'テーブル名を変更',
        'copy_table' => 'テーブルをコピー',
        'drop' => '削除',
        'truncate' => '空にする',
        'drop_selected' => '選択項目を削除',
        'truncate_selected' => '選択項目を空にする',
        'insert_record' => '行を挿入',
        'edit_record' => '行を編集',
        'save' => '保存',
        'cancel' => 'キャンセル',
        'search' => '検索',
        'filter' => 'フィルター',
        'clear' => 'クリア',
        'total_size' => 'サイズ',
        'data_size' => 'データ',
        'index_size' => 'インデックス',
        'overhead' => 'オーバーヘッド',
        'engine' => 'エンジン',
        'collation' => '照合順序',
        'actions' => '操作',
        'query_results' => '結果',
        'execution_time' => '時間',
        'rows' => '行',
        'records' => '行',
        'server' => 'サーバー',
        'total_records' => '行',
        'page' => 'ページ',
        'rows_per_page' => '表示件数',
        'recent_queries' => '履歴',
        'execute_query' => '実行',
        'export_query' => '.sql を保存',
        'back_to_table' => '戻る',
        'select_database' => 'データベース',
        'export_format' => '形式',
        'download_database' => 'ダウンロード',
        'export_entire_database' => 'データベースをエクスポート',
    ],
    'ar' => [
        'app_name' => 'Dabiro',
        'app_tagline' => 'واجهة احترافية لإدارة قواعد البيانات',
        'database_type_label' => 'محرك قاعدة البيانات',
        'host_label' => 'المضيف / الخادم',
        'port_label' => 'المنفذ',
        'username_label' => 'اسم المستخدم',
        'password_label' => 'كلمة المرور',
        'database_name_label' => 'قاعدة البيانات',
        'ssl_label' => 'طلب تشفير SSL / TLS',
        'connect_button' => 'اتصال',
        'connect_uri_label' => 'رابط الاتصال',
        'saved_connections' => 'الاتصالات المحفوظة',
        'logout' => 'قطع الاتصال',
        'databases' => 'قواعد البيانات',
        'schemas' => 'المخططات',
        'schema' => 'المخطط',
        'tables' => 'الجداول',
        'views' => 'العروض',
        'browse' => 'استعراض',
        'structure' => 'البنية',
        'sql_console' => 'SQL',
        'import_data' => 'استيراد',
        'export_data' => 'تصدير',
        'global_search' => 'بحث',
        'operations' => 'العمليات',
        'create_database' => 'إنشاء قاعدة بيانات',
        'create_table' => 'إنشاء جدول',
        'table_name' => 'الجدول',
        'columns' => 'الأعمدة',
        'indexes' => 'الفهارس',
        'foreign_keys' => 'المفاتيح الأجنبية',
        'add_column' => 'إضافة عمود',
        'add_index' => 'إضافة فهرس',
        'add_condition' => 'إضافة شرط',
        'rename_table' => 'إعادة تسمية الجدول',
        'copy_table' => 'نسخ الجدول',
        'drop' => 'حذف',
        'truncate' => 'إفراغ',
        'drop_selected' => 'حذف المحدد',
        'truncate_selected' => 'إفراغ المحدد',
        'insert_record' => 'إدراج صف',
        'edit_record' => 'تعديل الصف',
        'save' => 'حفظ',
        'cancel' => 'إلغاء',
        'search' => 'بحث',
        'filter' => 'تصفية',
        'clear' => 'مسح',
        'total_size' => 'الحجم',
        'data_size' => 'البيانات',
        'index_size' => 'الفهرس',
        'overhead' => 'العبء الزائد',
        'engine' => 'المحرك',
        'collation' => 'الترتيب',
        'actions' => 'الإجراءات',
        'query_results' => 'النتائج',
        'execution_time' => 'الوقت',
        'rows' => 'صفوف',
        'records' => 'الصفوف',
        'server' => 'الخادم',
        'total_records' => 'صفوف',
        'page' => 'صفحة',
        'rows_per_page' => 'لكل صفحة',
        'recent_queries' => 'السجل',
        'execute_query' => 'تنفيذ',
        'export_query' => 'حفظ .sql',
        'back_to_table' => 'رجوع',
        'select_database' => 'قاعدة البيانات',
        'export_format' => 'الصيغة',
        'download_database' => 'تنزيل',
        'export_entire_database' => 'تصدير قاعدة البيانات',
    ],
    'it' => [
        'app_name' => 'Dabiro',
        'app_tagline' => 'Interfaccia professionale per la gestione dei database',
        'database_type_label' => 'Motore del database',
        'host_label' => 'Host / Server',
        'port_label' => 'Porta',
        'username_label' => 'Nome utente',
        'password_label' => 'Password',
        'database_name_label' => 'Database',
        'ssl_label' => 'Richiedi crittografia SSL / TLS',
        'connect_button' => 'Connetti',
        'connect_uri_label' => 'URL di connessione',
        'saved_connections' => 'Connessioni salvate',
        'logout' => 'Disconnetti',
        'databases' => 'Database',
        'schemas' => 'Schemi',
        'schema' => 'Schema',
        'tables' => 'Tabelle',
        'views' => 'Viste',
        'browse' => 'Sfoglia',
        'structure' => 'Struttura',
        'sql_console' => 'SQL',
        'import_data' => 'Importa',
        'export_data' => 'Esporta',
        'global_search' => 'Cerca',
        'operations' => 'Operazioni',
        'create_database' => 'Crea database',
        'create_table' => 'Crea tabella',
        'table_name' => 'Tabella',
        'columns' => 'Colonne',
        'indexes' => 'Indici',
        'foreign_keys' => 'Chiavi esterne',
        'add_column' => 'Aggiungi colonna',
        'add_index' => 'Aggiungi indice',
        'add_condition' => 'Aggiungi condizione',
        'rename_table' => 'Rinomina tabella',
        'copy_table' => 'Copia tabella',
        'drop' => 'Elimina',
        'truncate' => 'Svuota',
        'drop_selected' => 'Elimina selezionate',
        'truncate_selected' => 'Svuota selezionate',
        'insert_record' => 'Inserisci riga',
        'edit_record' => 'Modifica riga',
        'save' => 'Salva',
        'cancel' => 'Annulla',
        'search' => 'Cerca',
        'filter' => 'Filtra',
        'clear' => 'Cancella',
        'total_size' => 'Dimensione',
        'data_size' => 'Dati',
        'index_size' => 'Indice',
        'overhead' => 'Overhead',
        'engine' => 'Motore',
        'collation' => 'Collation',
        'actions' => 'Azioni',
        'query_results' => 'Risultati',
        'execution_time' => 'Tempo',
        'rows' => 'righe',
        'records' => 'Righe',
        'server' => 'Server',
        'total_records' => 'righe',
        'page' => 'pagina',
        'rows_per_page' => 'Per pagina',
        'recent_queries' => 'Cronologia',
        'execute_query' => 'Esegui',
        'export_query' => 'Salva .sql',
        'back_to_table' => 'Indietro',
        'select_database' => 'Database',
        'export_format' => 'Formato',
        'download_database' => 'Scarica',
        'export_entire_database' => 'Esporta database',
    ],
    'ru' => [
        'app_name' => 'Dabiro',
        'app_tagline' => 'Профессиональный интерфейс управления базами данных',
        'database_type_label' => 'Движок базы данных',
        'host_label' => 'Хост / Сервер',
        'port_label' => 'Порт',
        'username_label' => 'Имя пользователя',
        'password_label' => 'Пароль',
        'database_name_label' => 'База данных',
        'ssl_label' => 'Требовать шифрование SSL / TLS',
        'connect_button' => 'Подключиться',
        'connect_uri_label' => 'URL подключения',
        'saved_connections' => 'Сохранённые подключения',
        'logout' => 'Отключиться',
        'databases' => 'Базы данных',
        'schemas' => 'Схемы',
        'schema' => 'Схема',
        'tables' => 'Таблицы',
        'views' => 'Представления',
        'browse' => 'Обзор',
        'structure' => 'Структура',
        'sql_console' => 'SQL',
        'import_data' => 'Импорт',
        'export_data' => 'Экспорт',
        'global_search' => 'Поиск',
        'operations' => 'Операции',
        'create_database' => 'Создать базу данных',
        'create_table' => 'Создать таблицу',
        'table_name' => 'Таблица',
        'columns' => 'Столбцы',
        'indexes' => 'Индексы',
        'foreign_keys' => 'Внешние ключи',
        'add_column' => 'Добавить столбец',
        'add_index' => 'Добавить индекс',
        'add_condition' => 'Добавить условие',
        'rename_table' => 'Переименовать таблицу',
        'copy_table' => 'Копировать таблицу',
        'drop' => 'Удалить',
        'truncate' => 'Очистить',
        'drop_selected' => 'Удалить выбранные',
        'truncate_selected' => 'Очистить выбранные',
        'insert_record' => 'Вставить строку',
        'edit_record' => 'Изменить строку',
        'save' => 'Сохранить',
        'cancel' => 'Отмена',
        'search' => 'Поиск',
        'filter' => 'Фильтр',
        'clear' => 'Сбросить',
        'total_size' => 'Размер',
        'data_size' => 'Данные',
        'index_size' => 'Индекс',
        'overhead' => 'Накладные расходы',
        'engine' => 'Движок',
        'collation' => 'Сравнение',
        'actions' => 'Действия',
        'query_results' => 'Результаты',
        'execution_time' => 'Время',
        'rows' => 'строк',
        'records' => 'Строки',
        'server' => 'Сервер',
        'total_records' => 'строк',
        'page' => 'страница',
        'rows_per_page' => 'На странице',
        'recent_queries' => 'История',
        'execute_query' => 'Выполнить',
        'export_query' => 'Сохранить .sql',
        'back_to_table' => 'Назад',
        'select_database' => 'База данных',
        'export_format' => 'Формат',
        'download_database' => 'Скачать',
        'export_entire_database' => 'Экспорт базы данных',
    ],
    'tr' => [
        'app_name' => 'Dabiro',
        'app_tagline' => 'Profesyonel veritabanı yönetim arayüzü',
        'database_type_label' => 'Veritabanı motoru',
        'host_label' => 'Sunucu / Host',
        'port_label' => 'Bağlantı noktası',
        'username_label' => 'Kullanıcı adı',
        'password_label' => 'Parola',
        'database_name_label' => 'Veritabanı',
        'ssl_label' => 'SSL / TLS şifrelemesi iste',
        'connect_button' => 'Bağlan',
        'connect_uri_label' => 'Bağlantı URL\'si',
        'saved_connections' => 'Kayıtlı bağlantılar',
        'logout' => 'Bağlantıyı kes',
        'databases' => 'Veritabanları',
        'schemas' => 'Şemalar',
        'schema' => 'Şema',
        'tables' => 'Tablolar',
        'views' => 'Görünümler',
        'browse' => 'Gözat',
        'structure' => 'Yapı',
        'sql_console' => 'SQL',
        'import_data' => 'İçe aktar',
        'export_data' => 'Dışa aktar',
        'global_search' => 'Ara',
        'operations' => 'İşlemler',
        'create_database' => 'Veritabanı oluştur',
        'create_table' => 'Tablo oluştur',
        'table_name' => 'Tablo',
        'columns' => 'Sütunlar',
        'indexes' => 'Dizinler',
        'foreign_keys' => 'Yabancı anahtarlar',
        'add_column' => 'Sütun ekle',
        'add_index' => 'Dizin ekle',
        'add_condition' => 'Koşul ekle',
        'rename_table' => 'Tabloyu yeniden adlandır',
        'copy_table' => 'Tabloyu kopyala',
        'drop' => 'Sil',
        'truncate' => 'Boşalt',
        'drop_selected' => 'Seçilenleri sil',
        'truncate_selected' => 'Seçilenleri boşalt',
        'insert_record' => 'Satır ekle',
        'edit_record' => 'Satırı düzenle',
        'save' => 'Kaydet',
        'cancel' => 'İptal',
        'search' => 'Ara',
        'filter' => 'Filtrele',
        'clear' => 'Temizle',
        'total_size' => 'Boyut',
        'data_size' => 'Veri',
        'index_size' => 'Dizin',
        'overhead' => 'Ek yük',
        'engine' => 'Motor',
        'collation' => 'Karşılaştırma',
        'actions' => 'İşlemler',
        'query_results' => 'Sonuçlar',
        'execution_time' => 'Süre',
        'rows' => 'satır',
        'records' => 'Satırlar',
        'server' => 'Sunucu',
        'total_records' => 'satır',
        'page' => 'sayfa',
        'rows_per_page' => 'Sayfa başına',
        'recent_queries' => 'Geçmiş',
        'execute_query' => 'Çalıştır',
        'export_query' => '.sql kaydet',
        'back_to_table' => 'Geri',
        'select_database' => 'Veritabanı',
        'export_format' => 'Biçim',
        'download_database' => 'İndir',
        'export_entire_database' => 'Veritabanını dışa aktar',
    ],
    'hi' => [
        'app_name' => 'Dabiro',
        'app_tagline' => 'पेशेवर डेटाबेस प्रबंधन इंटरफ़ेस',
        'database_type_label' => 'डेटाबेस इंजन',
        'host_label' => 'होस्ट / सर्वर',
        'port_label' => 'पोर्ट',
        'username_label' => 'उपयोगकर्ता नाम',
        'password_label' => 'पासवर्ड',
        'database_name_label' => 'डेटाबेस',
        'ssl_label' => 'SSL / TLS एन्क्रिप्शन आवश्यक करें',
        'connect_button' => 'कनेक्ट करें',
        'connect_uri_label' => 'कनेक्शन URL',
        'saved_connections' => 'सहेजे गए कनेक्शन',
        'logout' => 'डिस्कनेक्ट करें',
        'databases' => 'डेटाबेस',
        'schemas' => 'स्कीमा',
        'schema' => 'स्कीमा',
        'tables' => 'तालिकाएँ',
        'views' => 'व्यू',
        'browse' => 'ब्राउज़ करें',
        'structure' => 'संरचना',
        'sql_console' => 'SQL',
        'import_data' => 'आयात',
        'export_data' => 'निर्यात',
        'global_search' => 'खोजें',
        'operations' => 'संचालन',
        'create_database' => 'डेटाबेस बनाएँ',
        'create_table' => 'तालिका बनाएँ',
        'table_name' => 'तालिका',
        'columns' => 'कॉलम',
        'indexes' => 'इंडेक्स',
        'foreign_keys' => 'विदेशी कुंजियाँ',
        'add_column' => 'कॉलम जोड़ें',
        'add_index' => 'इंडेक्स जोड़ें',
        'add_condition' => 'शर्त जोड़ें',
        'rename_table' => 'तालिका का नाम बदलें',
        'copy_table' => 'तालिका कॉपी करें',
        'drop' => 'हटाएँ',
        'truncate' => 'खाली करें',
        'drop_selected' => 'चयनित हटाएँ',
        'truncate_selected' => 'चयनित खाली करें',
        'insert_record' => 'पंक्ति डालें',
        'edit_record' => 'पंक्ति संपादित करें',
        'save' => 'सहेजें',
        'cancel' => 'रद्द करें',
        'search' => 'खोजें',
        'filter' => 'फ़िल्टर',
        'clear' => 'साफ़ करें',
        'total_size' => 'आकार',
        'data_size' => 'डेटा',
        'index_size' => 'इंडेक्स',
        'overhead' => 'ओवरहेड',
        'engine' => 'इंजन',
        'collation' => 'कोलेशन',
        'actions' => 'क्रियाएँ',
        'query_results' => 'परिणाम',
        'execution_time' => 'समय',
        'rows' => 'पंक्तियाँ',
        'records' => 'पंक्तियाँ',
        'server' => 'सर्वर',
        'total_records' => 'पंक्तियाँ',
        'page' => 'पृष्ठ',
        'rows_per_page' => 'प्रति पृष्ठ',
        'recent_queries' => 'इतिहास',
        'execute_query' => 'चलाएँ',
        'export_query' => '.sql सहेजें',
        'back_to_table' => 'वापस',
        'select_database' => 'डेटाबेस',
        'export_format' => 'प्रारूप',
        'download_database' => 'डाउनलोड करें',
        'export_entire_database' => 'डेटाबेस निर्यात करें',
    ],
    'ko' => [
        'app_name' => 'Dabiro',
        'app_tagline' => '전문 데이터베이스 관리 인터페이스',
        'database_type_label' => '데이터베이스 엔진',
        'host_label' => '호스트 / 서버',
        'port_label' => '포트',
        'username_label' => '사용자 이름',
        'password_label' => '비밀번호',
        'database_name_label' => '데이터베이스',
        'ssl_label' => 'SSL / TLS 암호화 필요',
        'connect_button' => '연결',
        'connect_uri_label' => '연결 URL',
        'saved_connections' => '저장된 연결',
        'logout' => '연결 끊기',
        'databases' => '데이터베이스',
        'schemas' => '스키마',
        'schema' => '스키마',
        'tables' => '테이블',
        'views' => '뷰',
        'browse' => '찾아보기',
        'structure' => '구조',
        'sql_console' => 'SQL',
        'import_data' => '가져오기',
        'export_data' => '내보내기',
        'global_search' => '검색',
        'operations' => '작업',
        'create_database' => '데이터베이스 생성',
        'create_table' => '테이블 생성',
        'table_name' => '테이블',
        'columns' => '열',
        'indexes' => '인덱스',
        'foreign_keys' => '외래 키',
        'add_column' => '열 추가',
        'add_index' => '인덱스 추가',
        'add_condition' => '조건 추가',
        'rename_table' => '테이블 이름 변경',
        'copy_table' => '테이블 복사',
        'drop' => '삭제',
        'truncate' => '비우기',
        'drop_selected' => '선택 항목 삭제',
        'truncate_selected' => '선택 항목 비우기',
        'insert_record' => '행 삽입',
        'edit_record' => '행 편집',
        'save' => '저장',
        'cancel' => '취소',
        'search' => '검색',
        'filter' => '필터',
        'clear' => '지우기',
        'total_size' => '크기',
        'data_size' => '데이터',
        'index_size' => '인덱스',
        'overhead' => '오버헤드',
        'engine' => '엔진',
        'collation' => '데이터 정렬',
        'actions' => '작업',
        'query_results' => '결과',
        'execution_time' => '시간',
        'rows' => '행',
        'records' => '행',
        'server' => '서버',
        'total_records' => '행',
        'page' => '페이지',
        'rows_per_page' => '페이지당',
        'recent_queries' => '기록',
        'execute_query' => '실행',
        'export_query' => '.sql 저장',
        'back_to_table' => '뒤로',
        'select_database' => '데이터베이스',
        'export_format' => '형식',
        'download_database' => '다운로드',
        'export_entire_database' => '데이터베이스 내보내기',
    ],
];

$SUPPORTED_LANGS = [
    'en' => 'English', 'es' => 'Español', 'fr' => 'Français', 'de' => 'Deutsch',
    'pt' => 'Português', 'zh' => '中文', 'ja' => '日本語', 'ar' => 'العربية',
    'it' => 'Italiano', 'ru' => 'Русский', 'tr' => 'Türkçe', 'hi' => 'हिन्दी', 'ko' => '한국어',
];

$THEMES = [
    'light' => 'Light', 'dark' => 'Dark', 'slate' => 'Slate',
    'blue' => 'Blue', 'green' => 'Green', 'purple' => 'Purple', 'sunset' => 'Sunset',
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

// ─── SSH Tunnel Manager ───────────────────────────────────────────────────────
/**
 * Supervised replacement for the old fire-and-forget `ssh -f -N -L ...`.
 *
 * The previous implementation backgrounded ssh with -f, so the PID was never
 * known: the tunnel could not be health-checked, reused, or killed, and a
 * dropped connection surfaced only as an opaque "connection refused" from PDO.
 *
 * This version owns the child process:
 *   - launches detached via `setsid nohup ... & echo $!` so we capture the PID
 *   - waits for the forwarded port to actually accept a connection
 *   - records {pid, port, fingerprint} in a registry file
 *   - re-checks liveness before every database connection and silently
 *     re-establishes a tunnel that has died
 *   - reaps orphans and kills the tunnel on logout
 *
 * Password and key-passphrase auth go through SSH_ASKPASS, so no `sshpass`
 * (or anything else) has to be installed - on either end. The secret is written
 * to a 0600 file for the duration of the handshake instead of being passed on
 * the command line, where `ps` would expose it.
 */
class SshTunnel
{
    const READY_TIMEOUT = 20;   // seconds to wait for the forward to come up
    const ORPHAN_TTL    = 43200; // reap registry entries older than 12h

    /** Is tunnelling possible in this PHP environment? */
    public static function available(&$reason = null)
    {
        $disabled = array_map('trim', explode(',', (string)ini_get('disable_functions')));
        foreach (['shell_exec', 'exec'] as $fn) {
            if (in_array($fn, $disabled, true) || !function_exists($fn)) {
                $reason = "PHP function $fn() is disabled on this server, so Dabiro cannot start an SSH process.";
                return false;
            }
        }
        if (!self::sshBinary()) {
            $reason = 'The `ssh` client was not found on this server. Install openssh-client.';
            return false;
        }
        if (!data_dir()) {
            $reason = 'Dabiro has no writable data directory. Set DABIRO_DATA_DIR to a writable path.';
            return false;
        }
        return true;
    }

    public static function sshBinary()
    {
        foreach (['/usr/bin/ssh', '/bin/ssh', '/usr/local/bin/ssh'] as $p) {
            if (is_executable($p)) return $p;
        }
        $which = @shell_exec('command -v ssh 2>/dev/null');
        $which = trim((string)$which);
        return ($which && is_executable($which)) ? $which : null;
    }

    /**
     * Stable identity for a tunnel config, so we reuse instead of piling up.
     *
     * The credentials are part of the identity on purpose: without them a
     * connection attempt using a *different* (or wrong) password would happily
     * adopt an existing healthy tunnel and report success, never proving the new
     * credentials were valid at all.
     */
    public static function fingerprint(array $c)
    {
        $auth   = $c['auth'] ?? 'agent';
        $secret = '';
        if ($auth === 'password') {
            $secret = (string)($c['password'] ?? '');
        } elseif ($auth === 'key') {
            $secret = (string)($c['key'] ?? '') . "\0" . (string)($c['key_pass'] ?? '')
                    . "\0" . (!empty($c['key_is_path']) ? 'path' : 'inline');
        }
        return substr(hash('sha256', implode('|', [
            $c['host'] ?? '', $c['port'] ?? 22, $c['user'] ?? '',
            $c['target_host'] ?? '', $c['target_port'] ?? '',
            $auth, hash('sha256', $secret),
        ])), 0, 16);
    }

    private static function registryFile()
    {
        $d = data_dir();
        return $d ? $d . '/tunnels.json' : null;
    }

    private static function readRegistry()
    {
        $f = self::registryFile();
        if (!$f || !is_file($f)) return [];
        $raw = @file_get_contents($f);
        $data = $raw ? json_decode($raw, true) : [];
        return is_array($data) ? $data : [];
    }

    private static function writeRegistry(array $data)
    {
        $f = self::registryFile();
        if (!$f) return;
        $tmp = $f . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, json_encode($data)) !== false) {
            @chmod($tmp, 0600);
            @rename($tmp, $f);
        }
    }

    /** True when the process exists AND the forwarded port accepts connections. */
    public static function isAlive($pid, $port)
    {
        if (!$pid || !$port) return false;
        if (function_exists('posix_kill')) {
            if (!@posix_kill((int)$pid, 0)) return false;
        } elseif (!is_dir('/proc/' . (int)$pid)) {
            // No posix ext and no procfs - fall through to the socket probe alone.
        }
        $sock = @fsockopen('127.0.0.1', (int)$port, $errno, $errstr, 1.0);
        if (!$sock) return false;
        fclose($sock);
        return true;
    }

    /** Bind-test a port to be certain it is free before handing it to ssh. */
    private static function pickLocalPort($preferred = 0)
    {
        $try = function ($p) {
            $srv = @stream_socket_server("tcp://127.0.0.1:$p", $errno, $errstr);
            if (!$srv) return false;
            $name = stream_socket_get_name($srv, false);
            fclose($srv);
            return (int)substr($name, strrpos($name, ':') + 1);
        };
        if ($preferred > 0 && ($p = $try($preferred))) return $p;
        // Port 0 lets the OS hand us a guaranteed-free ephemeral port.
        for ($i = 0; $i < 10; $i++) {
            if ($p = $try(0)) return $p;
        }
        return 0;
    }

    /**
     * Establish a tunnel, reusing a healthy existing one when possible.
     * Returns ['ok'=>bool, 'port'=>int, 'pid'=>int, 'error'=>string, 'reused'=>bool]
     */
    public static function ensure(array $cfg)
    {
        if (!self::available($reason)) {
            return ['ok' => false, 'error' => $reason];
        }

        $fp  = self::fingerprint($cfg);
        $reg = self::readRegistry();
        self::reapOrphans($reg);

        if (isset($reg[$fp]) && self::isAlive($reg[$fp]['pid'] ?? 0, $reg[$fp]['port'] ?? 0)) {
            $reg[$fp]['seen'] = time();
            self::writeRegistry($reg);
            return ['ok' => true, 'port' => (int)$reg[$fp]['port'], 'pid' => (int)$reg[$fp]['pid'], 'reused' => true];
        }

        // Stale entry - make sure nothing is left running before replacing it.
        if (isset($reg[$fp])) {
            self::killPid($reg[$fp]['pid'] ?? 0);
            unset($reg[$fp]);
        }

        $res = self::spawn($cfg);
        if ($res['ok']) {
            $reg[$fp] = [
                'pid'    => $res['pid'],
                'port'   => $res['port'],
                'host'   => $cfg['host'] ?? '',
                'user'   => $cfg['user'] ?? '',
                'target' => ($cfg['target_host'] ?? '') . ':' . ($cfg['target_port'] ?? ''),
                'opened' => time(),
                'seen'   => time(),
            ];
            self::writeRegistry($reg);
        }
        return $res;
    }

    private static function spawn(array $cfg)
    {
        $dir = data_dir();
        $ssh = self::sshBinary();

        $sshHost = trim((string)($cfg['host'] ?? ''));
        $sshPort = (int)($cfg['port'] ?? 22) ?: 22;
        $sshUser = trim((string)($cfg['user'] ?? ''));
        $auth    = $cfg['auth'] ?? 'agent';
        $tHost   = trim((string)($cfg['target_host'] ?? '127.0.0.1')) ?: '127.0.0.1';
        $tPort   = (int)($cfg['target_port'] ?? 0);

        if ($sshHost === '') return ['ok' => false, 'error' => 'SSH host is required.'];
        if ($tPort <= 0)     return ['ok' => false, 'error' => 'A database port is required to build the tunnel.'];

        $lPort = self::pickLocalPort((int)($cfg['local_port'] ?? 0));
        if (!$lPort) return ['ok' => false, 'error' => 'Could not reserve a free local port for the tunnel.'];

        $token   = bin2hex(random_bytes(8));
        $logFile = $dir . "/tunnel_$token.log";
        $known   = $dir . '/known_hosts';
        @touch($known);
        @chmod($known, 0600);

        $env  = [];
        $args = [
            escapeshellarg($ssh),
            '-N',                                   // no remote command, forwarding only
            '-T',                                   // never allocate a TTY
            '-L', escapeshellarg("127.0.0.1:$lPort:$tHost:$tPort"),
            '-p', (string)$sshPort,
            '-o', escapeshellarg('ExitOnForwardFailure=yes'),
            '-o', escapeshellarg('StrictHostKeyChecking=accept-new'),
            '-o', escapeshellarg('UserKnownHostsFile=' . $known),
            '-o', escapeshellarg('ServerAliveInterval=15'),
            '-o', escapeshellarg('ServerAliveCountMax=3'),
            '-o', escapeshellarg('ConnectTimeout=10'),
            '-o', escapeshellarg('NumberOfPasswordPrompts=1'),
        ];

        $cleanup = [$logFile];

        if ($auth === 'key') {
            $keyFile = self::materialiseKey($cfg, $dir, $err);
            if (!$keyFile) return ['ok' => false, 'error' => $err];
            if (empty($cfg['key_is_path'])) $cleanup[] = $keyFile;
            $args[] = '-i';
            $args[] = escapeshellarg($keyFile);
            $args[] = '-o';
            $args[] = escapeshellarg('IdentitiesOnly=yes');
            if (($cfg['key_pass'] ?? '') !== '') {
                $ask = self::makeAskpass($cfg['key_pass'], $dir, $token);
                if (!$ask) return ['ok' => false, 'error' => 'Could not create the askpass helper.'];
                $cleanup[] = $ask['script'];
                $cleanup[] = $ask['secret'];
                $env += self::askpassEnv($ask['script']);
            } else {
                $args[] = '-o';
                $args[] = escapeshellarg('BatchMode=yes');
            }
        } elseif ($auth === 'password') {
            if (($cfg['password'] ?? '') === '') {
                return ['ok' => false, 'error' => 'SSH password is required.'];
            }
            $ask = self::makeAskpass($cfg['password'], $dir, $token);
            if (!$ask) return ['ok' => false, 'error' => 'Could not create the askpass helper.'];
            $cleanup[] = $ask['script'];
            $cleanup[] = $ask['secret'];
            $env += self::askpassEnv($ask['script']);
            $args[] = '-o';
            $args[] = escapeshellarg('PreferredAuthentications=password,keyboard-interactive');
            $args[] = '-o';
            $args[] = escapeshellarg('PubkeyAuthentication=no');
        } else {
            // 'agent' - reuse whatever ~/.ssh/config and ssh-agent already provide,
            // which is the closest match to running the ssh command by hand.
            $args[] = '-o';
            $args[] = escapeshellarg('BatchMode=yes');
        }

        $args[] = escapeshellarg(($sshUser !== '' ? $sshUser . '@' : '') . $sshHost);

        $envPrefix = '';
        foreach ($env as $k => $v) {
            $envPrefix .= $k . '=' . escapeshellarg($v) . ' ';
        }

        // setsid detaches from any controlling terminal, which is what makes ssh
        // fall back to SSH_ASKPASS instead of trying to read a password from a tty.
        $setsid = trim((string)@shell_exec('command -v setsid 2>/dev/null'));
        $prefix = ($setsid && is_executable($setsid)) ? escapeshellarg($setsid) . ' ' : '';

        $cmd = $envPrefix . $prefix . implode(' ', $args)
             . ' > ' . escapeshellarg($logFile) . ' 2>&1 & echo $!';

        $pid = (int)trim((string)@shell_exec($cmd));
        if ($pid <= 0) {
            self::cleanupFiles($cleanup);
            return ['ok' => false, 'error' => 'Failed to start the ssh process.'];
        }

        // Wait for the forward to actually accept traffic. `ssh -f` never did
        // this, which is why a failing tunnel used to look like a DB error.
        $deadline = microtime(true) + self::READY_TIMEOUT;
        $ready = false;
        while (microtime(true) < $deadline) {
            if (self::isAlive($pid, $lPort)) { $ready = true; break; }
            $stillRunning = function_exists('posix_kill') ? @posix_kill($pid, 0) : true;
            if (!$stillRunning) break;   // ssh exited - read the log for the reason
            usleep(200000);
        }

        $log = is_file($logFile) ? trim((string)@file_get_contents($logFile)) : '';

        if (!$ready) {
            self::killPid($pid);
            self::cleanupFiles($cleanup);
            return ['ok' => false, 'error' => self::explain($log, $auth)];
        }

        // Secrets are only needed for the handshake; remove them now.
        self::cleanupFiles(array_diff($cleanup, [$logFile]));
        @unlink($logFile);

        return ['ok' => true, 'pid' => $pid, 'port' => $lPort, 'reused' => false];
    }

    /** Turn ssh's stderr into something a human can act on. */
    private static function explain($log, $auth)
    {
        $log = trim((string)$log);
        $low = strtolower($log);
        $hint = null;

        if (strpos($low, 'permission denied') !== false) {
            $hint = $auth === 'agent'
                ? 'SSH refused the credentials. With "Use local SSH agent / config" the key must already be loaded for the PHP user - try key or password auth instead.'
                : 'SSH refused the credentials. Check the username, and the key or password.';
        } elseif (strpos($low, 'host key verification failed') !== false) {
            $hint = 'Host key verification failed. Remove the stale entry from ' . data_dir() . '/known_hosts and retry.';
        } elseif (strpos($low, 'could not resolve hostname') !== false) {
            $hint = 'The SSH hostname could not be resolved.';
        } elseif (strpos($low, 'connection refused') !== false) {
            $hint = 'The SSH server refused the connection - check the host and SSH port.';
        } elseif (strpos($low, 'connection timed out') !== false || strpos($low, 'operation timed out') !== false) {
            $hint = 'Timed out reaching the SSH server. A firewall may be blocking it.';
        } elseif (strpos($low, 'remote port forwarding failed') !== false
               || strpos($low, 'channel .* open failed') !== false
               || strpos($low, 'administratively prohibited') !== false) {
            $hint = 'The SSH server refused to open the forward. Confirm the database host/port are reachable from the bastion and that AllowTcpForwarding is enabled.';
        } elseif (strpos($low, 'bad configuration') !== false || strpos($low, 'unknown option') !== false) {
            $hint = 'This ssh client rejected an option Dabiro passed. Please report the log below.';
        } elseif ($log === '') {
            $hint = 'The tunnel did not come up and ssh reported nothing. The database port may not be reachable from the SSH host.';
        }

        $msg = 'SSH tunnel failed.';
        if ($hint) $msg .= ' ' . $hint;
        if ($log !== '') $msg .= "\n\n" . mb_substr($log, 0, 1200);
        return $msg;
    }

    private static function materialiseKey(array $cfg, $dir, &$err)
    {
        $err = null;
        $key = (string)($cfg['key'] ?? '');

        if (!empty($cfg['key_is_path'])) {
            $path = trim($key);
            if ($path === '' || !is_file($path)) { $err = "Private key file not found: $path"; return null; }
            if (!is_readable($path)) { $err = "Private key file is not readable by the web server user: $path"; return null; }
            return $path;
        }

        $key = trim($key);
        if ($key === '') { $err = 'A private key is required.'; return null; }
        // OpenSSH refuses keys whose final newline is missing.
        if (substr($key, -1) !== "\n") $key .= "\n";
        if (!preg_match('/-----BEGIN [A-Z ]*PRIVATE KEY-----/', $key)) {
            $err = 'That does not look like a private key (expected a -----BEGIN ... PRIVATE KEY----- block).';
            return null;
        }

        $file = $dir . '/key_' . bin2hex(random_bytes(8));
        if (@file_put_contents($file, $key) === false) { $err = 'Could not write the temporary key file.'; return null; }
        @chmod($file, 0600);
        return $file;
    }

    private static function makeAskpass($secret, $dir, $token)
    {
        $secretFile = $dir . "/ask_$token.secret";
        $scriptFile = $dir . "/ask_$token.sh";

        if (@file_put_contents($secretFile, (string)$secret) === false) return null;
        @chmod($secretFile, 0600);

        // The helper prints the secret and nothing else; ssh reads it on stdout.
        $script = "#!/bin/sh\ncat " . escapeshellarg($secretFile) . "\n";
        if (@file_put_contents($scriptFile, $script) === false) {
            @unlink($secretFile);
            return null;
        }
        @chmod($scriptFile, 0700);

        return ['script' => $scriptFile, 'secret' => $secretFile];
    }

    private static function askpassEnv($script)
    {
        return [
            'SSH_ASKPASS'         => $script,
            // OpenSSH >= 8.4 honours this and skips the tty entirely.
            'SSH_ASKPASS_REQUIRE' => 'force',
            // Older clients need DISPLAY set before they will use SSH_ASKPASS.
            'DISPLAY'             => getenv('DISPLAY') ?: ':0',
        ];
    }

    private static function cleanupFiles(array $files)
    {
        foreach ($files as $f) {
            if ($f && is_file($f)) @unlink($f);
        }
    }

    public static function killPid($pid)
    {
        $pid = (int)$pid;
        if ($pid <= 0) return;
        if (function_exists('posix_kill')) {
            @posix_kill($pid, 15);
            usleep(150000);
            if (@posix_kill($pid, 0)) @posix_kill($pid, 9);
        } else {
            @shell_exec('kill ' . $pid . ' 2>/dev/null');
        }
    }

    /** Drop dead or long-idle registry entries so the file cannot grow forever. */
    public static function reapOrphans(&$reg = null)
    {
        $own = ($reg === null);
        if ($own) $reg = self::readRegistry();
        $changed = false;
        foreach ($reg as $fp => $t) {
            $dead = !self::isAlive($t['pid'] ?? 0, $t['port'] ?? 0);
            $old  = (time() - (int)($t['seen'] ?? 0)) > self::ORPHAN_TTL;
            if ($dead || $old) {
                if ($old && !$dead) self::killPid($t['pid'] ?? 0);
                unset($reg[$fp]);
                $changed = true;
            }
        }
        if ($changed && $own) self::writeRegistry($reg);
        return $reg;
    }

    /** Tear down the tunnel belonging to this config (used on logout). */
    public static function close(array $cfg)
    {
        $fp  = self::fingerprint($cfg);
        $reg = self::readRegistry();
        if (isset($reg[$fp])) {
            self::killPid($reg[$fp]['pid'] ?? 0);
            unset($reg[$fp]);
            self::writeRegistry($reg);
        }
    }

    public static function status(array $cfg)
    {
        $fp  = self::fingerprint($cfg);
        $reg = self::readRegistry();
        if (!isset($reg[$fp])) return ['up' => false];
        $t = $reg[$fp];
        return [
            'up'     => self::isAlive($t['pid'] ?? 0, $t['port'] ?? 0),
            'port'   => (int)($t['port'] ?? 0),
            'pid'    => (int)($t['pid'] ?? 0),
            'target' => $t['target'] ?? '',
            'uptime' => time() - (int)($t['opened'] ?? time()),
        ];
    }
}

// ─── Database Connection Class ────────────────────────────────────────────────
/**
 * Thin driver abstraction over PDO for MySQL/MariaDB, PostgreSQL and SQLite.
 *
 * The central correctness fix over 1.x lives in selectDatabase(): the old code
 * ran `USE <db>` for every non-SQLite driver. PostgreSQL has no USE statement -
 * a connection is bound to one database for its lifetime - so every table
 * listing threw a syntax error. In getTables() that exception was uncaught and
 * killed the page render, which is why PostgreSQL showed no tables at all.
 * Switching databases on PostgreSQL now reconnects, which is the only way to do
 * it, and PostgreSQL schemas are first-class instead of hardcoded to 'public'.
 */
class DbConnection
{
    private $pdo = null;
    private $type = '';
    private $creds = [];          // kept so PostgreSQL can reconnect to switch database
    private $database = '';
    private $schema = '';
    private $serverVersion = null;

    /** Row-count estimates are used above this size instead of a slow COUNT(*). */
    const EXACT_COUNT_LIMIT = 250000;

    public function connect($type, $host, $user, $pass, $dbname = '', $port = '', $ssl = false)
    {
        $this->type = $type;
        $this->creds = compact('type', 'host', 'user', 'pass', 'port', 'ssl');
        $this->database = $dbname;

        // "host:port" in the host field wins over the separate port box.
        if ($type !== 'sqlite' && strpos($host, ':') !== false) {
            list($h, $prt) = explode(':', $host, 2);
            if (ctype_digit(trim($prt))) { $host = $h; $port = trim($prt); }
        }

        $opts = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ];

        try {
            switch ($type) {
                case 'mysql':
                    $p = $port ?: '3306';
                    $dsn = "mysql:host=$host;port=$p" . ($dbname ? ";dbname=$dbname" : '') . ";charset=utf8mb4";
                    $opts[PDO::ATTR_EMULATE_PREPARES] = false;
                    if ($ssl && defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                        $opts[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
                    }
                    $this->pdo = new PDO($dsn, $user, $pass, $opts);
                    break;

                case 'pgsql':
                    $p = $port ?: '5432';
                    $dsn = "pgsql:host=$host;port=$p;dbname=" . ($dbname ?: 'postgres');
                    if ($ssl) $dsn .= ';sslmode=require';
                    $this->pdo = new PDO($dsn, $user, $pass, $opts);
                    $this->database = $dbname ?: 'postgres';
                    $this->schema = $this->currentSchema();
                    break;

                case 'sqlite':
                    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
                        return 'The pdo_sqlite PHP extension is not installed on this server.';
                    }
                    if ($host !== ':memory:' && !is_file($host)) {
                        return "SQLite database file not found: $host";
                    }
                    $this->pdo = new PDO("sqlite:$host", null, null, $opts);
                    $this->pdo->exec('PRAGMA foreign_keys = ON');
                    $this->database = 'main';
                    break;

                default:
                    return 'Unsupported database type.';
            }
            return true;
        } catch (PDOException $e) {
            return $this->friendlyConnectError($e->getMessage());
        }
    }

    /** PDO connection errors are famously cryptic; add the likely cause. */
    private function friendlyConnectError($msg)
    {
        $low = strtolower($msg);
        $hint = '';
        if (strpos($low, 'connection refused') !== false) {
            $hint = ' Nothing is listening on that host and port. If the database is only reachable from a bastion, use the SSH Tunnel tab.';
        } elseif (strpos($low, 'access denied') !== false || strpos($low, 'password authentication failed') !== false) {
            $hint = ' The username or password was rejected by the database.';
        } elseif (strpos($low, 'unknown database') !== false || strpos($low, 'does not exist') !== false) {
            $hint = ' That database does not exist - leave the field blank to browse all databases.';
        } elseif (strpos($low, 'timed out') !== false || strpos($low, 'timeout') !== false) {
            $hint = ' The host did not respond. Check firewall rules, or tunnel through SSH.';
        } elseif (strpos($low, 'could not find driver') !== false) {
            $hint = ' The matching PDO driver is not installed in PHP.';
        } elseif (strpos($low, 'no such host') !== false || strpos($low, 'name or service not known') !== false) {
            $hint = ' The hostname could not be resolved.';
        }
        return $msg . $hint;
    }

    public function getPdo()      { return $this->pdo; }
    public function getType()     { return $this->type; }
    public function getDatabase() { return $this->database; }
    public function getSchema()   { return $this->schema; }
    public function isConnected() { return $this->pdo !== null; }

    public function serverVersion()
    {
        if ($this->serverVersion === null) {
            try {
                $this->serverVersion = (string)$this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
            } catch (Throwable $e) { $this->serverVersion = ''; }
        }
        return $this->serverVersion;
    }

    /** True for MariaDB/MySQL, where "schema" and "database" are the same thing. */
    public function schemasAreDatabases() { return $this->type !== 'pgsql'; }

    // ─── Identifier & value quoting ───────────────────────────────────────────
    public function quoteIdentifier($name)
    {
        $name = (string)$name;
        if ($this->type === 'mysql') return '`' . str_replace('`', '``', $name) . '`';
        return '"' . str_replace('"', '""', $name) . '"';
    }

    /** Schema-qualified table reference (PostgreSQL needs this; others do not). */
    public function qualify($table, $schema = null)
    {
        $q = $this->quoteIdentifier($table);
        if ($this->type === 'pgsql') {
            $s = $schema !== null ? $schema : $this->schema;
            if ($s !== '' && $s !== null) return $this->quoteIdentifier($s) . '.' . $q;
        }
        return $q;
    }

    public function quote($v)
    {
        if ($v === null) return 'NULL';
        return $this->pdo->quote((string)$v);
    }

    // ─── Query execution ──────────────────────────────────────────────────────
    public function query($sql)
    {
        return $this->pdo ? $this->pdo->query($sql) : null;
    }

    /** Parameterised query - preferred everywhere user data reaches SQL. */
    public function run($sql, array $params = [])
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public function all($sql, array $params = [])
    {
        return $this->run($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function one($sql, array $params = [])
    {
        $r = $this->run($sql, $params)->fetch(PDO::FETCH_ASSOC);
        return $r === false ? null : $r;
    }

    public function scalar($sql, array $params = [])
    {
        return $this->run($sql, $params)->fetchColumn();
    }

    // ─── Database / schema selection ──────────────────────────────────────────
    /**
     * Point this connection at $name. MySQL can USE; PostgreSQL must reconnect
     * because a connection is bound to a single database; SQLite has only 'main'.
     * Returns true, or an error string.
     */
    public function selectDatabase($name)
    {
        $name = (string)$name;
        if ($name === '' || $name === $this->database) return true;

        if ($this->type === 'sqlite') return true;

        if ($this->type === 'mysql') {
            try {
                $this->pdo->exec('USE ' . $this->quoteIdentifier($name));
                $this->database = $name;
                return true;
            } catch (PDOException $e) {
                return $e->getMessage();
            }
        }

        // PostgreSQL: reconnect against the requested database.
        $c = $this->creds;
        $fresh = new self();
        $res = $fresh->connect($c['type'], $c['host'], $c['user'], $c['pass'], $name, $c['port'], $c['ssl']);
        if ($res !== true) return $res;

        $this->pdo = $fresh->getPdo();
        $this->database = $name;
        $this->schema = $fresh->getSchema();
        $this->serverVersion = null;
        return true;
    }

    public function selectSchema($schema)
    {
        $schema = (string)$schema;
        if ($schema === '') return true;
        if ($this->type === 'pgsql') {
            try {
                // set_config keeps the schema name out of the SQL string entirely.
                $this->run('SELECT set_config(?, ?, false)', ['search_path', $schema . ', pg_catalog']);
                $this->schema = $schema;
                return true;
            } catch (PDOException $e) {
                return $e->getMessage();
            }
        }
        return $this->selectDatabase($schema);
    }

    private function currentSchema()
    {
        if ($this->type !== 'pgsql') return '';
        try {
            $s = (string)$this->scalar('SELECT current_schema()');
            return $s !== '' ? $s : 'public';
        } catch (Throwable $e) { return 'public'; }
    }

    /**
     * Which schema holds $table? Used when a URL names a table but no schema,
     * so a bookmark or a link from search still lands on the right relation
     * instead of an empty page under whichever schema was last viewed.
     * Prefers the current schema, then public.
     */
    public function findSchemaForTable($table)
    {
        if ($this->type !== 'pgsql' || $table === '') return null;
        try {
            $rows = $this->run(
                "SELECT n.nspname
                   FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
                  WHERE c.relname = ? AND c.relkind IN ('r','p','v','m')
                    AND n.nspname NOT LIKE 'pg\_%' AND n.nspname <> 'information_schema'
                  ORDER BY (n.nspname <> current_schema()), (n.nspname <> 'public'), n.nspname
                  LIMIT 1",
                [$table]
            )->fetchAll(PDO::FETCH_COLUMN);
            return $rows[0] ?? null;
        } catch (Throwable $e) { return null; }
    }

    /** Schemas inside the current PostgreSQL database (empty for other engines). */
    public function getSchemas()
    {
        if ($this->type !== 'pgsql') return [];
        try {
            return $this->run(
                "SELECT nspname FROM pg_namespace
                  WHERE nspname NOT LIKE 'pg\_%' AND nspname <> 'information_schema'
                  ORDER BY (nspname <> 'public'), nspname"
            )->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) { return []; }
    }

    // ─── Introspection ────────────────────────────────────────────────────────
    public function getDatabasesWithStats()
    {
        if (!$this->pdo) return [];
        $list = [];

        if ($this->type === 'mysql') {
            try {
                $rows = $this->all(
                    "SELECT table_schema AS db_name,
                            COUNT(table_name) AS table_count,
                            COALESCE(SUM(data_length + index_length), 0) AS total_size,
                            COALESCE(SUM(data_length), 0) AS data_size,
                            COALESCE(SUM(index_length), 0) AS index_size
                       FROM information_schema.TABLES
                      GROUP BY table_schema
                      ORDER BY table_schema"
                );
                foreach ($rows as $r) {
                    $list[$r['db_name']] = [
                        'name' => $r['db_name'],
                        'tables' => (int)$r['table_count'],
                        'size' => (float)$r['total_size'],
                        'data_size' => (float)$r['data_size'],
                        'index_size' => (float)$r['index_size'],
                        'exact' => true,
                    ];
                }
            } catch (Throwable $e) {}

            if (empty($list)) {
                // Restricted grants can hide information_schema; fall back.
                try {
                    foreach ($this->run('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN) as $d) {
                        $list[$d] = ['name' => $d, 'tables' => null, 'size' => 0,
                                     'data_size' => 0, 'index_size' => 0, 'exact' => false];
                    }
                } catch (Throwable $e) {}
            }

        } elseif ($this->type === 'pgsql') {
            // Table counts live in each database's own catalog, so they cannot be
            // read from here. Sizes are cheap; counts are filled in lazily by the
            // UI (action=db_table_count) rather than opening N connections now.
            try {
                $rows = $this->all(
                    "SELECT d.datname AS db_name,
                            pg_database_size(d.datname) AS total_size,
                            pg_catalog.has_database_privilege(d.datname, 'CONNECT') AS can_connect
                       FROM pg_database d
                      WHERE d.datistemplate = false
                      ORDER BY d.datname"
                );
                foreach ($rows as $r) {
                    $list[$r['db_name']] = [
                        'name' => $r['db_name'],
                        'tables' => ($r['db_name'] === $this->database) ? $this->countTables() : null,
                        'size' => (float)$r['total_size'],
                        'data_size' => null,
                        'index_size' => null,
                        'exact' => true,
                        'lazy_count' => !empty($r['can_connect']),
                    ];
                }
            } catch (Throwable $e) {}

        } elseif ($this->type === 'sqlite') {
            $path = $this->creds['host'] ?? '';
            $sz = ($path && is_file($path)) ? (float)filesize($path) : 0;
            $list['main'] = ['name' => 'main', 'tables' => $this->countTables(),
                             'size' => $sz, 'data_size' => $sz, 'index_size' => 0, 'exact' => true];
        }

        return $list;
    }

    public function countTables()
    {
        try {
            if ($this->type === 'pgsql') {
                return (int)$this->scalar(
                    "SELECT count(*) FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
                      WHERE c.relkind IN ('r','p','v','m')
                        AND n.nspname NOT LIKE 'pg\_%' AND n.nspname <> 'information_schema'"
                );
            }
            return count($this->getTables());
        } catch (Throwable $e) { return null; }
    }

    /** Table + view names for the current database/schema. */
    public function getTables($database = null, $schema = null)
    {
        if (!$this->pdo) return [];
        if ($database !== null && $database !== '') {
            $r = $this->selectDatabase($database);
            if ($r !== true) return [];
        }
        if ($schema !== null && $schema !== '') $this->selectSchema($schema);

        try {
            switch ($this->type) {
                case 'mysql':
                    return $this->run('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
                case 'pgsql':
                    return $this->run(
                        "SELECT c.relname
                           FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
                          WHERE n.nspname = ? AND c.relkind IN ('r','p','v','m')
                          ORDER BY c.relname",
                        [$this->schema ?: 'public']
                    )->fetchAll(PDO::FETCH_COLUMN);
                case 'sqlite':
                    return $this->run(
                        "SELECT name FROM sqlite_master
                          WHERE type IN ('table','view') AND name NOT LIKE 'sqlite_%'
                          ORDER BY name"
                    )->fetchAll(PDO::FETCH_COLUMN);
            }
        } catch (Throwable $e) {}
        return [];
    }

    public function getTablesWithStats($database = null, $schema = null)
    {
        if (!$this->pdo) return [];
        if ($database !== null && $database !== '') {
            $r = $this->selectDatabase($database);
            if ($r !== true) return [];
        }
        if ($schema !== null && $schema !== '') $this->selectSchema($schema);

        if ($this->type === 'mysql') {
            try {
                $rows = $this->run('SHOW TABLE STATUS')->fetchAll(PDO::FETCH_ASSOC);
                $res = [];
                foreach ($rows as $r) {
                    $m = array_change_key_case($r, CASE_LOWER);
                    $d = (float)($m['data_length'] ?? 0);
                    $i = (float)($m['index_length'] ?? 0);
                    $res[] = [
                        'Name' => $m['name'] ?? '',
                        'Engine' => $m['engine'] ?? '',
                        'Rows' => $m['rows'] === null ? null : (int)$m['rows'],
                        'Data_length' => $d,
                        'Index_length' => $i,
                        'Total_length' => $d + $i,
                        'Data_free' => (float)($m['data_free'] ?? 0),
                        'Auto_increment' => $m['auto_increment'] ?? null,
                        'Collation' => $m['collation'] ?? '',
                        'Comment' => $m['comment'] ?? '',
                        // InnoDB's row count is a sampled estimate, not a fact.
                        'Rows_exact' => (stripos($m['engine'] ?? '', 'innodb') === false),
                        'Is_view' => ($m['engine'] ?? null) === null && ($m['comment'] ?? '') === 'VIEW',
                    ];
                }
                if ($res) return $res;
            } catch (Throwable $e) {}

        } elseif ($this->type === 'pgsql') {
            try {
                return array_map(function ($r) {
                    $r['Rows'] = $r['Rows'] === null ? null : (int)$r['Rows'];
                    $r['Rows_exact'] = false;   // reltuples is an ANALYZE-time estimate
                    $r['Is_view'] = in_array($r['Relkind'] ?? '', ['v', 'm'], true);
                    return $r;
                }, $this->all(
                    "SELECT c.relname AS \"Name\",
                            CASE c.relkind WHEN 'v' THEN 'view' WHEN 'm' THEN 'matview'
                                           WHEN 'p' THEN 'partitioned' ELSE 'table' END AS \"Engine\",
                            c.relkind AS \"Relkind\",
                            CASE WHEN c.relkind IN ('v') THEN NULL
                                 ELSE GREATEST(c.reltuples, 0)::bigint END AS \"Rows\",
                            pg_table_size(c.oid) AS \"Data_length\",
                            pg_indexes_size(c.oid) AS \"Index_length\",
                            pg_total_relation_size(c.oid) AS \"Total_length\",
                            0 AS \"Data_free\",
                            NULL AS \"Auto_increment\",
                            '' AS \"Collation\",
                            COALESCE(obj_description(c.oid, 'pg_class'), '') AS \"Comment\"
                       FROM pg_class c
                       JOIN pg_namespace n ON n.oid = c.relnamespace
                      WHERE n.nspname = ? AND c.relkind IN ('r','p','v','m')
                      ORDER BY c.relname",
                    [$this->schema ?: 'public']
                ));
            } catch (Throwable $e) {}
        }

        // SQLite (and any fallback): sizes are not exposed, so report what we can.
        $stats = [];
        foreach ($this->getTables() as $t) {
            $stats[] = [
                'Name' => $t, 'Engine' => 'SQLite', 'Rows' => $this->getRowCount($t),
                'Data_length' => 0, 'Index_length' => 0, 'Total_length' => 0,
                'Data_free' => 0, 'Auto_increment' => null, 'Collation' => '',
                'Comment' => '', 'Rows_exact' => true, 'Is_view' => false,
            ];
        }
        return $stats;
    }

    /** Normalised column list: Field / Type / Null / Default / Key / Extra. */
    public function getColumns($table, $schema = null)
    {
        if (!$this->pdo || $table === '') return [];
        try {
            if ($this->type === 'mysql') {
                return $this->run('SHOW FULL COLUMNS FROM ' . $this->quoteIdentifier($table))
                            ->fetchAll(PDO::FETCH_ASSOC);
            }
            if ($this->type === 'pgsql') {
                return $this->all(
                    "SELECT a.attname AS \"Field\",
                            format_type(a.atttypid, a.atttypmod) AS \"Type\",
                            CASE WHEN a.attnotnull THEN 'NO' ELSE 'YES' END AS \"Null\",
                            pg_get_expr(ad.adbin, ad.adrelid) AS \"Default\",
                            CASE WHEN pk.attname IS NOT NULL THEN 'PRI' ELSE '' END AS \"Key\",
                            CASE WHEN pg_get_expr(ad.adbin, ad.adrelid) LIKE 'nextval%'
                                 THEN 'auto_increment' ELSE '' END AS \"Extra\",
                            COALESCE(col_description(a.attrelid, a.attnum), '') AS \"Comment\"
                       FROM pg_attribute a
                       JOIN pg_class c ON c.oid = a.attrelid
                       JOIN pg_namespace n ON n.oid = c.relnamespace
                       LEFT JOIN pg_attrdef ad ON ad.adrelid = c.oid AND ad.adnum = a.attnum
                       LEFT JOIN (
                            SELECT a2.attname
                              FROM pg_index i
                              JOIN pg_attribute a2 ON a2.attrelid = i.indrelid
                                                  AND a2.attnum = ANY(i.indkey)
                             WHERE i.indrelid = (? || '.' || ?)::regclass AND i.indisprimary
                       ) pk ON pk.attname = a.attname
                      WHERE n.nspname = ? AND c.relname = ?
                        AND a.attnum > 0 AND NOT a.attisdropped
                      ORDER BY a.attnum",
                    [
                        $this->quoteIdentifier($this->schema ?: 'public'),
                        $this->quoteIdentifier($table),
                        $this->schema ?: 'public',
                        $table,
                    ]
                );
            }
            if ($this->type === 'sqlite') {
                $rows = $this->all('PRAGMA table_info(' . $this->quoteIdentifier($table) . ')');
                return array_map(function ($r) {
                    return [
                        'Field' => $r['name'],
                        'Type' => $r['type'],
                        'Null' => $r['notnull'] ? 'NO' : 'YES',
                        'Default' => $r['dflt_value'],
                        'Key' => $r['pk'] ? 'PRI' : '',
                        'Extra' => '',
                        'Comment' => '',
                    ];
                }, $rows);
            }
        } catch (Throwable $e) {}
        return [];
    }

    /**
     * Primary-key columns for a table.
     *
     * 1.x addressed rows by every column in the SELECT, which silently failed on
     * float/blob columns and on NULLs (`col = NULL` never matches). Editing and
     * deleting now key off the real primary key when one exists.
     */
    public function getPrimaryKey($table)
    {
        if ($table === '') return [];
        try {
            if ($this->type === 'mysql') {
                $rows = $this->all('SHOW KEYS FROM ' . $this->quoteIdentifier($table) . " WHERE Key_name = 'PRIMARY'");
                return array_column($rows, 'Column_name');
            }
            if ($this->type === 'pgsql') {
                return $this->run(
                    "SELECT a.attname
                       FROM pg_index i
                       JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
                      WHERE i.indrelid = (? || '.' || ?)::regclass AND i.indisprimary
                      ORDER BY array_position(i.indkey, a.attnum)",
                    [$this->quoteIdentifier($this->schema ?: 'public'), $this->quoteIdentifier($table)]
                )->fetchAll(PDO::FETCH_COLUMN);
            }
            if ($this->type === 'sqlite') {
                $pk = [];
                foreach ($this->all('PRAGMA table_info(' . $this->quoteIdentifier($table) . ')') as $r) {
                    if ($r['pk']) $pk[(int)$r['pk']] = $r['name'];
                }
                ksort($pk);
                return array_values($pk);
            }
        } catch (Throwable $e) {}
        return [];
    }

    public function getIndexes($table)
    {
        if ($table === '') return [];
        $out = [];
        try {
            if ($this->type === 'mysql') {
                foreach ($this->all('SHOW INDEX FROM ' . $this->quoteIdentifier($table)) as $r) {
                    $n = $r['Key_name'];
                    if (!isset($out[$n])) {
                        $out[$n] = ['name' => $n, 'columns' => [],
                                    'unique' => !$r['Non_unique'],
                                    'primary' => $n === 'PRIMARY',
                                    'type' => $r['Index_type'] ?? ''];
                    }
                    $out[$n]['columns'][] = $r['Column_name'];
                }
            } elseif ($this->type === 'pgsql') {
                $rows = $this->all(
                    "SELECT i.relname AS name, ix.indisunique AS is_unique, ix.indisprimary AS is_primary,
                            am.amname AS type,
                            ARRAY(SELECT pg_get_indexdef(ix.indexrelid, k + 1, true)
                                    FROM generate_subscripts(ix.indkey, 1) AS k
                                   ORDER BY k) AS cols
                       FROM pg_index ix
                       JOIN pg_class i ON i.oid = ix.indexrelid
                       JOIN pg_class t ON t.oid = ix.indrelid
                       JOIN pg_namespace n ON n.oid = t.relnamespace
                       JOIN pg_am am ON am.oid = i.relam
                      WHERE n.nspname = ? AND t.relname = ?
                      ORDER BY i.relname",
                    [$this->schema ?: 'public', $table]
                );
                foreach ($rows as $r) {
                    $cols = trim((string)$r['cols'], '{}');
                    $out[$r['name']] = [
                        'name' => $r['name'],
                        'columns' => $cols === '' ? [] : array_map(function ($c) {
                            return trim($c, '"');
                        }, str_getcsv($cols)),
                        'unique' => !empty($r['is_unique']),
                        'primary' => !empty($r['is_primary']),
                        'type' => $r['type'] ?? '',
                    ];
                }
            } elseif ($this->type === 'sqlite') {
                foreach ($this->all('PRAGMA index_list(' . $this->quoteIdentifier($table) . ')') as $r) {
                    $cols = $this->all('PRAGMA index_info(' . $this->quoteIdentifier($r['name']) . ')');
                    $out[$r['name']] = [
                        'name' => $r['name'],
                        'columns' => array_column($cols, 'name'),
                        'unique' => !empty($r['unique']),
                        'primary' => ($r['origin'] ?? '') === 'pk',
                        'type' => '',
                    ];
                }
            }
        } catch (Throwable $e) {}
        return array_values($out);
    }

    public function getForeignKeys($table, $database = null)
    {
        if ($table === '') return [];
        try {
            if ($this->type === 'mysql') {
                return $this->all(
                    "SELECT CONSTRAINT_NAME AS name, COLUMN_NAME AS col,
                            REFERENCED_TABLE_NAME AS ref_table, REFERENCED_COLUMN_NAME AS ref_col
                       FROM information_schema.KEY_COLUMN_USAGE
                      WHERE TABLE_SCHEMA = COALESCE(?, DATABASE()) AND TABLE_NAME = ?
                        AND REFERENCED_TABLE_NAME IS NOT NULL
                      ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION",
                    [$database ?: null, $table]
                );
            }
            if ($this->type === 'pgsql') {
                return $this->all(
                    "SELECT con.conname AS name,
                            att.attname AS col,
                            cl.relname AS ref_table,
                            att2.attname AS ref_col
                       FROM pg_constraint con
                       JOIN pg_class c ON c.oid = con.conrelid
                       JOIN pg_namespace n ON n.oid = c.relnamespace
                       JOIN pg_class cl ON cl.oid = con.confrelid
                       JOIN LATERAL unnest(con.conkey, con.confkey) AS u(k, fk) ON true
                       JOIN pg_attribute att  ON att.attrelid = con.conrelid  AND att.attnum  = u.k
                       JOIN pg_attribute att2 ON att2.attrelid = con.confrelid AND att2.attnum = u.fk
                      WHERE con.contype = 'f' AND n.nspname = ? AND c.relname = ?
                      ORDER BY con.conname",
                    [$this->schema ?: 'public', $table]
                );
            }
            if ($this->type === 'sqlite') {
                $out = [];
                foreach ($this->all('PRAGMA foreign_key_list(' . $this->quoteIdentifier($table) . ')') as $r) {
                    $out[] = ['name' => 'fk_' . $r['id'], 'col' => $r['from'],
                              'ref_table' => $r['table'], 'ref_col' => $r['to']];
                }
                return $out;
            }
        } catch (Throwable $e) {}
        return [];
    }

    /**
     * Row count. Exact counts get slow on large tables, so past a threshold we
     * report the planner's estimate and the UI labels it with a "~".
     * Returns ['n' => int, 'exact' => bool].
     */
    public function getRowCountInfo($table, $whereSql = '', array $params = [])
    {
        if ($table === '') return ['n' => 0, 'exact' => true];

        // A filtered count must be exact - an estimate would not reflect the filter.
        if ($whereSql === '') {
            try {
                if ($this->type === 'pgsql') {
                    $est = (int)$this->scalar(
                        "SELECT GREATEST(c.reltuples, 0)::bigint
                           FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
                          WHERE n.nspname = ? AND c.relname = ?",
                        [$this->schema ?: 'public', $table]
                    );
                    if ($est > self::EXACT_COUNT_LIMIT) return ['n' => $est, 'exact' => false];
                } elseif ($this->type === 'mysql') {
                    $est = (int)$this->scalar(
                        'SELECT table_rows FROM information_schema.TABLES
                          WHERE table_schema = DATABASE() AND table_name = ?',
                        [$table]
                    );
                    if ($est > self::EXACT_COUNT_LIMIT) return ['n' => $est, 'exact' => false];
                }
            } catch (Throwable $e) {}
        }

        try {
            $n = (int)$this->scalar('SELECT COUNT(*) FROM ' . $this->qualify($table) . ' ' . $whereSql, $params);
            return ['n' => $n, 'exact' => true];
        } catch (Throwable $e) {
            return ['n' => 0, 'exact' => true];
        }
    }

    public function getRowCount($table)
    {
        $i = $this->getRowCountInfo($table);
        return $i['n'];
    }

    /** CREATE TABLE text, used by the export and the structure page. */
    public function getCreateTable($table)
    {
        try {
            if ($this->type === 'mysql') {
                $r = $this->one('SHOW CREATE TABLE ' . $this->quoteIdentifier($table));
                if ($r) {
                    foreach ($r as $k => $v) {
                        if (stripos($k, 'create') === 0) return $v;
                    }
                }
            } elseif ($this->type === 'sqlite') {
                return (string)$this->scalar('SELECT sql FROM sqlite_master WHERE name = ?', [$table]);
            }
        } catch (Throwable $e) {}
        return null;
    }
}

// ─── Connection Vault ─────────────────────────────────────────────────────────
/**
 * Optional at-rest storage for saved connections.
 *
 * 1.x kept "saved profiles" in browser localStorage, which meant plaintext
 * database passwords readable by any script on the page and lost on cache clear.
 * The vault instead encrypts the whole profile set with AES-256-GCM under a key
 * derived from a master password (PBKDF2-SHA256), and never writes the master
 * password anywhere. Forget it and the data is gone - by design.
 */
class Vault
{
    const ITERATIONS = 310000;   // OWASP guidance for PBKDF2-HMAC-SHA256
    const MAGIC = "DABIROV1\n";

    public static function file()
    {
        $d = data_dir();
        return $d ? $d . '/vault.bin' : null;
    }

    public static function exists()
    {
        $f = self::file();
        return $f && is_file($f) && filesize($f) > strlen(self::MAGIC);
    }

    public static function available(&$reason = null)
    {
        if (!function_exists('openssl_encrypt')) {
            $reason = 'The OpenSSL PHP extension is required to store saved connections securely.';
            return false;
        }
        if (!data_dir()) {
            $reason = 'No writable data directory. Set DABIRO_DATA_DIR to enable saved connections.';
            return false;
        }
        return true;
    }

    private static function deriveKey($password, $salt)
    {
        return hash_pbkdf2('sha256', $password, $salt, self::ITERATIONS, 32, true);
    }

    /** @return array|null  Decrypted profile list, or null if the password is wrong. */
    public static function load($master)
    {
        $f = self::file();
        if (!$f || !is_file($f)) return [];
        $raw = @file_get_contents($f);
        if ($raw === false || strncmp($raw, self::MAGIC, strlen(self::MAGIC)) !== 0) return null;

        $body = substr($raw, strlen(self::MAGIC));
        if (strlen($body) < 44) return null;

        $salt = substr($body, 0, 16);
        $iv   = substr($body, 16, 12);
        $tag  = substr($body, 28, 16);
        $ct   = substr($body, 44);

        $plain = @openssl_decrypt($ct, 'aes-256-gcm', self::deriveKey($master, $salt), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) return null;      // wrong password or tampered file

        $data = json_decode($plain, true);
        return is_array($data) ? $data : [];
    }

    public static function save($master, array $profiles)
    {
        $f = self::file();
        if (!$f) return false;

        $salt = random_bytes(16);
        $iv   = random_bytes(12);
        $tag  = '';
        $ct = openssl_encrypt(
            json_encode($profiles, JSON_UNESCAPED_UNICODE),
            'aes-256-gcm', self::deriveKey($master, $salt), OPENSSL_RAW_DATA, $iv, $tag
        );
        if ($ct === false) return false;

        $tmp = $f . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, self::MAGIC . $salt . $iv . $tag . $ct) === false) return false;
        @chmod($tmp, 0600);
        return @rename($tmp, $f);
    }
}

// ─── Authentication & Session ─────────────────────────────────────────────────
function is_logged_in()
{
    // A session left over from Dabiro 1.x has logged_in set but stores its
    // credentials under different keys. Treat it as logged out rather than
    // limping along with a half-populated session.
    if (empty($_SESSION['logged_in'])) return false;
    if (!isset($_SESSION['db']) || !is_array($_SESSION['db']) || empty($_SESSION['db']['type'])) {
        $_SESSION['logged_in'] = false;
        return false;
    }
    return true;
}

/** Session values that make up an SSH tunnel config, or null when unused. */
function session_ssh_config()
{
    return !empty($_SESSION['ssh']['enabled']) ? $_SESSION['ssh'] : null;
}

/**
 * Open a connection, bringing up the SSH tunnel first when one is configured.
 * Returns true, or an error string suitable for showing the user.
 */
function login(array $cfg)
{
    $ssh = null;
    $host = $cfg['host'];
    $port = $cfg['port'];

    $hasPendingSsh = false;
    if (!empty($_SESSION['pending_ssh'])) {
        $st = SshTunnel::status($_SESSION['pending_ssh']);
        if (!empty($st['up'])) {
            $hasPendingSsh = true;
        }
    }

    if (!empty($cfg['ssh']['enabled']) || $hasPendingSsh) {
        if ($hasPendingSsh) {
            $ssh = $_SESSION['pending_ssh'];
            $host = '127.0.0.1';
            $port = $ssh['local_port'] ?? $ssh['port_bound'] ?? $ssh['port'];
        } else {
            $ssh = $cfg['ssh'];
            // The DB host/port in the form are resolved *from the bastion*, which is
            // why "localhost" here means the remote server's own loopback - exactly
            // what `ssh -L <local>:localhost:<port> user@host` does.
            $ssh['target_host'] = $cfg['host'] !== '' ? $cfg['host'] : '127.0.0.1';
            $ssh['target_port'] = (int)($cfg['port'] ?: default_port($cfg['type']));

            $t = SshTunnel::ensure($ssh);
            if (empty($t['ok'])) return $t['error'];

            $host = '127.0.0.1';
            $port = $t['port'];
            $ssh['local_port'] = $t['port'];
            $ssh['port'] = $t['port'];
        }
    }

    $db = new DbConnection();
    $res = $db->connect($cfg['type'], $host, $cfg['user'], $cfg['pass'], $cfg['dbname'], $port, $cfg['ssl']);
    if ($res !== true) {
        // Do not leave a tunnel running for a direct 1-step SSH login that failed.
        // For 2-step SSH flow with active pending_ssh, preserve the tunnel so the user can fix DB credentials.
        if ($ssh && !$hasPendingSsh) SshTunnel::close($ssh);
        return $res;
    }

    session_regenerate_id(true);
    login_probe_set();          // lets the next request detect a login loop
    $_SESSION['logged_in'] = true;
    $_SESSION['db'] = [
        'type' => $cfg['type'], 'host' => $cfg['host'], 'port' => $cfg['port'],
        'user' => $cfg['user'], 'pass' => $cfg['pass'],
        'name' => $cfg['dbname'], 'ssl' => (bool)$cfg['ssl'],
    ];
    $_SESSION['ssh'] = $ssh ? ($ssh + ['enabled' => true]) : ['enabled' => false];
    unset($_SESSION['pending_ssh']);
    $_SESSION['last_activity'] = time();
    $_SESSION['schema'] = $db->getSchema();
    return true;
}

function default_port($type)
{
    return ['mysql' => 3306, 'pgsql' => 5432, 'sqlite' => 0][$type] ?? 0;
}

function logout()
{
    if ($ssh = session_ssh_config()) {
        SshTunnel::close($ssh);
    }
    if (!empty($_SESSION['pending_ssh'])) {
        SshTunnel::close($_SESSION['pending_ssh']);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    redirect('?');
}

/**
 * The one connection for this request.
 *
 * Memoised: 1.x called get_connection() from every action handler, opening a
 * fresh PDO (and, with SSH, re-checking the tunnel) several times per page load.
 *
 * Before connecting we re-assert the tunnel. If it died since the last request
 * it is transparently rebuilt - and because a rebuilt tunnel may land on a
 * different local port, we always use the port ensure() just returned rather
 * than a stale one from the session.
 */
function get_connection(&$error = null)
{
    static $conn = null, $failed = false;
    $error = null;

    if ($conn !== null) return $conn;
    if ($failed) return null;
    if (!is_logged_in()) return null;

    if (time() - ($_SESSION['last_activity'] ?? 0) > SESSION_TIMEOUT) {
        logout();
    }
    $_SESSION['last_activity'] = time();

    $c = $_SESSION['db'];
    $host = $c['host'];
    $port = $c['port'];

    if ($ssh = session_ssh_config()) {
        $t = SshTunnel::ensure($ssh);
        if (empty($t['ok'])) {
            $failed = true;
            $error = $t['error'];
            return null;
        }
        $host = '127.0.0.1';
        $port = $t['port'];
    }

    $db = new DbConnection();
    $res = $db->connect($c['type'], $host, $c['user'], $c['pass'], $c['name'], $port, $c['ssl']);
    if ($res !== true) {
        $failed = true;
        $error = $res;
        return null;
    }

    if (!empty($_SESSION['schema'])) {
        $db->selectSchema($_SESSION['schema']);
    }

    $conn = $db;
    return $conn;
}

/**
 * Point the shared connection at the database/schema this request asked for.
 *
 * The last-used schema is remembered, but schemas belong to a database: after
 * browsing shop.audit, a link without an explicit &schema= must not go looking
 * for a table inside "audit" once the database has changed (or when the schema
 * has since been dropped). An unknown schema falls back to public.
 */
function focus_connection($db, $database, $schema = '')
{
    if (!$db) return null;

    if ($database !== '' && $database !== null) {
        $r = $db->selectDatabase($database);
        if ($r !== true) return $r;
    }

    if ($db->getType() !== 'pgsql') return null;

    $available = $db->getSchemas();
    if ($schema === '' || !in_array($schema, $available, true)) {
        $schema = in_array('public', $available, true) ? 'public' : ($available[0] ?? '');
    }
    if ($schema === '') return null;

    $r = $db->selectSchema($schema);
    if ($r !== true) return $r;
    $_SESSION['schema'] = $schema;
    return null;
}

// ─── SQL utilities ────────────────────────────────────────────────────────────
/**
 * Split a script into statements, respecting string literals, identifier quotes,
 * line/block comments and MySQL's DELIMITER directive.
 *
 * 1.x handed whole files to PDO::exec(), which fails the moment a dump contains
 * a stored routine or a semicolon inside a string.
 */
function split_sql($sql)
{
    $out = [];
    $buf = '';
    $delim = ';';
    $i = 0;
    $len = strlen($sql);

    while ($i < $len) {
        $ch = $sql[$i];
        $two = substr($sql, $i, 2);

        // DELIMITER directive (MySQL dumps) - only valid at a statement boundary.
        if (trim($buf) === '' && stripos(substr($sql, $i, 10), 'delimiter ') === 0) {
            $eol = strpos($sql, "\n", $i);
            if ($eol === false) $eol = $len;
            $newDelim = trim(substr($sql, $i + 10, $eol - $i - 10));
            if ($newDelim !== '') $delim = $newDelim;
            $i = $eol + 1;
            $buf = '';
            continue;
        }

        if ($ch === '-' && $two === '--' && ($i + 2 >= $len || $sql[$i + 2] === ' ' || $sql[$i + 2] === "\t" || $sql[$i + 2] === "\n")) {
            $eol = strpos($sql, "\n", $i);
            $i = ($eol === false) ? $len : $eol + 1;
            continue;
        }
        if ($ch === '#') {
            $eol = strpos($sql, "\n", $i);
            $i = ($eol === false) ? $len : $eol + 1;
            continue;
        }
        if ($two === '/*') {
            $end = strpos($sql, '*/', $i + 2);
            $i = ($end === false) ? $len : $end + 2;
            continue;
        }

        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $quote = $ch;
            $buf .= $ch;
            $i++;
            while ($i < $len) {
                $c = $sql[$i];
                if ($c === '\\' && $quote !== '`' && $i + 1 < $len) {
                    $buf .= $c . $sql[$i + 1];
                    $i += 2;
                    continue;
                }
                $buf .= $c;
                $i++;
                if ($c === $quote) {
                    // A doubled quote is an escaped quote, not the end.
                    if ($i < $len && $sql[$i] === $quote) { $buf .= $sql[$i]; $i++; continue; }
                    break;
                }
            }
            continue;
        }

        $dlen = strlen($delim);
        if (substr($sql, $i, $dlen) === $delim) {
            if (trim($buf) !== '') $out[] = trim($buf);
            $buf = '';
            $i += $dlen;
            continue;
        }

        $buf .= $ch;
        $i++;
    }

    if (trim($buf) !== '') $out[] = trim($buf);
    return $out;
}

/** Does this statement return a result set? */
function sql_returns_rows($sql)
{
    return (bool)preg_match('/^\s*(\(*\s*SELECT|WITH|SHOW|DESCRIBE|DESC|EXPLAIN|PRAGMA|VALUES|TABLE|RETURNING)\b/i', $sql);
}

/**
 * Build a WHERE clause identifying one row.
 *
 * Prefers the primary key. Falls back to matching every supplied column, and -
 * unlike 1.x - emits `IS NULL` for null values instead of `= NULL`, which never
 * matches anything and made NULL-containing rows uneditable and undeletable.
 */
function row_identity(DbConnection $db, $table, array $values)
{
    $pk = $db->getPrimaryKey($table);
    $use = [];

    if ($pk) {
        foreach ($pk as $c) {
            if (array_key_exists($c, $values)) $use[$c] = $values[$c];
        }
        if (count($use) !== count($pk)) $use = [];   // incomplete key - fall back
    }
    if (!$use) $use = $values;

    $parts = [];
    $params = [];
    foreach ($use as $col => $val) {
        $q = $db->quoteIdentifier($col);
        if ($val === null || $val === '\0NULL\0') {
            $parts[] = "$q IS NULL";
        } else {
            $parts[] = "$q = ?";
            $params[] = $val;
        }
    }

    return [$parts ? implode(' AND ', $parts) : '1=0', $params, (bool)$pk];
}

/** Pull where_* values out of a query/post array. */
function extract_row_keys(array $src)
{
    $out = [];
    foreach ($src as $k => $v) {
        if (strncmp($k, 'where_', 6) === 0) $out[substr($k, 6)] = $v;
    }
    return $out;
}

// ─── Request context ──────────────────────────────────────────────────────────
$error_message   = null;
$success_message = null;
$loop_warning    = null;
$sql_batches     = null;   // one entry per executed statement
$sql_error       = null;
$conn_error      = null;

$req_db     = get_get('db', '');
$req_schema = get_get('schema', $_SESSION['schema'] ?? '');
$req_table  = get_get('table', '');
$action     = get_get('action', '');

// ─── Pre-auth actions ─────────────────────────────────────────────────────────
if (isset($_POST['login'])) {
    if (require_csrf(get_post('csrf_token'))) {
        $type = get_post('db_type');
        $cfg = [
            'type'   => $type,
            'host'   => trim(get_post('db_host')),
            'port'   => trim(get_post('db_port')),
            'user'   => get_post('db_user'),
            'pass'   => get_post('db_pass'),
            'dbname' => trim(get_post('db_name')),
            'ssl'    => (bool)get_post('db_ssl'),
            'ssh'    => ['enabled' => false],
        ];

        if (get_post('use_ssh') === '1') {
            $cfg['ssh'] = [
                'enabled'     => true,
                'host'        => trim(get_post('ssh_host')),
                'port'        => (int)(get_post('ssh_port', 22) ?: 22),
                'user'        => trim(get_post('ssh_user')),
                'auth'        => get_post('ssh_auth', 'agent'),
                'password'    => get_post('ssh_pass'),
                'key'         => get_post('ssh_key'),
                'key_pass'    => get_post('ssh_key_pass'),
                'key_is_path' => get_post('ssh_key_mode') === 'path',
                'local_port'  => (int)get_post('ssh_local_port', 0),
            ];
        }

        $res = login($cfg);
        if ($res === true) {
            redirect($cfg['dbname'] ? ('?page=tables&db=' . urlencode($cfg['dbname'])) : '?page=databases');
        }
        $error_message = $res;
    }
}

if (isset($_POST['logout']) && validate_csrf_token(get_post('csrf_token'))) {
    logout();
}

// Vault endpoints run before the DB connection because they gate login itself.
if ($action === 'vault') {
    if (!validate_csrf_token(get_post('csrf_token', get_get('csrf_token')))) {
        json_out(['ok' => false, 'error' => 'Invalid security token.'], 403);
    }
    if (!Vault::available($why)) json_out(['ok' => false, 'error' => $why], 400);

    $op     = get_post('op', get_get('op'));
    $master = (string)get_post('master');

    if ($op === 'list') {
        $data = Vault::load($master);
        if ($data === null) json_out(['ok' => false, 'error' => 'Wrong master password.'], 403);
        // Never send stored secrets back to the browser.
        $safe = [];
        foreach ($data as $name => $p) {
            unset($p['pass'], $p['ssh_pass'], $p['ssh_key'], $p['ssh_key_pass']);
            $p['has_secrets'] = true;
            $safe[$name] = $p;
        }
        json_out(['ok' => true, 'profiles' => $safe, 'exists' => Vault::exists()]);
    }

    if ($op === 'save') {
        $data = Vault::exists() ? Vault::load($master) : [];
        if ($data === null) json_out(['ok' => false, 'error' => 'Wrong master password.'], 403);
        $name = trim((string)get_post('name'));
        if ($name === '') json_out(['ok' => false, 'error' => 'A profile name is required.'], 400);
        $data[$name] = json_decode((string)get_post('profile'), true) ?: [];
        json_out(['ok' => (bool)Vault::save($master, $data)]);
    }

    if ($op === 'delete') {
        $data = Vault::load($master);
        if ($data === null) json_out(['ok' => false, 'error' => 'Wrong master password.'], 403);
        unset($data[(string)get_post('name')]);
        json_out(['ok' => (bool)Vault::save($master, $data)]);
    }

    if ($op === 'connect') {
        $data = Vault::load($master);
        if ($data === null) json_out(['ok' => false, 'error' => 'Wrong master password.'], 403);
        $p = $data[(string)get_post('name')] ?? null;
        if (!$p) json_out(['ok' => false, 'error' => 'No such profile.'], 404);

        $res = login([
            'type' => $p['type'] ?? 'mysql', 'host' => $p['host'] ?? '', 'port' => $p['port'] ?? '',
            'user' => $p['user'] ?? '', 'pass' => $p['pass'] ?? '', 'dbname' => $p['dbname'] ?? '',
            'ssl' => !empty($p['ssl']),
            'ssh' => !empty($p['ssh_enabled']) ? [
                'enabled' => true, 'host' => $p['ssh_host'] ?? '', 'port' => (int)($p['ssh_port'] ?? 22),
                'user' => $p['ssh_user'] ?? '', 'auth' => $p['ssh_auth'] ?? 'agent',
                'password' => $p['ssh_pass'] ?? '', 'key' => $p['ssh_key'] ?? '',
                'key_pass' => $p['ssh_key_pass'] ?? '', 'key_is_path' => !empty($p['ssh_key_is_path']),
                'local_port' => (int)($p['ssh_local_port'] ?? 0),
            ] : ['enabled' => false],
        ]);
        json_out($res === true
            ? ['ok' => true, 'redirect' => ($p['dbname'] ?? '') ? ('?page=tables&db=' . urlencode($p['dbname'])) : '?page=databases']
            : ['ok' => false, 'error' => $res]);
    }

    json_out(['ok' => false, 'error' => 'Unknown vault operation.'], 400);
}

// ── SSH Tunnel pre-auth endpoints (for 2-step connection flow) ──
if ($action === 'ssh_connect') {
    if (!validate_csrf_token(get_post('csrf_token', get_get('csrf_token')))) {
        json_out(['ok' => false, 'error' => 'Invalid security token.'], 403);
    }
    if (!SshTunnel::available($why)) {
        json_out(['ok' => false, 'error' => $why], 400);
    }

    $sshHost = trim((string)get_post('ssh_host'));
    $sshPort = (int)(get_post('ssh_port', 22) ?: 22);
    $sshUser = trim((string)get_post('ssh_user'));
    $sshAuth = (string)get_post('ssh_auth', 'agent');
    $targetHost = trim((string)get_post('target_host', '127.0.0.1')) ?: '127.0.0.1';
    $targetPort = (int)(get_post('target_port', 3306) ?: 3306);
    $localPort  = (int)get_post('ssh_local_port', 0);

    if ($sshHost === '') {
        json_out(['ok' => false, 'error' => 'SSH host is required.'], 400);
    }
    if ($sshUser === '' && $sshAuth !== 'agent') {
        json_out(['ok' => false, 'error' => 'SSH username is required.'], 400);
    }

    $sshCfg = [
        'enabled'     => true,
        'host'        => $sshHost,
        'port'        => $sshPort,
        'user'        => $sshUser,
        'auth'        => $sshAuth,
        'password'    => (string)get_post('ssh_pass'),
        'key'         => (string)get_post('ssh_key'),
        'key_pass'    => (string)get_post('ssh_key_pass'),
        'key_is_path' => get_post('ssh_key_mode') === 'path',
        'local_port'  => $localPort,
        'target_host' => $targetHost,
        'target_port' => $targetPort,
    ];

    if (!empty($_SESSION['pending_ssh'])) {
        SshTunnel::close($_SESSION['pending_ssh']);
        unset($_SESSION['pending_ssh']);
    }

    $res = SshTunnel::ensure($sshCfg);
    if (empty($res['ok'])) {
        json_out(['ok' => false, 'error' => $res['error'] ?? 'Could not establish SSH tunnel.'], 400);
    }

    $sshCfg['local_port'] = $res['port'];
    $sshCfg['port_bound'] = $res['port'];
    $sshCfg['pid'] = $res['pid'];
    $_SESSION['pending_ssh'] = $sshCfg;

    json_out([
        'ok'          => true,
        'port'        => $res['port'],
        'host'        => $sshHost,
        'user'        => $sshUser,
        'target_host' => $targetHost,
        'target_port' => $targetPort,
        'reused'      => !empty($res['reused'])
    ]);
}

if ($action === 'ssh_disconnect') {
    if (!validate_csrf_token(get_post('csrf_token', get_get('csrf_token')))) {
        json_out(['ok' => false, 'error' => 'Invalid security token.'], 403);
    }
    if (!empty($_SESSION['pending_ssh'])) {
        SshTunnel::close($_SESSION['pending_ssh']);
        unset($_SESSION['pending_ssh']);
    }
    json_out(['ok' => true]);
}

if ($action === 'ssh_status') {
    if (!empty($_SESSION['pending_ssh'])) {
        $st = SshTunnel::status($_SESSION['pending_ssh']);
        if (!empty($st['up'])) {
            json_out([
                'ok'          => true,
                'connected'   => true,
                'port'        => $st['port'],
                'host'        => $_SESSION['pending_ssh']['host'] ?? '',
                'user'        => $_SESSION['pending_ssh']['user'] ?? '',
                'target_host' => $_SESSION['pending_ssh']['target_host'] ?? '127.0.0.1',
                'target_port' => $_SESSION['pending_ssh']['target_port'] ?? 3306,
            ]);
        }
    }
    json_out(['ok' => true, 'connected' => false]);
}

// A session that survived the redirect means there is no loop to warn about.
if (is_logged_in()) {
    login_probe_clear();
} else {
    $loop_warning = login_loop_diagnosis();
}

// ─── Authenticated actions ────────────────────────────────────────────────────
$db = is_logged_in() ? get_connection($conn_error) : null;

if (is_logged_in() && !$db && !$conn_error) {
    $conn_error = 'The database connection could not be re-established.';
}

// Point the connection at the requested database/schema once, up front.
if ($db) {
    $focus_err = focus_connection($db, $req_db, $req_schema);
    if ($focus_err) $error_message = $focus_err;

    // When the URL names a table but not a schema, follow the table rather than
    // the remembered schema - otherwise a bookmarked ?table=orders renders empty
    // just because some other schema was viewed last.
    if ($db->getType() === 'pgsql' && $req_table !== '' && (string)get_get('schema', '') === '') {
        $owner = $db->findSchemaForTable($req_table);
        if ($owner !== null && $owner !== $db->getSchema()) {
            $db->selectSchema($owner);
            $_SESSION['schema'] = $owner;
        }
    }

    $req_schema = $db->getSchema();
}

// ── JSON endpoints ────────────────────────────────────────────────────────────
if ($action !== '' && is_ajax_action($action)) {
    if (!is_logged_in()) json_out(['ok' => false, 'error' => 'Not connected.'], 401);
    if (!$db) json_out(['ok' => false, 'error' => $conn_error ?: 'No connection.'], 502);

    switch ($action) {
        case 'get_tables':
            json_out($db->getTables(get_get('db'), get_get('schema')));

        case 'db_table_count':
            // PostgreSQL cannot count another database's tables from here, so the
            // Databases page fills these in lazily instead of blocking on N connects.
            $name = get_get('name');
            $probe = new DbConnection();
            $c = $_SESSION['db'];
            $host = $c['host'];
            $port = $c['port'];
            if ($ssh = session_ssh_config()) {
                $t = SshTunnel::ensure($ssh);
                if (empty($t['ok'])) json_out(['ok' => false], 502);
                $host = '127.0.0.1';
                $port = $t['port'];
            }
            if ($probe->connect($c['type'], $host, $c['user'], $c['pass'], $name, $port, $c['ssl']) !== true) {
                json_out(['ok' => false, 'name' => $name]);
            }
            json_out(['ok' => true, 'name' => $name, 'tables' => $probe->countTables()]);

        case 'schema_map':
            // Feeds SQL autocomplete and the command palette.
            $map = [];
            foreach ($db->getTables() as $t) {
                $map[$t] = array_column($db->getColumns($t), 'Field');
            }
            json_out([
                'ok' => true,
                'database' => $db->getDatabase(),
                'schema' => $db->getSchema(),
                'tables' => $map,
            ]);

        case 'palette':
            $out = [];
            foreach ($db->getDatabasesWithStats() as $n => $s) {
                $out[] = ['type' => 'database', 'name' => $n,
                          'url' => '?page=tables&db=' . urlencode($n)];
            }
            if ($req_db !== '') {
                foreach ($db->getSchemas() as $s) {
                    $out[] = ['type' => 'schema', 'name' => $s, 'context' => $req_db,
                              'url' => '?page=tables&db=' . urlencode($req_db) . '&schema=' . urlencode($s)];
                }
                foreach ($db->getTables() as $t) {
                    $out[] = ['type' => 'table', 'name' => $t, 'context' => $req_db,
                              'url' => '?page=browse&db=' . urlencode($req_db)
                                     . '&schema=' . urlencode($req_schema) . '&table=' . urlencode($t)];
                }
            }
            json_out(['ok' => true, 'items' => $out]);

        case 'tunnel_status':
            $ssh = session_ssh_config();
            json_out(['ok' => true, 'ssh' => (bool)$ssh,
                      'status' => $ssh ? SshTunnel::status($ssh) : ['up' => false]]);

        case 'cell_update':
            // Inline edit from the data grid.
            if (!validate_csrf_token(get_post('csrf_token'))) json_out(['ok' => false, 'error' => 'Invalid security token.'], 403);
            $t   = get_post('table');
            $col = get_post('column');
            $val = get_post('value');
            $isNull = get_post('is_null') === '1';
            $keys = json_decode((string)get_post('keys'), true) ?: [];
            if ($t === '' || $col === '') json_out(['ok' => false, 'error' => 'Missing table or column.'], 400);

            list($where, $params, $hasPk) = row_identity($db, $t, $keys);
            if (!$hasPk) json_out(['ok' => false, 'error' => 'This table has no primary key, so rows cannot be edited inline.'], 400);
            try {
                $st = $db->run(
                    'UPDATE ' . $db->qualify($t) . ' SET ' . $db->quoteIdentifier($col) . ' = ' .
                    ($isNull ? 'NULL' : '?') . ' WHERE ' . $where,
                    $isNull ? $params : array_merge([$val], $params)
                );
                json_out(['ok' => true, 'affected' => $st->rowCount()]);
            } catch (Throwable $e) {
                json_out(['ok' => false, 'error' => $e->getMessage()], 400);
            }
    }
}

function is_ajax_action($a)
{
    return in_array($a, ['get_tables', 'db_table_count', 'schema_map', 'palette', 'tunnel_status', 'cell_update'], true);
}

// ── Mutating page actions ─────────────────────────────────────────────────────
if ($db) {

    // Create database
    if (isset($_POST['create_database']) && require_csrf(get_post('csrf_token'))) {
        $new = trim(get_post('new_db_name'));
        if ($new !== '') {
            try {
                $db->query('CREATE DATABASE ' . $db->quoteIdentifier($new));
                $success_message = "Database \"$new\" created.";
            } catch (Throwable $e) { $error_message = $e->getMessage(); }
        }
    }

    // Create schema (PostgreSQL)
    if (isset($_POST['create_schema']) && require_csrf(get_post('csrf_token'))) {
        $new = trim(get_post('new_schema_name'));
        if ($new !== '' && $db->getType() === 'pgsql') {
            try {
                $db->query('CREATE SCHEMA ' . $db->quoteIdentifier($new));
                $success_message = "Schema \"$new\" created.";
            } catch (Throwable $e) { $error_message = $e->getMessage(); }
        }
    }

    // Create table
    if (isset($_POST['create_table']) && require_csrf(get_post('csrf_token'))) {
        $sql = get_post('create_table_sql');
        if (trim($sql) !== '') {
            try {
                $db->getPdo()->exec($sql);
                $success_message = 'Table created.';
            } catch (Throwable $e) { $error_message = $e->getMessage(); }
        }
    }

    // Add column
    if (isset($_POST['add_column']) && require_csrf(get_post('csrf_token'))) {
        $tbl  = get_post('table');
        $name = trim(get_post('col_name'));
        if ($tbl !== '' && $name !== '') {
            $type = get_post('col_type') . (trim(get_post('col_len')) !== '' ? '(' . trim(get_post('col_len')) . ')' : '');
            $null = get_post('col_null') === '1' ? 'NULL' : 'NOT NULL';
            $dflt = trim(get_post('col_dflt'));
            $sql  = 'ALTER TABLE ' . $db->qualify($tbl) . ' ADD COLUMN ' . $db->quoteIdentifier($name) . " $type $null";
            if ($dflt !== '') $sql .= ' DEFAULT ' . $db->quote($dflt);
            // AFTER/FIRST placement is MySQL-only syntax.
            $pos = get_post('col_pos');
            if ($pos !== '' && $db->getType() === 'mysql') {
                $sql .= $pos === 'FIRST' ? ' FIRST' : (' AFTER ' . $db->quoteIdentifier($pos));
            }
            try {
                $db->query($sql);
                $success_message = "Column \"$name\" added.";
            } catch (Throwable $e) { $error_message = $e->getMessage(); }
        }
    }

    // Modify column
    if (isset($_POST['edit_column']) && require_csrf(get_post('csrf_token'))) {
        $tbl = get_post('table');
        $old = trim(get_post('old_col_name'));
        $new = trim(get_post('col_name'));
        if ($tbl !== '' && $old !== '' && $new !== '') {
            $type = get_post('col_type') . (trim(get_post('col_len')) !== '' ? '(' . trim(get_post('col_len')) . ')' : '');
            $null = get_post('col_null') === '1';
            $dflt = trim(get_post('col_dflt'));
            $qt   = $db->qualify($tbl);
            try {
                if ($db->getType() === 'mysql') {
                    $sql = "ALTER TABLE $qt CHANGE COLUMN " . $db->quoteIdentifier($old) . ' '
                         . $db->quoteIdentifier($new) . " $type " . ($null ? 'NULL' : 'NOT NULL');
                    if ($dflt !== '') $sql .= ' DEFAULT ' . $db->quote($dflt);
                    $db->query($sql);
                } else {
                    // 1.x only ever renamed on PostgreSQL and silently dropped the
                    // type/null/default edits. Apply each as its own statement.
                    if ($old !== $new) {
                        $db->query("ALTER TABLE $qt RENAME COLUMN " . $db->quoteIdentifier($old) . ' TO ' . $db->quoteIdentifier($new));
                    }
                    if ($db->getType() === 'pgsql') {
                        $qc = $db->quoteIdentifier($new);
                        if (trim($type) !== '') {
                            $db->query("ALTER TABLE $qt ALTER COLUMN $qc TYPE $type USING $qc::$type");
                        }
                        $db->query("ALTER TABLE $qt ALTER COLUMN $qc " . ($null ? 'DROP NOT NULL' : 'SET NOT NULL'));
                        $db->query("ALTER TABLE $qt ALTER COLUMN $qc " .
                                   ($dflt !== '' ? 'SET DEFAULT ' . $db->quote($dflt) : 'DROP DEFAULT'));
                    }
                }
                $success_message = 'Column updated.';
            } catch (Throwable $e) { $error_message = $e->getMessage(); }
        }
    }

    // Drop column
    if ($action === 'drop_column' && validate_csrf_token(get_get('csrf_token'))) {
        $tbl = get_get('table');
        $col = get_get('col');
        if ($tbl !== '' && $col !== '') {
            try {
                $db->query('ALTER TABLE ' . $db->qualify($tbl) . ' DROP COLUMN ' . $db->quoteIdentifier($col));
                $success_message = "Column \"$col\" dropped.";
            } catch (Throwable $e) { $error_message = $e->getMessage(); }
        }
    }

    // Add index
    if (isset($_POST['add_index']) && require_csrf(get_post('csrf_token'))) {
        $tbl  = get_post('table');
        $cols = (array)get_post('index_columns', []);
        if ($tbl !== '' && $cols) {
            $qt   = $db->qualify($tbl);
            $list = implode(', ', array_map([$db, 'quoteIdentifier'], $cols));
            $type = get_post('index_type');
            $name = trim(get_post('index_name')) ?: ('idx_' . $tbl . '_' . implode('_', $cols));
            try {
                if ($type === 'PRIMARY KEY') {
                    $db->query("ALTER TABLE $qt ADD PRIMARY KEY ($list)");
                } elseif ($type === 'UNIQUE') {
                    $db->query('CREATE UNIQUE INDEX ' . $db->quoteIdentifier($name) . " ON $qt ($list)");
                } else {
                    $db->query('CREATE INDEX ' . $db->quoteIdentifier($name) . " ON $qt ($list)");
                }
                $success_message = 'Index created.';
            } catch (Throwable $e) { $error_message = $e->getMessage(); }
        }
    }

    // Drop index
    if ($action === 'drop_index' && validate_csrf_token(get_get('csrf_token'))) {
        $tbl = get_get('table');
        $idx = get_get('index');
        if ($tbl !== '' && $idx !== '') {
            try {
                if ($idx === 'PRIMARY' && $db->getType() === 'mysql') {
                    $db->query('ALTER TABLE ' . $db->qualify($tbl) . ' DROP PRIMARY KEY');
                } elseif ($db->getType() === 'mysql') {
                    $db->query('ALTER TABLE ' . $db->qualify($tbl) . ' DROP INDEX ' . $db->quoteIdentifier($idx));
                } else {
                    $db->query('DROP INDEX ' . ($db->getType() === 'pgsql'
                        ? $db->quoteIdentifier($db->getSchema()) . '.' : '') . $db->quoteIdentifier($idx));
                }
                $success_message = "Index \"$idx\" dropped.";
            } catch (Throwable $e) { $error_message = $e->getMessage(); }
        }
    }

    // Table operations
    if (isset($_POST['operation_action']) && require_csrf(get_post('csrf_token'))) {
        $op  = get_post('operation_action');
        $tbl = get_post('table');
        $qt  = $tbl !== '' ? $db->qualify($tbl) : '';
        if ($tbl !== '') {
            try {
                if ($op === 'rename_table') {
                    $new = trim(get_post('new_table_name'));
                    if ($new !== '' && $new !== $tbl) {
                        $db->query("ALTER TABLE $qt RENAME TO " . $db->quoteIdentifier($new));
                        redirect('?page=browse&db=' . urlencode($req_db) . '&schema=' . urlencode($req_schema) . '&table=' . urlencode($new));
                    }
                } elseif ($op === 'copy_table') {
                    $target = trim(get_post('copy_table_name'));
                    $data   = get_post('copy_data') === '1';
                    if ($target !== '') {
                        $qn = $db->qualify($target);
                        if ($db->getType() === 'mysql') {
                            $db->query("CREATE TABLE $qn LIKE $qt");
                            if ($data) $db->query("INSERT INTO $qn SELECT * FROM $qt");
                        } elseif ($db->getType() === 'pgsql') {
                            $db->query("CREATE TABLE $qn (LIKE $qt INCLUDING ALL)");
                            if ($data) $db->query("INSERT INTO $qn SELECT * FROM $qt");
                        } else {
                            $db->query("CREATE TABLE $qn AS SELECT * FROM $qt" . ($data ? '' : ' WHERE 0'));
                        }
                        $success_message = "Table copied to \"$target\".";
                    }
                } elseif ($op === 'alter_options' && $db->getType() === 'mysql') {
                    $eng = get_post('table_engine');
                    $col = trim(get_post('table_collation'));
                    $ai  = get_post('table_auto_increment');
                    // Whitelisted because engine/collation cannot be bound as parameters.
                    if (in_array($eng, ['InnoDB', 'MyISAM', 'MEMORY', 'ARCHIVE'], true)) {
                        $db->query("ALTER TABLE $qt ENGINE = $eng");
                    }
                    if ($col !== '' && preg_match('/^[A-Za-z0-9_]+$/', $col)) {
                        $db->query("ALTER TABLE $qt COLLATE = $col");
                    }
                    if ($ai !== '' && ctype_digit((string)$ai)) {
                        $db->query("ALTER TABLE $qt AUTO_INCREMENT = " . (int)$ai);
                    }
                    $success_message = 'Table options updated.';
                } elseif ($op === 'optimize_table') {
                    if ($db->getType() === 'mysql')      $db->query("OPTIMIZE TABLE $qt");
                    elseif ($db->getType() === 'pgsql')  $db->query("VACUUM ANALYZE $qt");
                    else                                 $db->query('VACUUM');
                    $success_message = 'Table optimised.';
                } elseif ($op === 'truncate_table') {
                    $db->query($db->getType() === 'sqlite' ? "DELETE FROM $qt" : "TRUNCATE TABLE $qt");
                    $success_message = "Table \"$tbl\" emptied.";
                } elseif ($op === 'drop_table') {
                    $db->query("DROP TABLE $qt");
                    redirect('?page=tables&db=' . urlencode($req_db) . '&schema=' . urlencode($req_schema));
                }
            } catch (Throwable $e) { $error_message = $e->getMessage(); }
        }
    }

    // Bulk table actions - the 1.x PHP build rendered these buttons but never
    // implemented a handler, so "Drop Selected" silently did nothing.
    if (isset($_POST['bulk_action']) && require_csrf(get_post('csrf_token'))) {
        $sel = (array)get_post('selected', []);
        $op  = get_post('bulk_action');
        $done = 0;
        try {
            foreach ($sel as $t) {
                $qt = $db->qualify($t);
                if ($op === 'drop') {
                    $db->query("DROP TABLE $qt");
                } elseif ($op === 'truncate') {
                    $db->query($db->getType() === 'sqlite' ? "DELETE FROM $qt" : "TRUNCATE TABLE $qt");
                } elseif ($op === 'optimize') {
                    if ($db->getType() === 'mysql')     $db->query("OPTIMIZE TABLE $qt");
                    elseif ($db->getType() === 'pgsql') $db->query("VACUUM ANALYZE $qt");
                }
                $done++;
            }
            if ($done) {
                $verb = ['drop' => 'dropped', 'truncate' => 'emptied', 'optimize' => 'optimised'][$op] ?? 'processed';
                $success_message = "$done table(s) $verb.";
            } else {
                $error_message = 'No tables were selected.';
            }
        } catch (Throwable $e) {
            $error_message = $e->getMessage() . ($done ? " ($done table(s) processed before the error.)" : '');
        }
    }

    // Delete record
    if ($action === 'delete' && validate_csrf_token(get_get('csrf_token'))) {
        $tbl = get_get('table');
        $keys = extract_row_keys($_GET);
        if ($tbl !== '' && $keys) {
            list($where, $params) = row_identity($db, $tbl, $keys);
            try {
                // No LIMIT: it is not valid on PostgreSQL DELETE, and the identity
                // clause already targets a single row when a primary key exists.
                $st = $db->run('DELETE FROM ' . $db->qualify($tbl) . ' WHERE ' . $where, $params);
                $success_message = $st->rowCount() . ' record(s) deleted.';
            } catch (Throwable $e) { $error_message = $e->getMessage(); }
        }
    }

    // Insert / update record
    if (isset($_POST['save_record']) && require_csrf(get_post('csrf_token'))) {
        $tbl    = get_post('table');
        $isEdit = get_post('is_edit') === '1';
        $fields = (array)get_post('field', []);
        $nulls  = (array)get_post('field_null', []);

        if ($tbl !== '' && $fields) {
            try {
                if ($isEdit) {
                    $sets = [];
                    $params = [];
                    foreach ($fields as $col => $val) {
                        if (isset($nulls[$col])) {
                            $sets[] = $db->quoteIdentifier($col) . ' = NULL';
                        } else {
                            $sets[] = $db->quoteIdentifier($col) . ' = ?';
                            $params[] = $val;
                        }
                    }
                    $keys = json_decode((string)get_post('row_keys'), true) ?: [];
                    list($where, $wparams) = row_identity($db, $tbl, $keys);
                    $st = $db->run(
                        'UPDATE ' . $db->qualify($tbl) . ' SET ' . implode(', ', $sets) . ' WHERE ' . $where,
                        array_merge($params, $wparams)
                    );
                    $success_message = $st->rowCount() . ' record(s) updated.';
                } else {
                    $cols = [];
                    $ph = [];
                    $params = [];
                    foreach ($fields as $col => $val) {
                        $cols[] = $db->quoteIdentifier($col);
                        if (isset($nulls[$col])) {
                            $ph[] = 'NULL';
                        } else {
                            $ph[] = '?';
                            $params[] = $val;
                        }
                    }
                    $db->run('INSERT INTO ' . $db->qualify($tbl) . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $ph) . ')', $params);
                    $success_message = 'Record inserted.';
                }
                if (get_post('then') === 'back') {
                    redirect('?page=browse&db=' . urlencode($req_db) . '&schema=' . urlencode($req_schema) . '&table=' . urlencode($tbl));
                }
            } catch (Throwable $e) { $error_message = $e->getMessage(); }
        }
    }

    // Execute SQL
    if (isset($_POST['execute_sql']) || isset($_POST['export_query'])) {
        if (!validate_csrf_token(get_post('csrf_token'))) {
            $sql_error = 'Security token validation failed.';
        } else {
            $raw = trim(get_post('sql'));

            if (isset($_POST['export_query'])) {
                header('Content-Type: application/sql; charset=utf-8');
                header('Content-Disposition: attachment; filename="query_' . date('Ymd_His') . '.sql"');
                echo "-- Dabiro query export\n-- " . date('c') . "\n\n" . $raw . "\n";
                exit;
            }

            if ($raw !== '') {
                $statements = split_sql($raw);
                $sql_batches = [];
                foreach ($statements as $stmt) {
                    $t0 = microtime(true);
                    $entry = ['sql' => $stmt, 'rows' => null, 'affected' => 0, 'error' => null, 'ms' => 0, 'truncated' => false];
                    try {
                        $st = $db->getPdo()->query($stmt);
                        $entry['ms'] = round((microtime(true) - $t0) * 1000, 2);
                        if ($st && $st->columnCount() > 0) {
                            // Cap what we hold in memory; the console is for looking,
                            // Export is for extracting.
                            $rows = [];
                            $n = 0;
                            while (($r = $st->fetch(PDO::FETCH_ASSOC)) !== false) {
                                if ($n++ >= 1000) { $entry['truncated'] = true; break; }
                                $rows[] = $r;
                            }
                            $entry['rows'] = $rows;
                        } else {
                            $entry['affected'] = $st ? $st->rowCount() : 0;
                        }
                    } catch (Throwable $e) {
                        $entry['ms'] = round((microtime(true) - $t0) * 1000, 2);
                        $entry['error'] = $e->getMessage();
                    }
                    $sql_batches[] = $entry;
                    if ($entry['error']) break;   // stop the batch at the first failure
                }
                foreach ($sql_batches as $b) {
                    if ($b['error']) { $sql_error = $b['error']; break; }
                }
            }
        }
    }

    // Import SQL
    if (isset($_POST['import_sql']) && require_csrf(get_post('csrf_token'))) {
        if (empty($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
            $codes = [
                UPLOAD_ERR_INI_SIZE => 'The file is larger than PHP\'s upload_max_filesize.',
                UPLOAD_ERR_FORM_SIZE => 'The file is larger than the form allows.',
                UPLOAD_ERR_PARTIAL => 'The upload was interrupted.',
                UPLOAD_ERR_NO_FILE => 'No file was selected.',
                UPLOAD_ERR_NO_TMP_DIR => 'PHP has no temporary directory to write to.',
                UPLOAD_ERR_CANT_WRITE => 'PHP could not write the uploaded file to disk.',
            ];
            $error_message = $codes[$_FILES['sql_file']['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'Upload failed.';
        } else {
            $content = file_get_contents($_FILES['sql_file']['tmp_name']);
            $stmts = split_sql($content);
            $okCount = 0;
            $stopOnError = get_post('import_stop_on_error', '1') === '1';
            $errors = [];
            try {
                $db->getPdo()->beginTransaction();
            } catch (Throwable $e) { /* DDL-heavy engines may not allow this */ }
            foreach ($stmts as $i => $s) {
                try {
                    $db->getPdo()->exec($s);
                    $okCount++;
                } catch (Throwable $e) {
                    $errors[] = 'Statement ' . ($i + 1) . ': ' . $e->getMessage();
                    if ($stopOnError) break;
                }
            }
            try {
                if ($db->getPdo()->inTransaction()) {
                    if ($errors && $stopOnError) $db->getPdo()->rollBack();
                    else $db->getPdo()->commit();
                }
            } catch (Throwable $e) {}

            if ($errors && $stopOnError) {
                $error_message = 'Import aborted and rolled back. ' . implode(' | ', array_slice($errors, 0, 3));
            } elseif ($errors) {
                $error_message = "$okCount statement(s) applied, " . count($errors) . ' failed. ' . implode(' | ', array_slice($errors, 0, 3));
            } else {
                $success_message = "Imported $okCount statement(s) successfully.";
            }
        }
    }

    // Export
    if (isset($_POST['export_database']) && validate_csrf_token(get_post('csrf_token'))) {
        $expDb  = get_post('export_db_name', $req_db);
        $expFmt = get_post('export_db_format', 'sql');
        $expTables = (array)get_post('export_tables', []);
        $withData = get_post('export_data', '1') === '1';

        if ($expDb !== '') $db->selectDatabase($expDb);
        if ($req_schema !== '') $db->selectSchema($req_schema);
        $tables = $expTables ?: $db->getTables();
        $stamp = date('Y-m-d_H-i-s');
        $base = preg_replace('/[^A-Za-z0-9_.-]/', '_', $expDb ?: 'export') . '_' . $stamp;

        // 1.x offered SQL/JSON/CSV/XML in the UI but only implemented SQL; the
        // other three fell through and silently re-rendered the page.
        export_dump($db, $tables, $expFmt, $base, $withData);
    }
}

// ─── Export ───────────────────────────────────────────────────────────────────
/**
 * Stream a dump of $tables in the requested format.
 *
 * Streamed row-by-row so exporting a large table cannot exhaust memory - 1.x
 * buffered whole result sets. Only the SQL format was ever implemented; JSON,
 * CSV and XML were selectable in the UI but did nothing.
 */
function export_dump(DbConnection $db, array $tables, $format, $basename, $withData = true)
{
    // Long exports must not die halfway through a download.
    @set_time_limit(0);
    while (ob_get_level() > 0) ob_end_clean();

    $send = function ($mime, $ext) use ($basename) {
        header("Content-Type: $mime; charset=utf-8");
        header('Content-Disposition: attachment; filename="' . $basename . '.' . $ext . '"');
        header('X-Content-Type-Options: nosniff');
    };

    $flush = function () {
        if (connection_aborted()) exit;
        flush();
    };

    switch ($format) {
        case 'json':
            $send('application/json', 'json');
            echo "{\n";
            $first = true;
            foreach ($tables as $t) {
                if (!$first) echo ",\n";
                $first = false;
                echo '  ' . json_encode((string)$t) . ": [\n";
                $rowFirst = true;
                foreach (export_rows($db, $t, $withData) as $row) {
                    echo ($rowFirst ? '    ' : ",\n    ") . json_encode($row, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                    $rowFirst = false;
                    $flush();
                }
                echo "\n  ]";
            }
            echo "\n}\n";
            exit;

        case 'csv':
            $send('text/csv', 'csv');
            $out = fopen('php://output', 'w');
            // Excel needs the BOM to read UTF-8 correctly.
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($tables as $t) {
                if (count($tables) > 1) fwrite($out, "# table: $t\n");
                $header = false;
                foreach (export_rows($db, $t, $withData) as $row) {
                    if (!$header) { fputcsv($out, array_keys($row)); $header = true; }
                    fputcsv($out, array_map(function ($v) { return $v === null ? '' : $v; }, $row));
                    $flush();
                }
                if (!$header) {
                    $cols = array_column($db->getColumns($t), 'Field');
                    if ($cols) fputcsv($out, $cols);
                }
                if (count($tables) > 1) fwrite($out, "\n");
            }
            fclose($out);
            exit;

        case 'xml':
            $send('application/xml', 'xml');
            echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<database name=\"" . h($db->getDatabase()) . "\">\n";
            foreach ($tables as $t) {
                echo '  <table name="' . h($t) . "\">\n";
                foreach (export_rows($db, $t, $withData) as $row) {
                    echo "    <row>\n";
                    foreach ($row as $k => $v) {
                        $tag = preg_replace('/[^A-Za-z0-9_.-]/', '_', (string)$k);
                        if ($tag === '' || ctype_digit($tag[0])) $tag = 'c_' . $tag;
                        if ($v === null) {
                            echo "      <$tag xsi:nil=\"true\"/>\n";
                        } else {
                            echo "      <$tag>" . h($v) . "</$tag>\n";
                        }
                    }
                    echo "    </row>\n";
                    $flush();
                }
                echo "  </table>\n";
            }
            echo "</database>\n";
            exit;

        case 'sql':
        default:
            $send('application/sql', 'sql');
            echo "-- Dabiro SQL dump\n";
            echo '-- Engine:   ' . $db->getType() . ' ' . $db->serverVersion() . "\n";
            echo '-- Database: ' . $db->getDatabase() . "\n";
            if ($db->getType() === 'pgsql') echo '-- Schema:   ' . $db->getSchema() . "\n";
            echo '-- Date:     ' . date('c') . "\n\n";

            if ($db->getType() === 'mysql') {
                echo "SET FOREIGN_KEY_CHECKS=0;\n";
                echo "SET NAMES utf8mb4;\n\n";
            }

            foreach ($tables as $t) {
                echo "--\n-- Table: $t\n--\n\n";
                $create = $db->getCreateTable($t);
                if ($create) {
                    echo 'DROP TABLE IF EXISTS ' . $db->quoteIdentifier($t) . ";\n";
                    echo rtrim($create, "; \n") . ";\n\n";
                }

                $cols = null;
                $count = 0;
                foreach (export_rows($db, $t, $withData) as $row) {
                    if ($cols === null) {
                        $cols = implode(', ', array_map([$db, 'quoteIdentifier'], array_keys($row)));
                    }
                    $vals = [];
                    foreach ($row as $v) {
                        $vals[] = $v === null ? 'NULL'
                            : (is_int($v) || is_float($v) ? (string)$v : $db->quote((string)$v));
                    }
                    echo 'INSERT INTO ' . $db->quoteIdentifier($t) . " ($cols) VALUES (" . implode(', ', $vals) . ");\n";
                    $count++;
                    if ($count % 200 === 0) $flush();
                }
                echo "\n";
                $flush();
            }

            if ($db->getType() === 'mysql') echo "SET FOREIGN_KEY_CHECKS=1;\n";
            exit;
    }
}

/** Generator so exports never hold a whole table in memory. */
function export_rows(DbConnection $db, $table, $withData = true)
{
    if (!$withData) return;
    try {
        $st = $db->getPdo()->query('SELECT * FROM ' . $db->qualify($table));
    } catch (Throwable $e) {
        return;
    }
    if (!$st) return;
    while (($row = $st->fetch(PDO::FETCH_ASSOC)) !== false) {
        yield $row;
    }
}

// ─── Routing Context ──────────────────────────────────────────────────────────
$page = get_get('page', is_logged_in() ? 'databases' : 'login');
$is_rtl = ($current_lang === 'ar');
$csrf = get_csrf_token();

$selected_db     = $req_db !== '' ? $req_db : ($_SESSION['db']['name'] ?? '');
$selected_schema = $req_schema;
$selected_table  = $req_table;

$nav_tables  = [];
$nav_schemas = [];
$db_type     = $_SESSION['db']['type'] ?? '';

if ($db) {
    if ($db->getType() === 'pgsql') {
        $nav_schemas = $db->getSchemas();
    }
    if ($selected_db !== '') {
        $nav_tables = $db->getTables();
    }
}

/** <use> reference into the inlined Lucide sprite. */
function ico($name, $cls = '', $size = null)
{
    $style = $size ? ' style="width:' . (int)$size . 'px;height:' . (int)$size . 'px"' : '';
    return '<svg class="ico ' . h($cls) . '"' . $style . ' aria-hidden="true"><use href="#i-' . h($name) . '"></use></svg>';
}

/** Preserve the current db/schema across links. */
function ctx_url(array $params)
{
    global $selected_db, $selected_schema;
    $base = ['db' => $selected_db];
    if ($selected_schema !== '') $base['schema'] = $selected_schema;
    return '?' . http_build_query(array_filter(array_merge($base, $params), function ($v) {
        return $v !== '' && $v !== null;
    }));
}

$engine_label = ['mysql' => 'MySQL', 'pgsql' => 'PostgreSQL', 'sqlite' => 'SQLite'][$db_type] ?? $db_type;

?><!DOCTYPE html>
<html lang="<?php echo h($current_lang); ?>" dir="<?php echo $is_rtl ? 'rtl' : 'ltr'; ?>" data-theme="<?php echo h($current_theme); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="light dark">
<meta name="robots" content="noindex, nofollow">
<title><?php echo h($selected_table ?: ($selected_db ?: __('app_name'))); ?> &middot; <?php echo h(__('app_name')); ?></title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%232f6fed' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><ellipse cx='12' cy='5' rx='9' ry='3'/><path d='M3 5V19A9 3 0 0 0 21 19V5'/><path d='M3 12A9 3 0 0 0 21 12'/></svg>">
<style>
/* ─── Design tokens ─────────────────────────────────────────────────────────
   Every theme redefines the same variable contract, so components never need
   to know which theme is active. */
:root, [data-theme="light"] {
    --accent: #2f6fed; --accent-hover: #1d56cc; --accent-soft: #eaf1fe; --accent-border: #c3d8fb;
    --accent-contrast: #ffffff;
    --ok: #0f9d58; --ok-soft: #e4f5ec; --ok-border: #b7e3ca;
    --warn: #b7791f; --warn-soft: #fdf5e4; --warn-border: #f0dfae;
    --danger: #d93a3a; --danger-hover: #b62d2d; --danger-soft: #fdecec; --danger-border: #f6c7c7;
    --bg: #f6f7f9; --surface: #ffffff; --surface-2: #f2f4f7; --surface-3: #e9edf2;
    --sidebar: #ffffff; --header: #ffffff;
    --text: #10151c; --text-dim: #5b6675; --text-faint: #8b95a3;
    --border: #e3e7ed; --border-strong: #cfd6df;
    --row-hover: #f5f8fd; --row-active: #eaf1fe;
    --shadow-1: 0 1px 2px rgba(16,21,28,.06), 0 1px 3px rgba(16,21,28,.04);
    --shadow-2: 0 4px 12px rgba(16,21,28,.08), 0 2px 4px rgba(16,21,28,.04);
    --shadow-3: 0 18px 48px rgba(16,21,28,.16), 0 4px 12px rgba(16,21,28,.08);
    --overlay: rgba(16,21,28,.42);
    --code-key: #a626a4; --code-str: #1a8f5f; --code-num: #b7791f; --code-com: #8b95a3; --code-fn: #2f6fed;
}
[data-theme="dark"] {
    --accent: #5b9dff; --accent-hover: #7db1ff; --accent-soft: #16233c; --accent-border: #24406e;
    --accent-contrast: #08111f;
    --ok: #3ddc97; --ok-soft: #10261d; --ok-border: #1d4535;
    --warn: #e8b45a; --warn-soft: #2a2113; --warn-border: #4a3a1c;
    --danger: #ff6b6b; --danger-hover: #ff8585; --danger-soft: #2c1618; --danger-border: #522527;
    --bg: #0a0e15; --surface: #111722; --surface-2: #171f2d; --surface-3: #1e2838;
    --sidebar: #0d121b; --header: #0d121b;
    --text: #e9eef6; --text-dim: #97a3b4; --text-faint: #6b7787;
    --border: #1e2635; --border-strong: #2c3648;
    --row-hover: #161e2b; --row-active: #16233c;
    --shadow-1: 0 1px 2px rgba(0,0,0,.4);
    --shadow-2: 0 4px 14px rgba(0,0,0,.45);
    --shadow-3: 0 20px 52px rgba(0,0,0,.62);
    --overlay: rgba(3,6,11,.68);
    --code-key: #d98be0; --code-str: #6fdba0; --code-num: #f0b866; --code-com: #6b7787; --code-fn: #5b9dff;
}
[data-theme="slate"] {
    --accent: #94a3b8; --accent-hover: #b3c0d1; --accent-soft: #1d2531; --accent-border: #2f3b4c;
    --accent-contrast: #0b0f16;
    --ok: #3ddc97; --ok-soft: #10261d; --ok-border: #1d4535;
    --warn: #e8b45a; --warn-soft: #2a2113; --warn-border: #4a3a1c;
    --danger: #f87171; --danger-hover: #fca5a5; --danger-soft: #2c1618; --danger-border: #522527;
    --bg: #0b0f16; --surface: #131924; --surface-2: #19212e; --surface-3: #212b3a;
    --sidebar: #0e131c; --header: #0e131c;
    --text: #eef2f7; --text-dim: #9aa6b6; --text-faint: #6e7a8a;
    --border: #202939; --border-strong: #2e394b;
    --row-hover: #182030; --row-active: #1d2531;
    --shadow-1: 0 1px 2px rgba(0,0,0,.4);
    --shadow-2: 0 4px 14px rgba(0,0,0,.45);
    --shadow-3: 0 20px 52px rgba(0,0,0,.62);
    --overlay: rgba(3,6,11,.68);
    --code-key: #c4b5fd; --code-str: #6fdba0; --code-num: #f0b866; --code-com: #6e7a8a; --code-fn: #94a3b8;
}
[data-theme="blue"] {
    --accent: #0284c7; --accent-hover: #0369a1; --accent-soft: #e0f2fe; --accent-border: #bae6fd;
    --accent-contrast: #ffffff;
    --ok: #0f9d58; --ok-soft: #e4f5ec; --ok-border: #b7e3ca;
    --warn: #b7791f; --warn-soft: #fdf5e4; --warn-border: #f0dfae;
    --danger: #d93a3a; --danger-hover: #b62d2d; --danger-soft: #fdecec; --danger-border: #f6c7c7;
    --bg: #f0f8ff; --surface: #ffffff; --surface-2: #e8f4fd; --surface-3: #daedfb;
    --sidebar: #ffffff; --header: #ffffff;
    --text: #0b3a56; --text-dim: #4a7593; --text-faint: #7ba3bf;
    --border: #cbe6fa; --border-strong: #a5d3f5;
    --row-hover: #eaf6fe; --row-active: #d9edfc;
    --shadow-1: 0 1px 2px rgba(2,132,199,.08);
    --shadow-2: 0 4px 12px rgba(2,132,199,.12);
    --shadow-3: 0 18px 48px rgba(2,132,199,.20);
    --overlay: rgba(8,47,73,.42);
    --code-key: #7c3aed; --code-str: #0f766e; --code-num: #b45309; --code-com: #7ba3bf; --code-fn: #0284c7;
}
[data-theme="green"] {
    --accent: #05966a; --accent-hover: #047857; --accent-soft: #dcfce9; --accent-border: #a9edc9;
    --accent-contrast: #ffffff;
    --ok: #05966a; --ok-soft: #dcfce9; --ok-border: #a9edc9;
    --warn: #b7791f; --warn-soft: #fdf5e4; --warn-border: #f0dfae;
    --danger: #d93a3a; --danger-hover: #b62d2d; --danger-soft: #fdecec; --danger-border: #f6c7c7;
    --bg: #f1fdf6; --surface: #ffffff; --surface-2: #e8f9f0; --surface-3: #d8f3e6;
    --sidebar: #ffffff; --header: #ffffff;
    --text: #05372a; --text-dim: #3f7a63; --text-faint: #71a891;
    --border: #c5ebd8; --border-strong: #9adcbb;
    --row-hover: #ebfaf2; --row-active: #d9f4e6;
    --shadow-1: 0 1px 2px rgba(5,150,106,.08);
    --shadow-2: 0 4px 12px rgba(5,150,106,.12);
    --shadow-3: 0 18px 48px rgba(5,150,106,.20);
    --overlay: rgba(4,55,42,.42);
    --code-key: #9333ea; --code-str: #047857; --code-num: #b45309; --code-com: #71a891; --code-fn: #05966a;
}
[data-theme="purple"] {
    --accent: #7c3aed; --accent-hover: #6d28d9; --accent-soft: #f0e9fe; --accent-border: #ddccfd;
    --accent-contrast: #ffffff;
    --ok: #0f9d58; --ok-soft: #e4f5ec; --ok-border: #b7e3ca;
    --warn: #b7791f; --warn-soft: #fdf5e4; --warn-border: #f0dfae;
    --danger: #d93a3a; --danger-hover: #b62d2d; --danger-soft: #fdecec; --danger-border: #f6c7c7;
    --bg: #faf7ff; --surface: #ffffff; --surface-2: #f4eeff; --surface-3: #ebe1fd;
    --sidebar: #ffffff; --header: #ffffff;
    --text: #2a1150; --text-dim: #6b528f; --text-faint: #9683b5;
    --border: #e5d9fb; --border-strong: #cdb8f6;
    --row-hover: #f6f1ff; --row-active: #ede4fe;
    --shadow-1: 0 1px 2px rgba(124,58,237,.08);
    --shadow-2: 0 4px 12px rgba(124,58,237,.12);
    --shadow-3: 0 18px 48px rgba(124,58,237,.20);
    --overlay: rgba(42,17,80,.42);
    --code-key: #c026d3; --code-str: #0f766e; --code-num: #b45309; --code-com: #9683b5; --code-fn: #7c3aed;
}
[data-theme="sunset"] {
    --accent: #ea6a1e; --accent-hover: #cc5613; --accent-soft: #fdeee2; --accent-border: #f8d3b6;
    --accent-contrast: #ffffff;
    --ok: #0f9d58; --ok-soft: #e4f5ec; --ok-border: #b7e3ca;
    --warn: #b7791f; --warn-soft: #fdf5e4; --warn-border: #f0dfae;
    --danger: #d93a3a; --danger-hover: #b62d2d; --danger-soft: #fdecec; --danger-border: #f6c7c7;
    --bg: #fff8f3; --surface: #ffffff; --surface-2: #fdf0e7; --surface-3: #fbe4d5;
    --sidebar: #ffffff; --header: #ffffff;
    --text: #4a2109; --text-dim: #8a5936; --text-faint: #b3866a;
    --border: #f7ddc9; --border-strong: #f0c3a2;
    --row-hover: #fff4ec; --row-active: #fde8da;
    --shadow-1: 0 1px 2px rgba(234,106,30,.08);
    --shadow-2: 0 4px 12px rgba(234,106,30,.12);
    --shadow-3: 0 18px 48px rgba(234,106,30,.20);
    --overlay: rgba(74,33,9,.42);
    --code-key: #c026d3; --code-str: #0f766e; --code-num: #b45309; --code-com: #b3866a; --code-fn: #ea6a1e;
}

:root {
    --r-sm: 6px; --r: 9px; --r-lg: 14px; --r-full: 999px;
    --sidebar-w: 268px; --topbar-h: 52px;
    --mono: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;
    --sans: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    --ease: cubic-bezier(.32,.72,0,1);
    --spring: cubic-bezier(.34,1.56,.64,1);
    --t-fast: .13s; --t: .2s; --t-slow: .34s;
}

/* ─── Base ─────────────────────────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; }
* { margin: 0; padding: 0; }
html { -webkit-text-size-adjust: 100%; }
body {
    font-family: var(--sans);
    background: var(--bg);
    color: var(--text);
    font-size: 13.5px;
    line-height: 1.5;
    min-height: 100vh;
    -webkit-font-smoothing: antialiased;
    text-rendering: optimizeLegibility;
    overflow-wrap: break-word;
}
a { color: var(--accent); text-decoration: none; }
a:hover { text-decoration: underline; }
h1, h2, h3, h4 { line-height: 1.25; font-weight: 680; letter-spacing: -.015em; }
code, pre, .mono { font-family: var(--mono); font-size: .92em; }
:focus-visible {
    outline: 2px solid var(--accent);
    outline-offset: 2px;
    border-radius: var(--r-sm);
}
::selection { background: var(--accent-soft); color: var(--text); }

::-webkit-scrollbar { width: 10px; height: 10px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb {
    background: var(--border-strong);
    border-radius: var(--r-full);
    border: 3px solid transparent;
    background-clip: content-box;
}
::-webkit-scrollbar-thumb:hover { background: var(--text-faint); background-clip: content-box; }
* { scrollbar-width: thin; scrollbar-color: var(--border-strong) transparent; }

/* ─── Lucide icons + motion ─────────────────────────────────────────────────
   lucide-animated.com ships React components driven by Motion. Reproducing that
   here would mean shipping React into a single-file tool, so the same motion
   vocabulary is expressed in CSS against the stock Lucide geometry. Each icon
   part is tagged .p0/.p1/... by the sprite builder, which is what lets us stagger
   and target individual strokes. */
.ico {
    width: 16px; height: 16px;
    flex: none;
    fill: none;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
    vertical-align: -.15em;
    overflow: visible;
}
.ico * { transform-box: fill-box; transform-origin: center; }
.ico-lg { width: 20px; height: 20px; }
.ico-xl { width: 26px; height: 26px; }

@keyframes ico-settle   { 0%,100% { transform: translateY(0) } 45% { transform: translateY(-1.6px) } }
@keyframes ico-spin     { to { transform: rotate(360deg) } }
@keyframes ico-nudge-r  { 0%,100% { transform: translateX(0) } 50% { transform: translateX(2.2px) } }
@keyframes ico-nudge-l  { 0%,100% { transform: translateX(0) } 50% { transform: translateX(-2.2px) } }
@keyframes ico-nudge-d  { 0%,100% { transform: translateY(0) } 50% { transform: translateY(2.2px) } }
@keyframes ico-nudge-u  { 0%,100% { transform: translateY(0) } 50% { transform: translateY(-2.2px) } }
@keyframes ico-pop      { 0%,100% { transform: scale(1) } 45% { transform: scale(1.18) } }
@keyframes ico-wiggle   { 0%,100% { transform: rotate(0) } 30% { transform: rotate(-11deg) } 65% { transform: rotate(9deg) } }
@keyframes ico-pulse    { 0%,100% { opacity: 1 } 50% { opacity: .45 } }
@keyframes ico-draw     { from { stroke-dashoffset: var(--dash, 64) } to { stroke-dashoffset: 0 } }

/* Hover choreography: parts move in sequence, not all at once. */
.hov:hover .ico [class^="p"], .ico.run [class^="p"] { animation-duration: .5s; animation-timing-function: var(--spring); }
.hov:hover .ico .p1, .ico.run .p1 { animation-delay: .045s; }
.hov:hover .ico .p2, .ico.run .p2 { animation-delay: .09s; }
.hov:hover .ico .p3, .ico.run .p3 { animation-delay: .135s; }

.hov:hover .ico-database [class^="p"] { animation-name: ico-settle; }
.hov:hover .ico-server   [class^="p"] { animation-name: ico-settle; }
.hov:hover .ico-layers   [class^="p"] { animation-name: ico-settle; }
.hov:hover .ico-search   [class^="p"] { animation-name: ico-pop; }
.hov:hover .ico-play     [class^="p"] { animation-name: ico-pop; }
.hov:hover .ico-zap      [class^="p"] { animation-name: ico-pop; }
.hov:hover .ico-plus     [class^="p"] { animation-name: ico-pop; }
.hov:hover .ico-check    [class^="p"] { animation-name: ico-pop; }
.hov:hover .ico-trash-2  [class^="p"] { animation-name: ico-wiggle; }
.hov:hover .ico-settings [class^="p"] { animation-name: ico-spin; animation-duration: 1.1s; animation-timing-function: linear; }
.hov:hover .ico-refresh-cw [class^="p"], .hov:hover .ico-rotate-cw [class^="p"] { animation-name: ico-spin; animation-duration: .7s; animation-timing-function: var(--ease); }
.hov:hover .ico-download [class^="p"] { animation-name: ico-nudge-d; }
.hov:hover .ico-upload   [class^="p"] { animation-name: ico-nudge-u; }
.hov:hover .ico-import   [class^="p"] { animation-name: ico-nudge-d; }
.hov:hover .ico-log-out  [class^="p"] { animation-name: ico-nudge-r; }
.hov:hover .ico-chevron-right [class^="p"], .hov:hover .ico-arrow-right [class^="p"], .hov:hover .ico-chevrons-right [class^="p"] { animation-name: ico-nudge-r; }
.hov:hover .ico-chevron-left [class^="p"], .hov:hover .ico-arrow-left [class^="p"], .hov:hover .ico-chevrons-left [class^="p"] { animation-name: ico-nudge-l; }
.hov:hover .ico-pencil   [class^="p"] { animation-name: ico-wiggle; }
.hov:hover .ico-square-pen [class^="p"] { animation-name: ico-wiggle; }
.hov:hover .ico-copy     [class^="p"] { animation-name: ico-nudge-r; }
.hov:hover .ico-key-round [class^="p"] { animation-name: ico-wiggle; }
.hov:hover .ico-lock     [class^="p"] { animation-name: ico-pop; }
.hov:hover .ico-plug-zap [class^="p"] { animation-name: ico-pop; }
.hov:hover .ico-cable    [class^="p"] { animation-name: ico-pop; }
.hov:hover .ico-filter   [class^="p"], .hov:hover .ico-funnel [class^="p"] { animation-name: ico-nudge-d; }
.hov:hover .ico-terminal [class^="p"] { animation-name: ico-nudge-r; }
.hov:hover .ico-command  [class^="p"] { animation-name: ico-pop; }
.hov:hover .ico-star     [class^="p"], .hov:hover .ico-sparkles [class^="p"] { animation-name: ico-pop; }
.hov:hover .ico-table    [class^="p"], .hov:hover .ico-table-2 [class^="p"], .hov:hover .ico-grid-2x2 [class^="p"] { animation-name: ico-pop; }
.hov:hover .ico-x        [class^="p"] { animation-name: ico-wiggle; }
.hov:hover .ico-save     [class^="p"] { animation-name: ico-pop; }
.hov:hover .ico-history  [class^="p"] { animation-name: ico-spin; animation-duration: .8s; }

.ico-spin { animation: ico-spin .85s linear infinite; }

@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        animation-duration: .001ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: .001ms !important;
        scroll-behavior: auto !important;
    }
}

/* ─── Layout ───────────────────────────────────────────────────────────────── */
.app { display: flex; min-height: 100vh; }
.sidebar {
    width: var(--sidebar-w);
    background: var(--sidebar);
    border-inline-end: 1px solid var(--border);
    display: flex; flex-direction: column;
    position: fixed; inset-block: 0; inset-inline-start: 0;
    z-index: 60;
    transition: transform var(--t) var(--ease);
}
.brand {
    display: flex; align-items: center; gap: 9px;
    padding: 0 14px; height: var(--topbar-h);
    border-bottom: 1px solid var(--border);
    font-weight: 700; font-size: 14.5px; letter-spacing: -.02em;
    flex: none;
}
.brand .ico { color: var(--accent); }
.brand-tag {
    margin-inline-start: auto;
    font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em;
    padding: 2px 6px; border-radius: var(--r-sm);
    background: var(--accent-soft); color: var(--accent);
    border: 1px solid var(--accent-border);
}
/* The nav column itself lays out; only the table list scrolls (see below). The
   auto overflow here is a fallback for very short viewports, where the fixed
   rows alone can outgrow the sidebar. */
.side-scroll {
    flex: 1; min-height: 0; overflow-y: auto; overflow-x: hidden; padding: 8px;
    display: flex; flex-direction: column;
}
.side-scroll > * { flex: none; }
.nav-link {
    display: flex; align-items: center; gap: 9px;
    padding: 7px 10px; border-radius: var(--r-sm);
    color: var(--text); font-weight: 520; font-size: 13px;
    text-decoration: none; white-space: nowrap;
    transition: background var(--t-fast) var(--ease), color var(--t-fast) var(--ease);
}
.nav-link:hover { background: var(--row-hover); text-decoration: none; }
.nav-link .ico { color: var(--text-dim); transition: color var(--t-fast); }
.nav-link:hover .ico { color: var(--accent); }
.nav-link.active { background: var(--accent-soft); color: var(--accent); font-weight: 650; }
.nav-link.active .ico { color: var(--accent); }
.nav-count { margin-inline-start: auto; font-size: 11px; color: var(--text-faint); font-variant-numeric: tabular-nums; }

.nav-group { margin-top: 14px; }

/* Tables group: header and filter stay pinned, the list takes the leftover
   height and scrolls on its own. */
.side-scroll > .nav-group-tables {
    flex: 1; min-height: 0;
    display: flex; flex-direction: column;
}
.nav-group-tables > * { flex: none; }
.nav-group-tables > #tblList {
    flex: 1; min-height: 120px;
    overflow-y: auto; overflow-x: hidden;
}
.nav-head {
    display: flex; align-items: center; gap: 6px;
    padding: 4px 10px; margin-bottom: 2px;
    font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em;
    color: var(--text-faint);
}
.nav-head .ico { width: 13px; height: 13px; }

.tree-item {
    display: flex; align-items: center; gap: 8px;
    padding: 5.5px 10px 5.5px 12px;
    border-radius: var(--r-sm);
    font-size: 12.5px; color: var(--text-dim);
    text-decoration: none;
    white-space: nowrap; overflow: hidden;
    transition: background var(--t-fast) var(--ease), color var(--t-fast) var(--ease);
}
.tree-item:hover { background: var(--row-hover); color: var(--text); text-decoration: none; }
.tree-item.active { background: var(--accent-soft); color: var(--accent); font-weight: 650; }
.tree-item .ico { width: 14px; height: 14px; opacity: .75; }
.tree-item.active .ico { opacity: 1; }
.tree-item .lbl { overflow: hidden; text-overflow: ellipsis; min-width: 0; flex: 1; }
.tree-item .kind {
    font-size: 9.5px; text-transform: uppercase; letter-spacing: .04em;
    color: var(--text-faint); font-weight: 700;
}

.side-foot { padding: 9px; border-top: 1px solid var(--border); flex: none; display: grid; gap: 7px; }
.conn-chip {
    display: flex; align-items: center; gap: 7px;
    padding: 7px 9px; border-radius: var(--r-sm);
    background: var(--surface-2); border: 1px solid var(--border);
    font-size: 11.5px; color: var(--text-dim);
    min-width: 0;
}
.conn-chip .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--ok); flex: none; }
.conn-chip .dot.warn { background: var(--warn); }
.conn-chip .txt { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.main { flex: 1; margin-inline-start: var(--sidebar-w); min-width: 0; display: flex; flex-direction: column; }
.topbar {
    height: var(--topbar-h);
    background: color-mix(in srgb, var(--header) 82%, transparent);
    backdrop-filter: saturate(180%) blur(12px);
    -webkit-backdrop-filter: saturate(180%) blur(12px);
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 12px;
    padding: 0 16px;
    position: sticky; top: 0; z-index: 40;
}
.crumbs { display: flex; align-items: center; gap: 5px; font-size: 12.5px; min-width: 0; overflow: hidden; }
.crumbs a { color: var(--text-dim); text-decoration: none; white-space: nowrap; padding: 3px 5px; border-radius: var(--r-sm); }
.crumbs a:hover { color: var(--text); background: var(--row-hover); text-decoration: none; }
.crumbs .sep { color: var(--text-faint); }
.crumbs strong { color: var(--text); font-weight: 650; white-space: nowrap; }
.topbar-right { margin-inline-start: auto; display: flex; align-items: center; gap: 7px; }

.content { padding: 20px; flex: 1; width: 100%; max-width: 1560px; margin-inline: auto; }
.page-head { display: flex; align-items: flex-start; gap: 14px; flex-wrap: wrap; margin-bottom: 16px; }
.page-head h2 { font-size: 19px; }
.page-head .sub { color: var(--text-dim); font-size: 12.5px; margin-top: 2px; }
.page-head .acts { margin-inline-start: auto; display: flex; gap: 7px; flex-wrap: wrap; }

/* ─── Cards ────────────────────────────────────────────────────────────────── */
.card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--r);
    margin-bottom: 16px;
    box-shadow: var(--shadow-1);
    overflow: hidden;
}
.card-head {
    padding: 11px 15px;
    border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 10px;
    background: var(--surface);
}
.card-head h3 { font-size: 13.5px; font-weight: 650; }
.card-head .right { margin-inline-start: auto; display: flex; align-items: center; gap: 7px; font-size: 12px; color: var(--text-dim); }
.card-body { padding: 15px; }
.card.danger { border-color: var(--danger-border); }
.card.danger .card-head { background: var(--danger-soft); color: var(--danger); border-bottom-color: var(--danger-border); }

/* ─── Buttons ──────────────────────────────────────────────────────────────── */
.btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    padding: 6.5px 12px;
    border-radius: var(--r-sm);
    font: inherit; font-size: 12.5px; font-weight: 600;
    line-height: 1.2;
    cursor: pointer;
    border: 1px solid transparent;
    text-decoration: none;
    white-space: nowrap;
    user-select: none;
    transition: background var(--t-fast) var(--ease), border-color var(--t-fast) var(--ease),
                color var(--t-fast) var(--ease), transform var(--t-fast) var(--ease),
                box-shadow var(--t-fast) var(--ease);
}
.btn:hover { text-decoration: none; }
.btn:active { transform: scale(.975); }
.btn:disabled, .btn[aria-disabled="true"] { opacity: .5; pointer-events: none; }
.btn-primary { background: var(--accent); color: var(--accent-contrast); box-shadow: var(--shadow-1); }
.btn-primary:hover { background: var(--accent-hover); box-shadow: var(--shadow-2); }
.btn-default { background: var(--surface); color: var(--text); border-color: var(--border-strong); }
.btn-default:hover { background: var(--surface-2); border-color: var(--text-faint); }
.btn-ghost { background: transparent; color: var(--text-dim); }
.btn-ghost:hover { background: var(--row-hover); color: var(--text); }
.btn-danger { background: var(--danger); color: #fff; }
.btn-danger:hover { background: var(--danger-hover); }
.btn-danger-soft { background: var(--danger-soft); color: var(--danger); border-color: var(--danger-border); }
.btn-danger-soft:hover { background: var(--danger); color: #fff; }
.btn-sm { padding: 3.5px 8px; font-size: 11.5px; gap: 4px; }
.btn-sm .ico { width: 13px; height: 13px; }
.btn-icon { padding: 6px; }
.btn-block { width: 100%; }

.btn-group { display: inline-flex; }
.btn-group .btn { border-radius: 0; margin-inline-start: -1px; }
.btn-group .btn:first-child { border-start-start-radius: var(--r-sm); border-end-start-radius: var(--r-sm); margin-inline-start: 0; }
.btn-group .btn:last-child { border-start-end-radius: var(--r-sm); border-end-end-radius: var(--r-sm); }

kbd {
    display: inline-block;
    padding: 1px 5px;
    font-family: var(--sans); font-size: 10.5px; font-weight: 600;
    color: var(--text-dim);
    background: var(--surface-2);
    border: 1px solid var(--border-strong);
    border-bottom-width: 2px;
    border-radius: 4px;
    line-height: 1.5;
}

/* ─── Forms ────────────────────────────────────────────────────────────────── */
.field { margin-bottom: 12px; }
.field > label, .lbl-text {
    display: block; margin-bottom: 4px;
    font-size: 12px; font-weight: 620; color: var(--text);
}
.field .hint { font-size: 11.5px; color: var(--text-faint); margin-top: 4px; line-height: 1.45; }
.input, input[type=text], input[type=password], input[type=number], input[type=search],
input[type=email], input[type=file], select, textarea {
    width: 100%;
    padding: 7.5px 10px;
    font: inherit; font-size: 13px;
    color: var(--text);
    background: var(--surface);
    border: 1px solid var(--border-strong);
    border-radius: var(--r-sm);
    transition: border-color var(--t-fast) var(--ease), box-shadow var(--t-fast) var(--ease), background var(--t-fast);
}
textarea { resize: vertical; min-height: 70px; }
select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%238b95a3' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 8px center;
    background-size: 14px;
    padding-inline-end: 28px;
}
[dir=rtl] select { background-position: left 8px center; padding-inline-end: 10px; padding-inline-start: 28px; }
.input:focus, input:focus, select:focus, textarea:focus {
    outline: none;
    border-color: var(--accent);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 18%, transparent);
}
input::placeholder, textarea::placeholder { color: var(--text-faint); }
input[type=checkbox], input[type=radio] { width: auto; accent-color: var(--accent); cursor: pointer; }
.input-sm { padding: 4px 8px; font-size: 12px; }
.check { display: inline-flex; align-items: center; gap: 7px; font-size: 12.5px; cursor: pointer; user-select: none; font-weight: 500; }
.row { display: flex; gap: 10px; flex-wrap: wrap; }
.row > * { flex: 1; min-width: 130px; }

.switch { position: relative; display: inline-flex; width: 34px; height: 19px; flex: none; }
.switch input { position: absolute; opacity: 0; width: 0; height: 0; }
.switch .track {
    position: absolute; inset: 0;
    background: var(--border-strong);
    border-radius: var(--r-full);
    transition: background var(--t) var(--ease);
}
.switch .track::before {
    content: ""; position: absolute;
    width: 13px; height: 13px; left: 3px; top: 3px;
    background: #fff; border-radius: 50%;
    box-shadow: var(--shadow-1);
    transition: transform var(--t) var(--spring);
}
.switch input:checked + .track { background: var(--accent); }
.switch input:checked + .track::before { transform: translateX(15px); }
.switch input:focus-visible + .track { box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 25%, transparent); }

.toggle-box {
    display: flex; align-items: center; justify-content: space-between; gap: 10px;
    padding: 8px 11px;
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: var(--r-sm);
    cursor: pointer; user-select: none;
    transition: border-color var(--t-fast) var(--ease);
}
.toggle-box:hover { border-color: var(--accent-border); }

/* ─── Tabs ─────────────────────────────────────────────────────────────────── */
.tabs {
    display: flex; gap: 2px;
    padding: 3px;
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: var(--r);
}
.tab {
    flex: 1;
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    padding: 6px 10px;
    font: inherit; font-size: 12px; font-weight: 620;
    color: var(--text-dim);
    background: transparent; border: 0;
    border-radius: var(--r-sm);
    cursor: pointer;
    transition: background var(--t-fast) var(--ease), color var(--t-fast) var(--ease);
}
.tab:hover { color: var(--text); }
.tab.active { background: var(--surface); color: var(--accent); box-shadow: var(--shadow-1); }

.subnav { display: flex; gap: 3px; border-bottom: 1px solid var(--border); margin-bottom: 16px; overflow-x: auto; }
.subnav a {
    padding: 8px 12px;
    font-size: 12.5px; font-weight: 600;
    color: var(--text-dim); text-decoration: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
    display: inline-flex; align-items: center; gap: 6px;
    white-space: nowrap;
    transition: color var(--t-fast) var(--ease), border-color var(--t-fast) var(--ease);
}
.subnav a:hover { color: var(--text); text-decoration: none; }
.subnav a.active { color: var(--accent); border-bottom-color: var(--accent); }

/* ─── Tables ───────────────────────────────────────────────────────────────── */
.tbl-wrap { width: 100%; overflow: auto; max-height: calc(100vh - 250px); overscroll-behavior: contain; }
.tbl { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 12.5px; }
.tbl th, .tbl td { padding: 7px 12px; text-align: start; border-bottom: 1px solid var(--border); }
.tbl thead th {
    position: sticky; top: 0; z-index: 2;
    background: var(--surface-2);
    font-weight: 650; font-size: 11.5px;
    color: var(--text-dim);
    white-space: nowrap;
    border-bottom: 1px solid var(--border-strong);
}
.tbl thead th a { color: inherit; display: inline-flex; align-items: center; gap: 4px; text-decoration: none; }
.tbl thead th a:hover { color: var(--accent); text-decoration: none; }
.tbl tbody tr { transition: background var(--t-fast) var(--ease); }
.tbl tbody tr:hover { background: var(--row-hover); }
.tbl tbody tr:last-child td { border-bottom: 0; }
.tbl tfoot td { background: var(--surface-2); font-weight: 650; border-top: 1px solid var(--border-strong); border-bottom: 0; }
.tbl .num { text-align: end; font-variant-numeric: tabular-nums; }
.tbl .nowrap { white-space: nowrap; }
.tbl .acts { text-align: end; white-space: nowrap; }
.tbl .acts .btn { opacity: .55; transition: opacity var(--t-fast); }
.tbl tr:hover .acts .btn, .tbl .acts .btn:focus-visible { opacity: 1; }
.tbl .pick { width: 34px; text-align: center; }

.cell { max-width: 380px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cell-num { font-variant-numeric: tabular-nums; text-align: end; }
.cell-edit { cursor: text; border-radius: 3px; }
.cell-edit:hover { box-shadow: inset 0 0 0 1px var(--accent-border); background: var(--accent-soft); }
.cell-input {
    width: 100%; padding: 2px 5px; font: inherit;
    border: 1px solid var(--accent); border-radius: 3px;
    background: var(--surface); color: var(--text);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 18%, transparent);
}
.cell.saving { opacity: .45; }
@keyframes flash-ok { from { background: color-mix(in srgb, var(--ok) 32%, transparent) } to { background: transparent } }
.cell.saved { animation: flash-ok .9s var(--ease); }

.badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 1.5px 6.5px;
    border-radius: var(--r-sm);
    font-size: 10.5px; font-weight: 650;
    background: var(--surface-3); color: var(--text-dim);
    border: 1px solid transparent;
    white-space: nowrap;
}
.badge-accent { background: var(--accent-soft); color: var(--accent); border-color: var(--accent-border); }
.badge-ok { background: var(--ok-soft); color: var(--ok); border-color: var(--ok-border); }
.badge-warn { background: var(--warn-soft); color: var(--warn); border-color: var(--warn-border); }
.badge-danger { background: var(--danger-soft); color: var(--danger); border-color: var(--danger-border); }
.badge-null { background: transparent; color: var(--text-faint); font-style: italic; border: 1px dashed var(--border-strong); }
.approx { color: var(--text-faint); font-weight: 400; }

.empty { padding: 44px 20px; text-align: center; color: var(--text-dim); }
.empty .ico { width: 34px; height: 34px; color: var(--text-faint); margin-bottom: 10px; stroke-width: 1.5; }
.empty p { font-size: 13px; margin-bottom: 3px; font-weight: 600; color: var(--text); }
.empty span { font-size: 12.5px; }

/* ─── Alerts & toasts ──────────────────────────────────────────────────────── */
.alert {
    display: flex; align-items: flex-start; gap: 9px;
    padding: 10px 13px;
    border-radius: var(--r);
    margin-bottom: 14px;
    font-size: 12.5px;
    border: 1px solid transparent;
    animation: slide-down var(--t-slow) var(--ease);
}
@keyframes slide-down { from { opacity: 0; transform: translateY(-7px) } to { opacity: 1; transform: none } }
.alert .ico { margin-top: 1px; flex: none; }
.alert-ok { background: var(--ok-soft); color: var(--ok); border-color: var(--ok-border); }
.alert-error { background: var(--danger-soft); color: var(--danger); border-color: var(--danger-border); }
.alert-warn { background: var(--warn-soft); color: var(--warn); border-color: var(--warn-border); }
.alert-info { background: var(--accent-soft); color: var(--accent); border-color: var(--accent-border); }
.alert pre { white-space: pre-wrap; font-size: 11.5px; margin-top: 6px; opacity: .9; }
.alert .close { margin-inline-start: auto; background: none; border: 0; color: inherit; cursor: pointer; opacity: .6; padding: 0; }
.alert .close:hover { opacity: 1; }

#toasts {
    position: fixed; z-index: 200;
    bottom: 16px; inset-inline-end: 16px;
    display: flex; flex-direction: column; gap: 8px;
    pointer-events: none;
    max-width: min(400px, calc(100vw - 32px));
}
.toast {
    display: flex; align-items: flex-start; gap: 9px;
    padding: 10px 13px;
    background: var(--surface);
    border: 1px solid var(--border-strong);
    border-radius: var(--r);
    box-shadow: var(--shadow-3);
    font-size: 12.5px;
    pointer-events: auto;
    animation: toast-in var(--t-slow) var(--spring);
}
.toast.out { animation: toast-out var(--t) var(--ease) forwards; }
@keyframes toast-in { from { opacity: 0; transform: translateY(12px) scale(.96) } to { opacity: 1; transform: none } }
@keyframes toast-out { to { opacity: 0; transform: translateX(18px) } }
.toast.ok .ico { color: var(--ok); }
.toast.error .ico { color: var(--danger); }
.toast.info .ico { color: var(--accent); }

/* ─── Modal ────────────────────────────────────────────────────────────────── */
.modal {
    position: fixed; inset: 0; z-index: 150;
    background: var(--overlay);
    backdrop-filter: blur(3px);
    display: flex; align-items: center; justify-content: center;
    padding: 18px;
    opacity: 0; visibility: hidden;
    transition: opacity var(--t) var(--ease), visibility var(--t);
}
.modal.open { opacity: 1; visibility: visible; }
.modal-box {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--r-lg);
    box-shadow: var(--shadow-3);
    width: 100%; max-width: 540px;
    max-height: calc(100vh - 36px);
    display: flex; flex-direction: column;
    transform: translateY(10px) scale(.985);
    transition: transform var(--t-slow) var(--spring);
}
.modal.open .modal-box { transform: none; }
.modal-box.wide { max-width: 760px; }
.modal-head { padding: 14px 16px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; }
.modal-head h3 { font-size: 14.5px; }
.modal-head .close { margin-inline-start: auto; }
.modal-body { padding: 16px; overflow-y: auto; }
.modal-foot { padding: 12px 16px; border-top: 1px solid var(--border); display: flex; gap: 8px; justify-content: flex-end; background: var(--surface-2); }

/* ─── Command palette ──────────────────────────────────────────────────────── */
#palette {
    position: fixed; inset: 0; z-index: 180;
    background: var(--overlay);
    backdrop-filter: blur(4px);
    display: flex; align-items: flex-start; justify-content: center;
    padding: 12vh 18px 18px;
    opacity: 0; visibility: hidden;
    transition: opacity var(--t) var(--ease), visibility var(--t);
}
#palette.open { opacity: 1; visibility: visible; }
.pal-box {
    width: 100%; max-width: 580px;
    background: var(--surface);
    border: 1px solid var(--border-strong);
    border-radius: var(--r-lg);
    box-shadow: var(--shadow-3);
    overflow: hidden;
    display: flex; flex-direction: column;
    max-height: 62vh;
    transform: translateY(-10px) scale(.985);
    transition: transform var(--t-slow) var(--spring);
}
#palette.open .pal-box { transform: none; }
.pal-input-wrap { display: flex; align-items: center; gap: 10px; padding: 13px 15px; border-bottom: 1px solid var(--border); }
.pal-input-wrap .ico { color: var(--text-faint); }
#palInput {
    flex: 1; border: 0; background: none; padding: 0;
    font-size: 14.5px; color: var(--text);
}
#palInput:focus { outline: none; box-shadow: none; }
.pal-list { overflow-y: auto; padding: 6px; }
.pal-item {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 11px;
    border-radius: var(--r-sm);
    cursor: pointer;
    color: var(--text); text-decoration: none;
    font-size: 13px;
}
.pal-item:hover, .pal-item.sel { background: var(--accent-soft); text-decoration: none; }
.pal-item.sel { box-shadow: inset 0 0 0 1px var(--accent-border); }
.pal-item .ico { color: var(--text-dim); }
.pal-item.sel .ico { color: var(--accent); }
.pal-item .nm { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pal-item .nm mark { background: color-mix(in srgb, var(--accent) 26%, transparent); color: inherit; border-radius: 2px; padding: 0 1px; }
.pal-item .ctx { font-size: 11px; color: var(--text-faint); white-space: nowrap; }
.pal-foot {
    padding: 8px 13px; border-top: 1px solid var(--border);
    background: var(--surface-2);
    display: flex; gap: 12px; align-items: center;
    font-size: 11px; color: var(--text-faint);
}
.pal-empty { padding: 30px; text-align: center; color: var(--text-faint); font-size: 12.5px; }

/* ─── SQL editor ───────────────────────────────────────────────────────────── */
.sql-editor { position: relative; border: 1px solid var(--border-strong); border-radius: var(--r); overflow: hidden; background: var(--surface); }
.sql-editor:focus-within { border-color: var(--accent); box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 18%, transparent); }
.sql-stack { position: relative; }
#sqlHighlight, #sqlInput {
    margin: 0; padding: 12px 14px;
    font-family: var(--mono); font-size: 13px; line-height: 1.6;
    white-space: pre-wrap; overflow-wrap: break-word; word-break: break-word;
    tab-size: 2;
    border: 0;
}
#sqlHighlight {
    position: absolute; inset: 0;
    pointer-events: none;
    color: var(--text);
    overflow: hidden;
}
#sqlInput {
    position: relative;
    width: 100%; min-height: 190px;
    background: transparent;
    color: transparent;
    caret-color: var(--text);
    resize: vertical;
    display: block;
}
#sqlInput:focus { outline: none; box-shadow: none; border: 0; }
#sqlInput::selection { background: color-mix(in srgb, var(--accent) 30%, transparent); }
.tok-key { color: var(--code-key); font-weight: 650; }
.tok-str { color: var(--code-str); }
.tok-num { color: var(--code-num); }
.tok-com { color: var(--code-com); font-style: italic; }
.tok-fn  { color: var(--code-fn); }
.sql-bar { display: flex; align-items: center; gap: 8px; padding: 9px 12px; border-top: 1px solid var(--border); background: var(--surface-2); flex-wrap: wrap; }

#sqlAuto {
    position: absolute; z-index: 30;
    background: var(--surface);
    border: 1px solid var(--border-strong);
    border-radius: var(--r-sm);
    box-shadow: var(--shadow-3);
    max-height: 210px; overflow-y: auto;
    min-width: 190px;
    display: none;
    padding: 4px;
}
#sqlAuto.open { display: block; }
.auto-item {
    display: flex; align-items: center; gap: 8px;
    padding: 5px 9px; border-radius: 5px;
    font-size: 12.5px; cursor: pointer;
    font-family: var(--mono);
}
.auto-item.sel, .auto-item:hover { background: var(--accent-soft); color: var(--accent); }
.auto-item .t { margin-inline-start: auto; font-family: var(--sans); font-size: 10px; color: var(--text-faint); }

.result-tabs { display: flex; gap: 3px; padding: 8px 12px 0; overflow-x: auto; border-bottom: 1px solid var(--border); }
.result-tab {
    padding: 6px 11px; border-radius: var(--r-sm) var(--r-sm) 0 0;
    font-size: 12px; font-weight: 600; color: var(--text-dim);
    background: none; border: 1px solid transparent; border-bottom: 0;
    cursor: pointer; white-space: nowrap; margin-bottom: -1px;
}
.result-tab.active { background: var(--surface); color: var(--accent); border-color: var(--border); }
.result-tab.err { color: var(--danger); }

/* ─── Login ────────────────────────────────────────────────────────────────── */
.login-wrap {
    min-height: 100vh;
    min-height: 100dvh;
    display: flex; align-items: center; justify-content: center;
    padding: 16px;
    background:
        radial-gradient(1100px 520px at 50% -10%, color-mix(in srgb, var(--accent) 13%, transparent), transparent 68%),
        var(--bg);
}
/* The card owns any overflow, so the page itself never scrolls. When the SSH
   panel is showing it widens into two columns rather than growing downwards. */
.login-card {
    width: 100%; max-width: 452px;
    max-height: calc(100dvh - 32px);
    overflow-y: auto;
    overscroll-behavior: contain;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--r-lg);
    box-shadow: var(--shadow-3);
    padding: 22px;
    animation: login-in .45s var(--ease);
    transition: max-width .3s var(--ease);
}
.login-card.wide { max-width: 900px; }

.login-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 0 22px;
    align-items: start;
}
.login-card.wide .login-grid { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
.login-card.wide .col-ssh { border-inline-end: 1px solid var(--border); padding-inline-end: 20px; }

/* Too narrow for two columns - fall back to one and let the card scroll. */
@media (max-width: 820px) {
    .login-card.wide { max-width: 452px; }
    .login-card.wide .login-grid { grid-template-columns: minmax(0, 1fr); }
    .login-card.wide .col-ssh { border-inline-end: 0; padding-inline-end: 0; }
}

/* Short viewports: tighten the chrome so the form still fits unscrolled. */
@media (max-height: 800px) {
    .login-card { padding: 16px 18px; }
    .login-head { margin-bottom: 10px; }
    .login-head .mark { width: 34px; height: 34px; margin-bottom: 5px; border-radius: 10px; }
    .login-head .mark .ico { width: 18px; height: 18px; }
    .login-head h1 { font-size: 17px; }
    .login-head p { display: none; }
    .login-card .field { margin-bottom: 7px; }
    .login-card .subpanel { padding: 9px; }
    .login-card .hint { display: none; }
    .login-card #sshKey { min-height: 46px; }
    .login-card .tabs { margin-bottom: 8px !important; }
    .login-card .subpanel > .t { margin-bottom: 6px; }
    .login-card .subpanel .field { margin-bottom: 6px; }
    .login-card .alert { padding: 7px 10px; font-size: 11.5px; margin-bottom: 9px; }
    .login-card .alert-info { font-size: 11px; }
    .login-card details.subpanel { margin-bottom: 8px !important; }
}
@media (max-height: 640px) {
    .login-wrap { align-items: flex-start; padding: 10px; }
    .login-card { max-height: calc(100dvh - 20px); }
    .login-card .tabs { margin-bottom: 8px !important; }
}
@keyframes login-in { from { opacity: 0; transform: translateY(10px) scale(.99) } to { opacity: 1; transform: none } }
.login-head { text-align: center; margin-bottom: 16px; }
.login-head .mark {
    display: inline-flex; align-items: center; justify-content: center;
    width: 42px; height: 42px; margin-bottom: 9px;
    border-radius: 12px;
    background: var(--accent-soft); color: var(--accent);
    border: 1px solid var(--accent-border);
}
.login-head .mark .ico { width: 22px; height: 22px; }
.login-head h1 { font-size: 19px; letter-spacing: -.025em; }
.login-head p { font-size: 12.5px; color: var(--text-dim); margin-top: 2px; }
.login-card .field { margin-bottom: 10px; }
.pane { display: none; }
.pane.on { display: block; animation: fade-in var(--t) var(--ease); }
@keyframes fade-in { from { opacity: 0 } to { opacity: 1 } }
.subpanel { padding: 11px; background: var(--surface-2); border: 1px solid var(--border); border-radius: var(--r); margin-bottom: 10px; }
.subpanel > .t { font-size: 11.5px; font-weight: 700; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; color: var(--text); }

.ssh-step-indicator {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-bottom: 14px;
}
.step-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 11px;
    border-radius: var(--r-full);
    font-size: 11.5px;
    font-weight: 600;
    color: var(--text-faint);
    background: var(--surface-2);
    border: 1px solid var(--border);
    transition: all var(--t-fast) var(--ease);
}
.step-pill .step-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 17px;
    height: 17px;
    border-radius: 50%;
    font-size: 10px;
    font-weight: 700;
    background: var(--border-strong);
    color: var(--text);
}
.step-pill.active {
    background: var(--accent-soft);
    color: var(--accent);
    border-color: var(--accent-border);
}
.step-pill.active .step-num {
    background: var(--accent);
    color: var(--accent-contrast);
}
.step-pill.done {
    background: var(--ok-soft);
    color: var(--ok);
    border-color: var(--ok-border);
}
.step-pill.done .step-num {
    background: var(--ok);
    color: #fff;
}
.step-sep {
    color: var(--text-faint);
    display: flex;
    align-items: center;
}
.step-sep .ico { width: 12px; height: 12px; }

.ssh-active-chip {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding: 8px 11px;
    border-radius: var(--r-sm);
    background: var(--ok-soft);
    border: 1px solid var(--ok-border);
    margin-bottom: 12px;
    font-size: 12px;
    color: var(--ok);
}
.ssh-active-info {
    display: flex;
    align-items: center;
    gap: 7px;
    min-width: 0;
    flex: 1;
    color: var(--text);
}
.ssh-active-info .dot {
    width: 7px; height: 7px; border-radius: 50%; background: var(--ok); flex: none;
}
.ssh-active-chip .btn {
    flex: none;
    color: var(--danger);
    padding: 3px 8px;
}
.ssh-active-chip .btn:hover {
    background: var(--danger-soft);
    color: var(--danger-hover);
}

/* ─── Utility ──────────────────────────────────────────────────────────────── */
.skeleton {
    background: linear-gradient(90deg, var(--surface-2) 25%, var(--surface-3) 50%, var(--surface-2) 75%);
    background-size: 200% 100%;
    animation: shimmer 1.3s infinite linear;
    border-radius: 4px;
    color: transparent !important;
    display: inline-block;
    min-width: 34px;
}
@keyframes shimmer { to { background-position: -200% 0 } }
.spin { animation: ico-spin .85s linear infinite; }
.muted { color: var(--text-dim); }
.faint { color: var(--text-faint); }
.small { font-size: 11.5px; }
.mt { margin-top: 12px; }
.flex { display: flex; align-items: center; gap: 8px; }
.flex-wrap { flex-wrap: wrap; }
.grow { flex: 1; min-width: 0; }
.right { margin-inline-start: auto; }
.hidden { display: none !important; }
.ellipsis { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.stat-row { display: flex; gap: 18px; flex-wrap: wrap; font-size: 12.5px; color: var(--text-dim); }
.stat-row b { color: var(--text); font-weight: 650; font-variant-numeric: tabular-nums; }

.sidebar-toggle { display: none; }
.scrim { display: none; }

/* ─── Responsive ───────────────────────────────────────────────────────────── */
@media (max-width: 1000px) {
    .sidebar { transform: translateX(-100%); box-shadow: var(--shadow-3); }
    [dir=rtl] .sidebar { transform: translateX(100%); }
    body.nav-open .sidebar { transform: none; }
    .main { margin-inline-start: 0; }
    .sidebar-toggle { display: inline-flex; }
    body.nav-open .scrim {
        display: block; position: fixed; inset: 0; z-index: 55;
        background: var(--overlay); backdrop-filter: blur(2px);
    }
    .content { padding: 14px; }
    .tbl-wrap { max-height: none; }
}
@media (max-width: 640px) {
    .page-head .acts { width: 100%; }
    .topbar { padding: 0 10px; gap: 8px; }
    .crumbs { font-size: 12px; }
    .hide-sm { display: none !important; }
    .modal { padding: 0; align-items: flex-end; }
    .modal-box { max-width: none; border-radius: var(--r-lg) var(--r-lg) 0 0; max-height: 88vh; }
    #palette { padding: 0; align-items: flex-end; }
    .pal-box { max-width: none; border-radius: var(--r-lg) var(--r-lg) 0 0; max-height: 78vh; }
}
@media print {
    .sidebar, .topbar, .btn, #toasts, .subnav { display: none !important; }
    .main { margin: 0; }
    .card { box-shadow: none; break-inside: avoid; }
}

</style>
</head>
<body>
<svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true"><symbol id="i-arrow-left" viewBox="0 0 24 24"><path class="p0" d="m12 19-7-7 7-7" /><path class="p1" d="M19 12H5" /></symbol><symbol id="i-arrow-right" viewBox="0 0 24 24"><path class="p0" d="M5 12h14" /><path class="p1" d="m12 5 7 7-7 7" /></symbol><symbol id="i-bookmark" viewBox="0 0 24 24"><path class="p0" d="M17 3a2 2 0 0 1 2 2v15a1 1 0 0 1-1.496.868l-4.512-2.578a2 2 0 0 0-1.984 0l-4.512 2.578A1 1 0 0 1 5 20V5a2 2 0 0 1 2-2z" /></symbol><symbol id="i-braces" viewBox="0 0 24 24"><path class="p0" d="M8 3H7a2 2 0 0 0-2 2v5a2 2 0 0 1-2 2 2 2 0 0 1 2 2v5c0 1.1.9 2 2 2h1" /><path class="p1" d="M16 21h1a2 2 0 0 0 2-2v-5c0-1.1.9-2 2-2a2 2 0 0 1-2-2V5a2 2 0 0 0-2-2h-1" /></symbol><symbol id="i-cable" viewBox="0 0 24 24"><path class="p0" d="M17 19a1 1 0 0 1-1-1v-2a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2a1 1 0 0 1-1 1z" /><path class="p1" d="M17 21v-2" /><path class="p2" d="M19 14V6.5a1 1 0 0 0-7 0v11a1 1 0 0 1-7 0V10" /><path class="p3" d="M21 21v-2" /><path class="p4" d="M3 5V3" /><path class="p5" d="M4 10a2 2 0 0 1-2-2V6a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2a2 2 0 0 1-2 2z" /><path class="p6" d="M7 5V3" /></symbol><symbol id="i-check" viewBox="0 0 24 24"><path class="p0" d="M20 6 9 17l-5-5" /></symbol><symbol id="i-chevron-left" viewBox="0 0 24 24"><path class="p0" d="m15 18-6-6 6-6" /></symbol><symbol id="i-chevron-right" viewBox="0 0 24 24"><path class="p0" d="m9 18 6-6-6-6" /></symbol><symbol id="i-chevrons-left" viewBox="0 0 24 24"><path class="p0" d="m11 17-5-5 5-5" /><path class="p1" d="m18 17-5-5 5-5" /></symbol><symbol id="i-chevrons-right" viewBox="0 0 24 24"><path class="p0" d="m6 17 5-5-5-5" /><path class="p1" d="m13 17 5-5-5-5" /></symbol><symbol id="i-circle-alert" viewBox="0 0 24 24"><circle class="p0" cx="12" cy="12" r="10" /><line class="p1" x1="12" x2="12" y1="8" y2="12" /><line class="p2" x1="12" x2="12.01" y1="16" y2="16" /></symbol><symbol id="i-circle-check" viewBox="0 0 24 24"><circle class="p0" cx="12" cy="12" r="10" /><path class="p1" d="m9 12 2 2 4-4" /></symbol><symbol id="i-circle-help" viewBox="0 0 24 24"><circle class="p0" cx="12" cy="12" r="10" /><path class="p1" d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3" /><path class="p2" d="M12 17h.01" /></symbol><symbol id="i-circle-x" viewBox="0 0 24 24"><circle class="p0" cx="12" cy="12" r="10" /><path class="p1" d="m15 9-6 6" /><path class="p2" d="m9 9 6 6" /></symbol><symbol id="i-columns-3" viewBox="0 0 24 24"><rect class="p0" width="18" height="18" x="3" y="3" rx="2" /><path class="p1" d="M9 3v18" /><path class="p2" d="M15 3v18" /></symbol><symbol id="i-command" viewBox="0 0 24 24"><path class="p0" d="M15 6v12a3 3 0 1 0 3-3H6a3 3 0 1 0 3 3V6a3 3 0 1 0-3 3h12a3 3 0 1 0-3-3" /></symbol><symbol id="i-copy" viewBox="0 0 24 24"><rect class="p0" width="14" height="14" x="8" y="8" rx="2" ry="2" /><path class="p1" d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2" /></symbol><symbol id="i-database" viewBox="0 0 24 24"><ellipse class="p0" cx="12" cy="5" rx="9" ry="3" /><path class="p1" d="M3 5V19A9 3 0 0 0 21 19V5" /><path class="p2" d="M3 12A9 3 0 0 0 21 12" /></symbol><symbol id="i-download" viewBox="0 0 24 24"><path class="p0" d="M12 15V3" /><path class="p1" d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><path class="p2" d="m7 10 5 5 5-5" /></symbol><symbol id="i-eye" viewBox="0 0 24 24"><path class="p0" d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0" /><circle class="p1" cx="12" cy="12" r="3" /></symbol><symbol id="i-file-code-2" viewBox="0 0 24 24"><path class="p0" d="M4 12.15V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.706.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2h-3.35" /><path class="p1" d="M14 2v5a1 1 0 0 0 1 1h5" /><path class="p2" d="m5 16-3 3 3 3" /><path class="p3" d="m9 22 3-3-3-3" /></symbol><symbol id="i-filter" viewBox="0 0 24 24"><path class="p0" d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z" /></symbol><symbol id="i-funnel" viewBox="0 0 24 24"><path class="p0" d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z" /></symbol><symbol id="i-git-branch" viewBox="0 0 24 24"><path class="p0" d="M15 6a9 9 0 0 0-9 9V3" /><circle class="p1" cx="18" cy="6" r="3" /><circle class="p2" cx="6" cy="18" r="3" /></symbol><symbol id="i-grid-2x2" viewBox="0 0 24 24"><path class="p0" d="M12 3v18" /><path class="p1" d="M3 12h18" /><rect class="p2" x="3" y="3" width="18" height="18" rx="2" /></symbol><symbol id="i-history" viewBox="0 0 24 24"><path class="p0" d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" /><path class="p1" d="M3 3v5h5" /><path class="p2" d="M12 7v5l4 2" /></symbol><symbol id="i-import" viewBox="0 0 24 24"><path class="p0" d="M12 3v12" /><path class="p1" d="m8 11 4 4 4-4" /><path class="p2" d="M8 5H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-4" /></symbol><symbol id="i-info" viewBox="0 0 24 24"><circle class="p0" cx="12" cy="12" r="10" /><path class="p1" d="M12 16v-4" /><path class="p2" d="M12 8h.01" /></symbol><symbol id="i-key-round" viewBox="0 0 24 24"><path class="p0" d="M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z" /><circle class="p1" cx="16.5" cy="7.5" r=".5" fill="currentColor" /></symbol><symbol id="i-layers" viewBox="0 0 24 24"><path class="p0" d="M12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83z" /><path class="p1" d="M2 12a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 12" /><path class="p2" d="M2 17a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 17" /></symbol><symbol id="i-link" viewBox="0 0 24 24"><path class="p0" d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" /><path class="p1" d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" /></symbol><symbol id="i-loader-circle" viewBox="0 0 24 24"><path class="p0" d="M21 12a9 9 0 1 1-6.219-8.56" /></symbol><symbol id="i-lock" viewBox="0 0 24 24"><rect class="p0" width="18" height="11" x="3" y="11" rx="2" ry="2" /><path class="p1" d="M7 11V7a5 5 0 0 1 10 0v4" /></symbol><symbol id="i-log-out" viewBox="0 0 24 24"><path class="p0" d="m16 17 5-5-5-5" /><path class="p1" d="M21 12H9" /><path class="p2" d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /></symbol><symbol id="i-panel-left" viewBox="0 0 24 24"><rect class="p0" width="18" height="18" x="3" y="3" rx="2" /><path class="p1" d="M9 3v18" /></symbol><symbol id="i-pencil" viewBox="0 0 24 24"><path class="p0" d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z" /><path class="p1" d="m15 5 4 4" /></symbol><symbol id="i-play" viewBox="0 0 24 24"><path class="p0" d="M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z" /></symbol><symbol id="i-plug-zap" viewBox="0 0 24 24"><path class="p0" d="M6.3 20.3a2.4 2.4 0 0 0 3.4 0L12 18l-6-6-2.3 2.3a2.4 2.4 0 0 0 0 3.4Z" /><path class="p1" d="m2 22 3-3" /><path class="p2" d="M7.5 13.5 10 11" /><path class="p3" d="M10.5 16.5 13 14" /><path class="p4" d="m18 3-4 4h6l-4 4" /></symbol><symbol id="i-plus" viewBox="0 0 24 24"><path class="p0" d="M5 12h14" /><path class="p1" d="M12 5v14" /></symbol><symbol id="i-refresh-cw" viewBox="0 0 24 24"><path class="p0" d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8" /><path class="p1" d="M21 3v5h-5" /><path class="p2" d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16" /><path class="p3" d="M8 16H3v5" /></symbol><symbol id="i-rotate-cw" viewBox="0 0 24 24"><path class="p0" d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8" /><path class="p1" d="M21 3v5h-5" /></symbol><symbol id="i-save" viewBox="0 0 24 24"><path class="p0" d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z" /><path class="p1" d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7" /><path class="p2" d="M7 3v4a1 1 0 0 0 1 1h7" /></symbol><symbol id="i-scan-search" viewBox="0 0 24 24"><path class="p0" d="M3 7V5a2 2 0 0 1 2-2h2" /><path class="p1" d="M17 3h2a2 2 0 0 1 2 2v2" /><path class="p2" d="M21 17v2a2 2 0 0 1-2 2h-2" /><path class="p3" d="M7 21H5a2 2 0 0 1-2-2v-2" /><circle class="p4" cx="12" cy="12" r="3" /><path class="p5" d="m16 16-1.9-1.9" /></symbol><symbol id="i-search" viewBox="0 0 24 24"><path class="p0" d="m21 21-4.34-4.34" /><circle class="p1" cx="11" cy="11" r="8" /></symbol><symbol id="i-server" viewBox="0 0 24 24"><rect class="p0" width="20" height="8" x="2" y="2" rx="2" ry="2" /><rect class="p1" width="20" height="8" x="2" y="14" rx="2" ry="2" /><line class="p2" x1="6" x2="6.01" y1="6" y2="6" /><line class="p3" x1="6" x2="6.01" y1="18" y2="18" /></symbol><symbol id="i-settings" viewBox="0 0 24 24"><path class="p0" d="M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915" /><circle class="p1" cx="12" cy="12" r="3" /></symbol><symbol id="i-shield-alert" viewBox="0 0 24 24"><path class="p0" d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" /><path class="p1" d="M12 8v4" /><path class="p2" d="M12 16h.01" /></symbol><symbol id="i-shield-check" viewBox="0 0 24 24"><path class="p0" d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z" /><path class="p1" d="m9 12 2 2 4-4" /></symbol><symbol id="i-sparkles" viewBox="0 0 24 24"><path class="p0" d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z" /><path class="p1" d="M20 2v4" /><path class="p2" d="M22 4h-4" /><circle class="p3" cx="4" cy="20" r="2" /></symbol><symbol id="i-square-pen" viewBox="0 0 24 24"><path class="p0" d="M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" /><path class="p1" d="M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z" /></symbol><symbol id="i-star" viewBox="0 0 24 24"><path class="p0" d="M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z" /></symbol><symbol id="i-table" viewBox="0 0 24 24"><path class="p0" d="M12 3v18" /><rect class="p1" width="18" height="18" x="3" y="3" rx="2" /><path class="p2" d="M3 9h18" /><path class="p3" d="M3 15h18" /></symbol><symbol id="i-table-2" viewBox="0 0 24 24"><path class="p0" d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18" /></symbol><symbol id="i-terminal" viewBox="0 0 24 24"><path class="p0" d="M12 19h8" /><path class="p1" d="m4 17 6-6-6-6" /></symbol><symbol id="i-text-cursor-input" viewBox="0 0 24 24"><path class="p0" d="M12 20h-1a2 2 0 0 1-2-2 2 2 0 0 1-2 2H6" /><path class="p1" d="M13 8h7a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-7" /><path class="p2" d="M5 16H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h1" /><path class="p3" d="M6 4h1a2 2 0 0 1 2 2 2 2 0 0 1 2-2h1" /><path class="p4" d="M9 6v12" /></symbol><symbol id="i-trash-2" viewBox="0 0 24 24"><path class="p0" d="M10 11v6" /><path class="p1" d="M14 11v6" /><path class="p2" d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6" /><path class="p3" d="M3 6h18" /><path class="p4" d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /></symbol><symbol id="i-triangle-alert" viewBox="0 0 24 24"><path class="p0" d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3" /><path class="p1" d="M12 9v4" /><path class="p2" d="M12 17h.01" /></symbol><symbol id="i-unplug" viewBox="0 0 24 24"><path class="p0" d="m19 5 3-3" /><path class="p1" d="m2 22 3-3" /><path class="p2" d="M6.3 20.3a2.4 2.4 0 0 0 3.4 0L12 18l-6-6-2.3 2.3a2.4 2.4 0 0 0 0 3.4Z" /><path class="p3" d="M7.5 13.5 10 11" /><path class="p4" d="M10.5 16.5 13 14" /><path class="p5" d="m12 6 6 6 2.3-2.3a2.4 2.4 0 0 0 0-3.4l-2.6-2.6a2.4 2.4 0 0 0-3.4 0Z" /></symbol><symbol id="i-upload" viewBox="0 0 24 24"><path class="p0" d="M12 3v12" /><path class="p1" d="m17 8-5-5-5 5" /><path class="p2" d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /></symbol><symbol id="i-waypoints" viewBox="0 0 24 24"><path class="p0" d="m10.586 5.414-5.172 5.172" /><path class="p1" d="m18.586 13.414-5.172 5.172" /><path class="p2" d="M6 12h12" /><circle class="p3" cx="12" cy="20" r="2" /><circle class="p4" cx="12" cy="4" r="2" /><circle class="p5" cx="20" cy="12" r="2" /><circle class="p6" cx="4" cy="12" r="2" /></symbol><symbol id="i-wrench" viewBox="0 0 24 24"><path class="p0" d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.106-3.105c.32-.322.863-.22.983.218a6 6 0 0 1-8.259 7.057l-7.91 7.91a1 1 0 0 1-2.999-3l7.91-7.91a6 6 0 0 1 7.057-8.259c.438.12.54.662.219.984z" /></symbol><symbol id="i-x" viewBox="0 0 24 24"><path class="p0" d="M18 6 6 18" /><path class="p1" d="m6 6 12 12" /></symbol><symbol id="i-zap" viewBox="0 0 24 24"><path class="p0" d="M15.914 4a1.5 1.5 0 00-2.474-1.561l-9 9A1.5 1.5 0 005.5 14h4.002a.5.5 0 01.471.666L8.086 20a1.5 1.5 0 002.475 1.56l9-9A1.5 1.5 0 0018.5 10h-3.997a.5.5 0 01-.472-.667z" /></symbol></svg>
<div id="toasts" aria-live="polite" aria-atomic="false"></div>

<?php if (!is_logged_in()): ?>
<?php
    $ssh_ok = SshTunnel::available($ssh_why);
    $vault_ok = Vault::available($vault_why);
    $has_pending_ssh = false;
    $pending_ssh_info = null;
    if (!empty($_SESSION['pending_ssh'])) {
        $st = SshTunnel::status($_SESSION['pending_ssh']);
        if (!empty($st['up'])) {
            $has_pending_ssh = true;
            $pending_ssh_info = $_SESSION['pending_ssh'];
            $pending_ssh_info['local_port'] = $st['port'];
        } else {
            unset($_SESSION['pending_ssh']);
        }
    }
?>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-head">
      <div class="mark"><?php echo ico('database'); ?></div>
      <h1><?php echo h(__('app_name')); ?></h1>
      <p><?php echo h(__('app_tagline')); ?></p>
    </div>

    <?php if ($loop_warning): ?>
      <div class="alert alert-warn">
        <?php echo ico('triangle-alert'); ?>
        <div class="grow"><b>You were signed in, but the session did not stick.</b>
          <div style="margin-top:4px"><?php echo h($loop_warning); ?></div></div>
      </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
      <div class="alert alert-error">
        <?php echo ico('circle-alert'); ?>
        <div class="grow"><?php
            $parts = explode("\n\n", $error_message, 2);
            echo '<div>' . h($parts[0]) . '</div>';
            if (isset($parts[1])) echo '<pre>' . h($parts[1]) . '</pre>';
        ?></div>
      </div>
    <?php endif; ?>

    <div class="tabs" style="margin-bottom:12px" role="tablist">
      <button type="button" class="tab <?php echo (!$has_pending_ssh ? 'active ' : ''); ?>hov" data-pane="direct" role="tab"><?php echo ico('plug-zap'); ?> Direct</button>
      <button type="button" class="tab <?php echo ($has_pending_ssh ? 'active ' : ''); ?>hov" data-pane="ssh" role="tab"><?php echo ico('shield-check'); ?> SSH Tunnel</button>
      <button type="button" class="tab hov" data-pane="saved" role="tab"><?php echo ico('bookmark'); ?> Saved</button>
    </div>

    <!-- Saved connections (encrypted vault) -->
    <div class="pane" id="pane-saved">
      <?php if (!$vault_ok): ?>
        <div class="alert alert-warn"><?php echo ico('triangle-alert'); ?><div><?php echo h($vault_why); ?></div></div>
      <?php else: ?>
        <div class="subpanel">
          <div class="t"><?php echo ico('lock'); ?> Master password</div>
          <div class="flex">
            <input type="password" id="vaultMaster" class="input input-sm grow" placeholder="Unlocks your saved connections" autocomplete="off">
            <button type="button" class="btn btn-default btn-sm hov" id="vaultUnlock"><?php echo ico('key-round'); ?> Unlock</button>
          </div>
          <div class="hint">Connections are encrypted with AES-256-GCM on this server. The master password is never stored &mdash; if you forget it, the saved connections cannot be recovered.</div>
        </div>
        <div id="vaultList"></div>
      <?php endif; ?>
    </div>

    <!-- Paste a connection URL (Direct only) -->
    <div class="pane <?php echo !$has_pending_ssh ? 'on' : ''; ?>" id="pane-uri-holder">
      <details class="subpanel" style="margin-bottom:10px">
        <summary style="cursor:pointer;font-size:11.5px;font-weight:700;display:flex;align-items:center;gap:6px">
          <?php echo ico('link'); ?> Paste a connection URL instead
        </summary>
        <input type="text" id="uriInput" class="input input-sm" style="margin-top:8px"
               placeholder="postgres://user:pass@host:5432/dbname?sslmode=require" autocomplete="off">
        <div class="hint">Fills in the fields below. Also accepts <code>mysql://</code> and <code>sqlite:///path/to.db</code>.</div>
      </details>
    </div>

    <!-- SSH Step Indicator -->
    <div id="sshStepper" class="ssh-step-indicator" style="<?php echo $has_pending_ssh ? '' : 'display:none;'; ?>">
      <div class="step-pill <?php echo $has_pending_ssh ? 'done' : 'active'; ?>" id="stepPill1">
        <span class="step-num">1</span>
        <span class="step-lbl">SSH Bastion</span>
      </div>
      <span class="step-sep"><?php echo ico('chevron-right'); ?></span>
      <div class="step-pill <?php echo $has_pending_ssh ? 'active' : ''; ?>" id="stepPill2">
        <span class="step-num">2</span>
        <span class="step-lbl">Database Login</span>
      </div>
    </div>

    <!-- SSH Step 1 Form -->
    <div id="sshStep1" style="display:none;">
      <?php if (!$ssh_ok): ?>
        <div class="alert alert-warn"><?php echo ico('triangle-alert'); ?><div><?php echo h($ssh_why); ?></div></div>
      <?php endif; ?>
      <div class="subpanel" style="margin-bottom:12px">
        <div class="t"><?php echo ico('shield-check'); ?> SSH Bastion Details</div>
        <div class="row">
          <div class="field" style="flex:3">
            <label for="sshHost">SSH host</label>
            <input type="text" name="ssh_host" id="sshHost" class="input-sm" placeholder="bastion.example.com" value="<?php echo h($pending_ssh_info['host'] ?? ''); ?>">
          </div>
          <div class="field" style="flex:1;min-width:80px">
            <label for="sshPort">Port</label>
            <input type="number" name="ssh_port" id="sshPort" class="input-sm" value="<?php echo h($pending_ssh_info['port'] ?? '22'); ?>">
          </div>
        </div>
        <div class="field">
          <label for="sshUser">SSH username</label>
          <input type="text" name="ssh_user" id="sshUser" class="input-sm" placeholder="ubuntu" value="<?php echo h($pending_ssh_info['user'] ?? ''); ?>">
        </div>
        <div class="field">
          <label for="sshAuth">Authentication</label>
          <select name="ssh_auth" id="sshAuth" class="input-sm">
            <option value="agent">Use this server's ~/.ssh config &amp; agent</option>
            <option value="key">Private key</option>
            <option value="password">Password</option>
          </select>
        </div>

        <div id="sshKeyBox" class="hidden">
          <div class="field">
            <label for="sshKeyMode">Key source</label>
            <select name="ssh_key_mode" id="sshKeyMode" class="input-sm">
              <option value="paste">Paste the key</option>
              <option value="path">Path to a key file on this server</option>
            </select>
          </div>
          <div class="field">
            <textarea name="ssh_key" id="sshKey" class="input-sm" rows="2"
                      placeholder="-----BEGIN OPENSSH PRIVATE KEY-----" spellcheck="false"></textarea>
          </div>
          <div class="field">
            <label for="sshKeyPass">Key passphrase <span class="faint">(if the key has one)</span></label>
            <input type="password" name="ssh_key_pass" id="sshKeyPass" class="input-sm" autocomplete="new-password">
          </div>
        </div>

        <div id="sshPassBox" class="field hidden">
          <label for="sshPass">SSH password</label>
          <input type="password" name="ssh_pass" id="sshPass" class="input-sm" autocomplete="new-password">
          <div class="hint">Handled through <code>SSH_ASKPASS</code>, so nothing extra needs installing and the password never appears in the process list.</div>
        </div>

        <div class="row">
          <div class="field" style="flex:2">
            <label for="targetHost">Remote DB host <span class="faint">(from bastion)</span></label>
            <input type="text" id="targetHost" class="input-sm" placeholder="127.0.0.1" value="127.0.0.1">
          </div>
          <div class="field" style="flex:1;min-width:80px">
            <label for="targetPort">DB Port</label>
            <input type="number" id="targetPort" class="input-sm" placeholder="3306" value="3306">
          </div>
        </div>

        <div class="field" style="margin-bottom:0">
          <label for="sshLocalPort">Local port <span class="faint">(optional)</span></label>
          <input type="number" name="ssh_local_port" id="sshLocalPort" class="input-sm" placeholder="auto">
          <div class="hint">Leave blank to pick a free port automatically.</div>
        </div>
      </div>

      <button type="button" id="sshConnectBtn" class="btn btn-primary btn-block hov" style="margin-top:6px;padding:9px">
        <span class="btn-label">Connect SSH Tunnel</span>
        <?php echo ico('arrow-right'); ?>
      </button>
    </div>

    <!-- Login Form (Step 2 / Direct DB Form) -->
    <form method="post" id="loginForm" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
      <input type="hidden" name="use_ssh" id="useSsh" value="<?php echo $has_pending_ssh ? '1' : '0'; ?>">

      <!-- Active SSH Chip (shown on Step 2) -->
      <div id="sshActiveChip" class="ssh-active-chip" style="<?php echo $has_pending_ssh ? '' : 'display:none;'; ?>">
        <div class="ssh-active-info">
          <span class="dot"></span>
          <div class="grow ellipsis">
            <span style="font-weight:600">SSH Tunnel Active:</span>
            <span id="sshActiveDetails" class="mono"><?php echo h($has_pending_ssh ? (($pending_ssh_info['user'] ? $pending_ssh_info['user'] . '@' : '') . $pending_ssh_info['host'] . ' (port ' . $pending_ssh_info['local_port'] . ')') : ''); ?></span>
          </div>
        </div>
        <button type="button" class="btn btn-ghost btn-sm hov" id="sshDisconnectBtn" title="Disconnect SSH Tunnel">
          <?php echo ico('unplug'); ?> Disconnect
        </button>
      </div>

      <div class="col-db">
        <div class="field">
          <label for="dbType"><?php echo h(__('database_type_label')); ?></label>
          <select name="db_type" id="dbType">
            <option value="mysql">MySQL / MariaDB</option>
            <option value="pgsql">PostgreSQL</option>
            <option value="sqlite">SQLite</option>
          </select>
        </div>

        <div class="row" id="hostRow">
          <div class="field" style="flex:3">
            <label for="dbHost" id="hostLabel"><?php echo h(__('host_label')); ?></label>
            <input type="text" name="db_host" id="dbHost" value="127.0.0.1" required>
          </div>
          <div class="field" style="flex:1;min-width:84px" id="portField">
            <label for="dbPort"><?php echo h(__('port_label')); ?></label>
            <input type="number" name="db_port" id="dbPort" placeholder="3306">
          </div>
        </div>

        <div class="row" id="credRow">
          <div class="field">
            <label for="dbUser"><?php echo h(__('username_label')); ?></label>
            <input type="text" name="db_user" id="dbUser" value="root" autocomplete="username">
          </div>
          <div class="field">
            <label for="dbPass"><?php echo h(__('password_label')); ?></label>
            <input type="password" name="db_pass" id="dbPass" autocomplete="current-password">
          </div>
        </div>

        <div class="row" style="align-items:flex-end">
          <div class="field" style="flex:2">
            <label for="dbName"><?php echo h(__('database_name_label')); ?> <span class="faint">(optional)</span></label>
            <input type="text" name="db_name" id="dbName" placeholder="Leave blank to browse all">
          </div>
          <div class="field" style="flex:1;min-width:120px">
            <label class="toggle-box" for="dbSsl">
              <span class="flex" style="gap:6px;font-size:12px;font-weight:600"><?php echo ico('lock'); ?> SSL</span>
              <span class="switch"><input type="checkbox" name="db_ssl" id="dbSsl" value="1"><span class="track"></span></span>
            </label>
          </div>
        </div>
      </div>

      <button type="submit" name="login" value="1" id="dbConnectBtn" class="btn btn-primary btn-block hov" style="margin-top:6px;padding:9px">
        <span class="btn-label"><?php echo h($has_pending_ssh ? 'Connect to Database' : __('connect_button')); ?></span>
        <?php echo ico('arrow-right'); ?>
      </button>

      <button type="button" id="sshBackBtn" class="btn btn-ghost btn-sm btn-block hov" style="margin-top:6px;<?php echo $has_pending_ssh ? '' : 'display:none;'; ?>">
        <?php echo ico('arrow-left'); ?> Edit SSH settings
      </button>

      <?php if ($vault_ok): ?>
      <details class="subpanel" style="margin-top:12px;margin-bottom:0">
        <summary style="cursor:pointer;font-size:11.5px;font-weight:700;display:flex;align-items:center;gap:6px">
          <?php echo ico('save'); ?> Save these settings for next time
        </summary>
        <div class="row" style="margin-top:8px">
          <input type="text" id="saveName" class="input-sm" placeholder="Name, e.g. Production">
          <input type="password" id="saveMaster" class="input-sm" placeholder="Master password" autocomplete="new-password">
        </div>
        <button type="button" class="btn btn-default btn-sm hov" id="saveProfileBtn" style="margin-top:8px">
          <?php echo ico('save'); ?> Save connection
        </button>
        <div class="hint">Stored encrypted at <code><?php echo h(DABIRO_DATA_DIR); ?></code>. Keep that path outside your web root.</div>
      </details>
      <?php endif; ?>
    </form>
  </div>
</div>

<?php else: /* ───────────────────────── Authenticated shell ───────────────── */ ?>
<div class="scrim" id="scrim"></div>
<div class="app">
  <aside class="sidebar" id="sidebar">
    <div class="brand hov">
      <?php echo ico('database', 'ico-lg ico-database'); ?>
      <span><?php echo h(__('app_name')); ?></span>
      <span class="brand-tag"><?php echo h($engine_label); ?></span>
    </div>

    <nav class="side-scroll">
      <a href="?page=databases" class="nav-link hov <?php echo $page === 'databases' ? 'active' : ''; ?>">
        <?php echo ico('server', 'ico-server'); ?> <span><?php echo h(__('databases')); ?></span>
      </a>
      <a href="<?php echo h(ctx_url(['page' => 'sql'])); ?>" class="nav-link hov <?php echo $page === 'sql' ? 'active' : ''; ?>">
        <?php echo ico('terminal', 'ico-terminal'); ?> <span><?php echo h(__('sql_console')); ?></span>
      </a>
      <a href="<?php echo h(ctx_url(['page' => 'search'])); ?>" class="nav-link hov <?php echo $page === 'search' ? 'active' : ''; ?>">
        <?php echo ico('scan-search', 'ico-search'); ?> <span><?php echo h(__('global_search')); ?></span>
      </a>
      <a href="<?php echo h(ctx_url(['page' => 'import'])); ?>" class="nav-link hov <?php echo $page === 'import' ? 'active' : ''; ?>">
        <?php echo ico('import', 'ico-import'); ?> <span><?php echo h(__('import_data')); ?></span>
      </a>
      <a href="<?php echo h(ctx_url(['page' => 'export'])); ?>" class="nav-link hov <?php echo $page === 'export' ? 'active' : ''; ?>">
        <?php echo ico('download', 'ico-download'); ?> <span><?php echo h(__('export_data')); ?></span>
      </a>

      <?php if ($nav_schemas): ?>
        <div class="nav-group">
          <div class="nav-head"><?php echo ico('git-branch'); ?> <?php echo h(__('schemas')); ?></div>
          <?php foreach ($nav_schemas as $s): ?>
            <a href="<?php echo h('?' . http_build_query(['page' => 'tables', 'db' => $selected_db, 'schema' => $s])); ?>"
               class="tree-item hov <?php echo $s === $selected_schema ? 'active' : ''; ?>">
              <?php echo ico('git-branch'); ?><span class="lbl"><?php echo h($s); ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($selected_db !== ''): ?>
        <div class="nav-group nav-group-tables">
          <div class="nav-head">
            <?php echo ico('table-2'); ?>
            <span class="ellipsis"><?php echo h($selected_db); ?></span>
            <span class="right" id="tblCount"><?php echo count($nav_tables); ?></span>
          </div>
          <?php if (count($nav_tables) > 7): ?>
            <div style="padding:2px 4px 6px">
              <input type="search" id="tblFilter" class="input-sm" placeholder="Filter tables&hellip;" aria-label="Filter tables">
            </div>
          <?php endif; ?>
          <div id="tblList">
            <?php foreach ($nav_tables as $t): ?>
              <a href="<?php echo h(ctx_url(['page' => 'browse', 'table' => $t])); ?>"
                 class="tree-item hov <?php echo $t === $selected_table ? 'active' : ''; ?>"
                 data-name="<?php echo h($t); ?>" title="<?php echo h($t); ?>">
                <?php echo ico('table', 'ico-table'); ?><span class="lbl"><?php echo h($t); ?></span>
              </a>
            <?php endforeach; ?>
          </div>
          <?php if (!$nav_tables): ?>
            <div class="tree-item faint" style="cursor:default">No tables</div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </nav>

    <div class="side-foot">
      <div class="conn-chip" id="connChip" title="<?php echo h(($_SESSION['db']['user'] ?? '') . '@' . ($_SESSION['db']['host'] ?? '')); ?>">
        <span class="dot" id="connDot"></span>
        <span class="txt grow"><?php echo h(($_SESSION['db']['user'] ?? '') . '@' . ($_SESSION['db']['host'] ?? '')); ?></span>
        <?php if (session_ssh_config()): ?><span title="Tunnelled over SSH"><?php echo ico('shield-check'); ?></span><?php endif; ?>
      </div>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
        <button type="submit" name="logout" value="1" class="btn btn-default btn-sm btn-block hov">
          <?php echo ico('log-out', 'ico-log-out'); ?> <?php echo h(__('logout')); ?>
        </button>
      </form>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <button type="button" class="btn btn-ghost btn-icon sidebar-toggle hov" id="navToggle" aria-label="Toggle navigation"><?php echo ico('panel-left'); ?></button>
      <nav class="crumbs" aria-label="Breadcrumb">
        <a href="?page=databases" class="hov"><?php echo ico('server'); ?> <span class="hide-sm"><?php echo h(__('databases')); ?></span></a>
        <?php if ($selected_db !== ''): ?>
          <span class="sep">/</span>
          <a href="<?php echo h('?' . http_build_query(['page' => 'tables', 'db' => $selected_db, 'schema' => $selected_schema])); ?>"><?php echo h($selected_db); ?></a>
        <?php endif; ?>
        <?php if ($db && $db->getType() === 'pgsql' && $selected_schema !== ''): ?>
          <span class="sep">/</span><span class="badge"><?php echo h($selected_schema); ?></span>
        <?php endif; ?>
        <?php if ($selected_table !== ''): ?>
          <span class="sep">/</span><strong><?php echo h($selected_table); ?></strong>
        <?php endif; ?>
      </nav>

      <div class="topbar-right">
        <button type="button" class="btn btn-default btn-sm hov" id="palBtn" title="Command palette">
          <?php echo ico('command'); ?><span class="hide-sm">Search</span>
          <kbd class="hide-sm"><?php echo strpos($_SERVER['HTTP_USER_AGENT'] ?? '', 'Mac') !== false ? '&#8984;K' : 'Ctrl K'; ?></kbd>
        </button>
        <select id="themeSel" class="input-sm hide-sm" style="width:auto" aria-label="Theme">
          <?php foreach ($THEMES as $k => $v): ?>
            <option value="<?php echo h($k); ?>" <?php echo $current_theme === $k ? 'selected' : ''; ?>><?php echo h($v); ?></option>
          <?php endforeach; ?>
        </select>
        <select id="langSel" class="input-sm hide-sm" style="width:auto" aria-label="Language">
          <?php foreach ($SUPPORTED_LANGS as $k => $v): ?>
            <option value="<?php echo h($k); ?>" <?php echo $current_lang === $k ? 'selected' : ''; ?>><?php echo h($v); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </header>

    <main class="content">
      <?php if ($conn_error): ?>
        <div class="alert alert-error"><?php echo ico('circle-alert'); ?>
          <div class="grow"><?php
            $parts = explode("\n\n", $conn_error, 2);
            echo '<div><b>Connection lost.</b> ' . h($parts[0]) . '</div>';
            if (isset($parts[1])) echo '<pre>' . h($parts[1]) . '</pre>';
          ?></div>
        </div>
      <?php endif; ?>
      <?php if ($success_message): ?>
        <div class="alert alert-ok"><?php echo ico('circle-check'); ?><div class="grow"><?php echo h($success_message); ?></div>
          <button type="button" class="close" onclick="this.parentElement.remove()"><?php echo ico('x'); ?></button></div>
      <?php endif; ?>
      <?php if ($error_message): ?>
        <div class="alert alert-error"><?php echo ico('circle-alert'); ?><div class="grow"><?php echo h($error_message); ?></div>
          <button type="button" class="close" onclick="this.parentElement.remove()"><?php echo ico('x'); ?></button></div>
      <?php endif; ?>

<?php if (!$db): ?>
  <div class="card"><div class="empty">
    <?php echo ico('unplug', '', 34); ?>
    <p>No database connection</p>
    <span>Dabiro could not reach the server for this request.</span>
    <div style="margin-top:14px"><a href="?" class="btn btn-default hov"><?php echo ico('refresh-cw'); ?> Retry</a></div>
  </div></div>

<?php elseif ($page === 'databases'): ?>
  <?php
    $db_stats = $db->getDatabasesWithStats();
    $tot_size = 0; $tot_tables = 0; $any_lazy = false;
    foreach ($db_stats as $s) {
        $tot_size += (float)$s['size'];
        if ($s['tables'] !== null) $tot_tables += (int)$s['tables'];
        if (!empty($s['lazy_count'])) $any_lazy = true;
    }
  ?>
  <div class="page-head">
    <div>
      <h2><?php echo h(__('databases')); ?></h2>
      <div class="sub"><?php echo count($db_stats); ?> databases &middot; <?php echo format_bytes($tot_size); ?>
        <?php if ($db->serverVersion()): ?>&middot; <?php echo h($engine_label . ' ' . $db->serverVersion()); ?><?php endif; ?>
      </div>
    </div>
    <div class="acts">
      <?php if ($db->getType() !== 'sqlite'): ?>
        <button class="btn btn-primary hov" data-modal="mCreateDb"><?php echo ico('plus'); ?> <?php echo h(__('create_database')); ?></button>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="tbl-wrap">
      <table class="tbl">
        <thead><tr>
          <th><?php echo h(__('database_name_label')); ?></th>
          <th class="num"><?php echo h(__('tables')); ?></th>
          <th class="num"><?php echo h(__('total_size')); ?></th>
          <th class="num hide-sm"><?php echo h(__('data_size')); ?></th>
          <th class="num hide-sm"><?php echo h(__('index_size')); ?></th>
          <th class="acts"><?php echo h(__('actions')); ?></th>
        </tr></thead>
        <tbody>
        <?php if (!$db_stats): ?>
          <tr><td colspan="6"><div class="empty"><?php echo ico('server', '', 34); ?><p>No databases visible</p>
            <span>This account may not have permission to list databases.</span></div></td></tr>
        <?php endif; ?>
        <?php foreach ($db_stats as $name => $s): ?>
          <tr>
            <td><a href="<?php echo h('?' . http_build_query(['page' => 'tables', 'db' => $name])); ?>" class="flex hov" style="gap:7px;font-weight:600">
              <?php echo ico('database', 'ico-database'); ?><?php echo h($name); ?></a></td>
            <td class="num"><?php
              if ($s['tables'] !== null) {
                  echo '<span class="badge">' . format_num($s['tables']) . '</span>';
              } elseif (!empty($s['lazy_count'])) {
                  // Filled in by JS; PostgreSQL cannot read another database's catalog.
                  echo '<span class="badge skeleton js-tblcount" data-db="' . h($name) . '">00</span>';
              } else {
                  echo '<span class="faint">&mdash;</span>';
              }
            ?></td>
            <td class="num"><b><?php echo format_bytes($s['size']); ?></b></td>
            <td class="num hide-sm muted"><?php echo $s['data_size'] === null ? '&mdash;' : format_bytes($s['data_size']); ?></td>
            <td class="num hide-sm muted"><?php echo $s['index_size'] === null ? '&mdash;' : format_bytes($s['index_size']); ?></td>
            <td class="acts">
              <a href="<?php echo h('?' . http_build_query(['page' => 'tables', 'db' => $name])); ?>" class="btn btn-default btn-sm hov"><?php echo ico('table-2'); ?> <?php echo h(__('tables')); ?></a>
              <a href="<?php echo h('?' . http_build_query(['page' => 'sql', 'db' => $name])); ?>" class="btn btn-default btn-sm hov"><?php echo ico('terminal'); ?> SQL</a>
              <a href="<?php echo h('?' . http_build_query(['page' => 'export', 'db' => $name])); ?>" class="btn btn-default btn-sm hov"><?php echo ico('download'); ?></a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="modal" id="mCreateDb">
    <div class="modal-box"><form method="post">
      <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
      <div class="modal-head"><h3><?php echo h(__('create_database')); ?></h3>
        <button type="button" class="btn btn-ghost btn-icon close-modal"><?php echo ico('x'); ?></button></div>
      <div class="modal-body"><div class="field">
        <label for="newDbName"><?php echo h(__('database_name_label')); ?></label>
        <input type="text" name="new_db_name" id="newDbName" required placeholder="my_database" pattern="[A-Za-z0-9_$-]+">
        <div class="hint">Letters, digits, underscore and hyphen.</div>
      </div></div>
      <div class="modal-foot">
        <button type="button" class="btn btn-default close-modal"><?php echo h(__('cancel')); ?></button>
        <button type="submit" name="create_database" value="1" class="btn btn-primary hov"><?php echo ico('plus'); ?> <?php echo h(__('create_database')); ?></button>
      </div>
    </form></div>
  </div>

<?php elseif ($page === 'tables'): ?>
  <?php
    $ts = $db->getTablesWithStats();
    $sum = ['rows' => 0, 'data' => 0, 'idx' => 0, 'total' => 0, 'free' => 0];
    $rows_estimated = false;
    foreach ($ts as $t) {
        $sum['rows']  += (int)($t['Rows'] ?? 0);
        $sum['data']  += (float)($t['Data_length'] ?? 0);
        $sum['idx']   += (float)($t['Index_length'] ?? 0);
        $sum['total'] += (float)($t['Total_length'] ?? 0);
        $sum['free']  += (float)($t['Data_free'] ?? 0);
        if (empty($t['Rows_exact'])) $rows_estimated = true;
    }
  ?>
  <div class="page-head">
    <div>
      <h2><?php echo h($selected_db); ?><?php if ($db->getType() === 'pgsql'): ?><span class="faint" style="font-weight:400">.<?php echo h($selected_schema); ?></span><?php endif; ?></h2>
      <div class="sub"><?php echo count($ts); ?> tables &middot; <?php echo $rows_estimated ? '~' : ''; ?><?php echo format_num($sum['rows']); ?> rows &middot; <?php echo format_bytes($sum['total']); ?></div>
    </div>
    <div class="acts">
      <?php if ($db->getType() === 'pgsql'): ?>
        <select class="input-sm" style="width:auto" onchange="location.href=this.value" aria-label="Schema">
          <?php foreach ($nav_schemas as $s): ?>
            <option value="<?php echo h('?' . http_build_query(['page' => 'tables', 'db' => $selected_db, 'schema' => $s])); ?>" <?php echo $s === $selected_schema ? 'selected' : ''; ?>><?php echo h($s); ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-default hov" data-modal="mCreateSchema"><?php echo ico('git-branch'); ?> New schema</button>
      <?php endif; ?>
      <button class="btn btn-primary hov" data-modal="mCreateTable"><?php echo ico('plus'); ?> <?php echo h(__('create_table')); ?></button>
    </div>
  </div>

  <form method="post" id="tablesForm">
    <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
    <div class="card">
      <div class="tbl-wrap">
        <table class="tbl">
          <thead><tr>
            <th class="pick"><input type="checkbox" id="selAll" aria-label="Select all"></th>
            <th><?php echo h(__('table_name')); ?></th>
            <th class="hide-sm"><?php echo h(__('engine')); ?></th>
            <th class="num"><?php echo h(__('records')); ?></th>
            <th class="num hide-sm"><?php echo h(__('data_size')); ?></th>
            <th class="num hide-sm"><?php echo h(__('index_size')); ?></th>
            <th class="num"><?php echo h(__('total_size')); ?></th>
            <th class="num hide-sm"><?php echo h(__('overhead')); ?></th>
            <th class="acts"><?php echo h(__('actions')); ?></th>
          </tr></thead>
          <tbody>
          <?php if (!$ts): ?>
            <tr><td colspan="9"><div class="empty"><?php echo ico('table-2', '', 34); ?><p>No tables yet</p>
              <span>Create one, or run some SQL to get started.</span></div></td></tr>
          <?php endif; ?>
          <?php foreach ($ts as $t): $n = $t['Name']; ?>
            <tr>
              <td class="pick"><input type="checkbox" name="selected[]" value="<?php echo h($n); ?>" class="sel-tbl"></td>
              <td><a href="<?php echo h(ctx_url(['page' => 'browse', 'table' => $n])); ?>" class="flex hov" style="gap:7px;font-weight:600">
                <?php echo ico(!empty($t['Is_view']) ? 'eye' : 'table', 'ico-table'); ?><?php echo h($n); ?></a></td>
              <td class="hide-sm"><span class="badge"><?php echo h($t['Engine'] ?: '&mdash;'); ?></span></td>
              <td class="num"><?php
                if ($t['Rows'] === null) echo '<span class="faint">&mdash;</span>';
                else echo (empty($t['Rows_exact']) ? '<span class="approx">~</span>' : '') . format_num($t['Rows']);
              ?></td>
              <td class="num hide-sm muted"><?php echo format_bytes($t['Data_length']); ?></td>
              <td class="num hide-sm muted"><?php echo format_bytes($t['Index_length']); ?></td>
              <td class="num"><b><?php echo format_bytes($t['Total_length']); ?></b></td>
              <td class="num hide-sm"><?php echo ($t['Data_free'] ?? 0) > 0 ? '<span class="badge badge-warn">' . format_bytes($t['Data_free']) . '</span>' : '<span class="faint">&mdash;</span>'; ?></td>
              <td class="acts">
                <a href="<?php echo h(ctx_url(['page' => 'browse', 'table' => $n])); ?>" class="btn btn-default btn-sm hov"><?php echo ico('eye'); ?> <?php echo h(__('browse')); ?></a>
                <a href="<?php echo h(ctx_url(['page' => 'structure', 'table' => $n])); ?>" class="btn btn-default btn-sm hov"><?php echo ico('columns-3'); ?></a>
                <a href="<?php echo h(ctx_url(['page' => 'operations', 'table' => $n])); ?>" class="btn btn-default btn-sm hov"><?php echo ico('settings', 'ico-settings'); ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <?php if ($ts): ?>
          <tfoot><tr>
            <td></td><td><?php echo count($ts); ?> tables</td><td class="hide-sm"></td>
            <td class="num"><?php echo $rows_estimated ? '~' : ''; ?><?php echo format_num($sum['rows']); ?></td>
            <td class="num hide-sm"><?php echo format_bytes($sum['data']); ?></td>
            <td class="num hide-sm"><?php echo format_bytes($sum['idx']); ?></td>
            <td class="num"><?php echo format_bytes($sum['total']); ?></td>
            <td class="num hide-sm"><?php echo format_bytes($sum['free']); ?></td>
            <td></td>
          </tr></tfoot>
          <?php endif; ?>
        </table>
      </div>
      <?php if ($ts): ?>
      <div class="flex flex-wrap" style="padding:10px 14px;background:var(--surface-2);border-top:1px solid var(--border)">
        <span class="small muted" id="selCount">None selected</span>
        <span class="right flex">
          <button type="submit" name="bulk_action" value="optimize" class="btn btn-default btn-sm hov" data-confirm="Optimise the selected tables?"><?php echo ico('wrench'); ?> Optimise</button>
          <button type="submit" name="bulk_action" value="truncate" class="btn btn-default btn-sm hov" data-confirm="Delete ALL rows from the selected tables? This cannot be undone."><?php echo ico('circle-x'); ?> <?php echo h(__('truncate_selected')); ?></button>
          <button type="submit" name="bulk_action" value="drop" class="btn btn-danger-soft btn-sm hov" data-confirm="Permanently DROP the selected tables? This cannot be undone."><?php echo ico('trash-2', 'ico-trash-2'); ?> <?php echo h(__('drop_selected')); ?></button>
        </span>
      </div>
      <?php endif; ?>
    </div>
  </form>

  <div class="modal" id="mCreateTable">
    <div class="modal-box wide"><form method="post" id="createTableForm">
      <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
      <input type="hidden" name="create_table_sql" id="createTableSql">
      <div class="modal-head"><h3><?php echo h(__('create_table')); ?></h3>
        <button type="button" class="btn btn-ghost btn-icon close-modal"><?php echo ico('x'); ?></button></div>
      <div class="modal-body">
        <div class="field"><label for="ctName"><?php echo h(__('table_name')); ?></label>
          <input type="text" id="ctName" required placeholder="users" pattern="[A-Za-z0-9_$-]+"></div>
        <div class="flex" style="margin:14px 0 8px"><strong style="font-size:12.5px"><?php echo h(__('columns')); ?></strong>
          <button type="button" class="btn btn-default btn-sm right hov" id="ctAdd"><?php echo ico('plus'); ?> <?php echo h(__('add_column')); ?></button></div>
        <div id="ctCols"></div>
        <details style="margin-top:12px"><summary class="small muted" style="cursor:pointer">Preview SQL</summary>
          <pre id="ctPreview" class="mono small" style="margin-top:8px;padding:10px;background:var(--surface-2);border-radius:var(--r-sm);white-space:pre-wrap"></pre></details>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-default close-modal"><?php echo h(__('cancel')); ?></button>
        <button type="submit" name="create_table" value="1" class="btn btn-primary hov"><?php echo ico('plus'); ?> <?php echo h(__('create_table')); ?></button>
      </div>
    </form></div>
  </div>

  <?php if ($db->getType() === 'pgsql'): ?>
  <div class="modal" id="mCreateSchema">
    <div class="modal-box"><form method="post">
      <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
      <div class="modal-head"><h3>Create schema</h3>
        <button type="button" class="btn btn-ghost btn-icon close-modal"><?php echo ico('x'); ?></button></div>
      <div class="modal-body"><div class="field"><label for="nsName">Schema name</label>
        <input type="text" name="new_schema_name" id="nsName" required placeholder="analytics" pattern="[A-Za-z0-9_$-]+"></div></div>
      <div class="modal-foot">
        <button type="button" class="btn btn-default close-modal"><?php echo h(__('cancel')); ?></button>
        <button type="submit" name="create_schema" value="1" class="btn btn-primary hov"><?php echo ico('plus'); ?> Create</button>
      </div>
    </form></div>
  </div>
  <?php endif; ?>

<?php elseif ($page === 'browse'): ?>
  <?php
    $cols = $db->getColumns($selected_table);
    $colNames = array_column($cols, 'Field');
    $pk = $db->getPrimaryKey($selected_table);
    $limit = max(1, min(1000, (int)get_get('limit', 50)));
    $curP = max(1, (int)get_get('p', 1));
    $sortCol = get_get('sort', '');
    $sortDir = strtoupper(get_get('dir', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

    // Build the filter. Values are bound; only whitelisted operators and
    // known column names ever reach the SQL text.
    $ops = ['=', '!=', '>', '<', '>=', '<=', 'LIKE %%', 'STARTS', 'ENDS', 'NOT LIKE', 'IN', 'IS NULL', 'IS NOT NULL'];
    $whereIn = (array)get_get('where', []);
    $clauses = []; $params = [];
    foreach ($whereIn as $w) {
        $c = $w['col'] ?? ''; $op = $w['op'] ?? '='; $v = $w['val'] ?? '';
        if ($c === '' || !in_array($c, $colNames, true) || !in_array($op, $ops, true)) continue;
        $q = $db->quoteIdentifier($c);
        if ($op === 'IS NULL')          { $clauses[] = "$q IS NULL"; }
        elseif ($op === 'IS NOT NULL')  { $clauses[] = "$q IS NOT NULL"; }
        elseif ($op === 'LIKE %%')      { $clauses[] = "$q LIKE ?";     $params[] = "%$v%"; }
        elseif ($op === 'STARTS')       { $clauses[] = "$q LIKE ?";     $params[] = "$v%"; }
        elseif ($op === 'ENDS')         { $clauses[] = "$q LIKE ?";     $params[] = "%$v"; }
        elseif ($op === 'NOT LIKE')     { $clauses[] = "$q NOT LIKE ?"; $params[] = "%$v%"; }
        elseif ($op === 'IN') {
            $parts = array_filter(array_map('trim', explode(',', $v)), function ($x) { return $x !== ''; });
            if ($parts) {
                $clauses[] = "$q IN (" . implode(',', array_fill(0, count($parts), '?')) . ')';
                foreach ($parts as $p) $params[] = $p;
            }
        } else { $clauses[] = "$q $op ?"; $params[] = $v; }
    }

    $simpleQ = trim(get_get('search', ''));
    $simpleF = get_get('search_field', '');
    if ($simpleQ !== '' && !$clauses) {
        if (in_array($simpleF, $colNames, true)) {
            $clauses[] = $db->quoteIdentifier($simpleF) . ' LIKE ?'; $params[] = "%$simpleQ%";
        } else {
            $or = [];
            foreach ($cols as $c) {
                if (preg_match('~char|text|varchar|enum|json|uuid|citext~i', (string)($c['Type'] ?? ''))) {
                    $or[] = $db->quoteIdentifier($c['Field']) . ' LIKE ?'; $params[] = "%$simpleQ%";
                }
            }
            if ($or) $clauses[] = '(' . implode(' OR ', $or) . ')';
        }
    }

    $whereSql = $clauses ? ' WHERE ' . implode(' AND ', $clauses) : '';
    $orderSql = (in_array($sortCol, $colNames, true)) ? ' ORDER BY ' . $db->quoteIdentifier($sortCol) . " $sortDir" : '';

    $cntInfo = $db->getRowCountInfo($selected_table, $whereSql, $params);
    $total = $cntInfo['n'];
    $pages = max(1, (int)ceil($total / $limit));
    $curP = min($curP, $pages);
    $offset = ($curP - 1) * $limit;

    $rows = [];
    try {
        $rows = $db->all('SELECT * FROM ' . $db->qualify($selected_table) . $whereSql . $orderSql
                         . ' LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset, $params);
    } catch (Throwable $e) { $error_message = $e->getMessage(); }

    $filterOpen = (bool)$whereIn;
  ?>
  <div class="page-head">
    <div>
      <h2><?php echo h($selected_table); ?></h2>
      <div class="sub"><?php echo $cntInfo['exact'] ? '' : '~'; ?><?php echo format_num($total); ?> rows
        &middot; page <?php echo $curP; ?> of <?php echo format_num($pages); ?>
        <?php if (!$pk): ?>&middot; <span class="badge badge-warn" title="Without a primary key, Dabiro matches rows on all their column values and inline editing is disabled."><?php echo ico('triangle-alert'); ?> no primary key</span><?php endif; ?>
      </div>
    </div>
    <div class="acts">
      <a href="<?php echo h(ctx_url(['page' => 'insert', 'table' => $selected_table])); ?>" class="btn btn-primary hov"><?php echo ico('plus'); ?> <?php echo h(__('insert_record')); ?></a>
      <button class="btn btn-default hov" id="filterToggle"><?php echo ico('funnel'); ?> <?php echo h(__('filter')); ?><?php if ($clauses): ?> <span class="badge badge-accent"><?php echo count($clauses); ?></span><?php endif; ?></button>
      <a href="<?php echo h(ctx_url(['page' => 'structure', 'table' => $selected_table])); ?>" class="btn btn-default hov"><?php echo ico('columns-3'); ?> <?php echo h(__('structure')); ?></a>
      <a href="<?php echo h(ctx_url(['page' => 'operations', 'table' => $selected_table])); ?>" class="btn btn-default hov"><?php echo ico('settings', 'ico-settings'); ?></a>
    </div>
  </div>

  <div class="card <?php echo $filterOpen ? '' : 'hidden'; ?>" id="filterBox">
    <div class="card-head"><h3><?php echo ico('funnel'); ?> <?php echo h(__('filter')); ?></h3></div>
    <form method="get" class="card-body">
      <input type="hidden" name="page" value="browse">
      <input type="hidden" name="db" value="<?php echo h($selected_db); ?>">
      <input type="hidden" name="schema" value="<?php echo h($selected_schema); ?>">
      <input type="hidden" name="table" value="<?php echo h($selected_table); ?>">
      <input type="hidden" name="limit" value="<?php echo (int)$limit; ?>">
      <div id="filterRows"><?php foreach ($whereIn as $i => $w): ?>
        <div class="row filter-row" style="margin-bottom:8px;align-items:center">
          <select name="where[<?php echo (int)$i; ?>][col]" class="input-sm" style="flex:2">
            <?php foreach ($colNames as $c): ?><option value="<?php echo h($c); ?>" <?php echo ($w['col'] ?? '') === $c ? 'selected' : ''; ?>><?php echo h($c); ?></option><?php endforeach; ?>
          </select>
          <select name="where[<?php echo (int)$i; ?>][op]" class="input-sm" style="flex:1.4">
            <?php foreach ($ops as $o): ?><option value="<?php echo h($o); ?>" <?php echo ($w['op'] ?? '') === $o ? 'selected' : ''; ?>><?php echo h($o); ?></option><?php endforeach; ?>
          </select>
          <input type="text" name="where[<?php echo (int)$i; ?>][val]" class="input-sm" style="flex:2" value="<?php echo h($w['val'] ?? ''); ?>" placeholder="value">
          <button type="button" class="btn btn-ghost btn-icon rm-filter" style="flex:0"><?php echo ico('x'); ?></button>
        </div>
      <?php endforeach; ?></div>
      <div class="flex" style="margin-top:8px">
        <button type="button" class="btn btn-default btn-sm hov" id="addFilter"><?php echo ico('plus'); ?> <?php echo h(__('add_condition')); ?></button>
        <span class="right flex">
          <a href="<?php echo h(ctx_url(['page' => 'browse', 'table' => $selected_table])); ?>" class="btn btn-ghost btn-sm"><?php echo h(__('clear')); ?></a>
          <button type="submit" class="btn btn-primary btn-sm hov"><?php echo ico('funnel'); ?> Apply</button>
        </span>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="tbl-wrap">
      <table class="tbl" id="dataGrid" data-table="<?php echo h($selected_table); ?>" data-haspk="<?php echo $pk ? '1' : '0'; ?>">
        <thead><tr>
          <th class="pick" style="width:76px"><?php echo h(__('actions')); ?></th>
          <?php foreach ($cols as $c): $f = $c['Field'];
            $nd = ($sortCol === $f && $sortDir === 'ASC') ? 'DESC' : 'ASC'; ?>
            <th><a href="<?php echo h(ctx_url(['page' => 'browse', 'table' => $selected_table, 'sort' => $f, 'dir' => $nd, 'limit' => $limit])); ?>">
              <?php echo h($f); ?>
              <?php if ($sortCol === $f) echo ico($sortDir === 'ASC' ? 'arrow-up' : 'arrow-down'); ?>
              <?php if (in_array($f, $pk, true)) echo ico('key-round'); ?>
            </a></th>
          <?php endforeach; ?>
        </tr></thead>
        <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="<?php echo count($cols) + 1; ?>"><div class="empty"><?php echo ico('scan-search', '', 34); ?>
            <p><?php echo $clauses ? 'No rows match the filter' : 'This table is empty'; ?></p>
            <span><?php echo $clauses ? 'Try relaxing a condition.' : 'Insert a row to get started.'; ?></span></div></td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $row): ?>
          <?php
            $keys = [];
            if ($pk) { foreach ($pk as $k) if (array_key_exists($k, $row)) $keys[$k] = $row[$k]; }
            if (!$keys) $keys = $row;
            $qs = [];
            foreach ($keys as $k => $v) $qs['where_' . $k] = $v;
            $editUrl = ctx_url(array_merge(['page' => 'edit', 'table' => $selected_table], $qs));
            $delUrl  = ctx_url(array_merge(['action' => 'delete', 'table' => $selected_table, 'csrf_token' => $csrf, 'page' => 'browse'], $qs));
          ?>
          <tr data-keys="<?php echo h(json_encode($keys, JSON_INVALID_UTF8_SUBSTITUTE)); ?>">
            <td class="pick" style="white-space:nowrap">
              <a href="<?php echo h($editUrl); ?>" class="btn btn-ghost btn-icon btn-sm hov" title="Edit row"><?php echo ico('square-pen'); ?></a>
              <a href="<?php echo h($delUrl); ?>" class="btn btn-ghost btn-icon btn-sm hov" data-confirm="Delete this row? This cannot be undone." title="Delete row"><?php echo ico('trash-2', 'ico-trash-2'); ?></a>
            </td>
            <?php foreach ($cols as $c): $f = $c['Field']; $v = $row[$f] ?? null; ?>
              <td class="cell<?php echo (is_int($v) || is_float($v)) ? ' cell-num' : ''; ?>" data-col="<?php echo h($f); ?>">
                <?php if ($v === null): ?>
                  <span class="badge badge-null">NULL</span>
                <?php else:
                  $t = truncate_cell((string)$v);
                  if ($t['truncated']): ?>
                    <span title="<?php echo h(mb_substr((string)$v, 0, 2000)); ?>"><?php echo h($t['text']); ?><span class="faint">&hellip;</span></span>
                  <?php else: echo h($t['text']); endif;
                endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="flex flex-wrap" style="padding:10px 14px;background:var(--surface-2);border-top:1px solid var(--border);gap:12px">
      <label class="flex small muted" style="gap:6px"><?php echo h(__('rows_per_page')); ?>
        <select class="input-sm" style="width:auto" onchange="location.href=this.value">
          <?php foreach ([25, 50, 100, 250, 500] as $l): ?>
            <option value="<?php echo h(ctx_url(['page' => 'browse', 'table' => $selected_table, 'limit' => $l, 'sort' => $sortCol, 'dir' => $sortDir])); ?>" <?php echo $l === $limit ? 'selected' : ''; ?>><?php echo $l; ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <?php if ($pk): ?><span class="small faint hide-sm"><?php echo ico('info'); ?> Double-click a cell to edit it inline</span><?php endif; ?>
      <span class="right flex" style="gap:4px">
        <?php
          $pg = function ($p) use ($selected_table, $sortCol, $sortDir, $limit) {
              return h(ctx_url(['page' => 'browse', 'table' => $selected_table, 'p' => $p, 'sort' => $sortCol, 'dir' => $sortDir, 'limit' => $limit]));
          };
        ?>
        <a class="btn btn-default btn-sm hov <?php echo $curP <= 1 ? 'disabled' : ''; ?>" <?php echo $curP <= 1 ? 'aria-disabled="true"' : 'href="' . $pg(1) . '"'; ?>><?php echo ico('chevrons-left'); ?></a>
        <a class="btn btn-default btn-sm hov" <?php echo $curP <= 1 ? 'aria-disabled="true"' : 'href="' . $pg($curP - 1) . '"'; ?>><?php echo ico('chevron-left'); ?></a>
        <span class="small mono" style="padding:0 8px"><?php echo format_num($curP); ?> / <?php echo format_num($pages); ?></span>
        <a class="btn btn-default btn-sm hov" <?php echo $curP >= $pages ? 'aria-disabled="true"' : 'href="' . $pg($curP + 1) . '"'; ?>><?php echo ico('chevron-right'); ?></a>
        <a class="btn btn-default btn-sm hov" <?php echo $curP >= $pages ? 'aria-disabled="true"' : 'href="' . $pg($pages) . '"'; ?>><?php echo ico('chevrons-right'); ?></a>
      </span>
    </div>
  </div>

<?php elseif ($page === 'structure'): ?>
  <?php
    $cols = $db->getColumns($selected_table);
    $idxs = $db->getIndexes($selected_table);
    $fks  = $db->getForeignKeys($selected_table, $selected_db);
    $create = $db->getCreateTable($selected_table);
  ?>
  <div class="page-head">
    <div><h2><?php echo h($selected_table); ?></h2>
      <div class="sub"><?php echo count($cols); ?> columns &middot; <?php echo count($idxs); ?> indexes &middot; <?php echo count($fks); ?> foreign keys</div></div>
    <div class="acts">
      <a href="<?php echo h(ctx_url(['page' => 'browse', 'table' => $selected_table])); ?>" class="btn btn-default hov"><?php echo ico('eye'); ?> <?php echo h(__('browse')); ?></a>
      <a href="<?php echo h(ctx_url(['page' => 'operations', 'table' => $selected_table])); ?>" class="btn btn-default hov"><?php echo ico('settings', 'ico-settings'); ?> <?php echo h(__('operations')); ?></a>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h3><?php echo ico('columns-3'); ?> <?php echo h(__('columns')); ?></h3>
      <span class="right"><button class="btn btn-primary btn-sm hov" data-modal="mAddCol"><?php echo ico('plus'); ?> <?php echo h(__('add_column')); ?></button></span></div>
    <div class="tbl-wrap">
      <table class="tbl"><thead><tr>
        <th>#</th><th>Name</th><th>Type</th><th>Null</th><th>Default</th><th>Key</th><th class="hide-sm">Extra</th><th class="acts"></th>
      </tr></thead><tbody>
      <?php foreach ($cols as $i => $c): ?>
        <tr>
          <td class="faint"><?php echo $i + 1; ?></td>
          <td><b><?php echo h($c['Field']); ?></b><?php if (!empty($c['Comment'])): ?><div class="small faint"><?php echo h($c['Comment']); ?></div><?php endif; ?></td>
          <td><span class="badge badge-accent mono"><?php echo h($c['Type']); ?></span></td>
          <td><?php echo ($c['Null'] ?? '') === 'YES' ? '<span class="badge">NULL</span>' : '<span class="badge badge-warn">NOT NULL</span>'; ?></td>
          <td class="mono small"><?php echo $c['Default'] === null ? '<span class="faint">&mdash;</span>' : h($c['Default']); ?></td>
          <td><?php echo ($c['Key'] ?? '') === 'PRI' ? '<span class="badge badge-accent">' . ico('key-round') . ' PK</span>' : (($c['Key'] ?? '') ? '<span class="badge">' . h($c['Key']) . '</span>' : ''); ?></td>
          <td class="hide-sm small faint"><?php echo h($c['Extra'] ?? ''); ?></td>
          <td class="acts">
            <button type="button" class="btn btn-ghost btn-icon btn-sm hov edit-col"
              data-name="<?php echo h($c['Field']); ?>" data-type="<?php echo h($c['Type']); ?>"
              data-null="<?php echo ($c['Null'] ?? '') === 'YES' ? '1' : '0'; ?>" data-default="<?php echo h($c['Default'] ?? ''); ?>"
              title="Edit column"><?php echo ico('square-pen'); ?></button>
            <a href="<?php echo h(ctx_url(['action' => 'drop_column', 'page' => 'structure', 'table' => $selected_table, 'col' => $c['Field'], 'csrf_token' => $csrf])); ?>"
               class="btn btn-ghost btn-icon btn-sm hov" data-confirm="Drop column &quot;<?php echo h($c['Field']); ?>&quot;? All data in it is lost." title="Drop column"><?php echo ico('trash-2', 'ico-trash-2'); ?></a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody></table>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h3><?php echo ico('key-round'); ?> <?php echo h(__('indexes')); ?></h3>
      <span class="right"><button class="btn btn-default btn-sm hov" data-modal="mAddIdx"><?php echo ico('plus'); ?> <?php echo h(__('add_index')); ?></button></span></div>
    <?php if (!$idxs): ?><div class="empty" style="padding:24px"><span>No indexes on this table.</span></div><?php else: ?>
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Name</th><th>Columns</th><th>Type</th><th class="acts"></th></tr></thead><tbody>
      <?php foreach ($idxs as $ix): ?>
        <tr>
          <td class="mono"><?php echo h($ix['name']); ?></td>
          <td><?php foreach ($ix['columns'] as $c) echo '<span class="badge mono">' . h($c) . '</span> '; ?></td>
          <td><?php
            if ($ix['primary']) echo '<span class="badge badge-accent">PRIMARY</span>';
            elseif ($ix['unique']) echo '<span class="badge badge-ok">UNIQUE</span>';
            else echo '<span class="badge">INDEX</span>';
            if (!empty($ix['type'])) echo ' <span class="small faint">' . h($ix['type']) . '</span>';
          ?></td>
          <td class="acts"><a href="<?php echo h(ctx_url(['action' => 'drop_index', 'page' => 'structure', 'table' => $selected_table, 'index' => $ix['name'], 'csrf_token' => $csrf])); ?>"
            class="btn btn-ghost btn-icon btn-sm hov" data-confirm="Drop index &quot;<?php echo h($ix['name']); ?>&quot;?"><?php echo ico('trash-2', 'ico-trash-2'); ?></a></td>
        </tr>
      <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
  </div>

  <?php if ($fks): ?>
  <div class="card">
    <div class="card-head"><h3><?php echo ico('waypoints'); ?> <?php echo h(__('foreign_keys')); ?></h3></div>
    <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Constraint</th><th>Column</th><th>References</th></tr></thead><tbody>
      <?php foreach ($fks as $f): ?>
        <tr><td class="mono small"><?php echo h($f['name']); ?></td>
          <td><span class="badge mono"><?php echo h($f['col']); ?></span></td>
          <td><?php echo ico('arrow-right'); ?>
            <a href="<?php echo h(ctx_url(['page' => 'browse', 'table' => $f['ref_table']])); ?>" class="mono"><?php echo h($f['ref_table']); ?>.<?php echo h($f['ref_col']); ?></a></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  </div>
  <?php endif; ?>

  <?php if ($create): ?>
  <div class="card">
    <div class="card-head"><h3><?php echo ico('file-code-2'); ?> Definition</h3>
      <span class="right"><button type="button" class="btn btn-ghost btn-sm hov" data-copy="#createSql"><?php echo ico('copy'); ?> Copy</button></span></div>
    <pre id="createSql" class="mono small" style="padding:14px;overflow-x:auto;white-space:pre"><?php echo h($create); ?></pre>
  </div>
  <?php endif; ?>

  <div class="modal" id="mAddCol"><div class="modal-box"><form method="post">
    <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
    <input type="hidden" name="table" value="<?php echo h($selected_table); ?>">
    <div class="modal-head"><h3><?php echo h(__('add_column')); ?></h3><button type="button" class="btn btn-ghost btn-icon close-modal"><?php echo ico('x'); ?></button></div>
    <div class="modal-body">
      <div class="row">
        <div class="field"><label>Name</label><input type="text" name="col_name" required></div>
        <div class="field"><label>Type</label><input type="text" name="col_type" list="typeList" value="VARCHAR" required></div>
      </div>
      <div class="row">
        <div class="field"><label>Length / values</label><input type="text" name="col_len" placeholder="255"></div>
        <div class="field"><label>Default</label><input type="text" name="col_dflt"></div>
      </div>
      <?php if ($db->getType() === 'mysql'): ?>
      <div class="field"><label>Position</label><select name="col_pos"><option value="">At the end</option><option value="FIRST">First</option>
        <?php foreach ($cols as $c): ?><option value="<?php echo h($c['Field']); ?>">After <?php echo h($c['Field']); ?></option><?php endforeach; ?></select></div>
      <?php endif; ?>
      <label class="check"><input type="checkbox" name="col_null" value="1" checked> Allow NULL</label>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-default close-modal"><?php echo h(__('cancel')); ?></button>
      <button type="submit" name="add_column" value="1" class="btn btn-primary hov"><?php echo ico('plus'); ?> Add</button></div>
  </form></div></div>

  <div class="modal" id="mEditCol"><div class="modal-box"><form method="post">
    <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
    <input type="hidden" name="table" value="<?php echo h($selected_table); ?>">
    <input type="hidden" name="old_col_name" id="ecOld">
    <div class="modal-head"><h3>Edit column</h3><button type="button" class="btn btn-ghost btn-icon close-modal"><?php echo ico('x'); ?></button></div>
    <div class="modal-body">
      <div class="row">
        <div class="field"><label>Name</label><input type="text" name="col_name" id="ecName" required></div>
        <div class="field"><label>Type</label><input type="text" name="col_type" id="ecType" list="typeList"></div>
      </div>
      <div class="row">
        <div class="field"><label>Length</label><input type="text" name="col_len" id="ecLen"></div>
        <div class="field"><label>Default</label><input type="text" name="col_dflt" id="ecDflt"></div>
      </div>
      <label class="check"><input type="checkbox" name="col_null" value="1" id="ecNull"> Allow NULL</label>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-default close-modal"><?php echo h(__('cancel')); ?></button>
      <button type="submit" name="edit_column" value="1" class="btn btn-primary hov"><?php echo ico('check'); ?> Save</button></div>
  </form></div></div>

  <div class="modal" id="mAddIdx"><div class="modal-box"><form method="post">
    <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
    <input type="hidden" name="table" value="<?php echo h($selected_table); ?>">
    <div class="modal-head"><h3><?php echo h(__('add_index')); ?></h3><button type="button" class="btn btn-ghost btn-icon close-modal"><?php echo ico('x'); ?></button></div>
    <div class="modal-body">
      <div class="row">
        <div class="field"><label>Name</label><input type="text" name="index_name" placeholder="auto"></div>
        <div class="field"><label>Type</label><select name="index_type"><option>INDEX</option><option>UNIQUE</option><option>PRIMARY KEY</option></select></div>
      </div>
      <div class="field"><label>Columns</label>
        <div style="max-height:190px;overflow-y:auto;border:1px solid var(--border);border-radius:var(--r-sm);padding:8px">
          <?php foreach ($cols as $c): ?>
            <label class="check" style="display:flex;padding:3px 0"><input type="checkbox" name="index_columns[]" value="<?php echo h($c['Field']); ?>"> <?php echo h($c['Field']); ?>
              <span class="badge mono right"><?php echo h($c['Type']); ?></span></label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-default close-modal"><?php echo h(__('cancel')); ?></button>
      <button type="submit" name="add_index" value="1" class="btn btn-primary hov"><?php echo ico('plus'); ?> Create</button></div>
  </form></div></div>

  <datalist id="typeList">
    <?php foreach (['INT', 'BIGINT', 'SMALLINT', 'DECIMAL', 'NUMERIC', 'FLOAT', 'DOUBLE', 'VARCHAR', 'CHAR', 'TEXT',
                    'LONGTEXT', 'DATE', 'DATETIME', 'TIMESTAMP', 'TIME', 'BOOLEAN', 'JSON', 'JSONB', 'UUID', 'BLOB', 'BYTEA'] as $t): ?>
      <option value="<?php echo $t; ?>"><?php endforeach; ?>
  </datalist>

<?php elseif ($page === 'insert' || $page === 'edit'): ?>
  <?php
    $isEdit = ($page === 'edit');
    $cols = $db->getColumns($selected_table);
    $keys = extract_row_keys($_GET);
    $vals = [];
    if ($isEdit && $keys) {
        list($w, $wp) = row_identity($db, $selected_table, $keys);
        try { $vals = $db->one('SELECT * FROM ' . $db->qualify($selected_table) . " WHERE $w LIMIT 1", $wp) ?: []; }
        catch (Throwable $e) { $error_message = $e->getMessage(); }
    }
  ?>
  <div class="page-head">
    <div><h2><?php echo h($isEdit ? __('edit_record') : __('insert_record')); ?></h2>
      <div class="sub"><?php echo h($selected_table); ?></div></div>
    <div class="acts"><a href="<?php echo h(ctx_url(['page' => 'browse', 'table' => $selected_table])); ?>" class="btn btn-default hov"><?php echo ico('arrow-left'); ?> <?php echo h(__('back_to_table')); ?></a></div>
  </div>

  <?php if ($isEdit && !$vals): ?>
    <div class="alert alert-warn"><?php echo ico('triangle-alert'); ?><div>That row could not be found. It may have been deleted.</div></div>
  <?php else: ?>
  <div class="card"><form method="post" class="card-body">
    <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
    <input type="hidden" name="table" value="<?php echo h($selected_table); ?>">
    <input type="hidden" name="is_edit" value="<?php echo $isEdit ? '1' : '0'; ?>">
    <input type="hidden" name="row_keys" value="<?php echo h(json_encode($keys, JSON_INVALID_UTF8_SUBSTITUTE)); ?>">
    <?php foreach ($cols as $c):
      $f = $c['Field'];
      $v = $isEdit ? ($vals[$f] ?? null) : null;
      $type = strtolower((string)$c['Type']);
      $long = (bool)preg_match('~text|json|blob|bytea~', $type);
      $nullable = ($c['Null'] ?? '') === 'YES';
      $auto = strpos((string)($c['Extra'] ?? ''), 'auto') !== false;
    ?>
      <div class="field">
        <label for="f_<?php echo h($f); ?>"><?php echo h($f); ?>
          <span class="badge mono"><?php echo h($c['Type']); ?></span>
          <?php if ($auto): ?><span class="badge badge-accent">auto</span><?php endif; ?>
          <?php if (!$nullable): ?><span class="badge badge-warn">required</span><?php endif; ?>
        </label>
        <div class="flex" style="align-items:flex-start">
          <?php if ($long): ?>
            <textarea name="field[<?php echo h($f); ?>]" id="f_<?php echo h($f); ?>" rows="3" class="grow" spellcheck="false"><?php echo h($v ?? ''); ?></textarea>
          <?php else: ?>
            <input type="text" name="field[<?php echo h($f); ?>]" id="f_<?php echo h($f); ?>" class="grow"
                   value="<?php echo h($v ?? ''); ?>" <?php echo $auto ? 'placeholder="auto-generated"' : ''; ?> spellcheck="false">
          <?php endif; ?>
          <?php if ($nullable): ?>
            <label class="check" style="padding-top:8px;white-space:nowrap">
              <input type="checkbox" name="field_null[<?php echo h($f); ?>]" value="1" class="null-box" <?php echo ($isEdit && $v === null) ? 'checked' : ''; ?>> NULL</label>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <div class="flex" style="margin-top:14px">
      <button type="submit" name="save_record" value="1" class="btn btn-primary hov"><?php echo ico('check'); ?> <?php echo h(__('save')); ?></button>
      <button type="submit" name="save_record" value="1" class="btn btn-default hov"><input type="hidden" name="then" value="back"><?php echo ico('arrow-left'); ?> Save &amp; go back</button>
      <a href="<?php echo h(ctx_url(['page' => 'browse', 'table' => $selected_table])); ?>" class="btn btn-ghost"><?php echo h(__('cancel')); ?></a>
    </div>
  </form></div>
  <?php endif; ?>

<?php elseif ($page === 'operations'): ?>
  <div class="page-head">
    <div><h2><?php echo h(__('operations')); ?></h2><div class="sub"><?php echo h($selected_table); ?></div></div>
    <div class="acts"><a href="<?php echo h(ctx_url(['page' => 'browse', 'table' => $selected_table])); ?>" class="btn btn-default hov"><?php echo ico('arrow-left'); ?> <?php echo h(__('back_to_table')); ?></a></div>
  </div>

  <div class="card"><div class="card-head"><h3><?php echo ico('text-cursor-input'); ?> <?php echo h(__('rename_table')); ?></h3></div>
    <form method="post" class="card-body">
      <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
      <input type="hidden" name="table" value="<?php echo h($selected_table); ?>">
      <input type="hidden" name="operation_action" value="rename_table">
      <div class="field"><label>New name</label><input type="text" name="new_table_name" value="<?php echo h($selected_table); ?>" required></div>
      <button class="btn btn-primary hov"><?php echo ico('check'); ?> Rename</button>
    </form></div>

  <div class="card"><div class="card-head"><h3><?php echo ico('copy'); ?> <?php echo h(__('copy_table')); ?></h3></div>
    <form method="post" class="card-body">
      <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
      <input type="hidden" name="table" value="<?php echo h($selected_table); ?>">
      <input type="hidden" name="operation_action" value="copy_table">
      <div class="field"><label>New table name</label><input type="text" name="copy_table_name" value="<?php echo h($selected_table . '_copy'); ?>" required></div>
      <label class="check"><input type="checkbox" name="copy_data" value="1" checked> Copy the rows too</label>
      <div class="mt"><button class="btn btn-primary hov"><?php echo ico('copy'); ?> Copy</button></div>
    </form></div>

  <?php if ($db->getType() === 'mysql'): ?>
  <div class="card"><div class="card-head"><h3><?php echo ico('settings', 'ico-settings'); ?> Engine &amp; collation</h3></div>
    <form method="post" class="card-body">
      <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
      <input type="hidden" name="table" value="<?php echo h($selected_table); ?>">
      <input type="hidden" name="operation_action" value="alter_options">
      <div class="row">
        <div class="field"><label>Storage engine</label><select name="table_engine"><option value="">Leave unchanged</option>
          <option>InnoDB</option><option>MyISAM</option><option>MEMORY</option><option>ARCHIVE</option></select></div>
        <div class="field"><label>Collation</label><input type="text" name="table_collation" placeholder="utf8mb4_unicode_ci"></div>
        <div class="field"><label>AUTO_INCREMENT</label><input type="number" name="table_auto_increment" placeholder="1000"></div>
      </div>
      <button class="btn btn-primary hov"><?php echo ico('check'); ?> Apply</button>
    </form></div>
  <?php endif; ?>

  <div class="card"><div class="card-head"><h3><?php echo ico('wrench'); ?> Maintenance</h3></div>
    <form method="post" class="card-body">
      <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
      <input type="hidden" name="table" value="<?php echo h($selected_table); ?>">
      <input type="hidden" name="operation_action" value="optimize_table">
      <p class="small muted" style="margin-bottom:10px">Reclaims free space and refreshes planner statistics
        (<code><?php echo $db->getType() === 'pgsql' ? 'VACUUM ANALYZE' : ($db->getType() === 'mysql' ? 'OPTIMIZE TABLE' : 'VACUUM'); ?></code>).</p>
      <button class="btn btn-default hov"><?php echo ico('wrench'); ?> Optimise table</button>
    </form></div>

  <div class="card danger"><div class="card-head"><h3><?php echo ico('shield-alert'); ?> Danger zone</h3></div>
    <div class="card-body flex flex-wrap">
      <form method="post"><input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
        <input type="hidden" name="table" value="<?php echo h($selected_table); ?>">
        <input type="hidden" name="operation_action" value="truncate_table">
        <button class="btn btn-danger-soft hov" data-confirm="Delete every row in &quot;<?php echo h($selected_table); ?>&quot;? The table stays, the data does not.">Empty table</button></form>
      <form method="post"><input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
        <input type="hidden" name="table" value="<?php echo h($selected_table); ?>">
        <input type="hidden" name="operation_action" value="drop_table">
        <button class="btn btn-danger hov" data-confirm="Permanently drop &quot;<?php echo h($selected_table); ?>&quot;? This cannot be undone." data-confirm-type="<?php echo h($selected_table); ?>"><?php echo ico('trash-2', 'ico-trash-2'); ?> Drop table</button></form>
    </div></div>

<?php elseif ($page === 'sql'): ?>
  <div class="page-head">
    <div><h2><?php echo h(__('sql_console')); ?></h2>
      <div class="sub"><?php echo $selected_db !== '' ? h($selected_db) . ($db->getType() === 'pgsql' ? '.' . h($selected_schema) : '') : 'No database selected'; ?></div></div>
  </div>

  <div class="card">
    <form method="post" id="sqlForm">
      <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
      <div class="sql-editor">
        <div class="sql-stack">
          <pre id="sqlHighlight" aria-hidden="true"></pre>
          <textarea name="sql" id="sqlInput" spellcheck="false" autocapitalize="off" autocomplete="off"
                    placeholder="SELECT * FROM …    &#10;&#10;Ctrl+Enter to run · Ctrl+Space for suggestions"><?php echo h(get_post('sql', get_get('pre_sql', ''))); ?></textarea>
          <div id="sqlAuto"></div>
        </div>
        <div class="sql-bar">
          <button type="submit" name="execute_sql" value="1" class="btn btn-primary btn-sm hov"><?php echo ico('play'); ?> <?php echo h(__('execute_query')); ?> <kbd>Ctrl&nbsp;&#9166;</kbd></button>
          <button type="submit" name="export_query" value="1" class="btn btn-default btn-sm hov"><?php echo ico('download'); ?> <?php echo h(__('export_query')); ?></button>
          <button type="button" class="btn btn-ghost btn-sm hov" id="sqlFormat"><?php echo ico('braces'); ?> Format</button>
          <span class="right flex">
            <select id="sqlHistory" class="input-sm" style="width:auto;max-width:190px"><option value="">History&hellip;</option></select>
            <button type="button" class="btn btn-ghost btn-icon btn-sm hov" id="sqlHistClear" title="Clear history"><?php echo ico('trash-2', 'ico-trash-2'); ?></button>
          </span>
        </div>
      </div>
    </form>
  </div>

  <?php if ($sql_batches !== null): ?>
    <div class="card">
      <?php if (count($sql_batches) > 1): ?>
        <div class="result-tabs" role="tablist">
          <?php foreach ($sql_batches as $i => $b): ?>
            <button type="button" class="result-tab <?php echo $i === 0 ? 'active' : ''; ?> <?php echo $b['error'] ? 'err' : ''; ?>" data-rt="<?php echo $i; ?>">
              <?php echo $b['error'] ? '&#9888; ' : ''; ?>#<?php echo $i + 1; ?>
              <span class="faint"><?php echo $b['rows'] !== null ? count($b['rows']) . ' rows' : $b['affected'] . ' affected'; ?></span>
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <?php foreach ($sql_batches as $i => $b): ?>
        <div class="rt-pane <?php echo $i === 0 ? '' : 'hidden'; ?>" data-rt="<?php echo $i; ?>">
          <div class="card-head">
            <h3 class="mono small ellipsis" style="max-width:60%"><?php echo h(mb_substr($b['sql'], 0, 120)); ?></h3>
            <span class="right"><?php echo $b['ms']; ?> ms
              <?php if ($b['rows'] !== null): ?>&middot; <?php echo count($b['rows']); ?> rows<?php endif; ?>
              <?php if ($b['truncated']): ?><span class="badge badge-warn">first 1000 shown</span><?php endif; ?></span>
          </div>
          <?php if ($b['error']): ?>
            <div style="padding:14px"><div class="alert alert-error" style="margin:0"><?php echo ico('circle-alert'); ?><div><?php echo h($b['error']); ?></div></div></div>
          <?php elseif ($b['rows'] === null): ?>
            <div style="padding:16px" class="flex"><?php echo ico('circle-check'); ?> <b><?php echo format_num($b['affected']); ?></b> row(s) affected.</div>
          <?php elseif (!$b['rows']): ?>
            <div class="empty"><?php echo ico('scan-search', '', 34); ?><p>No rows returned</p></div>
          <?php else: ?>
            <div class="tbl-wrap"><table class="tbl">
              <thead><tr><?php foreach (array_keys($b['rows'][0]) as $k): ?><th><?php echo h($k); ?></th><?php endforeach; ?></tr></thead>
              <tbody><?php foreach ($b['rows'] as $r): ?><tr><?php foreach ($r as $v): ?>
                <td class="cell"><?php if ($v === null) echo '<span class="badge badge-null">NULL</span>';
                  else { $t = truncate_cell((string)$v); echo h($t['text']) . ($t['truncated'] ? '<span class="faint">&hellip;</span>' : ''); } ?></td>
              <?php endforeach; ?></tr><?php endforeach; ?></tbody>
            </table></div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

<?php elseif ($page === 'export'): ?>
  <?php $allDbs = array_keys($db->getDatabasesWithStats()); $expTables = $db->getTables(); ?>
  <div class="page-head"><div><h2><?php echo h(__('export_data')); ?></h2>
    <div class="sub">Streamed straight to the browser, so table size is not limited by PHP's memory</div></div></div>
  <div class="card"><form method="post" class="card-body">
    <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
    <div class="row">
      <div class="field"><label for="expDb"><?php echo h(__('select_database')); ?></label>
        <select name="export_db_name" id="expDb"><?php foreach ($allDbs as $d): ?>
          <option value="<?php echo h($d); ?>" <?php echo $d === $selected_db ? 'selected' : ''; ?>><?php echo h($d); ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="expFmt"><?php echo h(__('export_format')); ?></label>
        <select name="export_db_format" id="expFmt">
          <option value="sql">SQL dump (.sql)</option>
          <option value="json">JSON (.json)</option>
          <option value="csv">CSV (.csv)</option>
          <option value="xml">XML (.xml)</option>
        </select></div>
    </div>
    <div class="field"><label>Tables <span class="faint">(none selected exports everything)</span></label>
      <div style="max-height:210px;overflow-y:auto;border:1px solid var(--border);border-radius:var(--r-sm);padding:8px">
        <?php if (!$expTables): ?><span class="faint small">No tables in this database.</span><?php endif; ?>
        <?php foreach ($expTables as $t): ?>
          <label class="check" style="display:flex;padding:3px 0"><input type="checkbox" name="export_tables[]" value="<?php echo h($t); ?>"> <?php echo h($t); ?></label>
        <?php endforeach; ?>
      </div></div>
    <label class="check"><input type="checkbox" name="export_data" value="1" checked> Include row data (uncheck for structure only)</label>
    <div class="mt"><button type="submit" name="export_database" value="1" class="btn btn-primary hov"><?php echo ico('download', 'ico-download'); ?> <?php echo h(__('download_database')); ?></button></div>
  </form></div>

<?php elseif ($page === 'import'): ?>
  <div class="page-head"><div><h2><?php echo h(__('import_data')); ?></h2>
    <div class="sub">Into <b><?php echo h($selected_db ?: 'the current database'); ?></b></div></div></div>
  <div class="card"><form method="post" enctype="multipart/form-data" class="card-body">
    <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
    <div class="field"><label for="sqlFile">SQL file</label>
      <input type="file" name="sql_file" id="sqlFile" accept=".sql,.txt,text/plain" required>
      <div class="hint">Server limit: <?php echo h(ini_get('upload_max_filesize')); ?> per file,
        <?php echo h(ini_get('post_max_size')); ?> per request. Statements are split correctly around
        strings, comments and <code>DELIMITER</code> blocks.</div></div>
    <label class="check"><input type="checkbox" name="import_stop_on_error" value="1" checked> Stop and roll back on the first error</label>
    <div class="mt"><button type="submit" name="import_sql" value="1" class="btn btn-primary hov"><?php echo ico('import', 'ico-import'); ?> Import</button></div>
  </form></div>

<?php elseif ($page === 'search'): ?>
  <?php
    $allDbs = array_keys($db->getDatabasesWithStats());
    $term = trim(get_post('search_term', ''));
    $sdb  = get_post('search_database', $selected_db ?: ($allDbs[0] ?? ''));
    $results = []; $scanned = 0; $searchErr = null;
    if ($term !== '' && $sdb !== '') {
        $r = $db->selectDatabase($sdb);
        if ($r !== true) { $searchErr = $r; }
        else {
            foreach ($db->getTables() as $t) {
                $scanned++;
                try {
                    $or = []; $bind = [];
                    foreach ($db->getColumns($t) as $c) {
                        if (preg_match('~char|text|varchar|enum|json|uuid|citext~i', (string)($c['Type'] ?? ''))) {
                            $or[] = $db->quoteIdentifier($c['Field']) . ' LIKE ?';
                            $bind[] = "%$term%";
                        }
                    }
                    if (!$or) continue;
                    $found = $db->all('SELECT * FROM ' . $db->qualify($t) . ' WHERE ' . implode(' OR ', $or) . ' LIMIT 10', $bind);
                    if ($found) $results[$t] = $found;
                } catch (Throwable $e) {}
            }
        }
    }
  ?>
  <div class="page-head"><div><h2><?php echo h(__('global_search')); ?></h2>
    <div class="sub">Searches every text-like column in every table</div></div></div>
  <div class="card"><form method="post" class="card-body">
    <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
    <div class="row">
      <div class="field" style="flex:3"><label for="st">Search for</label>
        <input type="text" name="search_term" id="st" value="<?php echo h($term); ?>" placeholder="Keyword&hellip;" required autofocus></div>
      <div class="field"><label for="sd"><?php echo h(__('select_database')); ?></label>
        <select name="search_database" id="sd"><?php foreach ($allDbs as $d): ?>
          <option value="<?php echo h($d); ?>" <?php echo $d === $sdb ? 'selected' : ''; ?>><?php echo h($d); ?></option><?php endforeach; ?></select></div>
    </div>
    <button class="btn btn-primary hov"><?php echo ico('search', 'ico-search'); ?> <?php echo h(__('search')); ?></button>
  </form></div>

  <?php if ($searchErr): ?>
    <div class="alert alert-error"><?php echo ico('circle-alert'); ?><div><?php echo h($searchErr); ?></div></div>
  <?php elseif ($term !== ''): ?>
    <div class="stat-row" style="margin-bottom:14px"><span><b><?php echo count($results); ?></b> of <b><?php echo $scanned; ?></b> tables matched &ldquo;<?php echo h($term); ?>&rdquo;</span></div>
    <?php if (!$results): ?>
      <div class="card"><div class="empty"><?php echo ico('scan-search', '', 34); ?><p>Nothing found</p><span>No text column contains that value.</span></div></div>
    <?php endif; ?>
    <?php foreach ($results as $t => $found): ?>
      <div class="card">
        <div class="card-head"><h3><?php echo ico('table', 'ico-table'); ?> <?php echo h($t); ?></h3>
          <span class="right"><span class="badge"><?php echo count($found); ?> shown</span>
            <a href="<?php echo h('?' . http_build_query(['page' => 'browse', 'db' => $sdb, 'schema' => $selected_schema, 'table' => $t, 'search' => $term])); ?>"
               class="btn btn-default btn-sm hov"><?php echo ico('eye'); ?> Browse</a></span></div>
        <div class="tbl-wrap" style="max-height:320px"><table class="tbl">
          <thead><tr><?php foreach (array_keys($found[0]) as $k): ?><th><?php echo h($k); ?></th><?php endforeach; ?></tr></thead>
          <tbody><?php foreach ($found as $r): ?><tr><?php foreach ($r as $v): ?>
            <td class="cell"><?php if ($v === null) echo '<span class="badge badge-null">NULL</span>';
              else { $tc = truncate_cell((string)$v, 90); echo h($tc['text']) . ($tc['truncated'] ? '<span class="faint">&hellip;</span>' : ''); } ?></td>
          <?php endforeach; ?></tr><?php endforeach; ?></tbody>
        </table></div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

<?php else: ?>
  <div class="card"><div class="empty"><?php echo ico('circle-help', '', 34); ?><p>Page not found</p>
    <span>&ldquo;<?php echo h($page); ?>&rdquo; is not a Dabiro page.</span>
    <div style="margin-top:14px"><a href="?page=databases" class="btn btn-primary hov">Go to databases</a></div></div></div>
<?php endif; ?>
    </main>
  </div>
</div>

<!-- Command palette -->
<div id="palette" role="dialog" aria-modal="true" aria-label="Command palette">
  <div class="pal-box">
    <div class="pal-input-wrap"><?php echo ico('search', 'ico-lg'); ?>
      <input type="text" id="palInput" placeholder="Jump to a database, schema, table or action&hellip;" autocomplete="off" spellcheck="false"></div>
    <div class="pal-list" id="palList"></div>
    <div class="pal-foot"><span><kbd>&uarr;</kbd><kbd>&darr;</kbd> navigate</span><span><kbd>&#9166;</kbd> open</span><span><kbd>esc</kbd> close</span></div>
  </div>
</div>
<?php endif; /* is_logged_in */ ?>

<script>
window.__DABIRO__ = <?php echo json_encode([
    'csrf'  => $csrf,
    'ctx'   => [
        'db' => $selected_db, 'schema' => $selected_schema,
        'table' => $selected_table, 'page' => $page,
        'loggedIn' => is_logged_in(), 'type' => $db_type,
    ],
    'cols'  => ($page === 'browse' && isset($colNames)) ? $colNames : [],
    'ops'   => ($page === 'browse' && isset($ops)) ? $ops : [],
    'i18n'  => ['host_label' => __('host_label')],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script>
(function () {
'use strict';

var D    = window.__DABIRO__ || {};
var CSRF = D.csrf || '';
var CTX  = D.ctx || {};

var $  = function (s, r) { return (r || document).querySelector(s); };
var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

function icon(n) {
    return '<svg class="ico ico-' + n + '" aria-hidden="true"><use href="#i-' + n + '"></use></svg>';
}

// ─── Toasts ──────────────────────────────────────────────────────────────────
var toastBox = $('#toasts');
function toast(msg, kind, ms) {
    if (!toastBox) return;
    kind = kind || 'info';
    var el = document.createElement('div');
    el.className = 'toast ' + kind;
    el.innerHTML = icon(kind === 'ok' ? 'circle-check' : kind === 'error' ? 'circle-alert' : 'info') +
                   '<div class="grow"></div>';
    el.lastChild.textContent = msg;
    toastBox.appendChild(el);
    var life = ms || (kind === 'error' ? 7000 : 3200);
    setTimeout(function () {
        el.classList.add('out');
        setTimeout(function () { el.remove(); }, 220);
    }, life);
}

// ─── Modals ──────────────────────────────────────────────────────────────────
var lastFocus = null;
function openModal(id) {
    var m = document.getElementById(id);
    if (!m) return;
    lastFocus = document.activeElement;
    m.classList.add('open');
    var f = m.querySelector('input:not([type=hidden]), select, textarea, button');
    if (f) setTimeout(function () { f.focus(); }, 60);
}
function closeModal(m) {
    m.classList.remove('open');
    if (lastFocus && lastFocus.focus) lastFocus.focus();
}
document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-modal]');
    if (t) { e.preventDefault(); openModal(t.getAttribute('data-modal')); return; }
    if (e.target.closest('.close-modal')) { var m = e.target.closest('.modal'); if (m) closeModal(m); return; }
    if (e.target.classList && e.target.classList.contains('modal')) closeModal(e.target);
});

// ─── Confirmation (replaces window.confirm) ─────────────────────────────────
// Destructive actions get a real dialog; the most destructive also require the
// object's name to be typed, so a stray click cannot drop a table.
var pending = null;
function buildConfirm() {
    var d = document.createElement('div');
    d.className = 'modal';
    d.id = '__confirm';
    d.innerHTML =
      '<div class="modal-box" style="max-width:420px">' +
        '<div class="modal-head"><h3>' + icon('triangle-alert') + ' <span id="__cTitle">Are you sure?</span></h3></div>' +
        '<div class="modal-body"><p id="__cMsg" style="font-size:13px"></p>' +
          '<div id="__cTypeWrap" class="field hidden" style="margin-top:12px">' +
            '<label>Type <b id="__cTypeName"></b> to confirm</label>' +
            '<input type="text" id="__cType" autocomplete="off" spellcheck="false"></div>' +
        '</div>' +
        '<div class="modal-foot"><button type="button" class="btn btn-default" id="__cNo">Cancel</button>' +
          '<button type="button" class="btn btn-danger" id="__cYes">Confirm</button></div>' +
      '</div>';
    document.body.appendChild(d);

    $('#__cNo').addEventListener('click', function () { pending = null; closeModal(d); });
    $('#__cYes').addEventListener('click', function () {
        var p = pending; pending = null; closeModal(d);
        if (!p) return;
        if (p.tagName === 'A') { window.location.href = p.href; }
        else { p.__ok = true; p.click(); }
    });
    $('#__cType').addEventListener('input', function () {
        $('#__cYes').disabled = this.value !== $('#__cTypeName').textContent;
    });
    return d;
}
var confirmEl = null;
document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-confirm]');
    if (!t || t.__ok) { if (t) t.__ok = false; return; }
    e.preventDefault();
    e.stopPropagation();
    confirmEl = confirmEl || buildConfirm();
    pending = t;
    $('#__cMsg').textContent = t.getAttribute('data-confirm');
    var typeName = t.getAttribute('data-confirm-type');
    var wrap = $('#__cTypeWrap'), inp = $('#__cType');
    if (typeName) {
        wrap.classList.remove('hidden');
        $('#__cTypeName').textContent = typeName;
        inp.value = '';
        $('#__cYes').disabled = true;
    } else {
        wrap.classList.add('hidden');
        $('#__cYes').disabled = false;
    }
    openModal('__confirm');
}, true);

// ─── Global keys ─────────────────────────────────────────────────────────────
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        var pal = $('#palette');
        if (pal && pal.classList.contains('open')) { closePalette(); return; }
        var open = $('.modal.open');
        if (open) { pending = null; closeModal(open); return; }
    }
    var mod = e.ctrlKey || e.metaKey;
    if (mod && (e.key === 'k' || e.key === 'K')) { e.preventDefault(); togglePalette(); }
    if (!mod && !e.altKey && e.key === '/' && !/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) {
        e.preventDefault(); togglePalette();
    }
});

// ─── Sidebar (mobile) ────────────────────────────────────────────────────────
var navToggle = $('#navToggle'), scrim = $('#scrim');
if (navToggle) navToggle.addEventListener('click', function () { document.body.classList.toggle('nav-open'); });
if (scrim) scrim.addEventListener('click', function () { document.body.classList.remove('nav-open'); });

// ─── Theme & language ────────────────────────────────────────────────────────
function swapParam(key, val) {
    var u = new URL(window.location.href);
    u.searchParams.set(key, val);
    window.location.href = u.toString();
}
var themeSel = $('#themeSel');
if (themeSel) themeSel.addEventListener('change', function () {
    // Apply instantly so the change feels immediate, then persist server-side.
    document.documentElement.setAttribute('data-theme', this.value);
    swapParam('set_theme', this.value);
});
var langSel = $('#langSel');
if (langSel) langSel.addEventListener('change', function () { swapParam('set_lang', this.value); });

// ─── Sidebar table filter ────────────────────────────────────────────────────
var tblFilter = $('#tblFilter');
if (tblFilter) {
    tblFilter.addEventListener('input', function () {
        var q = this.value.toLowerCase().trim(), n = 0;
        $$('#tblList .tree-item').forEach(function (el) {
            var hit = (el.getAttribute('data-name') || '').toLowerCase().indexOf(q) !== -1;
            el.style.display = hit ? '' : 'none';
            if (hit) n++;
        });
        var c = $('#tblCount'); if (c) c.textContent = n;
    });
}

// ─── Table selection ─────────────────────────────────────────────────────────
var selAll = $('#selAll');
function updateSelCount() {
    var n = $$('.sel-tbl:checked').length, el = $('#selCount');
    if (el) el.textContent = n ? (n + ' selected') : 'None selected';
}
if (selAll) selAll.addEventListener('change', function () {
    $$('.sel-tbl').forEach(function (c) { c.checked = selAll.checked; });
    updateSelCount();
});
$$('.sel-tbl').forEach(function (c) { c.addEventListener('change', updateSelCount); });

// Bulk buttons must not fire with an empty selection.
var tablesForm = $('#tablesForm');
if (tablesForm) tablesForm.addEventListener('submit', function (e) {
    if (!$$('.sel-tbl:checked').length) {
        e.preventDefault();
        toast('Select at least one table first.', 'error');
    }
});

// ─── Copy buttons ────────────────────────────────────────────────────────────
document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-copy]');
    if (!b) return;
    var src = $(b.getAttribute('data-copy'));
    if (!src) return;
    var text = src.textContent;
    var done = function () { toast('Copied to clipboard', 'ok', 1600); };
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done, function () { toast('Copy failed', 'error'); });
    } else {
        var ta = document.createElement('textarea');
        ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); done(); } catch (_) { toast('Copy failed', 'error'); }
        ta.remove();
    }
});

// ─── Lazy PostgreSQL table counts ────────────────────────────────────────────
// A PostgreSQL connection can only see its own database's catalog, so counts for
// the other databases are fetched here instead of blocking the page render.
var lazyCounts = $$('.js-tblcount');
if (lazyCounts.length) {
    var queue = lazyCounts.slice();
    var runNext = function () {
        var el = queue.shift();
        if (!el) return;
        fetch('?action=db_table_count&name=' + encodeURIComponent(el.getAttribute('data-db')),
              { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                el.classList.remove('skeleton');
                el.textContent = (d && d.ok && d.tables !== null) ? d.tables : '—';
                if (!d || !d.ok) el.classList.add('faint');
            })
            .catch(function () { el.classList.remove('skeleton'); el.textContent = '—'; })
            .then(runNext);
    };
    // Two at a time: responsive without opening a burst of connections.
    runNext(); runNext();
}

// ─── Connection / tunnel health ──────────────────────────────────────────────
var connDot = $('#connDot');
if (connDot) {
    var pollConn = function () {
        fetch('?action=tunnel_status', { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d || !d.ok) return;
                if (d.ssh) {
                    var up = d.status && d.status.up;
                    connDot.classList.toggle('warn', !up);
                    connDot.title = up ? ('SSH tunnel up on port ' + d.status.port) : 'SSH tunnel is down — it will be rebuilt on the next request';
                }
            })
            .catch(function () {});
    };
    if (CTX.loggedIn) setInterval(pollConn, 30000);
}

// ─── Command palette ─────────────────────────────────────────────────────────
var pal = $('#palette'), palInput = $('#palInput'), palList = $('#palList');
var palItems = [], palFiltered = [], palSel = 0, palLoaded = false;

var STATIC_ACTIONS = [
    { type: 'action', name: 'Databases',    url: '?page=databases', ico: 'server' },
    { type: 'action', name: 'SQL console',  url: '?page=sql',       ico: 'terminal' },
    { type: 'action', name: 'Global search', url: '?page=search',   ico: 'scan-search' },
    { type: 'action', name: 'Import SQL',   url: '?page=import',    ico: 'import' },
    { type: 'action', name: 'Export',       url: '?page=export',    ico: 'download' }
];

function togglePalette() {
    if (!pal) return;
    pal.classList.contains('open') ? closePalette() : openPalette();
}
function openPalette() {
    if (!pal) return;
    pal.classList.add('open');
    palInput.value = '';
    palInput.focus();
    if (!palLoaded) {
        palLoaded = true;
        var q = '?action=palette' + (CTX.db ? '&db=' + encodeURIComponent(CTX.db) : '') +
                (CTX.schema ? '&schema=' + encodeURIComponent(CTX.schema) : '');
        fetch(q, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                palItems = STATIC_ACTIONS.concat((d && d.items) || []);
                renderPalette();
            })
            .catch(function () { palItems = STATIC_ACTIONS; renderPalette(); });
    }
    renderPalette();
}
function closePalette() { if (pal) pal.classList.remove('open'); }

// Subsequence match, so "usr" finds "users" and "ordit" finds "order_items".
function fuzzy(needle, hay) {
    if (!needle) return { score: 0, marks: null };
    var n = needle.toLowerCase(), h = hay.toLowerCase();
    var exact = h.indexOf(n);
    if (exact !== -1) {
        return { score: 1000 - exact - (h.length - n.length) * 0.1,
                 marks: [[exact, exact + n.length]] };
    }
    var i = 0, marks = [], score = 0, last = -2;
    for (var j = 0; j < h.length && i < n.length; j++) {
        if (h[j] === n[i]) {
            marks.push([j, j + 1]);
            score += (j === last + 1) ? 6 : 1;
            last = j; i++;
        }
    }
    return i === n.length ? { score: score, marks: marks } : null;
}
function markup(text, marks) {
    if (!marks) return escapeHtml(text);
    var out = '', pos = 0;
    marks.forEach(function (m) {
        out += escapeHtml(text.slice(pos, m[0])) + '<mark>' + escapeHtml(text.slice(m[0], m[1])) + '</mark>';
        pos = m[1];
    });
    return out + escapeHtml(text.slice(pos));
}
function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}
var ICON_FOR = { database: 'database', schema: 'git-branch', table: 'table', action: null };

function renderPalette() {
    if (!palList) return;
    var q = palInput.value.trim();
    palFiltered = [];
    palItems.forEach(function (it) {
        var m = fuzzy(q, it.name);
        if (m) palFiltered.push({ it: it, score: m.score + (it.type === 'action' ? 2 : 0), marks: m.marks });
    });
    palFiltered.sort(function (a, b) { return b.score - a.score; });
    palFiltered = palFiltered.slice(0, 60);
    palSel = 0;

    if (!palFiltered.length) {
        palList.innerHTML = '<div class="pal-empty">' + (palItems.length ? 'No matches' : 'Loading&hellip;') + '</div>';
        return;
    }
    palList.innerHTML = palFiltered.map(function (r, i) {
        var it = r.it;
        var ic = it.ico || ICON_FOR[it.type] || 'chevron-right';
        return '<a class="pal-item' + (i === 0 ? ' sel' : '') + '" href="' + escapeHtml(it.url) + '" data-i="' + i + '">' +
               icon(ic) + '<span class="nm">' + markup(it.name, r.marks) + '</span>' +
               '<span class="ctx">' + escapeHtml(it.context || it.type) + '</span></a>';
    }).join('');
}
function movePalette(d) {
    var items = $$('.pal-item', palList);
    if (!items.length) return;
    items[palSel].classList.remove('sel');
    palSel = (palSel + d + items.length) % items.length;
    items[palSel].classList.add('sel');
    items[palSel].scrollIntoView({ block: 'nearest' });
}
if (palInput) {
    palInput.addEventListener('input', renderPalette);
    palInput.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown') { e.preventDefault(); movePalette(1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); movePalette(-1); }
        else if (e.key === 'Enter') {
            e.preventDefault();
            var el = $$('.pal-item', palList)[palSel];
            if (el) window.location.href = el.href;
        }
    });
}
var palBtn = $('#palBtn');
if (palBtn) palBtn.addEventListener('click', togglePalette);
if (pal) pal.addEventListener('click', function (e) { if (e.target === pal) closePalette(); });

// ─── Inline cell editing ─────────────────────────────────────────────────────
var grid = $('#dataGrid');
if (grid && grid.getAttribute('data-haspk') === '1') {
    grid.addEventListener('dblclick', function (e) {
        var td = e.target.closest('td.cell');
        if (!td || td.querySelector('input')) return;
        var tr = td.closest('tr');
        var col = td.getAttribute('data-col');
        if (!col || !tr) return;

        var wasNull = !!td.querySelector('.badge-null');
        var original = wasNull ? '' : td.textContent.replace(/…$/, '');
        var input = document.createElement('input');
        input.className = 'cell-input';
        input.value = original;
        td.textContent = '';
        td.appendChild(input);
        input.focus();
        input.select();

        var done = false;
        var finish = function (save) {
            if (done) return;
            done = true;
            var val = input.value;
            if (!save || val === original) { restore(wasNull ? null : original); return; }
            td.classList.add('saving');
            var body = new URLSearchParams();
            body.set('csrf_token', CSRF);
            body.set('table', grid.getAttribute('data-table'));
            body.set('column', col);
            body.set('value', val);
            body.set('keys', tr.getAttribute('data-keys'));
            fetch('?action=cell_update', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: body.toString()
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                td.classList.remove('saving');
                if (d && d.ok) {
                    restore(val);
                    td.classList.add('saved');
                    setTimeout(function () { td.classList.remove('saved'); }, 950);
                    toast('Saved', 'ok', 1500);
                } else {
                    restore(wasNull ? null : original);
                    toast((d && d.error) || 'Update failed', 'error');
                }
            })
            .catch(function () {
                td.classList.remove('saving');
                restore(wasNull ? null : original);
                toast('Network error', 'error');
            });
        };
        var restore = function (v) {
            td.textContent = '';
            if (v === null) {
                var b = document.createElement('span');
                b.className = 'badge badge-null';
                b.textContent = 'NULL';
                td.appendChild(b);
            } else {
                td.textContent = v;
            }
        };
        input.addEventListener('blur', function () { finish(true); });
        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter') { ev.preventDefault(); finish(true); }
            else if (ev.key === 'Escape') { ev.preventDefault(); finish(false); }
        });
    });
    $$('#dataGrid td.cell').forEach(function (td) { td.classList.add('cell-edit'); });
}

// ─── Browse filter rows ──────────────────────────────────────────────────────
var filterToggle = $('#filterToggle'), filterBox = $('#filterBox');
if (filterToggle && filterBox) {
    filterToggle.addEventListener('click', function () {
        filterBox.classList.toggle('hidden');
        if (!filterBox.classList.contains('hidden')) {
            if (!$('.filter-row', filterBox)) addFilterRow();
            var f = filterBox.querySelector('select'); if (f) f.focus();
        }
    });
}
var COLS = D.cols || [];
var OPS  = D.ops || [];
function addFilterRow() {
    var wrap = $('#filterRows');
    if (!wrap) return;
    var i = $$('.filter-row', wrap).length + Date.now() % 1000;
    var row = document.createElement('div');
    row.className = 'row filter-row';
    row.style.cssText = 'margin-bottom:8px;align-items:center';
    row.innerHTML =
        '<select name="where[' + i + '][col]" class="input-sm" style="flex:2">' +
            COLS.map(function (c) { return '<option value="' + escapeHtml(c) + '">' + escapeHtml(c) + '</option>'; }).join('') + '</select>' +
        '<select name="where[' + i + '][op]" class="input-sm" style="flex:1.4">' +
            OPS.map(function (o) { return '<option value="' + escapeHtml(o) + '">' + escapeHtml(o) + '</option>'; }).join('') + '</select>' +
        '<input type="text" name="where[' + i + '][val]" class="input-sm" style="flex:2" placeholder="value">' +
        '<button type="button" class="btn btn-ghost btn-icon rm-filter" style="flex:0">' + icon('x') + '</button>';
    wrap.appendChild(row);
}
var addFilterBtn = $('#addFilter');
if (addFilterBtn) addFilterBtn.addEventListener('click', addFilterRow);
document.addEventListener('click', function (e) {
    var b = e.target.closest('.rm-filter');
    if (b) b.closest('.filter-row').remove();
});

// ─── Structure: edit column modal ────────────────────────────────────────────
$$('.edit-col').forEach(function (b) {
    b.addEventListener('click', function () {
        var type = b.getAttribute('data-type') || '';
        var m = type.match(/^([^(]+)\(([^)]*)\)/);
        $('#ecOld').value = b.getAttribute('data-name');
        $('#ecName').value = b.getAttribute('data-name');
        $('#ecType').value = m ? m[1].trim() : type;
        $('#ecLen').value = m ? m[2] : '';
        $('#ecDflt').value = b.getAttribute('data-default') || '';
        $('#ecNull').checked = b.getAttribute('data-null') === '1';
        openModal('mEditCol');
    });
});

// ─── Create-table builder ────────────────────────────────────────────────────
var ctCols = $('#ctCols');
if (ctCols) {
    var TYPES = ['INT', 'BIGINT', 'VARCHAR', 'TEXT', 'BOOLEAN', 'DECIMAL', 'DATE', 'DATETIME', 'TIMESTAMP', 'JSON', 'UUID', 'FLOAT'];
    function ctRow(name, type, len) {
        var d = document.createElement('div');
        d.className = 'row ct-row';
        d.style.cssText = 'margin-bottom:8px;align-items:center';
        d.innerHTML =
            '<input type="text" class="input-sm ct-name" placeholder="column" style="flex:2" value="' + escapeHtml(name || '') + '">' +
            '<select class="input-sm ct-type" style="flex:1.4">' +
                TYPES.map(function (t) { return '<option' + (t === type ? ' selected' : '') + '>' + t + '</option>'; }).join('') + '</select>' +
            '<input type="text" class="input-sm ct-len" placeholder="len" style="flex:.7" value="' + escapeHtml(len || '') + '">' +
            '<label class="check" style="flex:0;white-space:nowrap"><input type="checkbox" class="ct-null"> null</label>' +
            '<label class="check" style="flex:0;white-space:nowrap"><input type="checkbox" class="ct-pk"> pk</label>' +
            '<button type="button" class="btn btn-ghost btn-icon ct-rm" style="flex:0">' + icon('x') + '</button>';
        ctCols.appendChild(d);
        d.addEventListener('input', ctPreview);
        d.addEventListener('change', ctPreview);
        ctPreview();
    }
    function ctSql() {
        var name = ($('#ctName').value || '').trim();
        if (!name) return '';
        var q = CTX.type === 'mysql' ? '`' : '"';
        var qi = function (s) { return q + String(s).split(q).join(q + q) + q; };
        var defs = [], pks = [];
        $$('.ct-row', ctCols).forEach(function (r) {
            var n = $('.ct-name', r).value.trim();
            if (!n) return;
            var t = $('.ct-type', r).value;
            var l = $('.ct-len', r).value.trim();
            var isPk = $('.ct-pk', r).checked;
            var nullable = $('.ct-null', r).checked;
            var def = qi(n) + ' ' + t + (l ? '(' + l + ')' : '');
            if (isPk) {
                if (t === 'INT' || t === 'BIGINT') {
                    def = qi(n) + ' ' + (CTX.type === 'pgsql' ? (t === 'BIGINT' ? 'BIGSERIAL' : 'SERIAL')
                                       : t + ' AUTO_INCREMENT');
                }
                pks.push(qi(n));
                def += ' NOT NULL';
            } else {
                def += nullable ? ' NULL' : ' NOT NULL';
            }
            defs.push('  ' + def);
        });
        if (!defs.length) return '';
        if (pks.length) defs.push('  PRIMARY KEY (' + pks.join(', ') + ')');
        return 'CREATE TABLE ' + qi(name) + ' (\n' + defs.join(',\n') + '\n)';
    }
    function ctPreview() {
        var p = $('#ctPreview');
        if (p) p.textContent = ctSql() || '-- add a name and at least one column';
    }
    $('#ctAdd').addEventListener('click', function () { ctRow('', 'VARCHAR', '255'); });
    $('#ctName').addEventListener('input', ctPreview);
    ctCols.addEventListener('click', function (e) {
        var b = e.target.closest('.ct-rm');
        if (b) { b.closest('.ct-row').remove(); ctPreview(); }
    });
    ctRow('id', 'INT', '');
    var firstPk = $('.ct-pk', ctCols); if (firstPk) firstPk.checked = true;
    ctRow('name', 'VARCHAR', '255');
    ctPreview();

    $('#createTableForm').addEventListener('submit', function (e) {
        var sql = ctSql();
        if (!sql) { e.preventDefault(); toast('Add a table name and at least one column.', 'error'); return; }
        $('#createTableSql').value = sql;
    });
}

// ─── SQL console ─────────────────────────────────────────────────────────────
var sqlInput = $('#sqlInput'), sqlHi = $('#sqlHighlight');
if (sqlInput && sqlHi) {
    var KEYWORDS = ('SELECT FROM WHERE INSERT UPDATE DELETE SET VALUES INTO JOIN LEFT RIGHT INNER OUTER FULL CROSS ON ' +
        'GROUP BY ORDER HAVING LIMIT OFFSET UNION ALL DISTINCT AS AND OR NOT NULL IS IN LIKE ILIKE BETWEEN EXISTS CASE ' +
        'WHEN THEN ELSE END CREATE TABLE ALTER DROP TRUNCATE INDEX UNIQUE PRIMARY KEY FOREIGN REFERENCES DEFAULT ' +
        'CONSTRAINT ADD COLUMN RENAME TO VIEW DATABASE SCHEMA GRANT REVOKE BEGIN COMMIT ROLLBACK TRANSACTION WITH ' +
        'RECURSIVE RETURNING USING NATURAL ASC DESC EXPLAIN ANALYZE VACUUM SHOW DESCRIBE PRAGMA IF ELSIF LOOP ' +
        'INT INTEGER BIGINT SMALLINT VARCHAR CHAR TEXT DATE DATETIME TIMESTAMP BOOLEAN DECIMAL NUMERIC FLOAT DOUBLE ' +
        'JSON JSONB UUID SERIAL BLOB BYTEA').split(/\s+/);
    var KW = {};
    KEYWORDS.forEach(function (k) { KW[k] = 1; });
    var FUNCS = /\b(COUNT|SUM|AVG|MIN|MAX|COALESCE|NULLIF|CAST|CONCAT|SUBSTRING|LENGTH|LOWER|UPPER|TRIM|ROUND|NOW|DATE_TRUNC|EXTRACT|ARRAY_AGG|STRING_AGG|JSON_AGG|ROW_NUMBER|RANK|DENSE_RANK)\b/i;

    function highlight(src) {
        var out = '', i = 0;
        while (i < src.length) {
            var c = src[i], two = src.substr(i, 2);
            if (two === '--' || c === '#') {
                var e = src.indexOf('\n', i); if (e === -1) e = src.length;
                out += '<span class="tok-com">' + escapeHtml(src.slice(i, e)) + '</span>'; i = e; continue;
            }
            if (two === '/*') {
                var e2 = src.indexOf('*/', i + 2); e2 = e2 === -1 ? src.length : e2 + 2;
                out += '<span class="tok-com">' + escapeHtml(src.slice(i, e2)) + '</span>'; i = e2; continue;
            }
            if (c === "'" || c === '"' || c === '`') {
                var j = i + 1;
                while (j < src.length) {
                    if (src[j] === '\\') { j += 2; continue; }
                    if (src[j] === c) { j++; break; }
                    j++;
                }
                var cls = c === "'" ? 'tok-str' : 'tok-fn';
                out += '<span class="' + cls + '">' + escapeHtml(src.slice(i, j)) + '</span>'; i = j; continue;
            }
            if (/[0-9]/.test(c) && !/[A-Za-z_]/.test(src[i - 1] || '')) {
                var k = i;
                while (k < src.length && /[0-9.]/.test(src[k])) k++;
                out += '<span class="tok-num">' + escapeHtml(src.slice(i, k)) + '</span>'; i = k; continue;
            }
            if (/[A-Za-z_]/.test(c)) {
                var m = i;
                while (m < src.length && /[A-Za-z0-9_$]/.test(src[m])) m++;
                var word = src.slice(i, m);
                if (KW[word.toUpperCase()]) out += '<span class="tok-key">' + escapeHtml(word) + '</span>';
                else if (FUNCS.test(word) && src[m] === '(') out += '<span class="tok-fn">' + escapeHtml(word) + '</span>';
                else out += escapeHtml(word);
                i = m; continue;
            }
            out += escapeHtml(c); i++;
        }
        // Trailing newline keeps the mirrored <pre> the same height as the textarea.
        return out + '\n';
    }
    var syncHi = function () {
        sqlHi.innerHTML = highlight(sqlInput.value);
        sqlHi.scrollTop = sqlInput.scrollTop;
        sqlHi.scrollLeft = sqlInput.scrollLeft;
    };
    sqlInput.addEventListener('input', syncHi);
    sqlInput.addEventListener('scroll', function () {
        sqlHi.scrollTop = sqlInput.scrollTop;
        sqlHi.scrollLeft = sqlInput.scrollLeft;
    });
    syncHi();

    // History (local to this browser)
    var HKEY = 'dabiro.sqlhistory';
    function histLoad() { try { return JSON.parse(localStorage.getItem(HKEY) || '[]'); } catch (_) { return []; } }
    function histRender() {
        var sel = $('#sqlHistory');
        if (!sel) return;
        var h = histLoad();
        sel.innerHTML = '<option value="">History (' + h.length + ')&hellip;</option>' +
            h.map(function (q) {
                return '<option value="' + escapeHtml(q) + '">' + escapeHtml(q.slice(0, 70).replace(/\s+/g, ' ')) + '</option>';
            }).join('');
    }
    histRender();
    var histSel = $('#sqlHistory');
    if (histSel) histSel.addEventListener('change', function () {
        if (this.value) { sqlInput.value = this.value; syncHi(); sqlInput.focus(); }
    });
    var histClear = $('#sqlHistClear');
    if (histClear) histClear.addEventListener('click', function () {
        localStorage.removeItem(HKEY); histRender(); toast('History cleared', 'ok', 1500);
    });

    var sqlForm = $('#sqlForm');
    sqlForm.addEventListener('submit', function () {
        var q = sqlInput.value.trim();
        if (!q) return;
        var h = histLoad().filter(function (x) { return x !== q; });
        h.unshift(q);
        try { localStorage.setItem(HKEY, JSON.stringify(h.slice(0, 40))); } catch (_) {}
    });

    sqlInput.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            var btn = sqlForm.querySelector('[name=execute_sql]');
            btn.click();
            return;
        }
        if (e.key === 'Tab' && !autoOpen) {
            e.preventDefault();
            var s = this.selectionStart, en = this.selectionEnd;
            this.value = this.value.slice(0, s) + '  ' + this.value.slice(en);
            this.selectionStart = this.selectionEnd = s + 2;
            syncHi();
        }
    });

    // Very light formatter: newline before major clauses.
    var fmtBtn = $('#sqlFormat');
    if (fmtBtn) fmtBtn.addEventListener('click', function () {
        var s = sqlInput.value.replace(/\s+/g, ' ').trim();
        s = s.replace(/\s*\b(FROM|WHERE|LEFT JOIN|RIGHT JOIN|INNER JOIN|JOIN|GROUP BY|ORDER BY|HAVING|LIMIT|OFFSET|UNION ALL|UNION|VALUES|SET|RETURNING)\b/gi,
                      function (m, kw) { return '\n' + kw.toUpperCase(); });
        s = s.replace(/,\s*/g, ',\n  ');
        sqlInput.value = s;
        syncHi();
    });

    // Autocomplete over the live schema.
    var schema = null, autoBox = $('#sqlAuto'), autoOpen = false, autoItems = [], autoSel = 0, autoStart = 0;
    fetch('?action=schema_map' + (CTX.db ? '&db=' + encodeURIComponent(CTX.db) : '') +
          (CTX.schema ? '&schema=' + encodeURIComponent(CTX.schema) : ''),
          { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (d && d.ok) schema = d.tables; })
        .catch(function () {});

    function currentWord() {
        var pos = sqlInput.selectionStart;
        var before = sqlInput.value.slice(0, pos);
        var m = before.match(/[A-Za-z0-9_$.]*$/);
        return { word: m ? m[0] : '', start: pos - (m ? m[0].length : 0) };
    }
    function closeAuto() { autoOpen = false; autoBox.classList.remove('open'); }
    function showAuto() {
        if (!schema) return;
        var cw = currentWord();
        if (cw.word.length < 1) { closeAuto(); return; }
        autoStart = cw.start;
        var cands = [];
        var dot = cw.word.lastIndexOf('.');
        if (dot > 0) {
            var tbl = cw.word.slice(0, dot), frag = cw.word.slice(dot + 1).toLowerCase();
            (schema[tbl] || []).forEach(function (c) {
                if (c.toLowerCase().indexOf(frag) === 0) cands.push({ v: tbl + '.' + c, l: c, t: 'column' });
            });
        } else {
            var frag2 = cw.word.toLowerCase();
            Object.keys(schema).forEach(function (t) {
                if (t.toLowerCase().indexOf(frag2) === 0) cands.push({ v: t, l: t, t: 'table' });
            });
            var seen = {};
            Object.keys(schema).forEach(function (t) {
                schema[t].forEach(function (c) {
                    if (!seen[c] && c.toLowerCase().indexOf(frag2) === 0) { seen[c] = 1; cands.push({ v: c, l: c, t: 'column' }); }
                });
            });
            KEYWORDS.forEach(function (k) {
                if (k.toLowerCase().indexOf(frag2) === 0) cands.push({ v: k, l: k, t: 'keyword' });
            });
        }
        cands = cands.slice(0, 12);
        if (!cands.length) { closeAuto(); return; }
        autoItems = cands; autoSel = 0;
        autoBox.innerHTML = cands.map(function (c, i) {
            return '<div class="auto-item' + (i === 0 ? ' sel' : '') + '" data-i="' + i + '">' +
                   escapeHtml(c.l) + '<span class="t">' + c.t + '</span></div>';
        }).join('');
        // Position near the caret line rather than following exact glyph metrics.
        var lines = sqlInput.value.slice(0, autoStart).split('\n');
        var top = Math.min(lines.length * 20.8 + 14, sqlInput.clientHeight - 10);
        autoBox.style.top = top + 'px';
        autoBox.style.left = '18px';
        autoOpen = true;
        autoBox.classList.add('open');
    }
    function applyAuto(i) {
        var c = autoItems[i];
        if (!c) return;
        var pos = sqlInput.selectionStart;
        sqlInput.value = sqlInput.value.slice(0, autoStart) + c.v + sqlInput.value.slice(pos);
        sqlInput.selectionStart = sqlInput.selectionEnd = autoStart + c.v.length;
        closeAuto();
        syncHi();
        sqlInput.focus();
    }
    autoBox.addEventListener('mousedown', function (e) {
        var it = e.target.closest('.auto-item');
        if (it) { e.preventDefault(); applyAuto(+it.getAttribute('data-i')); }
    });
    sqlInput.addEventListener('input', function () { if (autoOpen) showAuto(); });
    sqlInput.addEventListener('blur', function () { setTimeout(closeAuto, 140); });
    sqlInput.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === ' ') { e.preventDefault(); showAuto(); return; }
        if (!autoOpen) return;
        var items = $$('.auto-item', autoBox);
        if (e.key === 'ArrowDown') {
            e.preventDefault(); items[autoSel].classList.remove('sel');
            autoSel = (autoSel + 1) % items.length; items[autoSel].classList.add('sel');
            items[autoSel].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault(); items[autoSel].classList.remove('sel');
            autoSel = (autoSel - 1 + items.length) % items.length; items[autoSel].classList.add('sel');
            items[autoSel].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter' || e.key === 'Tab') {
            e.preventDefault(); applyAuto(autoSel);
        } else if (e.key === 'Escape') {
            e.preventDefault(); closeAuto();
        }
    });
}

// Multi-statement result tabs
$$('.result-tab').forEach(function (t) {
    t.addEventListener('click', function () {
        var i = t.getAttribute('data-rt');
        $$('.result-tab').forEach(function (x) { x.classList.toggle('active', x === t); });
        $$('.rt-pane').forEach(function (p) { p.classList.toggle('hidden', p.getAttribute('data-rt') !== i); });
    });
});

// ─── Insert/edit: NULL checkbox disables its input ───────────────────────────
$$('.null-box').forEach(function (cb) {
    var sync = function () {
        var field = cb.closest('.flex').querySelector('input[type=text], textarea');
        if (!field) return;
        field.disabled = cb.checked;
        field.style.opacity = cb.checked ? '.45' : '';
    };
    cb.addEventListener('change', sync);
    sync();
});

// ─── Login screen ────────────────────────────────────────────────────────────
var loginForm = $('#loginForm');
if (loginForm) {
    var DEFAULT_PORTS = { mysql: '3306', pgsql: '5432', sqlite: '' };
    var activePane = 'direct';
    var isSshConnected = $('#sshActiveChip') && $('#sshActiveChip').style.display !== 'none';
    if (isSshConnected) activePane = 'ssh';

    function setPane(pane) {
        activePane = pane;
        $$('.tab[data-pane]').forEach(function (t) { t.classList.toggle('active', t.getAttribute('data-pane') === pane); });

        var isSsh = pane === 'ssh';
        var isSaved = pane === 'saved';
        var isDirect = pane === 'direct';

        var paneSaved = $('#pane-saved');
        if (paneSaved) {
            paneSaved.classList.toggle('on', isSaved);
            paneSaved.style.display = isSaved ? 'block' : 'none';
        }

        var holder = $('#pane-uri-holder');
        if (holder) {
            holder.classList.toggle('on', isDirect);
            holder.style.display = isDirect ? 'block' : 'none';
        }

        var stepper = $('#sshStepper');
        if (stepper) stepper.style.display = isSsh ? 'flex' : 'none';

        var sshStep1 = $('#sshStep1');
        var sshActiveChip = $('#sshActiveChip');
        var sshBackBtn = $('#sshBackBtn');
        var dbConnectBtn = $('#dbConnectBtn');

        $('#useSsh').value = isSsh ? '1' : '0';

        if (isSaved) {
            loginForm.style.display = 'none';
            if (sshStep1) sshStep1.style.display = 'none';
        } else if (isDirect) {
            loginForm.style.display = 'block';
            if (sshStep1) sshStep1.style.display = 'none';
            if (sshActiveChip) sshActiveChip.style.display = 'none';
            if (sshBackBtn) sshBackBtn.style.display = 'none';
            if (dbConnectBtn) {
                var lbl = dbConnectBtn.querySelector('.btn-label');
                if (lbl) lbl.textContent = (D.i18n && D.i18n.connect_button) || 'Connect';
            }
            var hl = $('#hostLabel');
            if (hl) hl.textContent = (D.i18n && D.i18n.host_label) || 'Host / Server';
            var hr = $('#hostRow');
            if (hr) hr.style.display = '';
        } else if (isSsh) {
            if (isSshConnected) {
                // Show Step 2
                if (sshStep1) sshStep1.style.display = 'none';
                loginForm.style.display = 'block';
                if (sshActiveChip) sshActiveChip.style.display = 'flex';
                if (sshBackBtn) sshBackBtn.style.display = 'block';
                $('#stepPill1').classList.add('done');
                $('#stepPill1').classList.remove('active');
                $('#stepPill2').classList.add('active');
                if (dbConnectBtn) {
                    var lbl2 = dbConnectBtn.querySelector('.btn-label');
                    if (lbl2) lbl2.textContent = 'Connect to Database';
                }
                var hl2 = $('#hostLabel');
                if (hl2) hl2.textContent = 'Remote DB host (from bastion)';
            } else {
                // Show Step 1
                if (sshStep1) sshStep1.style.display = 'block';
                loginForm.style.display = 'none';
                if (sshActiveChip) sshActiveChip.style.display = 'none';
                if (sshBackBtn) sshBackBtn.style.display = 'none';
                $('#stepPill1').classList.add('active');
                $('#stepPill1').classList.remove('done');
                $('#stepPill2').classList.remove('active');
            }
        }
    }

    $$('.tab[data-pane]').forEach(function (tab) {
        tab.addEventListener('click', function () {
            setPane(tab.getAttribute('data-pane'));
        });
    });

    var dbType = $('#dbType');
    function syncType() {
        var t = dbType.value;
        var isSqlite = t === 'sqlite';
        $('#portField').classList.toggle('hidden', isSqlite);
        $('#credRow').classList.toggle('hidden', isSqlite);
        $('#dbPort').placeholder = DEFAULT_PORTS[t] || '';
        var hl = $('#hostLabel');
        if (hl && isSqlite) hl.textContent = 'Path to the .sqlite file';
        else if (hl && $('#useSsh').value !== '1') hl.textContent = (D.i18n && D.i18n.host_label) || 'Host / Server';
        if (t === 'pgsql' && $('#dbUser').value === 'root') $('#dbUser').value = 'postgres';
        if (t === 'mysql' && $('#dbUser').value === 'postgres') $('#dbUser').value = 'root';
        var tp = $('#targetPort');
        if (tp && (!tp.value || tp.value === '3306' || tp.value === '5432')) {
            tp.value = DEFAULT_PORTS[t] || '3306';
        }
    }
    dbType.addEventListener('change', syncType);
    syncType();

    var sshAuth = $('#sshAuth');
    if (sshAuth) {
        var syncAuth = function () {
            $('#sshKeyBox').classList.toggle('hidden', sshAuth.value !== 'key');
            $('#sshPassBox').classList.toggle('hidden', sshAuth.value !== 'password');
        };
        sshAuth.addEventListener('change', syncAuth);
        syncAuth();
        $('#sshKeyMode').addEventListener('change', function () {
            var paste = this.value === 'paste';
            var ta = $('#sshKey');
            ta.placeholder = paste ? '-----BEGIN OPENSSH PRIVATE KEY-----' : '/home/www-data/.ssh/id_ed25519';
            ta.rows = paste ? 3 : 1;
        });
    }

    // Step 1: Connect SSH button
    var sshConnectBtn = $('#sshConnectBtn');
    if (sshConnectBtn) {
        sshConnectBtn.addEventListener('click', function () {
            var host = ($('#sshHost').value || '').trim();
            var port = ($('#sshPort').value || '22').trim();
            var user = ($('#sshUser').value || '').trim();
            var auth = sshAuth ? sshAuth.value : 'agent';
            if (!host) { toast('SSH host is required.', 'error'); $('#sshHost').focus(); return; }
            if (!user && auth !== 'agent') { toast('SSH username is required.', 'error'); $('#sshUser').focus(); return; }

            sshConnectBtn.disabled = true;
            var lbl = sshConnectBtn.querySelector('.btn-label');
            if (lbl) lbl.textContent = 'Opening SSH tunnel…';
            var ic = sshConnectBtn.querySelector('.ico use');
            if (ic) ic.setAttribute('href', '#i-loader-circle');
            sshConnectBtn.querySelector('.ico').classList.add('ico-spin');

            var body = new URLSearchParams();
            body.set('csrf_token', CSRF);
            body.set('ssh_host', host);
            body.set('ssh_port', port);
            body.set('ssh_user', user);
            body.set('ssh_auth', auth);
            body.set('ssh_pass', $('#sshPass') ? $('#sshPass').value : '');
            body.set('ssh_key', $('#sshKey') ? $('#sshKey').value : '');
            body.set('ssh_key_pass', $('#sshKeyPass') ? $('#sshKeyPass').value : '');
            body.set('ssh_key_mode', $('#sshKeyMode') ? $('#sshKeyMode').value : 'paste');
            body.set('ssh_local_port', $('#sshLocalPort') ? $('#sshLocalPort').value : '');
            body.set('target_host', $('#targetHost') ? ($('#targetHost').value || '127.0.0.1') : '127.0.0.1');
            body.set('target_port', $('#targetPort') ? ($('#targetPort').value || DEFAULT_PORTS[dbType.value] || '3306') : (DEFAULT_PORTS[dbType.value] || '3306'));

            fetch('?action=ssh_connect', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: body.toString()
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                sshConnectBtn.disabled = false;
                if (lbl) lbl.textContent = 'Connect SSH Tunnel';
                if (ic) ic.setAttribute('href', '#i-arrow-right');
                sshConnectBtn.querySelector('.ico').classList.remove('ico-spin');

                if (d && d.ok) {
                    isSshConnected = true;
                    var detailStr = (d.user ? d.user + '@' : '') + d.host + ' (port ' + d.port + ')';
                    $('#sshActiveDetails').textContent = detailStr;
                    if ($('#dbHost') && $('#targetHost')) $('#dbHost').value = $('#targetHost').value || '127.0.0.1';
                    if ($('#dbPort') && $('#targetPort')) $('#dbPort').value = $('#targetPort').value || DEFAULT_PORTS[dbType.value] || '';
                    toast('SSH tunnel established!', 'ok', 2000);
                    setPane('ssh');
                    var pw = $('#dbPass');
                    if (pw) setTimeout(function () { pw.focus(); }, 100);
                } else {
                    toast((d && d.error) || 'Failed to establish SSH tunnel.', 'error', 7000);
                }
            })
            .catch(function (err) {
                sshConnectBtn.disabled = false;
                if (lbl) lbl.textContent = 'Connect SSH Tunnel';
                if (ic) ic.setAttribute('href', '#i-arrow-right');
                sshConnectBtn.querySelector('.ico').classList.remove('ico-spin');
                toast('Network error while connecting SSH tunnel.', 'error');
            });
        });
    }

    // Step 2: Disconnect SSH button
    var sshDisconnectBtn = $('#sshDisconnectBtn');
    if (sshDisconnectBtn) {
        sshDisconnectBtn.addEventListener('click', function () {
            sshDisconnectBtn.disabled = true;
            var body = new URLSearchParams();
            body.set('csrf_token', CSRF);
            fetch('?action=ssh_disconnect', {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: body.toString()
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                sshDisconnectBtn.disabled = false;
                isSshConnected = false;
                toast('SSH tunnel disconnected.', 'info', 1800);
                setPane('ssh');
            })
            .catch(function () {
                sshDisconnectBtn.disabled = false;
                isSshConnected = false;
                setPane('ssh');
            });
        });
    }

    // Step 2: Back to SSH settings
    var sshBackBtn = $('#sshBackBtn');
    if (sshBackBtn) {
        sshBackBtn.addEventListener('click', function () {
            $('#sshStep1').style.display = 'block';
            loginForm.style.display = 'none';
            $('#stepPill1').classList.add('active');
            $('#stepPill1').classList.remove('done');
            $('#stepPill2').classList.remove('active');
        });
    }

    // Direct URL input
    var uri = $('#uriInput');
    if (uri) uri.addEventListener('input', function () {
        var v = this.value.trim();
        if (!v) return;
        try {
            var sq = v.match(/^sqlite:\/\/(.*)$/i);
            if (sq) {
                dbType.value = 'sqlite'; syncType();
                $('#dbHost').value = sq[1].replace(/^\//, '/');
                return;
            }
            var u = new URL(v);
            var p = u.protocol.replace(':', '').toLowerCase();
            if (/^postgres|^pg/.test(p)) dbType.value = 'pgsql';
            else if (/^mysql|^mariadb/.test(p)) dbType.value = 'mysql';
            syncType();
            if (u.hostname) $('#dbHost').value = decodeURIComponent(u.hostname);
            if (u.port) $('#dbPort').value = u.port;
            if (u.username) $('#dbUser').value = decodeURIComponent(u.username);
            if (u.password) $('#dbPass').value = decodeURIComponent(u.password);
            var path = u.pathname.replace(/^\//, '');
            if (path) $('#dbName').value = decodeURIComponent(path);
            if (/sslmode=require|ssl=true/i.test(u.search)) $('#dbSsl').checked = true;
            toast('Connection details filled in', 'ok', 1800);
        } catch (_) {}
    });

    // Form submission progress indicator
    loginForm.addEventListener('submit', function () {
        var b = loginForm.querySelector('[name=login]');
        if (!b) return;
        var lbl = b.querySelector('.btn-label');
        if (lbl) lbl.textContent = $('#useSsh').value === '1' ? 'Connecting to database…' : 'Connecting…';
        var ic = b.querySelector('.ico use');
        if (ic) ic.setAttribute('href', '#i-loader-circle');
        b.querySelector('.ico').classList.add('ico-spin');
        setTimeout(function () { b.disabled = true; }, 0);
    });

    // ── Vault ──
    function vaultCall(op, extra) {
        var body = new URLSearchParams();
        body.set('csrf_token', CSRF);
        body.set('op', op);
        Object.keys(extra || {}).forEach(function (k) { body.set(k, extra[k]); });
        return fetch('?action=vault', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: body.toString()
        }).then(function (r) { return r.json(); });
    }
    var unlockBtn = $('#vaultUnlock');
    if (unlockBtn) {
        var renderVault = function (profiles) {
            var list = $('#vaultList');
            var names = Object.keys(profiles || {});
            if (!names.length) {
                list.innerHTML = '<div class="empty" style="padding:24px"><span>No saved connections yet.</span></div>';
                return;
            }
            list.innerHTML = names.map(function (n) {
                var p = profiles[n];
                return '<div class="flex" style="padding:9px;border:1px solid var(--border);border-radius:var(--r-sm);margin-bottom:7px">' +
                    icon(p.ssh_enabled ? 'shield-check' : 'database') +
                    '<div class="grow" style="min-width:0"><div style="font-weight:650">' + escapeHtml(n) + '</div>' +
                    '<div class="small faint ellipsis">' + escapeHtml((p.user || '') + '@' + (p.host || '') + (p.dbname ? ' / ' + p.dbname : '')) + '</div></div>' +
                    '<button type="button" class="btn btn-primary btn-sm v-go" data-n="' + escapeHtml(n) + '">Connect</button>' +
                    '<button type="button" class="btn btn-ghost btn-icon btn-sm v-del" data-n="' + escapeHtml(n) + '">' + icon('trash-2') + '</button></div>';
            }).join('');
        };
        unlockBtn.addEventListener('click', function () {
            var m = $('#vaultMaster').value;
            if (!m) { toast('Enter your master password.', 'error'); return; }
            vaultCall('list', { master: m }).then(function (d) {
                if (!d.ok) { toast(d.error || 'Could not unlock.', 'error'); return; }
                renderVault(d.profiles);
                toast('Unlocked', 'ok', 1500);
            }).catch(function () { toast('Request failed', 'error'); });
        });
        $('#vaultList').addEventListener('click', function (e) {
            var go = e.target.closest('.v-go'), del = e.target.closest('.v-del');
            var m = $('#vaultMaster').value;
            if (go) {
                go.disabled = true; go.textContent = 'Connecting…';
                vaultCall('connect', { master: m, name: go.getAttribute('data-n') }).then(function (d) {
                    if (d.ok) window.location.href = d.redirect;
                    else { toast(d.error || 'Connection failed', 'error'); go.disabled = false; go.textContent = 'Connect'; }
                });
            } else if (del) {
                vaultCall('delete', { master: m, name: del.getAttribute('data-n') }).then(function (d) {
                    if (d.ok) { unlockBtn.click(); toast('Deleted', 'ok', 1500); }
                    else toast(d.error || 'Delete failed', 'error');
                });
            }
        });
    }
    var saveBtn = $('#saveProfileBtn');
    if (saveBtn) saveBtn.addEventListener('click', function () {
        var name = $('#saveName').value.trim(), master = $('#saveMaster').value;
        if (!name || !master) { toast('A name and master password are both required.', 'error'); return; }
        var p = {
            type: dbType.value, host: $('#dbHost').value, port: $('#dbPort').value,
            user: $('#dbUser').value, pass: $('#dbPass').value, dbname: $('#dbName').value,
            ssl: $('#dbSsl').checked,
            ssh_enabled: $('#useSsh').value === '1',
            ssh_host: $('#sshHost') ? $('#sshHost').value : '', ssh_port: $('#sshPort') ? $('#sshPort').value : 22,
            ssh_user: $('#sshUser') ? $('#sshUser').value : '', ssh_auth: sshAuth ? sshAuth.value : 'agent',
            ssh_pass: $('#sshPass') ? $('#sshPass').value : '', ssh_key: $('#sshKey') ? $('#sshKey').value : '',
            ssh_key_pass: $('#sshKeyPass') ? $('#sshKeyPass').value : '',
            ssh_key_is_path: $('#sshKeyMode') ? $('#sshKeyMode').value === 'path' : false,
            ssh_local_port: $('#sshLocalPort') ? $('#sshLocalPort').value : ''
        };
        saveBtn.disabled = true;
        vaultCall('save', { master: master, name: name, profile: JSON.stringify(p) }).then(function (d) {
            saveBtn.disabled = false;
            toast(d.ok ? 'Connection saved' : (d.error || 'Save failed'), d.ok ? 'ok' : 'error');
        }).catch(function () { saveBtn.disabled = false; toast('Save failed', 'error'); });
    });
}

})();

</script>
</body>
</html>
