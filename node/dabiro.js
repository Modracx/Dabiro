'use strict';
/**
 * Dabiro - Professional Database Management System (Node.js Edition)
 * Feature-complete port of the PHP edition; the UI, stylesheet and client-side
 * code are generated from the same shared assets, so the two cannot drift.
 *
 * Version: 2.0.0
 * Kenneth D'silva (Modracx), Copyright (c) 2025
 * Licensed under the MIT License - https://opensource.org/licenses/MIT
 *
 * Icons: Lucide (ISC License) - https://lucide.dev
 */

const express = require('express');
const session = require('express-session');
const cookieParser = require('cookie-parser');
const multer = require('multer');
const crypto = require('crypto');
const net = require('net');
const fs = require('fs');
const path = require('path');
const os = require('os');

// Optional drivers - absent ones simply disable their engine.
let mysql2 = null, pg = null, sqlite3 = null, SSH2 = null;
try { mysql2 = require('mysql2/promise'); } catch (_) {}
try { pg = require('pg'); } catch (_) {}
try { sqlite3 = require('sqlite3'); } catch (_) {}
try { SSH2 = require('ssh2'); } catch (_) {}

// ─── Configuration ────────────────────────────────────────────────────────────
const VERSION = '2.0.0';
const SESSION_TIMEOUT = 3600 * 1000;
const PORT = parseInt(process.env.PORT, 10) || 5050;
const HOST = process.env.HOST || '127.0.0.1';
const DATA_DIR = process.env.DABIRO_DATA_DIR || path.join(os.tmpdir(), '.dabiro');

// A random per-boot secret would invalidate every session on restart, so warn
// once and let the operator pin it.
let SESSION_SECRET = process.env.SESSION_SECRET;
if (!SESSION_SECRET) {
    SESSION_SECRET = crypto.randomBytes(32).toString('hex');
    console.warn('[dabiro] SESSION_SECRET is not set - sessions will not survive a restart.');
}

function dataDir() {
    try {
        if (!fs.existsSync(DATA_DIR)) fs.mkdirSync(DATA_DIR, { recursive: true, mode: 0o700 });
        return DATA_DIR;
    } catch (_) { return null; }
}

// ─── Translations ─────────────────────────────────────────────────────────────
// Mirrors the PHP edition: only 'en' carries strings, everything else falls back.
const TRANSLATIONS = { en: {
        "app_name": "Dabiro",
        "app_tagline": "Professional Database Management Interface",
        "database_type_label": "Database Engine",
        "host_label": "Host / Server",
        "port_label": "Port",
        "username_label": "Username",
        "password_label": "Password",
        "database_name_label": "Database",
        "ssl_label": "Require SSL / TLS Encryption",
        "connect_button": "Connect",
        "connect_uri_label": "Connection URL",
        "saved_connections": "Saved Connections",
        "logout": "Disconnect",
        "databases": "Databases",
        "schemas": "Schemas",
        "schema": "Schema",
        "tables": "Tables",
        "views": "Views",
        "browse": "Browse",
        "structure": "Structure",
        "sql_console": "SQL",
        "import_data": "Import",
        "export_data": "Export",
        "global_search": "Search",
        "operations": "Operations",
        "create_database": "Create Database",
        "create_table": "Create Table",
        "table_name": "Table",
        "columns": "Columns",
        "indexes": "Indexes",
        "foreign_keys": "Foreign Keys",
        "add_column": "Add Column",
        "add_index": "Add Index",
        "add_condition": "Add Condition",
        "rename_table": "Rename Table",
        "copy_table": "Copy Table",
        "drop": "Drop",
        "truncate": "Empty",
        "drop_selected": "Drop Selected",
        "truncate_selected": "Empty Selected",
        "insert_record": "Insert Row",
        "edit_record": "Edit Row",
        "save": "Save",
        "cancel": "Cancel",
        "search": "Search",
        "filter": "Filter",
        "clear": "Clear",
        "total_size": "Size",
        "data_size": "Data",
        "index_size": "Index",
        "overhead": "Overhead",
        "engine": "Engine",
        "collation": "Collation",
        "actions": "Actions",
        "query_results": "Results",
        "execution_time": "Time",
        "rows": "rows",
        "records": "Rows",
        "server": "Server",
        "total_records": "rows",
        "page": "page",
        "rows_per_page": "Per page",
        "recent_queries": "History",
        "execute_query": "Run",
        "export_query": "Save .sql",
        "back_to_table": "Back",
        "select_database": "Database",
        "export_format": "Format",
        "download_database": "Download",
        "export_entire_database": "Export Database"
},
"es": {
        "app_name": "Dabiro",
        "app_tagline": "Interfaz profesional de gestión de bases de datos",
        "database_type_label": "Motor de base de datos",
        "host_label": "Host / Servidor",
        "port_label": "Puerto",
        "username_label": "Usuario",
        "password_label": "Contraseña",
        "database_name_label": "Base de datos",
        "ssl_label": "Requerir cifrado SSL / TLS",
        "connect_button": "Conectar",
        "connect_uri_label": "URL de conexión",
        "saved_connections": "Conexiones guardadas",
        "logout": "Desconectar",
        "databases": "Bases de datos",
        "schemas": "Esquemas",
        "schema": "Esquema",
        "tables": "Tablas",
        "views": "Vistas",
        "browse": "Explorar",
        "structure": "Estructura",
        "sql_console": "SQL",
        "import_data": "Importar",
        "export_data": "Exportar",
        "global_search": "Buscar",
        "operations": "Operaciones",
        "create_database": "Crear base de datos",
        "create_table": "Crear tabla",
        "table_name": "Tabla",
        "columns": "Columnas",
        "indexes": "Índices",
        "foreign_keys": "Claves foráneas",
        "add_column": "Añadir columna",
        "add_index": "Añadir índice",
        "add_condition": "Añadir condición",
        "rename_table": "Renombrar tabla",
        "copy_table": "Copiar tabla",
        "drop": "Eliminar",
        "truncate": "Vaciar",
        "drop_selected": "Eliminar seleccionadas",
        "truncate_selected": "Vaciar seleccionadas",
        "insert_record": "Insertar fila",
        "edit_record": "Editar fila",
        "save": "Guardar",
        "cancel": "Cancelar",
        "search": "Buscar",
        "filter": "Filtrar",
        "clear": "Limpiar",
        "total_size": "Tamaño",
        "data_size": "Datos",
        "index_size": "Índice",
        "overhead": "Sobrecarga",
        "engine": "Motor",
        "collation": "Cotejamiento",
        "actions": "Acciones",
        "query_results": "Resultados",
        "execution_time": "Tiempo",
        "rows": "filas",
        "records": "Filas",
        "server": "Servidor",
        "total_records": "filas",
        "page": "página",
        "rows_per_page": "Por página",
        "recent_queries": "Historial",
        "execute_query": "Ejecutar",
        "export_query": "Guardar .sql",
        "back_to_table": "Volver",
        "select_database": "Base de datos",
        "export_format": "Formato",
        "download_database": "Descargar",
        "export_entire_database": "Exportar base de datos"
},
"fr": {
        "app_name": "Dabiro",
        "app_tagline": "Interface professionnelle de gestion de bases de données",
        "database_type_label": "Moteur de base de données",
        "host_label": "Hôte / Serveur",
        "port_label": "Port",
        "username_label": "Utilisateur",
        "password_label": "Mot de passe",
        "database_name_label": "Base de données",
        "ssl_label": "Exiger le chiffrement SSL / TLS",
        "connect_button": "Se connecter",
        "connect_uri_label": "URL de connexion",
        "saved_connections": "Connexions enregistrées",
        "logout": "Déconnecter",
        "databases": "Bases de données",
        "schemas": "Schémas",
        "schema": "Schéma",
        "tables": "Tables",
        "views": "Vues",
        "browse": "Parcourir",
        "structure": "Structure",
        "sql_console": "SQL",
        "import_data": "Importer",
        "export_data": "Exporter",
        "global_search": "Rechercher",
        "operations": "Opérations",
        "create_database": "Créer une base de données",
        "create_table": "Créer une table",
        "table_name": "Table",
        "columns": "Colonnes",
        "indexes": "Index",
        "foreign_keys": "Clés étrangères",
        "add_column": "Ajouter une colonne",
        "add_index": "Ajouter un index",
        "add_condition": "Ajouter une condition",
        "rename_table": "Renommer la table",
        "copy_table": "Copier la table",
        "drop": "Supprimer",
        "truncate": "Vider",
        "drop_selected": "Supprimer la sélection",
        "truncate_selected": "Vider la sélection",
        "insert_record": "Insérer une ligne",
        "edit_record": "Modifier la ligne",
        "save": "Enregistrer",
        "cancel": "Annuler",
        "search": "Rechercher",
        "filter": "Filtrer",
        "clear": "Effacer",
        "total_size": "Taille",
        "data_size": "Données",
        "index_size": "Index",
        "overhead": "Surcharge",
        "engine": "Moteur",
        "collation": "Interclassement",
        "actions": "Actions",
        "query_results": "Résultats",
        "execution_time": "Temps",
        "rows": "lignes",
        "records": "Lignes",
        "server": "Serveur",
        "total_records": "lignes",
        "page": "page",
        "rows_per_page": "Par page",
        "recent_queries": "Historique",
        "execute_query": "Exécuter",
        "export_query": "Enregistrer .sql",
        "back_to_table": "Retour",
        "select_database": "Base de données",
        "export_format": "Format",
        "download_database": "Télécharger",
        "export_entire_database": "Exporter la base de données"
},
"de": {
        "app_name": "Dabiro",
        "app_tagline": "Professionelle Datenbankverwaltung",
        "database_type_label": "Datenbank-Engine",
        "host_label": "Host / Server",
        "port_label": "Port",
        "username_label": "Benutzername",
        "password_label": "Passwort",
        "database_name_label": "Datenbank",
        "ssl_label": "SSL-/TLS-Verschlüsselung erforderlich",
        "connect_button": "Verbinden",
        "connect_uri_label": "Verbindungs-URL",
        "saved_connections": "Gespeicherte Verbindungen",
        "logout": "Trennen",
        "databases": "Datenbanken",
        "schemas": "Schemas",
        "schema": "Schema",
        "tables": "Tabellen",
        "views": "Views",
        "browse": "Anzeigen",
        "structure": "Struktur",
        "sql_console": "SQL",
        "import_data": "Importieren",
        "export_data": "Exportieren",
        "global_search": "Suchen",
        "operations": "Operationen",
        "create_database": "Datenbank erstellen",
        "create_table": "Tabelle erstellen",
        "table_name": "Tabelle",
        "columns": "Spalten",
        "indexes": "Indizes",
        "foreign_keys": "Fremdschlüssel",
        "add_column": "Spalte hinzufügen",
        "add_index": "Index hinzufügen",
        "add_condition": "Bedingung hinzufügen",
        "rename_table": "Tabelle umbenennen",
        "copy_table": "Tabelle kopieren",
        "drop": "Löschen",
        "truncate": "Leeren",
        "drop_selected": "Auswahl löschen",
        "truncate_selected": "Auswahl leeren",
        "insert_record": "Zeile einfügen",
        "edit_record": "Zeile bearbeiten",
        "save": "Speichern",
        "cancel": "Abbrechen",
        "search": "Suchen",
        "filter": "Filtern",
        "clear": "Zurücksetzen",
        "total_size": "Größe",
        "data_size": "Daten",
        "index_size": "Index",
        "overhead": "Overhead",
        "engine": "Engine",
        "collation": "Kollation",
        "actions": "Aktionen",
        "query_results": "Ergebnisse",
        "execution_time": "Zeit",
        "rows": "Zeilen",
        "records": "Zeilen",
        "server": "Server",
        "total_records": "Zeilen",
        "page": "Seite",
        "rows_per_page": "Pro Seite",
        "recent_queries": "Verlauf",
        "execute_query": "Ausführen",
        "export_query": ".sql speichern",
        "back_to_table": "Zurück",
        "select_database": "Datenbank",
        "export_format": "Format",
        "download_database": "Herunterladen",
        "export_entire_database": "Datenbank exportieren"
},
"pt": {
        "app_name": "Dabiro",
        "app_tagline": "Interface profissional de gerenciamento de banco de dados",
        "database_type_label": "Motor de banco de dados",
        "host_label": "Host / Servidor",
        "port_label": "Porta",
        "username_label": "Usuário",
        "password_label": "Senha",
        "database_name_label": "Banco de dados",
        "ssl_label": "Exigir criptografia SSL / TLS",
        "connect_button": "Conectar",
        "connect_uri_label": "URL de conexão",
        "saved_connections": "Conexões salvas",
        "logout": "Desconectar",
        "databases": "Bancos de dados",
        "schemas": "Esquemas",
        "schema": "Esquema",
        "tables": "Tabelas",
        "views": "Visões",
        "browse": "Navegar",
        "structure": "Estrutura",
        "sql_console": "SQL",
        "import_data": "Importar",
        "export_data": "Exportar",
        "global_search": "Pesquisar",
        "operations": "Operações",
        "create_database": "Criar banco de dados",
        "create_table": "Criar tabela",
        "table_name": "Tabela",
        "columns": "Colunas",
        "indexes": "Índices",
        "foreign_keys": "Chaves estrangeiras",
        "add_column": "Adicionar coluna",
        "add_index": "Adicionar índice",
        "add_condition": "Adicionar condição",
        "rename_table": "Renomear tabela",
        "copy_table": "Copiar tabela",
        "drop": "Excluir",
        "truncate": "Esvaziar",
        "drop_selected": "Excluir selecionadas",
        "truncate_selected": "Esvaziar selecionadas",
        "insert_record": "Inserir linha",
        "edit_record": "Editar linha",
        "save": "Salvar",
        "cancel": "Cancelar",
        "search": "Pesquisar",
        "filter": "Filtrar",
        "clear": "Limpar",
        "total_size": "Tamanho",
        "data_size": "Dados",
        "index_size": "Índice",
        "overhead": "Sobrecarga",
        "engine": "Motor",
        "collation": "Collation",
        "actions": "Ações",
        "query_results": "Resultados",
        "execution_time": "Tempo",
        "rows": "linhas",
        "records": "Linhas",
        "server": "Servidor",
        "total_records": "linhas",
        "page": "página",
        "rows_per_page": "Por página",
        "recent_queries": "Histórico",
        "execute_query": "Executar",
        "export_query": "Salvar .sql",
        "back_to_table": "Voltar",
        "select_database": "Banco de dados",
        "export_format": "Formato",
        "download_database": "Baixar",
        "export_entire_database": "Exportar banco de dados"
},
"zh": {
        "app_name": "Dabiro",
        "app_tagline": "专业数据库管理界面",
        "database_type_label": "数据库引擎",
        "host_label": "主机 / 服务器",
        "port_label": "端口",
        "username_label": "用户名",
        "password_label": "密码",
        "database_name_label": "数据库",
        "ssl_label": "要求 SSL / TLS 加密",
        "connect_button": "连接",
        "connect_uri_label": "连接 URL",
        "saved_connections": "已保存的连接",
        "logout": "断开连接",
        "databases": "数据库",
        "schemas": "模式",
        "schema": "模式",
        "tables": "表",
        "views": "视图",
        "browse": "浏览",
        "structure": "结构",
        "sql_console": "SQL",
        "import_data": "导入",
        "export_data": "导出",
        "global_search": "搜索",
        "operations": "操作",
        "create_database": "创建数据库",
        "create_table": "创建表",
        "table_name": "表",
        "columns": "列",
        "indexes": "索引",
        "foreign_keys": "外键",
        "add_column": "添加列",
        "add_index": "添加索引",
        "add_condition": "添加条件",
        "rename_table": "重命名表",
        "copy_table": "复制表",
        "drop": "删除",
        "truncate": "清空",
        "drop_selected": "删除所选",
        "truncate_selected": "清空所选",
        "insert_record": "插入行",
        "edit_record": "编辑行",
        "save": "保存",
        "cancel": "取消",
        "search": "搜索",
        "filter": "筛选",
        "clear": "清除",
        "total_size": "大小",
        "data_size": "数据",
        "index_size": "索引",
        "overhead": "开销",
        "engine": "引擎",
        "collation": "排序规则",
        "actions": "操作",
        "query_results": "结果",
        "execution_time": "耗时",
        "rows": "行",
        "records": "行数",
        "server": "服务器",
        "total_records": "行",
        "page": "页",
        "rows_per_page": "每页",
        "recent_queries": "历史",
        "execute_query": "执行",
        "export_query": "保存 .sql",
        "back_to_table": "返回",
        "select_database": "数据库",
        "export_format": "格式",
        "download_database": "下载",
        "export_entire_database": "导出数据库"
},
"ja": {
        "app_name": "Dabiro",
        "app_tagline": "プロフェッショナルなデータベース管理インターフェース",
        "database_type_label": "データベースエンジン",
        "host_label": "ホスト / サーバー",
        "port_label": "ポート",
        "username_label": "ユーザー名",
        "password_label": "パスワード",
        "database_name_label": "データベース",
        "ssl_label": "SSL / TLS 暗号化を必須にする",
        "connect_button": "接続",
        "connect_uri_label": "接続 URL",
        "saved_connections": "保存された接続",
        "logout": "切断",
        "databases": "データベース",
        "schemas": "スキーマ",
        "schema": "スキーマ",
        "tables": "テーブル",
        "views": "ビュー",
        "browse": "参照",
        "structure": "構造",
        "sql_console": "SQL",
        "import_data": "インポート",
        "export_data": "エクスポート",
        "global_search": "検索",
        "operations": "操作",
        "create_database": "データベースを作成",
        "create_table": "テーブルを作成",
        "table_name": "テーブル",
        "columns": "カラム",
        "indexes": "インデックス",
        "foreign_keys": "外部キー",
        "add_column": "カラムを追加",
        "add_index": "インデックスを追加",
        "add_condition": "条件を追加",
        "rename_table": "テーブル名を変更",
        "copy_table": "テーブルをコピー",
        "drop": "削除",
        "truncate": "空にする",
        "drop_selected": "選択項目を削除",
        "truncate_selected": "選択項目を空にする",
        "insert_record": "行を挿入",
        "edit_record": "行を編集",
        "save": "保存",
        "cancel": "キャンセル",
        "search": "検索",
        "filter": "フィルター",
        "clear": "クリア",
        "total_size": "サイズ",
        "data_size": "データ",
        "index_size": "インデックス",
        "overhead": "オーバーヘッド",
        "engine": "エンジン",
        "collation": "照合順序",
        "actions": "操作",
        "query_results": "結果",
        "execution_time": "時間",
        "rows": "行",
        "records": "行",
        "server": "サーバー",
        "total_records": "行",
        "page": "ページ",
        "rows_per_page": "表示件数",
        "recent_queries": "履歴",
        "execute_query": "実行",
        "export_query": ".sql を保存",
        "back_to_table": "戻る",
        "select_database": "データベース",
        "export_format": "形式",
        "download_database": "ダウンロード",
        "export_entire_database": "データベースをエクスポート"
},
"ar": {
        "app_name": "Dabiro",
        "app_tagline": "واجهة احترافية لإدارة قواعد البيانات",
        "database_type_label": "محرك قاعدة البيانات",
        "host_label": "المضيف / الخادم",
        "port_label": "المنفذ",
        "username_label": "اسم المستخدم",
        "password_label": "كلمة المرور",
        "database_name_label": "قاعدة البيانات",
        "ssl_label": "طلب تشفير SSL / TLS",
        "connect_button": "اتصال",
        "connect_uri_label": "رابط الاتصال",
        "saved_connections": "الاتصالات المحفوظة",
        "logout": "قطع الاتصال",
        "databases": "قواعد البيانات",
        "schemas": "المخططات",
        "schema": "المخطط",
        "tables": "الجداول",
        "views": "العروض",
        "browse": "استعراض",
        "structure": "البنية",
        "sql_console": "SQL",
        "import_data": "استيراد",
        "export_data": "تصدير",
        "global_search": "بحث",
        "operations": "العمليات",
        "create_database": "إنشاء قاعدة بيانات",
        "create_table": "إنشاء جدول",
        "table_name": "الجدول",
        "columns": "الأعمدة",
        "indexes": "الفهارس",
        "foreign_keys": "المفاتيح الأجنبية",
        "add_column": "إضافة عمود",
        "add_index": "إضافة فهرس",
        "add_condition": "إضافة شرط",
        "rename_table": "إعادة تسمية الجدول",
        "copy_table": "نسخ الجدول",
        "drop": "حذف",
        "truncate": "إفراغ",
        "drop_selected": "حذف المحدد",
        "truncate_selected": "إفراغ المحدد",
        "insert_record": "إدراج صف",
        "edit_record": "تعديل الصف",
        "save": "حفظ",
        "cancel": "إلغاء",
        "search": "بحث",
        "filter": "تصفية",
        "clear": "مسح",
        "total_size": "الحجم",
        "data_size": "البيانات",
        "index_size": "الفهرس",
        "overhead": "العبء الزائد",
        "engine": "المحرك",
        "collation": "الترتيب",
        "actions": "الإجراءات",
        "query_results": "النتائج",
        "execution_time": "الوقت",
        "rows": "صفوف",
        "records": "الصفوف",
        "server": "الخادم",
        "total_records": "صفوف",
        "page": "صفحة",
        "rows_per_page": "لكل صفحة",
        "recent_queries": "السجل",
        "execute_query": "تنفيذ",
        "export_query": "حفظ .sql",
        "back_to_table": "رجوع",
        "select_database": "قاعدة البيانات",
        "export_format": "الصيغة",
        "download_database": "تنزيل",
        "export_entire_database": "تصدير قاعدة البيانات"
},
"it": {
        "app_name": "Dabiro",
        "app_tagline": "Interfaccia professionale per la gestione dei database",
        "database_type_label": "Motore del database",
        "host_label": "Host / Server",
        "port_label": "Porta",
        "username_label": "Nome utente",
        "password_label": "Password",
        "database_name_label": "Database",
        "ssl_label": "Richiedi crittografia SSL / TLS",
        "connect_button": "Connetti",
        "connect_uri_label": "URL di connessione",
        "saved_connections": "Connessioni salvate",
        "logout": "Disconnetti",
        "databases": "Database",
        "schemas": "Schemi",
        "schema": "Schema",
        "tables": "Tabelle",
        "views": "Viste",
        "browse": "Sfoglia",
        "structure": "Struttura",
        "sql_console": "SQL",
        "import_data": "Importa",
        "export_data": "Esporta",
        "global_search": "Cerca",
        "operations": "Operazioni",
        "create_database": "Crea database",
        "create_table": "Crea tabella",
        "table_name": "Tabella",
        "columns": "Colonne",
        "indexes": "Indici",
        "foreign_keys": "Chiavi esterne",
        "add_column": "Aggiungi colonna",
        "add_index": "Aggiungi indice",
        "add_condition": "Aggiungi condizione",
        "rename_table": "Rinomina tabella",
        "copy_table": "Copia tabella",
        "drop": "Elimina",
        "truncate": "Svuota",
        "drop_selected": "Elimina selezionate",
        "truncate_selected": "Svuota selezionate",
        "insert_record": "Inserisci riga",
        "edit_record": "Modifica riga",
        "save": "Salva",
        "cancel": "Annulla",
        "search": "Cerca",
        "filter": "Filtra",
        "clear": "Cancella",
        "total_size": "Dimensione",
        "data_size": "Dati",
        "index_size": "Indice",
        "overhead": "Overhead",
        "engine": "Motore",
        "collation": "Collation",
        "actions": "Azioni",
        "query_results": "Risultati",
        "execution_time": "Tempo",
        "rows": "righe",
        "records": "Righe",
        "server": "Server",
        "total_records": "righe",
        "page": "pagina",
        "rows_per_page": "Per pagina",
        "recent_queries": "Cronologia",
        "execute_query": "Esegui",
        "export_query": "Salva .sql",
        "back_to_table": "Indietro",
        "select_database": "Database",
        "export_format": "Formato",
        "download_database": "Scarica",
        "export_entire_database": "Esporta database"
},
"ru": {
        "app_name": "Dabiro",
        "app_tagline": "Профессиональный интерфейс управления базами данных",
        "database_type_label": "Движок базы данных",
        "host_label": "Хост / Сервер",
        "port_label": "Порт",
        "username_label": "Имя пользователя",
        "password_label": "Пароль",
        "database_name_label": "База данных",
        "ssl_label": "Требовать шифрование SSL / TLS",
        "connect_button": "Подключиться",
        "connect_uri_label": "URL подключения",
        "saved_connections": "Сохранённые подключения",
        "logout": "Отключиться",
        "databases": "Базы данных",
        "schemas": "Схемы",
        "schema": "Схема",
        "tables": "Таблицы",
        "views": "Представления",
        "browse": "Обзор",
        "structure": "Структура",
        "sql_console": "SQL",
        "import_data": "Импорт",
        "export_data": "Экспорт",
        "global_search": "Поиск",
        "operations": "Операции",
        "create_database": "Создать базу данных",
        "create_table": "Создать таблицу",
        "table_name": "Таблица",
        "columns": "Столбцы",
        "indexes": "Индексы",
        "foreign_keys": "Внешние ключи",
        "add_column": "Добавить столбец",
        "add_index": "Добавить индекс",
        "add_condition": "Добавить условие",
        "rename_table": "Переименовать таблицу",
        "copy_table": "Копировать таблицу",
        "drop": "Удалить",
        "truncate": "Очистить",
        "drop_selected": "Удалить выбранные",
        "truncate_selected": "Очистить выбранные",
        "insert_record": "Вставить строку",
        "edit_record": "Изменить строку",
        "save": "Сохранить",
        "cancel": "Отмена",
        "search": "Поиск",
        "filter": "Фильтр",
        "clear": "Сбросить",
        "total_size": "Размер",
        "data_size": "Данные",
        "index_size": "Индекс",
        "overhead": "Накладные расходы",
        "engine": "Движок",
        "collation": "Сравнение",
        "actions": "Действия",
        "query_results": "Результаты",
        "execution_time": "Время",
        "rows": "строк",
        "records": "Строки",
        "server": "Сервер",
        "total_records": "строк",
        "page": "страница",
        "rows_per_page": "На странице",
        "recent_queries": "История",
        "execute_query": "Выполнить",
        "export_query": "Сохранить .sql",
        "back_to_table": "Назад",
        "select_database": "База данных",
        "export_format": "Формат",
        "download_database": "Скачать",
        "export_entire_database": "Экспорт базы данных"
},
"tr": {
        "app_name": "Dabiro",
        "app_tagline": "Profesyonel veritabanı yönetim arayüzü",
        "database_type_label": "Veritabanı motoru",
        "host_label": "Sunucu / Host",
        "port_label": "Bağlantı noktası",
        "username_label": "Kullanıcı adı",
        "password_label": "Parola",
        "database_name_label": "Veritabanı",
        "ssl_label": "SSL / TLS şifrelemesi iste",
        "connect_button": "Bağlan",
        "connect_uri_label": "Bağlantı URL'si",
        "saved_connections": "Kayıtlı bağlantılar",
        "logout": "Bağlantıyı kes",
        "databases": "Veritabanları",
        "schemas": "Şemalar",
        "schema": "Şema",
        "tables": "Tablolar",
        "views": "Görünümler",
        "browse": "Gözat",
        "structure": "Yapı",
        "sql_console": "SQL",
        "import_data": "İçe aktar",
        "export_data": "Dışa aktar",
        "global_search": "Ara",
        "operations": "İşlemler",
        "create_database": "Veritabanı oluştur",
        "create_table": "Tablo oluştur",
        "table_name": "Tablo",
        "columns": "Sütunlar",
        "indexes": "Dizinler",
        "foreign_keys": "Yabancı anahtarlar",
        "add_column": "Sütun ekle",
        "add_index": "Dizin ekle",
        "add_condition": "Koşul ekle",
        "rename_table": "Tabloyu yeniden adlandır",
        "copy_table": "Tabloyu kopyala",
        "drop": "Sil",
        "truncate": "Boşalt",
        "drop_selected": "Seçilenleri sil",
        "truncate_selected": "Seçilenleri boşalt",
        "insert_record": "Satır ekle",
        "edit_record": "Satırı düzenle",
        "save": "Kaydet",
        "cancel": "İptal",
        "search": "Ara",
        "filter": "Filtrele",
        "clear": "Temizle",
        "total_size": "Boyut",
        "data_size": "Veri",
        "index_size": "Dizin",
        "overhead": "Ek yük",
        "engine": "Motor",
        "collation": "Karşılaştırma",
        "actions": "İşlemler",
        "query_results": "Sonuçlar",
        "execution_time": "Süre",
        "rows": "satır",
        "records": "Satırlar",
        "server": "Sunucu",
        "total_records": "satır",
        "page": "sayfa",
        "rows_per_page": "Sayfa başına",
        "recent_queries": "Geçmiş",
        "execute_query": "Çalıştır",
        "export_query": ".sql kaydet",
        "back_to_table": "Geri",
        "select_database": "Veritabanı",
        "export_format": "Biçim",
        "download_database": "İndir",
        "export_entire_database": "Veritabanını dışa aktar"
},
"hi": {
        "app_name": "Dabiro",
        "app_tagline": "पेशेवर डेटाबेस प्रबंधन इंटरफ़ेस",
        "database_type_label": "डेटाबेस इंजन",
        "host_label": "होस्ट / सर्वर",
        "port_label": "पोर्ट",
        "username_label": "उपयोगकर्ता नाम",
        "password_label": "पासवर्ड",
        "database_name_label": "डेटाबेस",
        "ssl_label": "SSL / TLS एन्क्रिप्शन आवश्यक करें",
        "connect_button": "कनेक्ट करें",
        "connect_uri_label": "कनेक्शन URL",
        "saved_connections": "सहेजे गए कनेक्शन",
        "logout": "डिस्कनेक्ट करें",
        "databases": "डेटाबेस",
        "schemas": "स्कीमा",
        "schema": "स्कीमा",
        "tables": "तालिकाएँ",
        "views": "व्यू",
        "browse": "ब्राउज़ करें",
        "structure": "संरचना",
        "sql_console": "SQL",
        "import_data": "आयात",
        "export_data": "निर्यात",
        "global_search": "खोजें",
        "operations": "संचालन",
        "create_database": "डेटाबेस बनाएँ",
        "create_table": "तालिका बनाएँ",
        "table_name": "तालिका",
        "columns": "कॉलम",
        "indexes": "इंडेक्स",
        "foreign_keys": "विदेशी कुंजियाँ",
        "add_column": "कॉलम जोड़ें",
        "add_index": "इंडेक्स जोड़ें",
        "add_condition": "शर्त जोड़ें",
        "rename_table": "तालिका का नाम बदलें",
        "copy_table": "तालिका कॉपी करें",
        "drop": "हटाएँ",
        "truncate": "खाली करें",
        "drop_selected": "चयनित हटाएँ",
        "truncate_selected": "चयनित खाली करें",
        "insert_record": "पंक्ति डालें",
        "edit_record": "पंक्ति संपादित करें",
        "save": "सहेजें",
        "cancel": "रद्द करें",
        "search": "खोजें",
        "filter": "फ़िल्टर",
        "clear": "साफ़ करें",
        "total_size": "आकार",
        "data_size": "डेटा",
        "index_size": "इंडेक्स",
        "overhead": "ओवरहेड",
        "engine": "इंजन",
        "collation": "कोलेशन",
        "actions": "क्रियाएँ",
        "query_results": "परिणाम",
        "execution_time": "समय",
        "rows": "पंक्तियाँ",
        "records": "पंक्तियाँ",
        "server": "सर्वर",
        "total_records": "पंक्तियाँ",
        "page": "पृष्ठ",
        "rows_per_page": "प्रति पृष्ठ",
        "recent_queries": "इतिहास",
        "execute_query": "चलाएँ",
        "export_query": ".sql सहेजें",
        "back_to_table": "वापस",
        "select_database": "डेटाबेस",
        "export_format": "प्रारूप",
        "download_database": "डाउनलोड करें",
        "export_entire_database": "डेटाबेस निर्यात करें"
},
"ko": {
        "app_name": "Dabiro",
        "app_tagline": "전문 데이터베이스 관리 인터페이스",
        "database_type_label": "데이터베이스 엔진",
        "host_label": "호스트 / 서버",
        "port_label": "포트",
        "username_label": "사용자 이름",
        "password_label": "비밀번호",
        "database_name_label": "데이터베이스",
        "ssl_label": "SSL / TLS 암호화 필요",
        "connect_button": "연결",
        "connect_uri_label": "연결 URL",
        "saved_connections": "저장된 연결",
        "logout": "연결 끊기",
        "databases": "데이터베이스",
        "schemas": "스키마",
        "schema": "스키마",
        "tables": "테이블",
        "views": "뷰",
        "browse": "찾아보기",
        "structure": "구조",
        "sql_console": "SQL",
        "import_data": "가져오기",
        "export_data": "내보내기",
        "global_search": "검색",
        "operations": "작업",
        "create_database": "데이터베이스 생성",
        "create_table": "테이블 생성",
        "table_name": "테이블",
        "columns": "열",
        "indexes": "인덱스",
        "foreign_keys": "외래 키",
        "add_column": "열 추가",
        "add_index": "인덱스 추가",
        "add_condition": "조건 추가",
        "rename_table": "테이블 이름 변경",
        "copy_table": "테이블 복사",
        "drop": "삭제",
        "truncate": "비우기",
        "drop_selected": "선택 항목 삭제",
        "truncate_selected": "선택 항목 비우기",
        "insert_record": "행 삽입",
        "edit_record": "행 편집",
        "save": "저장",
        "cancel": "취소",
        "search": "검색",
        "filter": "필터",
        "clear": "지우기",
        "total_size": "크기",
        "data_size": "데이터",
        "index_size": "인덱스",
        "overhead": "오버헤드",
        "engine": "엔진",
        "collation": "데이터 정렬",
        "actions": "작업",
        "query_results": "결과",
        "execution_time": "시간",
        "rows": "행",
        "records": "행",
        "server": "서버",
        "total_records": "행",
        "page": "페이지",
        "rows_per_page": "페이지당",
        "recent_queries": "기록",
        "execute_query": "실행",
        "export_query": ".sql 저장",
        "back_to_table": "뒤로",
        "select_database": "데이터베이스",
        "export_format": "형식",
        "download_database": "다운로드",
        "export_entire_database": "데이터베이스 내보내기"
}
};

const LANGS = {
    en: 'English', es: 'Español', fr: 'Français', de: 'Deutsch',
    pt: 'Português', zh: '中文', ja: '日本語', ar: 'العربية',
    it: 'Italiano', ru: 'Русский', tr: 'Türkçe', hi: 'हिन्दी', ko: '한국어',
};

const THEMES = {
    light: 'Light', dark: 'Dark', slate: 'Slate',
    blue: 'Blue', green: 'Green', purple: 'Purple', sunset: 'Sunset',
};

function t(lang, key, def) {
    if (TRANSLATIONS[lang] && TRANSLATIONS[lang][key]) return TRANSLATIONS[lang][key];
    if (TRANSLATIONS.en[key]) return TRANSLATIONS.en[key];
    return def !== undefined ? def : key;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
const ESC = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#x27;' };
function h(v) {
    if (v === null || v === undefined) return '';
    return String(v).replace(/[&<>"']/g, (c) => ESC[c]);
}

function formatBytes(bytes, precision = 2) {
    bytes = Number(bytes);
    if (!isFinite(bytes) || bytes <= 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    const pow = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
    const val = bytes / Math.pow(1024, pow);
    return (pow === 0 ? String(Math.round(val)) : val.toFixed(precision)) + ' ' + units[pow];
}

function formatNum(n) {
    const v = Number(n);
    return isFinite(v) ? v.toLocaleString('en-US') : '0';
}

/** Truncate without splitting a surrogate pair. */
function truncateCell(str, len = 120) {
    const chars = Array.from(String(str));
    if (chars.length <= len) return { text: String(str), truncated: false };
    return { text: chars.slice(0, len).join(''), truncated: true };
}

function qs(obj) {
    const p = new URLSearchParams();
    for (const [k, v] of Object.entries(obj)) {
        if (v !== '' && v !== null && v !== undefined) p.set(k, String(v));
    }
    return p.toString();
}

/** Values may arrive as scalars or arrays depending on how the form was built. */
function arr(v) {
    if (v === undefined || v === null) return [];
    return Array.isArray(v) ? v : [v];
}

// ─── CSRF ─────────────────────────────────────────────────────────────────────
function csrfToken(req) {
    if (!req.session.csrf) req.session.csrf = crypto.randomBytes(32).toString('hex');
    return req.session.csrf;
}

function validateCsrf(req, token) {
    if (!token || !req.session.csrf) return false;
    const a = Buffer.from(String(req.session.csrf));
    const b = Buffer.from(String(token));
    return a.length === b.length && crypto.timingSafeEqual(a, b);
}

// ─── Connection Vault ─────────────────────────────────────────────────────────
/**
 * Same format and guarantees as the PHP edition: AES-256-GCM under a PBKDF2
 * key, written 0600, master password never persisted.
 */
const Vault = {
    ITERATIONS: 310000,
    MAGIC: Buffer.from('DABIROV1\n'),

    file() {
        const d = dataDir();
        return d ? path.join(d, 'vault.bin') : null;
    },

    available() {
        if (!dataDir()) return 'No writable data directory. Set DABIRO_DATA_DIR to enable saved connections.';
        return null;
    },

    exists() {
        const f = this.file();
        try { return !!f && fs.statSync(f).size > this.MAGIC.length; } catch (_) { return false; }
    },

    key(password, salt) {
        return crypto.pbkdf2Sync(password, salt, this.ITERATIONS, 32, 'sha256');
    },

    /** @returns {object|null} null means the master password was wrong. */
    load(master) {
        const f = this.file();
        if (!f || !fs.existsSync(f)) return {};
        let raw;
        try { raw = fs.readFileSync(f); } catch (_) { return null; }
        if (raw.length < this.MAGIC.length + 44) return null;
        if (!raw.subarray(0, this.MAGIC.length).equals(this.MAGIC)) return null;

        const body = raw.subarray(this.MAGIC.length);
        const salt = body.subarray(0, 16);
        const iv = body.subarray(16, 28);
        const tag = body.subarray(28, 44);
        const ct = body.subarray(44);
        try {
            const d = crypto.createDecipheriv('aes-256-gcm', this.key(master, salt), iv);
            d.setAuthTag(tag);
            const plain = Buffer.concat([d.update(ct), d.final()]).toString('utf8');
            const parsed = JSON.parse(plain);
            return parsed && typeof parsed === 'object' ? parsed : {};
        } catch (_) {
            return null;
        }
    },

    save(master, profiles) {
        const f = this.file();
        if (!f) return false;
        try {
            const salt = crypto.randomBytes(16);
            const iv = crypto.randomBytes(12);
            const c = crypto.createCipheriv('aes-256-gcm', this.key(master, salt), iv);
            const ct = Buffer.concat([c.update(JSON.stringify(profiles), 'utf8'), c.final()]);
            const tmp = f + '.' + process.pid + '.tmp';
            fs.writeFileSync(tmp, Buffer.concat([this.MAGIC, salt, iv, c.getAuthTag(), ct]), { mode: 0o600 });
            fs.renameSync(tmp, f);
            return true;
        } catch (_) { return false; }
    },
};

// ─── SSH Tunnel ───────────────────────────────────────────────────────────────
/**
 * In-process port forward built on ssh2.
 *
 * The PHP edition has to spawn and supervise an `ssh` child process; Node can
 * speak SSH directly, so there is no child to orphan, no askpass file, and no
 * dependency on an ssh binary or sshpass being installed. A local TCP server
 * accepts connections and forwards each one down the SSH transport - exactly
 * what `ssh -L <local>:<dbhost>:<dbport> user@bastion` does.
 *
 * Tunnels are keyed by a fingerprint that includes the credentials, so changing
 * or mistyping a password can never silently reuse someone else's live tunnel.
 */
const tunnels = new Map();

function tunnelFingerprint(cfg) {
    let secret = '';
    if (cfg.auth === 'password') secret = cfg.password || '';
    else if (cfg.auth === 'key') secret = (cfg.key || '') + '\0' + (cfg.keyPass || '') + '\0' + (cfg.keyIsPath ? 'path' : 'inline');
    return crypto.createHash('sha256')
        .update([cfg.host, cfg.port, cfg.user, cfg.targetHost, cfg.targetPort, cfg.auth,
                 crypto.createHash('sha256').update(secret).digest('hex')].join('|'))
        .digest('hex').slice(0, 16);
}

function explainSshError(err, auth) {
    const msg = (err && err.message) ? err.message : String(err || 'unknown error');
    const low = msg.toLowerCase();
    let hint = '';
    if (low.includes('all configured authentication methods failed')) {
        hint = auth === 'agent'
            ? ' SSH refused the credentials. With "use the local SSH agent" the agent must be reachable from this process (SSH_AUTH_SOCK) - otherwise choose key or password auth.'
            : ' SSH refused the credentials. Check the username, and the key or password.';
    } else if (low.includes('econnrefused')) {
        hint = ' The SSH server refused the connection - check the host and SSH port.';
    } else if (low.includes('enotfound') || low.includes('eai_again')) {
        hint = ' The SSH hostname could not be resolved.';
    } else if (low.includes('etimedout') || low.includes('timed out')) {
        hint = ' Timed out reaching the SSH server. A firewall may be blocking it.';
    } else if (low.includes('cannot parse privatekey') || low.includes('unsupported key format')) {
        hint = ' The private key could not be parsed. If it is encrypted, supply the passphrase.';
    } else if (low.includes('bad passphrase') || low.includes('integrity check failed')) {
        hint = ' The key passphrase is wrong.';
    } else if (low.includes('administratively prohibited') || low.includes('open failed')) {
        hint = ' The SSH server refused to open the forward. Confirm the database host/port are reachable from the bastion and that AllowTcpForwarding is enabled.';
    }
    return 'SSH tunnel failed.' + hint + '\n\n' + msg;
}

function readKey(cfg) {
    if (!cfg.key) throw new Error('A private key is required.');
    if (cfg.keyIsPath) {
        const p = String(cfg.key).trim();
        if (!fs.existsSync(p)) throw new Error('Private key file not found: ' + p);
        return fs.readFileSync(p);
    }
    let k = String(cfg.key).trim();
    if (!/-----BEGIN [A-Z ]*PRIVATE KEY-----/.test(k)) {
        throw new Error('That does not look like a private key (expected a -----BEGIN ... PRIVATE KEY----- block).');
    }
    return Buffer.from(k.endsWith('\n') ? k : k + '\n');
}

function openTunnel(cfg) {
    return new Promise((resolve) => {
        if (!SSH2) {
            resolve({ ok: false, error: 'The ssh2 package is not installed. Run: npm install ssh2' });
            return;
        }

        const opts = {
            host: cfg.host,
            port: cfg.port || 22,
            username: cfg.user,
            readyTimeout: 20000,
            keepaliveInterval: 15000,
            keepaliveCountMax: 3,
        };

        try {
            if (cfg.auth === 'key') {
                opts.privateKey = readKey(cfg);
                if (cfg.keyPass) opts.passphrase = cfg.keyPass;
            } else if (cfg.auth === 'password') {
                if (!cfg.password) throw new Error('SSH password is required.');
                opts.password = cfg.password;
                // Some servers only offer keyboard-interactive for passwords.
                opts.tryKeyboard = true;
            } else {
                if (!process.env.SSH_AUTH_SOCK) {
                    throw new Error('No SSH agent is available to this process (SSH_AUTH_SOCK is unset).');
                }
                opts.agent = process.env.SSH_AUTH_SOCK;
            }
        } catch (e) {
            resolve({ ok: false, error: explainSshError(e, cfg.auth) });
            return;
        }

        const client = new SSH2.Client();
        let settled = false;
        const done = (r) => { if (!settled) { settled = true; resolve(r); } };

        client.on('keyboard-interactive', (n, i, il, prompts, cb) => cb(prompts.map(() => cfg.password || '')));

        client.on('ready', () => {
            const server = net.createServer((sock) => {
                client.forwardOut('127.0.0.1', 0, cfg.targetHost || '127.0.0.1', cfg.targetPort, (err, stream) => {
                    if (err) { sock.destroy(); return; }
                    sock.pipe(stream).pipe(sock);
                    stream.on('error', () => sock.destroy());
                    sock.on('error', () => stream.destroy());
                });
            });
            server.on('error', (e) => {
                client.end();
                done({ ok: false, error: 'Could not bind the local tunnel port: ' + e.message });
            });
            server.listen(cfg.localPort || 0, '127.0.0.1', () => {
                done({ ok: true, port: server.address().port, client, server });
            });
        });

        client.on('error', (err) => done({ ok: false, error: explainSshError(err, cfg.auth) }));
        client.on('close', () => done({ ok: false, error: 'The SSH connection closed before the tunnel was ready.' }));

        try { client.connect(opts); }
        catch (e) { done({ ok: false, error: explainSshError(e, cfg.auth) }); }
    });
}

/** Reuse a healthy tunnel, otherwise build a fresh one (self-healing). */
async function ensureTunnel(cfg) {
    const fp = tunnelFingerprint(cfg);
    const existing = tunnels.get(fp);
    if (existing && existing.alive) {
        existing.seen = Date.now();
        return { ok: true, port: existing.port, reused: true };
    }
    if (existing) closeTunnelEntry(fp);

    const r = await openTunnel(cfg);
    if (!r.ok) return r;

    const entry = {
        port: r.port, client: r.client, server: r.server, alive: true,
        opened: Date.now(), seen: Date.now(),
        target: (cfg.targetHost || '') + ':' + (cfg.targetPort || ''),
    };
    // A dropped transport marks the tunnel dead; the next request rebuilds it.
    r.client.on('close', () => { entry.alive = false; });
    r.client.on('error', () => { entry.alive = false; });
    tunnels.set(fp, entry);
    return { ok: true, port: r.port, reused: false };
}

function closeTunnelEntry(fp) {
    const e = tunnels.get(fp);
    if (!e) return;
    try { e.server && e.server.close(); } catch (_) {}
    try { e.client && e.client.end(); } catch (_) {}
    tunnels.delete(fp);
}

function closeTunnel(cfg) { closeTunnelEntry(tunnelFingerprint(cfg)); }

function tunnelStatus(cfg) {
    const e = tunnels.get(tunnelFingerprint(cfg));
    if (!e) return { up: false };
    return { up: !!e.alive, port: e.port, target: e.target, uptime: Math.round((Date.now() - e.opened) / 1000) };
}

process.on('exit', () => { for (const fp of Array.from(tunnels.keys())) closeTunnelEntry(fp); });

// ─── Database Connection ──────────────────────────────────────────────────────
/**
 * Async mirror of the PHP DbConnection, carrying the same correctness fixes:
 * PostgreSQL switches database by reconnecting (it has no USE), schemas are
 * first-class, row identity prefers the real primary key, and large tables
 * report an estimated count rather than blocking on COUNT(*).
 */
class Db {
    constructor() {
        this.conn = null;
        this.type = '';
        this.creds = {};
        this.database = '';
        this.schema = '';
        this._version = null;
    }

    static EXACT_COUNT_LIMIT = 250000;

    async connect(type, host, user, pass, dbname = '', port = '', ssl = false) {
        this.type = type;
        this.creds = { type, host, user, pass, port, ssl };
        this.database = dbname;

        if (type !== 'sqlite' && typeof host === 'string' && host.includes(':')) {
            const [hh, pp] = host.split(':', 2);
            if (/^\d+$/.test(pp)) { host = hh; port = pp; }
        }

        try {
            if (type === 'mysql') {
                if (!mysql2) return 'The mysql2 package is not installed. Run: npm install mysql2';
                const opts = {
                    host, port: parseInt(port, 10) || 3306, user, password: pass,
                    database: dbname || undefined, charset: 'utf8mb4',
                    // Multi-statement stays off: the SQL console splits scripts
                    // itself so each statement gets its own timed result.
                    multipleStatements: false,
                    dateStrings: true, supportBigNumbers: true, bigNumberStrings: true,
                    connectTimeout: 15000,
                };
                if (ssl) opts.ssl = { rejectUnauthorized: false };
                this.conn = await mysql2.createConnection(opts);

            } else if (type === 'pgsql') {
                if (!pg) return 'The pg package is not installed. Run: npm install pg';
                const opts = {
                    host, port: parseInt(port, 10) || 5432, user, password: pass,
                    database: dbname || 'postgres', connectionTimeoutMillis: 15000,
                };
                if (ssl) opts.ssl = { rejectUnauthorized: false };
                this.conn = new pg.Client(opts);
                await this.conn.connect();
                this.database = dbname || 'postgres';
                this.schema = await this._currentSchema();

            } else if (type === 'sqlite') {
                if (!sqlite3) return 'The sqlite3 package is not installed. Run: npm install sqlite3';
                if (host !== ':memory:' && !fs.existsSync(host)) return 'SQLite database file not found: ' + host;
                await new Promise((res, rej) => {
                    this.conn = new sqlite3.Database(host, (e) => (e ? rej(e) : res()));
                });
                await this.run('PRAGMA foreign_keys = ON');
                this.database = 'main';

            } else {
                return 'Unsupported database type.';
            }
            return true;
        } catch (e) {
            return this._friendlyError(e);
        }
    }

    _friendlyError(e) {
        const msg = (e && e.message) ? e.message : String(e);
        const low = msg.toLowerCase();
        let hint = '';
        if (low.includes('econnrefused')) {
            hint = ' Nothing is listening on that host and port. If the database is only reachable from a bastion, use the SSH Tunnel tab.';
        } else if (low.includes('access denied') || low.includes('password authentication failed') || low.includes('auth')) {
            hint = ' The username or password was rejected by the database.';
        } else if (low.includes('unknown database') || low.includes('does not exist')) {
            hint = ' That database does not exist - leave the field blank to browse all databases.';
        } else if (low.includes('etimedout') || low.includes('timeout')) {
            hint = ' The host did not respond. Check firewall rules, or tunnel through SSH.';
        } else if (low.includes('enotfound') || low.includes('eai_again')) {
            hint = ' The hostname could not be resolved.';
        }
        return msg + hint;
    }

    async close() {
        if (!this.conn) return;
        try {
            if (this.type === 'mysql') await this.conn.end();
            else if (this.type === 'pgsql') await this.conn.end();
            else if (this.type === 'sqlite') await new Promise((r) => this.conn.close(() => r()));
        } catch (_) {}
        this.conn = null;
    }

    getType() { return this.type; }
    getDatabase() { return this.database; }
    getSchema() { return this.schema; }

    async serverVersion() {
        if (this._version !== null) return this._version;
        try {
            if (this.type === 'mysql') this._version = String((await this.all('SELECT VERSION() v'))[0].v);
            else if (this.type === 'pgsql') this._version = String((await this.all('SHOW server_version'))[0].server_version);
            else this._version = String((await this.all('SELECT sqlite_version() v'))[0].v);
        } catch (_) { this._version = ''; }
        return this._version;
    }

    quoteId(name) {
        name = String(name);
        return this.type === 'mysql'
            ? '`' + name.split('`').join('``') + '`'
            : '"' + name.split('"').join('""') + '"';
    }

    qualify(table, schema) {
        const q = this.quoteId(table);
        if (this.type === 'pgsql') {
            const s = schema !== undefined ? schema : this.schema;
            if (s) return this.quoteId(s) + '.' + q;
        }
        return q;
    }

    /** Convert '?' placeholders to $n for pg; other drivers take '?' natively. */
    _bind(sql) {
        if (this.type !== 'pgsql') return sql;
        let i = 0;
        return sql.replace(/\?/g, () => '$' + (++i));
    }

    async run(sql, params = []) {
        if (!this.conn) throw new Error('Not connected');
        if (this.type === 'mysql') {
            const [rows, fields] = await this.conn.query(sql, params);
            return { rows: Array.isArray(rows) ? rows : [], fields: fields || [],
                     affected: (rows && rows.affectedRows) || 0, isSelect: Array.isArray(rows) };
        }
        if (this.type === 'pgsql') {
            const r = await this.conn.query(this._bind(sql), params);
            const isSelect = Array.isArray(r.rows) && (r.command === 'SELECT' || r.command === 'SHOW' || r.fields.length > 0);
            return { rows: r.rows || [], fields: r.fields || [], affected: r.rowCount || 0, isSelect };
        }
        // sqlite3
        const isSelect = /^\s*(\(*\s*SELECT|WITH|PRAGMA|EXPLAIN|VALUES)\b/i.test(sql);
        if (isSelect) {
            const rows = await new Promise((res, rej) =>
                this.conn.all(sql, params, (e, r) => (e ? rej(e) : res(r || []))));
            return { rows, fields: rows[0] ? Object.keys(rows[0]).map((n) => ({ name: n })) : [], affected: 0, isSelect: true };
        }
        const info = await new Promise((res, rej) =>
            this.conn.run(sql, params, function (e) { e ? rej(e) : res({ changes: this.changes, lastID: this.lastID }); }));
        return { rows: [], fields: [], affected: info.changes, isSelect: false };
    }

    async all(sql, params = []) { return (await this.run(sql, params)).rows; }
    async one(sql, params = []) { return (await this.all(sql, params))[0] || null; }
    async scalar(sql, params = []) {
        const r = await this.one(sql, params);
        return r ? Object.values(r)[0] : null;
    }

    // ── Database / schema selection ──
    async selectDatabase(name) {
        name = String(name || '');
        if (!name || name === this.database) return true;
        if (this.type === 'sqlite') return true;

        if (this.type === 'mysql') {
            try {
                await this.run('USE ' + this.quoteId(name));
                this.database = name;
                return true;
            } catch (e) { return this._friendlyError(e); }
        }

        // PostgreSQL binds a connection to one database - reconnect to switch.
        const c = this.creds;
        const fresh = new Db();
        const res = await fresh.connect(c.type, c.host, c.user, c.pass, name, c.port, c.ssl);
        if (res !== true) return res;
        try { await this.conn.end(); } catch (_) {}
        this.conn = fresh.conn;
        this.database = name;
        this.schema = fresh.schema;
        this._version = null;
        return true;
    }

    async selectSchema(schema) {
        if (!schema) return true;
        if (this.type === 'pgsql') {
            try {
                await this.run('SELECT set_config(?, ?, false)', ['search_path', schema + ', pg_catalog']);
                this.schema = schema;
                return true;
            } catch (e) { return this._friendlyError(e); }
        }
        return this.selectDatabase(schema);
    }

    async _currentSchema() {
        if (this.type !== 'pgsql') return '';
        try { return (await this.scalar('SELECT current_schema()')) || 'public'; }
        catch (_) { return 'public'; }
    }

    async getSchemas() {
        if (this.type !== 'pgsql') return [];
        try {
            const rows = await this.all(
                `SELECT nspname FROM pg_namespace
                  WHERE nspname NOT LIKE 'pg\\_%' AND nspname <> 'information_schema'
                  ORDER BY (nspname <> 'public'), nspname`);
            return rows.map((r) => r.nspname);
        } catch (_) { return []; }
    }

    async findSchemaForTable(table) {
        if (this.type !== 'pgsql' || !table) return null;
        try {
            const rows = await this.all(
                `SELECT n.nspname FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
                  WHERE c.relname = ? AND c.relkind IN ('r','p','v','m')
                    AND n.nspname NOT LIKE 'pg\\_%' AND n.nspname <> 'information_schema'
                  ORDER BY (n.nspname <> current_schema()), (n.nspname <> 'public'), n.nspname LIMIT 1`, [table]);
            return rows.length ? rows[0].nspname : null;
        } catch (_) { return null; }
    }

    // ── Introspection ──
    async getDatabasesWithStats() {
        const list = {};
        if (this.type === 'mysql') {
            try {
                const rows = await this.all(
                    `SELECT table_schema AS db_name, COUNT(table_name) AS table_count,
                            COALESCE(SUM(data_length + index_length), 0) AS total_size,
                            COALESCE(SUM(data_length), 0) AS data_size,
                            COALESCE(SUM(index_length), 0) AS index_size
                       FROM information_schema.TABLES GROUP BY table_schema ORDER BY table_schema`);
                for (const r of rows) {
                    list[r.db_name] = {
                        name: r.db_name, tables: Number(r.table_count), size: Number(r.total_size),
                        data_size: Number(r.data_size), index_size: Number(r.index_size), exact: true,
                    };
                }
            } catch (_) {}
            if (!Object.keys(list).length) {
                try {
                    for (const r of await this.all('SHOW DATABASES')) {
                        const n = Object.values(r)[0];
                        list[n] = { name: n, tables: null, size: 0, data_size: 0, index_size: 0, exact: false };
                    }
                } catch (_) {}
            }

        } else if (this.type === 'pgsql') {
            try {
                const rows = await this.all(
                    `SELECT d.datname AS db_name, pg_database_size(d.datname) AS total_size,
                            pg_catalog.has_database_privilege(d.datname, 'CONNECT') AS can_connect
                       FROM pg_database d WHERE d.datistemplate = false ORDER BY d.datname`);
                for (const r of rows) {
                    list[r.db_name] = {
                        name: r.db_name,
                        tables: r.db_name === this.database ? await this.countTables() : null,
                        size: Number(r.total_size), data_size: null, index_size: null,
                        exact: true, lazy_count: !!r.can_connect,
                    };
                }
            } catch (_) {}

        } else if (this.type === 'sqlite') {
            let sz = 0;
            try { sz = fs.statSync(this.creds.host).size; } catch (_) {}
            list.main = { name: 'main', tables: await this.countTables(), size: sz, data_size: sz, index_size: 0, exact: true };
        }
        return list;
    }

    async countTables() {
        try {
            if (this.type === 'pgsql') {
                return Number(await this.scalar(
                    `SELECT count(*) FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
                      WHERE c.relkind IN ('r','p','v','m')
                        AND n.nspname NOT LIKE 'pg\\_%' AND n.nspname <> 'information_schema'`));
            }
            return (await this.getTables()).length;
        } catch (_) { return null; }
    }

    async getTables(database, schema) {
        if (database) {
            const r = await this.selectDatabase(database);
            if (r !== true) return [];
        }
        if (schema) await this.selectSchema(schema);
        try {
            if (this.type === 'mysql') {
                return (await this.all('SHOW TABLES')).map((r) => Object.values(r)[0]);
            }
            if (this.type === 'pgsql') {
                return (await this.all(
                    `SELECT c.relname FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
                      WHERE n.nspname = ? AND c.relkind IN ('r','p','v','m') ORDER BY c.relname`,
                    [this.schema || 'public'])).map((r) => r.relname);
            }
            return (await this.all(
                `SELECT name FROM sqlite_master WHERE type IN ('table','view')
                  AND name NOT LIKE 'sqlite_%' ORDER BY name`)).map((r) => r.name);
        } catch (_) { return []; }
    }

    async getTablesWithStats(database, schema) {
        if (database) {
            const r = await this.selectDatabase(database);
            if (r !== true) return [];
        }
        if (schema) await this.selectSchema(schema);

        if (this.type === 'mysql') {
            try {
                const rows = await this.all('SHOW TABLE STATUS');
                if (rows.length) {
                    return rows.map((r) => {
                        const m = {};
                        for (const k of Object.keys(r)) m[k.toLowerCase()] = r[k];
                        const d = Number(m.data_length || 0), i = Number(m.index_length || 0);
                        return {
                            Name: m.name || '', Engine: m.engine || '',
                            Rows: m.rows === null || m.rows === undefined ? null : Number(m.rows),
                            Data_length: d, Index_length: i, Total_length: d + i,
                            Data_free: Number(m.data_free || 0), Auto_increment: m.auto_increment || null,
                            Collation: m.collation || '', Comment: m.comment || '',
                            Rows_exact: !String(m.engine || '').toLowerCase().includes('innodb'),
                            Is_view: !m.engine && m.comment === 'VIEW',
                        };
                    });
                }
            } catch (_) {}

        } else if (this.type === 'pgsql') {
            try {
                const rows = await this.all(
                    `SELECT c.relname AS "Name",
                            CASE c.relkind WHEN 'v' THEN 'view' WHEN 'm' THEN 'matview'
                                           WHEN 'p' THEN 'partitioned' ELSE 'table' END AS "Engine",
                            c.relkind AS "Relkind",
                            CASE WHEN c.relkind = 'v' THEN NULL
                                 ELSE GREATEST(c.reltuples, 0)::bigint END AS "Rows",
                            pg_table_size(c.oid) AS "Data_length",
                            pg_indexes_size(c.oid) AS "Index_length",
                            pg_total_relation_size(c.oid) AS "Total_length",
                            0 AS "Data_free", NULL AS "Auto_increment", '' AS "Collation",
                            COALESCE(obj_description(c.oid, 'pg_class'), '') AS "Comment"
                       FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
                      WHERE n.nspname = ? AND c.relkind IN ('r','p','v','m') ORDER BY c.relname`,
                    [this.schema || 'public']);
                return rows.map((r) => Object.assign({}, r, {
                    Rows: r.Rows === null ? null : Number(r.Rows),
                    Data_length: Number(r.Data_length), Index_length: Number(r.Index_length),
                    Total_length: Number(r.Total_length),
                    Rows_exact: false,
                    Is_view: ['v', 'm'].includes(r.Relkind),
                }));
            } catch (_) {}
        }

        const out = [];
        for (const t of await this.getTables()) {
            out.push({
                Name: t, Engine: 'SQLite', Rows: (await this.getRowCountInfo(t)).n,
                Data_length: 0, Index_length: 0, Total_length: 0, Data_free: 0,
                Auto_increment: null, Collation: '', Comment: '', Rows_exact: true, Is_view: false,
            });
        }
        return out;
    }

    async getColumns(table) {
        if (!table) return [];
        try {
            if (this.type === 'mysql') {
                return await this.all('SHOW FULL COLUMNS FROM ' + this.quoteId(table));
            }
            if (this.type === 'pgsql') {
                return await this.all(
                    `SELECT a.attname AS "Field",
                            format_type(a.atttypid, a.atttypmod) AS "Type",
                            CASE WHEN a.attnotnull THEN 'NO' ELSE 'YES' END AS "Null",
                            pg_get_expr(ad.adbin, ad.adrelid) AS "Default",
                            CASE WHEN pk.attname IS NOT NULL THEN 'PRI' ELSE '' END AS "Key",
                            CASE WHEN pg_get_expr(ad.adbin, ad.adrelid) LIKE 'nextval%'
                                 THEN 'auto_increment' ELSE '' END AS "Extra",
                            COALESCE(col_description(a.attrelid, a.attnum), '') AS "Comment"
                       FROM pg_attribute a
                       JOIN pg_class c ON c.oid = a.attrelid
                       JOIN pg_namespace n ON n.oid = c.relnamespace
                       LEFT JOIN pg_attrdef ad ON ad.adrelid = c.oid AND ad.adnum = a.attnum
                       LEFT JOIN (SELECT a2.attname FROM pg_index i
                                    JOIN pg_attribute a2 ON a2.attrelid = i.indrelid AND a2.attnum = ANY(i.indkey)
                                   WHERE i.indrelid = (? || '.' || ?)::regclass AND i.indisprimary) pk
                              ON pk.attname = a.attname
                      WHERE n.nspname = ? AND c.relname = ? AND a.attnum > 0 AND NOT a.attisdropped
                      ORDER BY a.attnum`,
                    [this.quoteId(this.schema || 'public'), this.quoteId(table), this.schema || 'public', table]);
            }
            const rows = await this.all('PRAGMA table_info(' + this.quoteId(table) + ')');
            return rows.map((r) => ({
                Field: r.name, Type: r.type, Null: r.notnull ? 'NO' : 'YES',
                Default: r.dflt_value, Key: r.pk ? 'PRI' : '', Extra: '', Comment: '',
            }));
        } catch (_) { return []; }
    }

    async getPrimaryKey(table) {
        if (!table) return [];
        try {
            if (this.type === 'mysql') {
                const rows = await this.all('SHOW KEYS FROM ' + this.quoteId(table) + " WHERE Key_name = 'PRIMARY'");
                return rows.map((r) => r.Column_name);
            }
            if (this.type === 'pgsql') {
                const rows = await this.all(
                    `SELECT a.attname FROM pg_index i
                       JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
                      WHERE i.indrelid = (? || '.' || ?)::regclass AND i.indisprimary
                      ORDER BY array_position(i.indkey, a.attnum)`,
                    [this.quoteId(this.schema || 'public'), this.quoteId(table)]);
                return rows.map((r) => r.attname);
            }
            const rows = await this.all('PRAGMA table_info(' + this.quoteId(table) + ')');
            return rows.filter((r) => r.pk).sort((a, b) => a.pk - b.pk).map((r) => r.name);
        } catch (_) { return []; }
    }

    async getIndexes(table) {
        if (!table) return [];
        const out = {};
        try {
            if (this.type === 'mysql') {
                for (const r of await this.all('SHOW INDEX FROM ' + this.quoteId(table))) {
                    if (!out[r.Key_name]) {
                        out[r.Key_name] = { name: r.Key_name, columns: [], unique: !r.Non_unique,
                                            primary: r.Key_name === 'PRIMARY', type: r.Index_type || '' };
                    }
                    out[r.Key_name].columns.push(r.Column_name);
                }
            } else if (this.type === 'pgsql') {
                const rows = await this.all(
                    `SELECT i.relname AS name, ix.indisunique AS is_unique, ix.indisprimary AS is_primary,
                            am.amname AS type,
                            ARRAY(SELECT pg_get_indexdef(ix.indexrelid, k + 1, true)
                                    FROM generate_subscripts(ix.indkey, 1) AS k ORDER BY k) AS cols
                       FROM pg_index ix
                       JOIN pg_class i ON i.oid = ix.indexrelid
                       JOIN pg_class t ON t.oid = ix.indrelid
                       JOIN pg_namespace n ON n.oid = t.relnamespace
                       JOIN pg_am am ON am.oid = i.relam
                      WHERE n.nspname = ? AND t.relname = ? ORDER BY i.relname`,
                    [this.schema || 'public', table]);
                for (const r of rows) {
                    out[r.name] = { name: r.name, columns: (r.cols || []).map((c) => String(c).replace(/^"|"$/g, '')),
                                    unique: !!r.is_unique, primary: !!r.is_primary, type: r.type || '' };
                }
            } else {
                for (const r of await this.all('PRAGMA index_list(' + this.quoteId(table) + ')')) {
                    const cols = await this.all('PRAGMA index_info(' + this.quoteId(r.name) + ')');
                    out[r.name] = { name: r.name, columns: cols.map((c) => c.name),
                                    unique: !!r.unique, primary: r.origin === 'pk', type: '' };
                }
            }
        } catch (_) {}
        return Object.values(out);
    }

    async getForeignKeys(table, database) {
        if (!table) return [];
        try {
            if (this.type === 'mysql') {
                return await this.all(
                    `SELECT CONSTRAINT_NAME AS name, COLUMN_NAME AS col,
                            REFERENCED_TABLE_NAME AS ref_table, REFERENCED_COLUMN_NAME AS ref_col
                       FROM information_schema.KEY_COLUMN_USAGE
                      WHERE TABLE_SCHEMA = COALESCE(?, DATABASE()) AND TABLE_NAME = ?
                        AND REFERENCED_TABLE_NAME IS NOT NULL
                      ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION`, [database || null, table]);
            }
            if (this.type === 'pgsql') {
                return await this.all(
                    `SELECT con.conname AS name, att.attname AS col,
                            cl.relname AS ref_table, att2.attname AS ref_col
                       FROM pg_constraint con
                       JOIN pg_class c ON c.oid = con.conrelid
                       JOIN pg_namespace n ON n.oid = c.relnamespace
                       JOIN pg_class cl ON cl.oid = con.confrelid
                       JOIN LATERAL unnest(con.conkey, con.confkey) AS u(k, fk) ON true
                       JOIN pg_attribute att ON att.attrelid = con.conrelid AND att.attnum = u.k
                       JOIN pg_attribute att2 ON att2.attrelid = con.confrelid AND att2.attnum = u.fk
                      WHERE con.contype = 'f' AND n.nspname = ? AND c.relname = ?
                      ORDER BY con.conname`, [this.schema || 'public', table]);
            }
            const rows = await this.all('PRAGMA foreign_key_list(' + this.quoteId(table) + ')');
            return rows.map((r) => ({ name: 'fk_' + r.id, col: r.from, ref_table: r.table, ref_col: r.to }));
        } catch (_) { return []; }
    }

    async getRowCountInfo(table, whereSql = '', params = []) {
        if (!table) return { n: 0, exact: true };
        if (!whereSql) {
            try {
                if (this.type === 'pgsql') {
                    const est = Number(await this.scalar(
                        `SELECT GREATEST(c.reltuples, 0)::bigint FROM pg_class c
                           JOIN pg_namespace n ON n.oid = c.relnamespace
                          WHERE n.nspname = ? AND c.relname = ?`, [this.schema || 'public', table]));
                    if (est > Db.EXACT_COUNT_LIMIT) return { n: est, exact: false };
                } else if (this.type === 'mysql') {
                    const est = Number(await this.scalar(
                        `SELECT table_rows FROM information_schema.TABLES
                          WHERE table_schema = DATABASE() AND table_name = ?`, [table]));
                    if (est > Db.EXACT_COUNT_LIMIT) return { n: est, exact: false };
                }
            } catch (_) {}
        }
        try {
            const n = Number(await this.scalar('SELECT COUNT(*) FROM ' + this.qualify(table) + ' ' + whereSql, params));
            return { n, exact: true };
        } catch (_) { return { n: 0, exact: true }; }
    }

    async getCreateTable(table) {
        try {
            if (this.type === 'mysql') {
                const r = await this.one('SHOW CREATE TABLE ' + this.quoteId(table));
                if (r) for (const k of Object.keys(r)) if (/^create/i.test(k)) return r[k];
            } else if (this.type === 'sqlite') {
                return await this.scalar('SELECT sql FROM sqlite_master WHERE name = ?', [table]);
            }
        } catch (_) {}
        return null;
    }
}

// ─── View helpers ─────────────────────────────────────────────────────────────
const SPRITE = "<svg xmlns=\"http://www.w3.org/2000/svg\" style=\"display:none\" aria-hidden=\"true\"><symbol id=\"i-arrow-left\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"m12 19-7-7 7-7\" /><path class=\"p1\" d=\"M19 12H5\" /></symbol><symbol id=\"i-arrow-right\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M5 12h14\" /><path class=\"p1\" d=\"m12 5 7 7-7 7\" /></symbol><symbol id=\"i-bookmark\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M17 3a2 2 0 0 1 2 2v15a1 1 0 0 1-1.496.868l-4.512-2.578a2 2 0 0 0-1.984 0l-4.512 2.578A1 1 0 0 1 5 20V5a2 2 0 0 1 2-2z\" /></symbol><symbol id=\"i-braces\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M8 3H7a2 2 0 0 0-2 2v5a2 2 0 0 1-2 2 2 2 0 0 1 2 2v5c0 1.1.9 2 2 2h1\" /><path class=\"p1\" d=\"M16 21h1a2 2 0 0 0 2-2v-5c0-1.1.9-2 2-2a2 2 0 0 1-2-2V5a2 2 0 0 0-2-2h-1\" /></symbol><symbol id=\"i-cable\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M17 19a1 1 0 0 1-1-1v-2a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2a1 1 0 0 1-1 1z\" /><path class=\"p1\" d=\"M17 21v-2\" /><path class=\"p2\" d=\"M19 14V6.5a1 1 0 0 0-7 0v11a1 1 0 0 1-7 0V10\" /><path class=\"p3\" d=\"M21 21v-2\" /><path class=\"p4\" d=\"M3 5V3\" /><path class=\"p5\" d=\"M4 10a2 2 0 0 1-2-2V6a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2a2 2 0 0 1-2 2z\" /><path class=\"p6\" d=\"M7 5V3\" /></symbol><symbol id=\"i-check\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M20 6 9 17l-5-5\" /></symbol><symbol id=\"i-chevron-left\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"m15 18-6-6 6-6\" /></symbol><symbol id=\"i-chevron-right\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"m9 18 6-6-6-6\" /></symbol><symbol id=\"i-chevrons-left\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"m11 17-5-5 5-5\" /><path class=\"p1\" d=\"m18 17-5-5 5-5\" /></symbol><symbol id=\"i-chevrons-right\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"m6 17 5-5-5-5\" /><path class=\"p1\" d=\"m13 17 5-5-5-5\" /></symbol><symbol id=\"i-circle-alert\" viewBox=\"0 0 24 24\"><circle class=\"p0\" cx=\"12\" cy=\"12\" r=\"10\" /><line class=\"p1\" x1=\"12\" x2=\"12\" y1=\"8\" y2=\"12\" /><line class=\"p2\" x1=\"12\" x2=\"12.01\" y1=\"16\" y2=\"16\" /></symbol><symbol id=\"i-circle-check\" viewBox=\"0 0 24 24\"><circle class=\"p0\" cx=\"12\" cy=\"12\" r=\"10\" /><path class=\"p1\" d=\"m9 12 2 2 4-4\" /></symbol><symbol id=\"i-circle-help\" viewBox=\"0 0 24 24\"><circle class=\"p0\" cx=\"12\" cy=\"12\" r=\"10\" /><path class=\"p1\" d=\"M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3\" /><path class=\"p2\" d=\"M12 17h.01\" /></symbol><symbol id=\"i-circle-x\" viewBox=\"0 0 24 24\"><circle class=\"p0\" cx=\"12\" cy=\"12\" r=\"10\" /><path class=\"p1\" d=\"m15 9-6 6\" /><path class=\"p2\" d=\"m9 9 6 6\" /></symbol><symbol id=\"i-columns-3\" viewBox=\"0 0 24 24\"><rect class=\"p0\" width=\"18\" height=\"18\" x=\"3\" y=\"3\" rx=\"2\" /><path class=\"p1\" d=\"M9 3v18\" /><path class=\"p2\" d=\"M15 3v18\" /></symbol><symbol id=\"i-command\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M15 6v12a3 3 0 1 0 3-3H6a3 3 0 1 0 3 3V6a3 3 0 1 0-3 3h12a3 3 0 1 0-3-3\" /></symbol><symbol id=\"i-copy\" viewBox=\"0 0 24 24\"><rect class=\"p0\" width=\"14\" height=\"14\" x=\"8\" y=\"8\" rx=\"2\" ry=\"2\" /><path class=\"p1\" d=\"M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2\" /></symbol><symbol id=\"i-database\" viewBox=\"0 0 24 24\"><ellipse class=\"p0\" cx=\"12\" cy=\"5\" rx=\"9\" ry=\"3\" /><path class=\"p1\" d=\"M3 5V19A9 3 0 0 0 21 19V5\" /><path class=\"p2\" d=\"M3 12A9 3 0 0 0 21 12\" /></symbol><symbol id=\"i-download\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M12 15V3\" /><path class=\"p1\" d=\"M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4\" /><path class=\"p2\" d=\"m7 10 5 5 5-5\" /></symbol><symbol id=\"i-eye\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0\" /><circle class=\"p1\" cx=\"12\" cy=\"12\" r=\"3\" /></symbol><symbol id=\"i-file-code-2\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M4 12.15V4a2 2 0 0 1 2-2h8a2.4 2.4 0 0 1 1.706.706l3.588 3.588A2.4 2.4 0 0 1 20 8v12a2 2 0 0 1-2 2h-3.35\" /><path class=\"p1\" d=\"M14 2v5a1 1 0 0 0 1 1h5\" /><path class=\"p2\" d=\"m5 16-3 3 3 3\" /><path class=\"p3\" d=\"m9 22 3-3-3-3\" /></symbol><symbol id=\"i-filter\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z\" /></symbol><symbol id=\"i-funnel\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z\" /></symbol><symbol id=\"i-git-branch\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M15 6a9 9 0 0 0-9 9V3\" /><circle class=\"p1\" cx=\"18\" cy=\"6\" r=\"3\" /><circle class=\"p2\" cx=\"6\" cy=\"18\" r=\"3\" /></symbol><symbol id=\"i-grid-2x2\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M12 3v18\" /><path class=\"p1\" d=\"M3 12h18\" /><rect class=\"p2\" x=\"3\" y=\"3\" width=\"18\" height=\"18\" rx=\"2\" /></symbol><symbol id=\"i-history\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8\" /><path class=\"p1\" d=\"M3 3v5h5\" /><path class=\"p2\" d=\"M12 7v5l4 2\" /></symbol><symbol id=\"i-import\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M12 3v12\" /><path class=\"p1\" d=\"m8 11 4 4 4-4\" /><path class=\"p2\" d=\"M8 5H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-4\" /></symbol><symbol id=\"i-info\" viewBox=\"0 0 24 24\"><circle class=\"p0\" cx=\"12\" cy=\"12\" r=\"10\" /><path class=\"p1\" d=\"M12 16v-4\" /><path class=\"p2\" d=\"M12 8h.01\" /></symbol><symbol id=\"i-key-round\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M2.586 17.414A2 2 0 0 0 2 18.828V21a1 1 0 0 0 1 1h3a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h1a1 1 0 0 0 1-1v-1a1 1 0 0 1 1-1h.172a2 2 0 0 0 1.414-.586l.814-.814a6.5 6.5 0 1 0-4-4z\" /><circle class=\"p1\" cx=\"16.5\" cy=\"7.5\" r=\".5\" fill=\"currentColor\" /></symbol><symbol id=\"i-layers\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83z\" /><path class=\"p1\" d=\"M2 12a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 12\" /><path class=\"p2\" d=\"M2 17a1 1 0 0 0 .58.91l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9A1 1 0 0 0 22 17\" /></symbol><symbol id=\"i-link\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71\" /><path class=\"p1\" d=\"M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71\" /></symbol><symbol id=\"i-loader-circle\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M21 12a9 9 0 1 1-6.219-8.56\" /></symbol><symbol id=\"i-lock\" viewBox=\"0 0 24 24\"><rect class=\"p0\" width=\"18\" height=\"11\" x=\"3\" y=\"11\" rx=\"2\" ry=\"2\" /><path class=\"p1\" d=\"M7 11V7a5 5 0 0 1 10 0v4\" /></symbol><symbol id=\"i-log-out\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"m16 17 5-5-5-5\" /><path class=\"p1\" d=\"M21 12H9\" /><path class=\"p2\" d=\"M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4\" /></symbol><symbol id=\"i-panel-left\" viewBox=\"0 0 24 24\"><rect class=\"p0\" width=\"18\" height=\"18\" x=\"3\" y=\"3\" rx=\"2\" /><path class=\"p1\" d=\"M9 3v18\" /></symbol><symbol id=\"i-pencil\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z\" /><path class=\"p1\" d=\"m15 5 4 4\" /></symbol><symbol id=\"i-play\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M5 5a2 2 0 0 1 3.008-1.728l11.997 6.998a2 2 0 0 1 .003 3.458l-12 7A2 2 0 0 1 5 19z\" /></symbol><symbol id=\"i-plug-zap\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M6.3 20.3a2.4 2.4 0 0 0 3.4 0L12 18l-6-6-2.3 2.3a2.4 2.4 0 0 0 0 3.4Z\" /><path class=\"p1\" d=\"m2 22 3-3\" /><path class=\"p2\" d=\"M7.5 13.5 10 11\" /><path class=\"p3\" d=\"M10.5 16.5 13 14\" /><path class=\"p4\" d=\"m18 3-4 4h6l-4 4\" /></symbol><symbol id=\"i-plus\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M5 12h14\" /><path class=\"p1\" d=\"M12 5v14\" /></symbol><symbol id=\"i-refresh-cw\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8\" /><path class=\"p1\" d=\"M21 3v5h-5\" /><path class=\"p2\" d=\"M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16\" /><path class=\"p3\" d=\"M8 16H3v5\" /></symbol><symbol id=\"i-rotate-cw\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8\" /><path class=\"p1\" d=\"M21 3v5h-5\" /></symbol><symbol id=\"i-save\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z\" /><path class=\"p1\" d=\"M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7\" /><path class=\"p2\" d=\"M7 3v4a1 1 0 0 0 1 1h7\" /></symbol><symbol id=\"i-scan-search\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M3 7V5a2 2 0 0 1 2-2h2\" /><path class=\"p1\" d=\"M17 3h2a2 2 0 0 1 2 2v2\" /><path class=\"p2\" d=\"M21 17v2a2 2 0 0 1-2 2h-2\" /><path class=\"p3\" d=\"M7 21H5a2 2 0 0 1-2-2v-2\" /><circle class=\"p4\" cx=\"12\" cy=\"12\" r=\"3\" /><path class=\"p5\" d=\"m16 16-1.9-1.9\" /></symbol><symbol id=\"i-search\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"m21 21-4.34-4.34\" /><circle class=\"p1\" cx=\"11\" cy=\"11\" r=\"8\" /></symbol><symbol id=\"i-server\" viewBox=\"0 0 24 24\"><rect class=\"p0\" width=\"20\" height=\"8\" x=\"2\" y=\"2\" rx=\"2\" ry=\"2\" /><rect class=\"p1\" width=\"20\" height=\"8\" x=\"2\" y=\"14\" rx=\"2\" ry=\"2\" /><line class=\"p2\" x1=\"6\" x2=\"6.01\" y1=\"6\" y2=\"6\" /><line class=\"p3\" x1=\"6\" x2=\"6.01\" y1=\"18\" y2=\"18\" /></symbol><symbol id=\"i-settings\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M9.671 4.136a2.34 2.34 0 0 1 4.659 0 2.34 2.34 0 0 0 3.319 1.915 2.34 2.34 0 0 1 2.33 4.033 2.34 2.34 0 0 0 0 3.831 2.34 2.34 0 0 1-2.33 4.033 2.34 2.34 0 0 0-3.319 1.915 2.34 2.34 0 0 1-4.659 0 2.34 2.34 0 0 0-3.32-1.915 2.34 2.34 0 0 1-2.33-4.033 2.34 2.34 0 0 0 0-3.831A2.34 2.34 0 0 1 6.35 6.051a2.34 2.34 0 0 0 3.319-1.915\" /><circle class=\"p1\" cx=\"12\" cy=\"12\" r=\"3\" /></symbol><symbol id=\"i-shield-alert\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z\" /><path class=\"p1\" d=\"M12 8v4\" /><path class=\"p2\" d=\"M12 16h.01\" /></symbol><symbol id=\"i-shield-check\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z\" /><path class=\"p1\" d=\"m9 12 2 2 4-4\" /></symbol><symbol id=\"i-sparkles\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z\" /><path class=\"p1\" d=\"M20 2v4\" /><path class=\"p2\" d=\"M22 4h-4\" /><circle class=\"p3\" cx=\"4\" cy=\"20\" r=\"2\" /></symbol><symbol id=\"i-square-pen\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M12 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7\" /><path class=\"p1\" d=\"M18.375 2.625a1 1 0 0 1 3 3l-9.013 9.014a2 2 0 0 1-.853.505l-2.873.84a.5.5 0 0 1-.62-.62l.84-2.873a2 2 0 0 1 .506-.852z\" /></symbol><symbol id=\"i-star\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z\" /></symbol><symbol id=\"i-table\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M12 3v18\" /><rect class=\"p1\" width=\"18\" height=\"18\" x=\"3\" y=\"3\" rx=\"2\" /><path class=\"p2\" d=\"M3 9h18\" /><path class=\"p3\" d=\"M3 15h18\" /></symbol><symbol id=\"i-table-2\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18\" /></symbol><symbol id=\"i-terminal\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M12 19h8\" /><path class=\"p1\" d=\"m4 17 6-6-6-6\" /></symbol><symbol id=\"i-text-cursor-input\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M12 20h-1a2 2 0 0 1-2-2 2 2 0 0 1-2 2H6\" /><path class=\"p1\" d=\"M13 8h7a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-7\" /><path class=\"p2\" d=\"M5 16H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h1\" /><path class=\"p3\" d=\"M6 4h1a2 2 0 0 1 2 2 2 2 0 0 1 2-2h1\" /><path class=\"p4\" d=\"M9 6v12\" /></symbol><symbol id=\"i-trash-2\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M10 11v6\" /><path class=\"p1\" d=\"M14 11v6\" /><path class=\"p2\" d=\"M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6\" /><path class=\"p3\" d=\"M3 6h18\" /><path class=\"p4\" d=\"M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2\" /></symbol><symbol id=\"i-triangle-alert\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3\" /><path class=\"p1\" d=\"M12 9v4\" /><path class=\"p2\" d=\"M12 17h.01\" /></symbol><symbol id=\"i-unplug\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"m19 5 3-3\" /><path class=\"p1\" d=\"m2 22 3-3\" /><path class=\"p2\" d=\"M6.3 20.3a2.4 2.4 0 0 0 3.4 0L12 18l-6-6-2.3 2.3a2.4 2.4 0 0 0 0 3.4Z\" /><path class=\"p3\" d=\"M7.5 13.5 10 11\" /><path class=\"p4\" d=\"M10.5 16.5 13 14\" /><path class=\"p5\" d=\"m12 6 6 6 2.3-2.3a2.4 2.4 0 0 0 0-3.4l-2.6-2.6a2.4 2.4 0 0 0-3.4 0Z\" /></symbol><symbol id=\"i-upload\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M12 3v12\" /><path class=\"p1\" d=\"m17 8-5-5-5 5\" /><path class=\"p2\" d=\"M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4\" /></symbol><symbol id=\"i-waypoints\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"m10.586 5.414-5.172 5.172\" /><path class=\"p1\" d=\"m18.586 13.414-5.172 5.172\" /><path class=\"p2\" d=\"M6 12h12\" /><circle class=\"p3\" cx=\"12\" cy=\"20\" r=\"2\" /><circle class=\"p4\" cx=\"12\" cy=\"4\" r=\"2\" /><circle class=\"p5\" cx=\"20\" cy=\"12\" r=\"2\" /><circle class=\"p6\" cx=\"4\" cy=\"12\" r=\"2\" /></symbol><symbol id=\"i-wrench\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.106-3.105c.32-.322.863-.22.983.218a6 6 0 0 1-8.259 7.057l-7.91 7.91a1 1 0 0 1-2.999-3l7.91-7.91a6 6 0 0 1 7.057-8.259c.438.12.54.662.219.984z\" /></symbol><symbol id=\"i-x\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M18 6 6 18\" /><path class=\"p1\" d=\"m6 6 12 12\" /></symbol><symbol id=\"i-zap\" viewBox=\"0 0 24 24\"><path class=\"p0\" d=\"M15.914 4a1.5 1.5 0 00-2.474-1.561l-9 9A1.5 1.5 0 005.5 14h4.002a.5.5 0 01.471.666L8.086 20a1.5 1.5 0 002.475 1.56l9-9A1.5 1.5 0 0018.5 10h-3.997a.5.5 0 01-.472-.667z\" /></symbol></svg>";
const STYLES = "/* \u2500\u2500\u2500 Design tokens \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\n   Every theme redefines the same variable contract, so components never need\n   to know which theme is active. */\n:root, [data-theme=\"light\"] {\n    --accent: #2f6fed; --accent-hover: #1d56cc; --accent-soft: #eaf1fe; --accent-border: #c3d8fb;\n    --accent-contrast: #ffffff;\n    --ok: #0f9d58; --ok-soft: #e4f5ec; --ok-border: #b7e3ca;\n    --warn: #b7791f; --warn-soft: #fdf5e4; --warn-border: #f0dfae;\n    --danger: #d93a3a; --danger-hover: #b62d2d; --danger-soft: #fdecec; --danger-border: #f6c7c7;\n    --bg: #f6f7f9; --surface: #ffffff; --surface-2: #f2f4f7; --surface-3: #e9edf2;\n    --sidebar: #ffffff; --header: #ffffff;\n    --text: #10151c; --text-dim: #5b6675; --text-faint: #8b95a3;\n    --border: #e3e7ed; --border-strong: #cfd6df;\n    --row-hover: #f5f8fd; --row-active: #eaf1fe;\n    --shadow-1: 0 1px 2px rgba(16,21,28,.06), 0 1px 3px rgba(16,21,28,.04);\n    --shadow-2: 0 4px 12px rgba(16,21,28,.08), 0 2px 4px rgba(16,21,28,.04);\n    --shadow-3: 0 18px 48px rgba(16,21,28,.16), 0 4px 12px rgba(16,21,28,.08);\n    --overlay: rgba(16,21,28,.42);\n    --code-key: #a626a4; --code-str: #1a8f5f; --code-num: #b7791f; --code-com: #8b95a3; --code-fn: #2f6fed;\n}\n[data-theme=\"dark\"] {\n    --accent: #5b9dff; --accent-hover: #7db1ff; --accent-soft: #16233c; --accent-border: #24406e;\n    --accent-contrast: #08111f;\n    --ok: #3ddc97; --ok-soft: #10261d; --ok-border: #1d4535;\n    --warn: #e8b45a; --warn-soft: #2a2113; --warn-border: #4a3a1c;\n    --danger: #ff6b6b; --danger-hover: #ff8585; --danger-soft: #2c1618; --danger-border: #522527;\n    --bg: #0a0e15; --surface: #111722; --surface-2: #171f2d; --surface-3: #1e2838;\n    --sidebar: #0d121b; --header: #0d121b;\n    --text: #e9eef6; --text-dim: #97a3b4; --text-faint: #6b7787;\n    --border: #1e2635; --border-strong: #2c3648;\n    --row-hover: #161e2b; --row-active: #16233c;\n    --shadow-1: 0 1px 2px rgba(0,0,0,.4);\n    --shadow-2: 0 4px 14px rgba(0,0,0,.45);\n    --shadow-3: 0 20px 52px rgba(0,0,0,.62);\n    --overlay: rgba(3,6,11,.68);\n    --code-key: #d98be0; --code-str: #6fdba0; --code-num: #f0b866; --code-com: #6b7787; --code-fn: #5b9dff;\n}\n[data-theme=\"slate\"] {\n    --accent: #94a3b8; --accent-hover: #b3c0d1; --accent-soft: #1d2531; --accent-border: #2f3b4c;\n    --accent-contrast: #0b0f16;\n    --ok: #3ddc97; --ok-soft: #10261d; --ok-border: #1d4535;\n    --warn: #e8b45a; --warn-soft: #2a2113; --warn-border: #4a3a1c;\n    --danger: #f87171; --danger-hover: #fca5a5; --danger-soft: #2c1618; --danger-border: #522527;\n    --bg: #0b0f16; --surface: #131924; --surface-2: #19212e; --surface-3: #212b3a;\n    --sidebar: #0e131c; --header: #0e131c;\n    --text: #eef2f7; --text-dim: #9aa6b6; --text-faint: #6e7a8a;\n    --border: #202939; --border-strong: #2e394b;\n    --row-hover: #182030; --row-active: #1d2531;\n    --shadow-1: 0 1px 2px rgba(0,0,0,.4);\n    --shadow-2: 0 4px 14px rgba(0,0,0,.45);\n    --shadow-3: 0 20px 52px rgba(0,0,0,.62);\n    --overlay: rgba(3,6,11,.68);\n    --code-key: #c4b5fd; --code-str: #6fdba0; --code-num: #f0b866; --code-com: #6e7a8a; --code-fn: #94a3b8;\n}\n[data-theme=\"blue\"] {\n    --accent: #0284c7; --accent-hover: #0369a1; --accent-soft: #e0f2fe; --accent-border: #bae6fd;\n    --accent-contrast: #ffffff;\n    --ok: #0f9d58; --ok-soft: #e4f5ec; --ok-border: #b7e3ca;\n    --warn: #b7791f; --warn-soft: #fdf5e4; --warn-border: #f0dfae;\n    --danger: #d93a3a; --danger-hover: #b62d2d; --danger-soft: #fdecec; --danger-border: #f6c7c7;\n    --bg: #f0f8ff; --surface: #ffffff; --surface-2: #e8f4fd; --surface-3: #daedfb;\n    --sidebar: #ffffff; --header: #ffffff;\n    --text: #0b3a56; --text-dim: #4a7593; --text-faint: #7ba3bf;\n    --border: #cbe6fa; --border-strong: #a5d3f5;\n    --row-hover: #eaf6fe; --row-active: #d9edfc;\n    --shadow-1: 0 1px 2px rgba(2,132,199,.08);\n    --shadow-2: 0 4px 12px rgba(2,132,199,.12);\n    --shadow-3: 0 18px 48px rgba(2,132,199,.20);\n    --overlay: rgba(8,47,73,.42);\n    --code-key: #7c3aed; --code-str: #0f766e; --code-num: #b45309; --code-com: #7ba3bf; --code-fn: #0284c7;\n}\n[data-theme=\"green\"] {\n    --accent: #05966a; --accent-hover: #047857; --accent-soft: #dcfce9; --accent-border: #a9edc9;\n    --accent-contrast: #ffffff;\n    --ok: #05966a; --ok-soft: #dcfce9; --ok-border: #a9edc9;\n    --warn: #b7791f; --warn-soft: #fdf5e4; --warn-border: #f0dfae;\n    --danger: #d93a3a; --danger-hover: #b62d2d; --danger-soft: #fdecec; --danger-border: #f6c7c7;\n    --bg: #f1fdf6; --surface: #ffffff; --surface-2: #e8f9f0; --surface-3: #d8f3e6;\n    --sidebar: #ffffff; --header: #ffffff;\n    --text: #05372a; --text-dim: #3f7a63; --text-faint: #71a891;\n    --border: #c5ebd8; --border-strong: #9adcbb;\n    --row-hover: #ebfaf2; --row-active: #d9f4e6;\n    --shadow-1: 0 1px 2px rgba(5,150,106,.08);\n    --shadow-2: 0 4px 12px rgba(5,150,106,.12);\n    --shadow-3: 0 18px 48px rgba(5,150,106,.20);\n    --overlay: rgba(4,55,42,.42);\n    --code-key: #9333ea; --code-str: #047857; --code-num: #b45309; --code-com: #71a891; --code-fn: #05966a;\n}\n[data-theme=\"purple\"] {\n    --accent: #7c3aed; --accent-hover: #6d28d9; --accent-soft: #f0e9fe; --accent-border: #ddccfd;\n    --accent-contrast: #ffffff;\n    --ok: #0f9d58; --ok-soft: #e4f5ec; --ok-border: #b7e3ca;\n    --warn: #b7791f; --warn-soft: #fdf5e4; --warn-border: #f0dfae;\n    --danger: #d93a3a; --danger-hover: #b62d2d; --danger-soft: #fdecec; --danger-border: #f6c7c7;\n    --bg: #faf7ff; --surface: #ffffff; --surface-2: #f4eeff; --surface-3: #ebe1fd;\n    --sidebar: #ffffff; --header: #ffffff;\n    --text: #2a1150; --text-dim: #6b528f; --text-faint: #9683b5;\n    --border: #e5d9fb; --border-strong: #cdb8f6;\n    --row-hover: #f6f1ff; --row-active: #ede4fe;\n    --shadow-1: 0 1px 2px rgba(124,58,237,.08);\n    --shadow-2: 0 4px 12px rgba(124,58,237,.12);\n    --shadow-3: 0 18px 48px rgba(124,58,237,.20);\n    --overlay: rgba(42,17,80,.42);\n    --code-key: #c026d3; --code-str: #0f766e; --code-num: #b45309; --code-com: #9683b5; --code-fn: #7c3aed;\n}\n[data-theme=\"sunset\"] {\n    --accent: #ea6a1e; --accent-hover: #cc5613; --accent-soft: #fdeee2; --accent-border: #f8d3b6;\n    --accent-contrast: #ffffff;\n    --ok: #0f9d58; --ok-soft: #e4f5ec; --ok-border: #b7e3ca;\n    --warn: #b7791f; --warn-soft: #fdf5e4; --warn-border: #f0dfae;\n    --danger: #d93a3a; --danger-hover: #b62d2d; --danger-soft: #fdecec; --danger-border: #f6c7c7;\n    --bg: #fff8f3; --surface: #ffffff; --surface-2: #fdf0e7; --surface-3: #fbe4d5;\n    --sidebar: #ffffff; --header: #ffffff;\n    --text: #4a2109; --text-dim: #8a5936; --text-faint: #b3866a;\n    --border: #f7ddc9; --border-strong: #f0c3a2;\n    --row-hover: #fff4ec; --row-active: #fde8da;\n    --shadow-1: 0 1px 2px rgba(234,106,30,.08);\n    --shadow-2: 0 4px 12px rgba(234,106,30,.12);\n    --shadow-3: 0 18px 48px rgba(234,106,30,.20);\n    --overlay: rgba(74,33,9,.42);\n    --code-key: #c026d3; --code-str: #0f766e; --code-num: #b45309; --code-com: #b3866a; --code-fn: #ea6a1e;\n}\n\n:root {\n    --r-sm: 6px; --r: 9px; --r-lg: 14px; --r-full: 999px;\n    --sidebar-w: 268px; --topbar-h: 52px;\n    --mono: ui-monospace, SFMono-Regular, \"SF Mono\", Menlo, Consolas, \"Liberation Mono\", monospace;\n    --sans: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;\n    --ease: cubic-bezier(.32,.72,0,1);\n    --spring: cubic-bezier(.34,1.56,.64,1);\n    --t-fast: .13s; --t: .2s; --t-slow: .34s;\n}\n\n/* \u2500\u2500\u2500 Base \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */\n*, *::before, *::after { box-sizing: border-box; }\n* { margin: 0; padding: 0; }\nhtml { -webkit-text-size-adjust: 100%; }\nbody {\n    font-family: var(--sans);\n    background: var(--bg);\n    color: var(--text);\n    font-size: 13.5px;\n    line-height: 1.5;\n    min-height: 100vh;\n    -webkit-font-smoothing: antialiased;\n    text-rendering: optimizeLegibility;\n    overflow-wrap: break-word;\n}\na { color: var(--accent); text-decoration: none; }\na:hover { text-decoration: underline; }\nh1, h2, h3, h4 { line-height: 1.25; font-weight: 680; letter-spacing: -.015em; }\ncode, pre, .mono { font-family: var(--mono); font-size: .92em; }\n:focus-visible {\n    outline: 2px solid var(--accent);\n    outline-offset: 2px;\n    border-radius: var(--r-sm);\n}\n::selection { background: var(--accent-soft); color: var(--text); }\n\n::-webkit-scrollbar { width: 10px; height: 10px; }\n::-webkit-scrollbar-track { background: transparent; }\n::-webkit-scrollbar-thumb {\n    background: var(--border-strong);\n    border-radius: var(--r-full);\n    border: 3px solid transparent;\n    background-clip: content-box;\n}\n::-webkit-scrollbar-thumb:hover { background: var(--text-faint); background-clip: content-box; }\n* { scrollbar-width: thin; scrollbar-color: var(--border-strong) transparent; }\n\n/* \u2500\u2500\u2500 Lucide icons + motion \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\n   lucide-animated.com ships React components driven by Motion. Reproducing that\n   here would mean shipping React into a single-file tool, so the same motion\n   vocabulary is expressed in CSS against the stock Lucide geometry. Each icon\n   part is tagged .p0/.p1/... by the sprite builder, which is what lets us stagger\n   and target individual strokes. */\n.ico {\n    width: 16px; height: 16px;\n    flex: none;\n    fill: none;\n    stroke: currentColor;\n    stroke-width: 2;\n    stroke-linecap: round;\n    stroke-linejoin: round;\n    vertical-align: -.15em;\n    overflow: visible;\n}\n.ico * { transform-box: fill-box; transform-origin: center; }\n.ico-lg { width: 20px; height: 20px; }\n.ico-xl { width: 26px; height: 26px; }\n\n@keyframes ico-settle   { 0%,100% { transform: translateY(0) } 45% { transform: translateY(-1.6px) } }\n@keyframes ico-spin     { to { transform: rotate(360deg) } }\n@keyframes ico-nudge-r  { 0%,100% { transform: translateX(0) } 50% { transform: translateX(2.2px) } }\n@keyframes ico-nudge-l  { 0%,100% { transform: translateX(0) } 50% { transform: translateX(-2.2px) } }\n@keyframes ico-nudge-d  { 0%,100% { transform: translateY(0) } 50% { transform: translateY(2.2px) } }\n@keyframes ico-nudge-u  { 0%,100% { transform: translateY(0) } 50% { transform: translateY(-2.2px) } }\n@keyframes ico-pop      { 0%,100% { transform: scale(1) } 45% { transform: scale(1.18) } }\n@keyframes ico-wiggle   { 0%,100% { transform: rotate(0) } 30% { transform: rotate(-11deg) } 65% { transform: rotate(9deg) } }\n@keyframes ico-pulse    { 0%,100% { opacity: 1 } 50% { opacity: .45 } }\n@keyframes ico-draw     { from { stroke-dashoffset: var(--dash, 64) } to { stroke-dashoffset: 0 } }\n\n/* Hover choreography: parts move in sequence, not all at once. */\n.hov:hover .ico [class^=\"p\"], .ico.run [class^=\"p\"] { animation-duration: .5s; animation-timing-function: var(--spring); }\n.hov:hover .ico .p1, .ico.run .p1 { animation-delay: .045s; }\n.hov:hover .ico .p2, .ico.run .p2 { animation-delay: .09s; }\n.hov:hover .ico .p3, .ico.run .p3 { animation-delay: .135s; }\n\n.hov:hover .ico-database [class^=\"p\"] { animation-name: ico-settle; }\n.hov:hover .ico-server   [class^=\"p\"] { animation-name: ico-settle; }\n.hov:hover .ico-layers   [class^=\"p\"] { animation-name: ico-settle; }\n.hov:hover .ico-search   [class^=\"p\"] { animation-name: ico-pop; }\n.hov:hover .ico-play     [class^=\"p\"] { animation-name: ico-pop; }\n.hov:hover .ico-zap      [class^=\"p\"] { animation-name: ico-pop; }\n.hov:hover .ico-plus     [class^=\"p\"] { animation-name: ico-pop; }\n.hov:hover .ico-check    [class^=\"p\"] { animation-name: ico-pop; }\n.hov:hover .ico-trash-2  [class^=\"p\"] { animation-name: ico-wiggle; }\n.hov:hover .ico-settings [class^=\"p\"] { animation-name: ico-spin; animation-duration: 1.1s; animation-timing-function: linear; }\n.hov:hover .ico-refresh-cw [class^=\"p\"], .hov:hover .ico-rotate-cw [class^=\"p\"] { animation-name: ico-spin; animation-duration: .7s; animation-timing-function: var(--ease); }\n.hov:hover .ico-download [class^=\"p\"] { animation-name: ico-nudge-d; }\n.hov:hover .ico-upload   [class^=\"p\"] { animation-name: ico-nudge-u; }\n.hov:hover .ico-import   [class^=\"p\"] { animation-name: ico-nudge-d; }\n.hov:hover .ico-log-out  [class^=\"p\"] { animation-name: ico-nudge-r; }\n.hov:hover .ico-chevron-right [class^=\"p\"], .hov:hover .ico-arrow-right [class^=\"p\"], .hov:hover .ico-chevrons-right [class^=\"p\"] { animation-name: ico-nudge-r; }\n.hov:hover .ico-chevron-left [class^=\"p\"], .hov:hover .ico-arrow-left [class^=\"p\"], .hov:hover .ico-chevrons-left [class^=\"p\"] { animation-name: ico-nudge-l; }\n.hov:hover .ico-pencil   [class^=\"p\"] { animation-name: ico-wiggle; }\n.hov:hover .ico-square-pen [class^=\"p\"] { animation-name: ico-wiggle; }\n.hov:hover .ico-copy     [class^=\"p\"] { animation-name: ico-nudge-r; }\n.hov:hover .ico-key-round [class^=\"p\"] { animation-name: ico-wiggle; }\n.hov:hover .ico-lock     [class^=\"p\"] { animation-name: ico-pop; }\n.hov:hover .ico-plug-zap [class^=\"p\"] { animation-name: ico-pop; }\n.hov:hover .ico-cable    [class^=\"p\"] { animation-name: ico-pop; }\n.hov:hover .ico-filter   [class^=\"p\"], .hov:hover .ico-funnel [class^=\"p\"] { animation-name: ico-nudge-d; }\n.hov:hover .ico-terminal [class^=\"p\"] { animation-name: ico-nudge-r; }\n.hov:hover .ico-command  [class^=\"p\"] { animation-name: ico-pop; }\n.hov:hover .ico-star     [class^=\"p\"], .hov:hover .ico-sparkles [class^=\"p\"] { animation-name: ico-pop; }\n.hov:hover .ico-table    [class^=\"p\"], .hov:hover .ico-table-2 [class^=\"p\"], .hov:hover .ico-grid-2x2 [class^=\"p\"] { animation-name: ico-pop; }\n.hov:hover .ico-x        [class^=\"p\"] { animation-name: ico-wiggle; }\n.hov:hover .ico-save     [class^=\"p\"] { animation-name: ico-pop; }\n.hov:hover .ico-history  [class^=\"p\"] { animation-name: ico-spin; animation-duration: .8s; }\n\n.ico-spin { animation: ico-spin .85s linear infinite; }\n\n@media (prefers-reduced-motion: reduce) {\n    *, *::before, *::after {\n        animation-duration: .001ms !important;\n        animation-iteration-count: 1 !important;\n        transition-duration: .001ms !important;\n        scroll-behavior: auto !important;\n    }\n}\n\n/* \u2500\u2500\u2500 Layout \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */\n.app { display: flex; min-height: 100vh; }\n.sidebar {\n    width: var(--sidebar-w);\n    background: var(--sidebar);\n    border-inline-end: 1px solid var(--border);\n    display: flex; flex-direction: column;\n    position: fixed; inset-block: 0; inset-inline-start: 0;\n    z-index: 60;\n    transition: transform var(--t) var(--ease);\n}\n.brand {\n    display: flex; align-items: center; gap: 9px;\n    padding: 0 14px; height: var(--topbar-h);\n    border-bottom: 1px solid var(--border);\n    font-weight: 700; font-size: 14.5px; letter-spacing: -.02em;\n    flex: none;\n}\n.brand .ico { color: var(--accent); }\n.brand-tag {\n    margin-inline-start: auto;\n    font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em;\n    padding: 2px 6px; border-radius: var(--r-sm);\n    background: var(--accent-soft); color: var(--accent);\n    border: 1px solid var(--accent-border);\n}\n/* The nav column itself lays out; only the table list scrolls (see below). The\n   auto overflow here is a fallback for very short viewports, where the fixed\n   rows alone can outgrow the sidebar. */\n.side-scroll {\n    flex: 1; min-height: 0; overflow-y: auto; overflow-x: hidden; padding: 8px;\n    display: flex; flex-direction: column;\n}\n.side-scroll > * { flex: none; }\n.nav-link {\n    display: flex; align-items: center; gap: 9px;\n    padding: 7px 10px; border-radius: var(--r-sm);\n    color: var(--text); font-weight: 520; font-size: 13px;\n    text-decoration: none; white-space: nowrap;\n    transition: background var(--t-fast) var(--ease), color var(--t-fast) var(--ease);\n}\n.nav-link:hover { background: var(--row-hover); text-decoration: none; }\n.nav-link .ico { color: var(--text-dim); transition: color var(--t-fast); }\n.nav-link:hover .ico { color: var(--accent); }\n.nav-link.active { background: var(--accent-soft); color: var(--accent); font-weight: 650; }\n.nav-link.active .ico { color: var(--accent); }\n.nav-count { margin-inline-start: auto; font-size: 11px; color: var(--text-faint); font-variant-numeric: tabular-nums; }\n\n.nav-group { margin-top: 14px; }\n\n/* Tables group: header and filter stay pinned, the list takes the leftover\n   height and scrolls on its own. */\n.side-scroll > .nav-group-tables {\n    flex: 1; min-height: 0;\n    display: flex; flex-direction: column;\n}\n.nav-group-tables > * { flex: none; }\n.nav-group-tables > #tblList {\n    flex: 1; min-height: 120px;\n    overflow-y: auto; overflow-x: hidden;\n}\n.nav-head {\n    display: flex; align-items: center; gap: 6px;\n    padding: 4px 10px; margin-bottom: 2px;\n    font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em;\n    color: var(--text-faint);\n}\n.nav-head .ico { width: 13px; height: 13px; }\n\n.tree-item {\n    display: flex; align-items: center; gap: 8px;\n    padding: 5.5px 10px 5.5px 12px;\n    border-radius: var(--r-sm);\n    font-size: 12.5px; color: var(--text-dim);\n    text-decoration: none;\n    white-space: nowrap; overflow: hidden;\n    transition: background var(--t-fast) var(--ease), color var(--t-fast) var(--ease);\n}\n.tree-item:hover { background: var(--row-hover); color: var(--text); text-decoration: none; }\n.tree-item.active { background: var(--accent-soft); color: var(--accent); font-weight: 650; }\n.tree-item .ico { width: 14px; height: 14px; opacity: .75; }\n.tree-item.active .ico { opacity: 1; }\n.tree-item .lbl { overflow: hidden; text-overflow: ellipsis; min-width: 0; flex: 1; }\n.tree-item .kind {\n    font-size: 9.5px; text-transform: uppercase; letter-spacing: .04em;\n    color: var(--text-faint); font-weight: 700;\n}\n\n.side-foot { padding: 9px; border-top: 1px solid var(--border); flex: none; display: grid; gap: 7px; }\n.conn-chip {\n    display: flex; align-items: center; gap: 7px;\n    padding: 7px 9px; border-radius: var(--r-sm);\n    background: var(--surface-2); border: 1px solid var(--border);\n    font-size: 11.5px; color: var(--text-dim);\n    min-width: 0;\n}\n.conn-chip .dot { width: 7px; height: 7px; border-radius: 50%; background: var(--ok); flex: none; }\n.conn-chip .dot.warn { background: var(--warn); }\n.conn-chip .txt { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }\n\n.main { flex: 1; margin-inline-start: var(--sidebar-w); min-width: 0; display: flex; flex-direction: column; }\n.topbar {\n    height: var(--topbar-h);\n    background: color-mix(in srgb, var(--header) 82%, transparent);\n    backdrop-filter: saturate(180%) blur(12px);\n    -webkit-backdrop-filter: saturate(180%) blur(12px);\n    border-bottom: 1px solid var(--border);\n    display: flex; align-items: center; gap: 12px;\n    padding: 0 16px;\n    position: sticky; top: 0; z-index: 40;\n}\n.crumbs { display: flex; align-items: center; gap: 5px; font-size: 12.5px; min-width: 0; overflow: hidden; }\n.crumbs a { color: var(--text-dim); text-decoration: none; white-space: nowrap; padding: 3px 5px; border-radius: var(--r-sm); }\n.crumbs a:hover { color: var(--text); background: var(--row-hover); text-decoration: none; }\n.crumbs .sep { color: var(--text-faint); }\n.crumbs strong { color: var(--text); font-weight: 650; white-space: nowrap; }\n.topbar-right { margin-inline-start: auto; display: flex; align-items: center; gap: 7px; }\n\n.content { padding: 20px; flex: 1; width: 100%; max-width: 1560px; margin-inline: auto; }\n.page-head { display: flex; align-items: flex-start; gap: 14px; flex-wrap: wrap; margin-bottom: 16px; }\n.page-head h2 { font-size: 19px; }\n.page-head .sub { color: var(--text-dim); font-size: 12.5px; margin-top: 2px; }\n.page-head .acts { margin-inline-start: auto; display: flex; gap: 7px; flex-wrap: wrap; }\n\n/* \u2500\u2500\u2500 Cards \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */\n.card {\n    background: var(--surface);\n    border: 1px solid var(--border);\n    border-radius: var(--r);\n    margin-bottom: 16px;\n    box-shadow: var(--shadow-1);\n    overflow: hidden;\n}\n.card-head {\n    padding: 11px 15px;\n    border-bottom: 1px solid var(--border);\n    display: flex; align-items: center; gap: 10px;\n    background: var(--surface);\n}\n.card-head h3 { font-size: 13.5px; font-weight: 650; }\n.card-head .right { margin-inline-start: auto; display: flex; align-items: center; gap: 7px; font-size: 12px; color: var(--text-dim); }\n.card-body { padding: 15px; }\n.card.danger { border-color: var(--danger-border); }\n.card.danger .card-head { background: var(--danger-soft); color: var(--danger); border-bottom-color: var(--danger-border); }\n\n/* \u2500\u2500\u2500 Buttons \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */\n.btn {\n    display: inline-flex; align-items: center; justify-content: center; gap: 6px;\n    padding: 6.5px 12px;\n    border-radius: var(--r-sm);\n    font: inherit; font-size: 12.5px; font-weight: 600;\n    line-height: 1.2;\n    cursor: pointer;\n    border: 1px solid transparent;\n    text-decoration: none;\n    white-space: nowrap;\n    user-select: none;\n    transition: background var(--t-fast) var(--ease), border-color var(--t-fast) var(--ease),\n                color var(--t-fast) var(--ease), transform var(--t-fast) var(--ease),\n                box-shadow var(--t-fast) var(--ease);\n}\n.btn:hover { text-decoration: none; }\n.btn:active { transform: scale(.975); }\n.btn:disabled, .btn[aria-disabled=\"true\"] { opacity: .5; pointer-events: none; }\n.btn-primary { background: var(--accent); color: var(--accent-contrast); box-shadow: var(--shadow-1); }\n.btn-primary:hover { background: var(--accent-hover); box-shadow: var(--shadow-2); }\n.btn-default { background: var(--surface); color: var(--text); border-color: var(--border-strong); }\n.btn-default:hover { background: var(--surface-2); border-color: var(--text-faint); }\n.btn-ghost { background: transparent; color: var(--text-dim); }\n.btn-ghost:hover { background: var(--row-hover); color: var(--text); }\n.btn-danger { background: var(--danger); color: #fff; }\n.btn-danger:hover { background: var(--danger-hover); }\n.btn-danger-soft { background: var(--danger-soft); color: var(--danger); border-color: var(--danger-border); }\n.btn-danger-soft:hover { background: var(--danger); color: #fff; }\n.btn-sm { padding: 3.5px 8px; font-size: 11.5px; gap: 4px; }\n.btn-sm .ico { width: 13px; height: 13px; }\n.btn-icon { padding: 6px; }\n.btn-block { width: 100%; }\n\n.btn-group { display: inline-flex; }\n.btn-group .btn { border-radius: 0; margin-inline-start: -1px; }\n.btn-group .btn:first-child { border-start-start-radius: var(--r-sm); border-end-start-radius: var(--r-sm); margin-inline-start: 0; }\n.btn-group .btn:last-child { border-start-end-radius: var(--r-sm); border-end-end-radius: var(--r-sm); }\n\nkbd {\n    display: inline-block;\n    padding: 1px 5px;\n    font-family: var(--sans); font-size: 10.5px; font-weight: 600;\n    color: var(--text-dim);\n    background: var(--surface-2);\n    border: 1px solid var(--border-strong);\n    border-bottom-width: 2px;\n    border-radius: 4px;\n    line-height: 1.5;\n}\n\n/* \u2500\u2500\u2500 Forms \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */\n.field { margin-bottom: 12px; }\n.field > label, .lbl-text {\n    display: block; margin-bottom: 4px;\n    font-size: 12px; font-weight: 620; color: var(--text);\n}\n.field .hint { font-size: 11.5px; color: var(--text-faint); margin-top: 4px; line-height: 1.45; }\n.input, input[type=text], input[type=password], input[type=number], input[type=search],\ninput[type=email], input[type=file], select, textarea {\n    width: 100%;\n    padding: 7.5px 10px;\n    font: inherit; font-size: 13px;\n    color: var(--text);\n    background: var(--surface);\n    border: 1px solid var(--border-strong);\n    border-radius: var(--r-sm);\n    transition: border-color var(--t-fast) var(--ease), box-shadow var(--t-fast) var(--ease), background var(--t-fast);\n}\ntextarea { resize: vertical; min-height: 70px; }\nselect {\n    appearance: none;\n    background-image: url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%238b95a3' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E\");\n    background-repeat: no-repeat;\n    background-position: right 8px center;\n    background-size: 14px;\n    padding-inline-end: 28px;\n}\n[dir=rtl] select { background-position: left 8px center; padding-inline-end: 10px; padding-inline-start: 28px; }\n.input:focus, input:focus, select:focus, textarea:focus {\n    outline: none;\n    border-color: var(--accent);\n    box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 18%, transparent);\n}\ninput::placeholder, textarea::placeholder { color: var(--text-faint); }\ninput[type=checkbox], input[type=radio] { width: auto; accent-color: var(--accent); cursor: pointer; }\n.input-sm { padding: 4px 8px; font-size: 12px; }\n.check { display: inline-flex; align-items: center; gap: 7px; font-size: 12.5px; cursor: pointer; user-select: none; font-weight: 500; }\n.row { display: flex; gap: 10px; flex-wrap: wrap; }\n.row > * { flex: 1; min-width: 130px; }\n\n.switch { position: relative; display: inline-flex; width: 34px; height: 19px; flex: none; }\n.switch input { position: absolute; opacity: 0; width: 0; height: 0; }\n.switch .track {\n    position: absolute; inset: 0;\n    background: var(--border-strong);\n    border-radius: var(--r-full);\n    transition: background var(--t) var(--ease);\n}\n.switch .track::before {\n    content: \"\"; position: absolute;\n    width: 13px; height: 13px; left: 3px; top: 3px;\n    background: #fff; border-radius: 50%;\n    box-shadow: var(--shadow-1);\n    transition: transform var(--t) var(--spring);\n}\n.switch input:checked + .track { background: var(--accent); }\n.switch input:checked + .track::before { transform: translateX(15px); }\n.switch input:focus-visible + .track { box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 25%, transparent); }\n\n.toggle-box {\n    display: flex; align-items: center; justify-content: space-between; gap: 10px;\n    padding: 8px 11px;\n    background: var(--surface-2);\n    border: 1px solid var(--border);\n    border-radius: var(--r-sm);\n    cursor: pointer; user-select: none;\n    transition: border-color var(--t-fast) var(--ease);\n}\n.toggle-box:hover { border-color: var(--accent-border); }\n\n/* \u2500\u2500\u2500 Tabs \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */\n.tabs {\n    display: flex; gap: 2px;\n    padding: 3px;\n    background: var(--surface-2);\n    border: 1px solid var(--border);\n    border-radius: var(--r);\n}\n.tab {\n    flex: 1;\n    display: inline-flex; align-items: center; justify-content: center; gap: 6px;\n    padding: 6px 10px;\n    font: inherit; font-size: 12px; font-weight: 620;\n    color: var(--text-dim);\n    background: transparent; border: 0;\n    border-radius: var(--r-sm);\n    cursor: pointer;\n    transition: background var(--t-fast) var(--ease), color var(--t-fast) var(--ease);\n}\n.tab:hover { color: var(--text); }\n.tab.active { background: var(--surface); color: var(--accent); box-shadow: var(--shadow-1); }\n\n.subnav { display: flex; gap: 3px; border-bottom: 1px solid var(--border); margin-bottom: 16px; overflow-x: auto; }\n.subnav a {\n    padding: 8px 12px;\n    font-size: 12.5px; font-weight: 600;\n    color: var(--text-dim); text-decoration: none;\n    border-bottom: 2px solid transparent;\n    margin-bottom: -1px;\n    display: inline-flex; align-items: center; gap: 6px;\n    white-space: nowrap;\n    transition: color var(--t-fast) var(--ease), border-color var(--t-fast) var(--ease);\n}\n.subnav a:hover { color: var(--text); text-decoration: none; }\n.subnav a.active { color: var(--accent); border-bottom-color: var(--accent); }\n\n/* \u2500\u2500\u2500 Tables \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */\n.tbl-wrap { width: 100%; overflow: auto; max-height: calc(100vh - 250px); overscroll-behavior: contain; }\n.tbl { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 12.5px; }\n.tbl th, .tbl td { padding: 7px 12px; text-align: start; border-bottom: 1px solid var(--border); }\n.tbl thead th {\n    position: sticky; top: 0; z-index: 2;\n    background: var(--surface-2);\n    font-weight: 650; font-size: 11.5px;\n    color: var(--text-dim);\n    white-space: nowrap;\n    border-bottom: 1px solid var(--border-strong);\n}\n.tbl thead th a { color: inherit; display: inline-flex; align-items: center; gap: 4px; text-decoration: none; }\n.tbl thead th a:hover { color: var(--accent); text-decoration: none; }\n.tbl tbody tr { transition: background var(--t-fast) var(--ease); }\n.tbl tbody tr:hover { background: var(--row-hover); }\n.tbl tbody tr:last-child td { border-bottom: 0; }\n.tbl tfoot td { background: var(--surface-2); font-weight: 650; border-top: 1px solid var(--border-strong); border-bottom: 0; }\n.tbl .num { text-align: end; font-variant-numeric: tabular-nums; }\n.tbl .nowrap { white-space: nowrap; }\n.tbl .acts { text-align: end; white-space: nowrap; }\n.tbl .acts .btn { opacity: .55; transition: opacity var(--t-fast); }\n.tbl tr:hover .acts .btn, .tbl .acts .btn:focus-visible { opacity: 1; }\n.tbl .pick { width: 34px; text-align: center; }\n\n.cell { max-width: 380px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }\n.cell-num { font-variant-numeric: tabular-nums; text-align: end; }\n.cell-edit { cursor: text; border-radius: 3px; }\n.cell-edit:hover { box-shadow: inset 0 0 0 1px var(--accent-border); background: var(--accent-soft); }\n.cell-input {\n    width: 100%; padding: 2px 5px; font: inherit;\n    border: 1px solid var(--accent); border-radius: 3px;\n    background: var(--surface); color: var(--text);\n    box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 18%, transparent);\n}\n.cell.saving { opacity: .45; }\n@keyframes flash-ok { from { background: color-mix(in srgb, var(--ok) 32%, transparent) } to { background: transparent } }\n.cell.saved { animation: flash-ok .9s var(--ease); }\n\n.badge {\n    display: inline-flex; align-items: center; gap: 4px;\n    padding: 1.5px 6.5px;\n    border-radius: var(--r-sm);\n    font-size: 10.5px; font-weight: 650;\n    background: var(--surface-3); color: var(--text-dim);\n    border: 1px solid transparent;\n    white-space: nowrap;\n}\n.badge-accent { background: var(--accent-soft); color: var(--accent); border-color: var(--accent-border); }\n.badge-ok { background: var(--ok-soft); color: var(--ok); border-color: var(--ok-border); }\n.badge-warn { background: var(--warn-soft); color: var(--warn); border-color: var(--warn-border); }\n.badge-danger { background: var(--danger-soft); color: var(--danger); border-color: var(--danger-border); }\n.badge-null { background: transparent; color: var(--text-faint); font-style: italic; border: 1px dashed var(--border-strong); }\n.approx { color: var(--text-faint); font-weight: 400; }\n\n.empty { padding: 44px 20px; text-align: center; color: var(--text-dim); }\n.empty .ico { width: 34px; height: 34px; color: var(--text-faint); margin-bottom: 10px; stroke-width: 1.5; }\n.empty p { font-size: 13px; margin-bottom: 3px; font-weight: 600; color: var(--text); }\n.empty span { font-size: 12.5px; }\n\n/* \u2500\u2500\u2500 Alerts & toasts \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */\n.alert {\n    display: flex; align-items: flex-start; gap: 9px;\n    padding: 10px 13px;\n    border-radius: var(--r);\n    margin-bottom: 14px;\n    font-size: 12.5px;\n    border: 1px solid transparent;\n    animation: slide-down var(--t-slow) var(--ease);\n}\n@keyframes slide-down { from { opacity: 0; transform: translateY(-7px) } to { opacity: 1; transform: none } }\n.alert .ico { margin-top: 1px; flex: none; }\n.alert-ok { background: var(--ok-soft); color: var(--ok); border-color: var(--ok-border); }\n.alert-error { background: var(--danger-soft); color: var(--danger); border-color: var(--danger-border); }\n.alert-warn { background: var(--warn-soft); color: var(--warn); border-color: var(--warn-border); }\n.alert-info { background: var(--accent-soft); color: var(--accent); border-color: var(--accent-border); }\n.alert pre { white-space: pre-wrap; font-size: 11.5px; margin-top: 6px; opacity: .9; }\n.alert .close { margin-inline-start: auto; background: none; border: 0; color: inherit; cursor: pointer; opacity: .6; padding: 0; }\n.alert .close:hover { opacity: 1; }\n\n#toasts {\n    position: fixed; z-index: 200;\n    bottom: 16px; inset-inline-end: 16px;\n    display: flex; flex-direction: column; gap: 8px;\n    pointer-events: none;\n    max-width: min(400px, calc(100vw - 32px));\n}\n.toast {\n    display: flex; align-items: flex-start; gap: 9px;\n    padding: 10px 13px;\n    background: var(--surface);\n    border: 1px solid var(--border-strong);\n    border-radius: var(--r);\n    box-shadow: var(--shadow-3);\n    font-size: 12.5px;\n    pointer-events: auto;\n    animation: toast-in var(--t-slow) var(--spring);\n}\n.toast.out { animation: toast-out var(--t) var(--ease) forwards; }\n@keyframes toast-in { from { opacity: 0; transform: translateY(12px) scale(.96) } to { opacity: 1; transform: none } }\n@keyframes toast-out { to { opacity: 0; transform: translateX(18px) } }\n.toast.ok .ico { color: var(--ok); }\n.toast.error .ico { color: var(--danger); }\n.toast.info .ico { color: var(--accent); }\n\n/* \u2500\u2500\u2500 Modal \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */\n.modal {\n    position: fixed; inset: 0; z-index: 150;\n    background: var(--overlay);\n    backdrop-filter: blur(3px);\n    display: flex; align-items: center; justify-content: center;\n    padding: 18px;\n    opacity: 0; visibility: hidden;\n    transition: opacity var(--t) var(--ease), visibility var(--t);\n}\n.modal.open { opacity: 1; visibility: visible; }\n.modal-box {\n    background: var(--surface);\n    border: 1px solid var(--border);\n    border-radius: var(--r-lg);\n    box-shadow: var(--shadow-3);\n    width: 100%; max-width: 540px;\n    max-height: calc(100vh - 36px);\n    display: flex; flex-direction: column;\n    transform: translateY(10px) scale(.985);\n    transition: transform var(--t-slow) var(--spring);\n}\n.modal.open .modal-box { transform: none; }\n.modal-box.wide { max-width: 760px; }\n.modal-head { padding: 14px 16px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; }\n.modal-head h3 { font-size: 14.5px; }\n.modal-head .close { margin-inline-start: auto; }\n.modal-body { padding: 16px; overflow-y: auto; }\n.modal-foot { padding: 12px 16px; border-top: 1px solid var(--border); display: flex; gap: 8px; justify-content: flex-end; background: var(--surface-2); }\n\n/* \u2500\u2500\u2500 Command palette \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */\n#palette {\n    position: fixed; inset: 0; z-index: 180;\n    background: var(--overlay);\n    backdrop-filter: blur(4px);\n    display: flex; align-items: flex-start; justify-content: center;\n    padding: 12vh 18px 18px;\n    opacity: 0; visibility: hidden;\n    transition: opacity var(--t) var(--ease), visibility var(--t);\n}\n#palette.open { opacity: 1; visibility: visible; }\n.pal-box {\n    width: 100%; max-width: 580px;\n    background: var(--surface);\n    border: 1px solid var(--border-strong);\n    border-radius: var(--r-lg);\n    box-shadow: var(--shadow-3);\n    overflow: hidden;\n    display: flex; flex-direction: column;\n    max-height: 62vh;\n    transform: translateY(-10px) scale(.985);\n    transition: transform var(--t-slow) var(--spring);\n}\n#palette.open .pal-box { transform: none; }\n.pal-input-wrap { display: flex; align-items: center; gap: 10px; padding: 13px 15px; border-bottom: 1px solid var(--border); }\n.pal-input-wrap .ico { color: var(--text-faint); }\n#palInput {\n    flex: 1; border: 0; background: none; padding: 0;\n    font-size: 14.5px; color: var(--text);\n}\n#palInput:focus { outline: none; box-shadow: none; }\n.pal-list { overflow-y: auto; padding: 6px; }\n.pal-item {\n    display: flex; align-items: center; gap: 10px;\n    padding: 8px 11px;\n    border-radius: var(--r-sm);\n    cursor: pointer;\n    color: var(--text); text-decoration: none;\n    font-size: 13px;\n}\n.pal-item:hover, .pal-item.sel { background: var(--accent-soft); text-decoration: none; }\n.pal-item.sel { box-shadow: inset 0 0 0 1px var(--accent-border); }\n.pal-item .ico { color: var(--text-dim); }\n.pal-item.sel .ico { color: var(--accent); }\n.pal-item .nm { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }\n.pal-item .nm mark { background: color-mix(in srgb, var(--accent) 26%, transparent); color: inherit; border-radius: 2px; padding: 0 1px; }\n.pal-item .ctx { font-size: 11px; color: var(--text-faint); white-space: nowrap; }\n.pal-foot {\n    padding: 8px 13px; border-top: 1px solid var(--border);\n    background: var(--surface-2);\n    display: flex; gap: 12px; align-items: center;\n    font-size: 11px; color: var(--text-faint);\n}\n.pal-empty { padding: 30px; text-align: center; color: var(--text-faint); font-size: 12.5px; }\n\n/* \u2500\u2500\u2500 SQL editor \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */\n.sql-editor { position: relative; border: 1px solid var(--border-strong); border-radius: var(--r); overflow: hidden; background: var(--surface); }\n.sql-editor:focus-within { border-color: var(--accent); box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 18%, transparent); }\n.sql-stack { position: relative; }\n#sqlHighlight, #sqlInput {\n    margin: 0; padding: 12px 14px;\n    font-family: var(--mono); font-size: 13px; line-height: 1.6;\n    white-space: pre-wrap; overflow-wrap: break-word; word-break: break-word;\n    tab-size: 2;\n    border: 0;\n}\n#sqlHighlight {\n    position: absolute; inset: 0;\n    pointer-events: none;\n    color: var(--text);\n    overflow: hidden;\n}\n#sqlInput {\n    position: relative;\n    width: 100%; min-height: 190px;\n    background: transparent;\n    color: transparent;\n    caret-color: var(--text);\n    resize: vertical;\n    display: block;\n}\n#sqlInput:focus { outline: none; box-shadow: none; border: 0; }\n#sqlInput::selection { background: color-mix(in srgb, var(--accent) 30%, transparent); }\n.tok-key { color: var(--code-key); font-weight: 650; }\n.tok-str { color: var(--code-str); }\n.tok-num { color: var(--code-num); }\n.tok-com { color: var(--code-com); font-style: italic; }\n.tok-fn  { color: var(--code-fn); }\n.sql-bar { display: flex; align-items: center; gap: 8px; padding: 9px 12px; border-top: 1px solid var(--border); background: var(--surface-2); flex-wrap: wrap; }\n\n#sqlAuto {\n    position: absolute; z-index: 30;\n    background: var(--surface);\n    border: 1px solid var(--border-strong);\n    border-radius: var(--r-sm);\n    box-shadow: var(--shadow-3);\n    max-height: 210px; overflow-y: auto;\n    min-width: 190px;\n    display: none;\n    padding: 4px;\n}\n#sqlAuto.open { display: block; }\n.auto-item {\n    display: flex; align-items: center; gap: 8px;\n    padding: 5px 9px; border-radius: 5px;\n    font-size: 12.5px; cursor: pointer;\n    font-family: var(--mono);\n}\n.auto-item.sel, .auto-item:hover { background: var(--accent-soft); color: var(--accent); }\n.auto-item .t { margin-inline-start: auto; font-family: var(--sans); font-size: 10px; color: var(--text-faint); }\n\n.result-tabs { display: flex; gap: 3px; padding: 8px 12px 0; overflow-x: auto; border-bottom: 1px solid var(--border); }\n.result-tab {\n    padding: 6px 11px; border-radius: var(--r-sm) var(--r-sm) 0 0;\n    font-size: 12px; font-weight: 600; color: var(--text-dim);\n    background: none; border: 1px solid transparent; border-bottom: 0;\n    cursor: pointer; white-space: nowrap; margin-bottom: -1px;\n}\n.result-tab.active { background: var(--surface); color: var(--accent); border-color: var(--border); }\n.result-tab.err { color: var(--danger); }\n\n/* \u2500\u2500\u2500 Login \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */\n.login-wrap {\n    min-height: 100vh;\n    min-height: 100dvh;\n    display: flex; align-items: center; justify-content: center;\n    padding: 16px;\n    background:\n        radial-gradient(1100px 520px at 50% -10%, color-mix(in srgb, var(--accent) 13%, transparent), transparent 68%),\n        var(--bg);\n}\n/* The card owns any overflow, so the page itself never scrolls. When the SSH\n   panel is showing it widens into two columns rather than growing downwards. */\n.login-card {\n    width: 100%; max-width: 452px;\n    max-height: calc(100dvh - 32px);\n    overflow-y: auto;\n    overscroll-behavior: contain;\n    background: var(--surface);\n    border: 1px solid var(--border);\n    border-radius: var(--r-lg);\n    box-shadow: var(--shadow-3);\n    padding: 22px;\n    animation: login-in .45s var(--ease);\n    transition: max-width .3s var(--ease);\n}\n.login-card.wide { max-width: 900px; }\n\n.login-grid {\n    display: grid;\n    grid-template-columns: minmax(0, 1fr);\n    gap: 0 22px;\n    align-items: start;\n}\n.login-card.wide .login-grid { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }\n.login-card.wide .col-ssh { border-inline-end: 1px solid var(--border); padding-inline-end: 20px; }\n\n/* Too narrow for two columns - fall back to one and let the card scroll. */\n@media (max-width: 820px) {\n    .login-card.wide { max-width: 452px; }\n    .login-card.wide .login-grid { grid-template-columns: minmax(0, 1fr); }\n    .login-card.wide .col-ssh { border-inline-end: 0; padding-inline-end: 0; }\n}\n\n/* Short viewports: tighten the chrome so the form still fits unscrolled. */\n@media (max-height: 800px) {\n    .login-card { padding: 16px 18px; }\n    .login-head { margin-bottom: 10px; }\n    .login-head .mark { width: 34px; height: 34px; margin-bottom: 5px; border-radius: 10px; }\n    .login-head .mark .ico { width: 18px; height: 18px; }\n    .login-head h1 { font-size: 17px; }\n    .login-head p { display: none; }\n    .login-card .field { margin-bottom: 7px; }\n    .login-card .subpanel { padding: 9px; }\n    .login-card .hint { display: none; }\n    .login-card #sshKey { min-height: 46px; }\n    .login-card .tabs { margin-bottom: 8px !important; }\n    .login-card .subpanel > .t { margin-bottom: 6px; }\n    .login-card .subpanel .field { margin-bottom: 6px; }\n    .login-card .alert { padding: 7px 10px; font-size: 11.5px; margin-bottom: 9px; }\n    .login-card .alert-info { font-size: 11px; }\n    .login-card details.subpanel { margin-bottom: 8px !important; }\n}\n@media (max-height: 640px) {\n    .login-wrap { align-items: flex-start; padding: 10px; }\n    .login-card { max-height: calc(100dvh - 20px); }\n    .login-card .tabs { margin-bottom: 8px !important; }\n}\n@keyframes login-in { from { opacity: 0; transform: translateY(10px) scale(.99) } to { opacity: 1; transform: none } }\n.login-head { text-align: center; margin-bottom: 16px; }\n.login-head .mark {\n    display: inline-flex; align-items: center; justify-content: center;\n    width: 42px; height: 42px; margin-bottom: 9px;\n    border-radius: 12px;\n    background: var(--accent-soft); color: var(--accent);\n    border: 1px solid var(--accent-border);\n}\n.login-head .mark .ico { width: 22px; height: 22px; }\n.login-head h1 { font-size: 19px; letter-spacing: -.025em; }\n.login-head p { font-size: 12.5px; color: var(--text-dim); margin-top: 2px; }\n.login-card .field { margin-bottom: 10px; }\n.pane { display: none; }\n.pane.on { display: block; animation: fade-in var(--t) var(--ease); }\n@keyframes fade-in { from { opacity: 0 } to { opacity: 1 } }\n.subpanel { padding: 11px; background: var(--surface-2); border: 1px solid var(--border); border-radius: var(--r); margin-bottom: 10px; }\n.subpanel > .t { font-size: 11.5px; font-weight: 700; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; color: var(--text); }\n\n/* \u2500\u2500\u2500 Utility \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */\n.skeleton {\n    background: linear-gradient(90deg, var(--surface-2) 25%, var(--surface-3) 50%, var(--surface-2) 75%);\n    background-size: 200% 100%;\n    animation: shimmer 1.3s infinite linear;\n    border-radius: 4px;\n    color: transparent !important;\n    display: inline-block;\n    min-width: 34px;\n}\n@keyframes shimmer { to { background-position: -200% 0 } }\n.spin { animation: ico-spin .85s linear infinite; }\n.muted { color: var(--text-dim); }\n.faint { color: var(--text-faint); }\n.small { font-size: 11.5px; }\n.mt { margin-top: 12px; }\n.flex { display: flex; align-items: center; gap: 8px; }\n.flex-wrap { flex-wrap: wrap; }\n.grow { flex: 1; min-width: 0; }\n.right { margin-inline-start: auto; }\n.hidden { display: none !important; }\n.ellipsis { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }\n.stat-row { display: flex; gap: 18px; flex-wrap: wrap; font-size: 12.5px; color: var(--text-dim); }\n.stat-row b { color: var(--text); font-weight: 650; font-variant-numeric: tabular-nums; }\n\n.sidebar-toggle { display: none; }\n.scrim { display: none; }\n\n/* \u2500\u2500\u2500 Responsive \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500 */\n@media (max-width: 1000px) {\n    .sidebar { transform: translateX(-100%); box-shadow: var(--shadow-3); }\n    [dir=rtl] .sidebar { transform: translateX(100%); }\n    body.nav-open .sidebar { transform: none; }\n    .main { margin-inline-start: 0; }\n    .sidebar-toggle { display: inline-flex; }\n    body.nav-open .scrim {\n        display: block; position: fixed; inset: 0; z-index: 55;\n        background: var(--overlay); backdrop-filter: blur(2px);\n    }\n    .content { padding: 14px; }\n    .tbl-wrap { max-height: none; }\n}\n@media (max-width: 640px) {\n    .page-head .acts { width: 100%; }\n    .topbar { padding: 0 10px; gap: 8px; }\n    .crumbs { font-size: 12px; }\n    .hide-sm { display: none !important; }\n    .modal { padding: 0; align-items: flex-end; }\n    .modal-box { max-width: none; border-radius: var(--r-lg) var(--r-lg) 0 0; max-height: 88vh; }\n    #palette { padding: 0; align-items: flex-end; }\n    .pal-box { max-width: none; border-radius: var(--r-lg) var(--r-lg) 0 0; max-height: 78vh; }\n}\n@media print {\n    .sidebar, .topbar, .btn, #toasts, .subnav { display: none !important; }\n    .main { margin: 0; }\n    .card { box-shadow: none; break-inside: avoid; }\n}\n";
const CLIENT_JS = "(function () {\n'use strict';\n\nvar D    = window.__DABIRO__ || {};\nvar CSRF = D.csrf || '';\nvar CTX  = D.ctx || {};\n\nvar $  = function (s, r) { return (r || document).querySelector(s); };\nvar $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };\n\nfunction icon(n) {\n    return '<svg class=\"ico ico-' + n + '\" aria-hidden=\"true\"><use href=\"#i-' + n + '\"></use></svg>';\n}\n\n// \u2500\u2500\u2500 Toasts \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\nvar toastBox = $('#toasts');\nfunction toast(msg, kind, ms) {\n    if (!toastBox) return;\n    kind = kind || 'info';\n    var el = document.createElement('div');\n    el.className = 'toast ' + kind;\n    el.innerHTML = icon(kind === 'ok' ? 'circle-check' : kind === 'error' ? 'circle-alert' : 'info') +\n                   '<div class=\"grow\"></div>';\n    el.lastChild.textContent = msg;\n    toastBox.appendChild(el);\n    var life = ms || (kind === 'error' ? 7000 : 3200);\n    setTimeout(function () {\n        el.classList.add('out');\n        setTimeout(function () { el.remove(); }, 220);\n    }, life);\n}\n\n// \u2500\u2500\u2500 Modals \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\nvar lastFocus = null;\nfunction openModal(id) {\n    var m = document.getElementById(id);\n    if (!m) return;\n    lastFocus = document.activeElement;\n    m.classList.add('open');\n    var f = m.querySelector('input:not([type=hidden]), select, textarea, button');\n    if (f) setTimeout(function () { f.focus(); }, 60);\n}\nfunction closeModal(m) {\n    m.classList.remove('open');\n    if (lastFocus && lastFocus.focus) lastFocus.focus();\n}\ndocument.addEventListener('click', function (e) {\n    var t = e.target.closest('[data-modal]');\n    if (t) { e.preventDefault(); openModal(t.getAttribute('data-modal')); return; }\n    if (e.target.closest('.close-modal')) { var m = e.target.closest('.modal'); if (m) closeModal(m); return; }\n    if (e.target.classList && e.target.classList.contains('modal')) closeModal(e.target);\n});\n\n// \u2500\u2500\u2500 Confirmation (replaces window.confirm) \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\n// Destructive actions get a real dialog; the most destructive also require the\n// object's name to be typed, so a stray click cannot drop a table.\nvar pending = null;\nfunction buildConfirm() {\n    var d = document.createElement('div');\n    d.className = 'modal';\n    d.id = '__confirm';\n    d.innerHTML =\n      '<div class=\"modal-box\" style=\"max-width:420px\">' +\n        '<div class=\"modal-head\"><h3>' + icon('triangle-alert') + ' <span id=\"__cTitle\">Are you sure?</span></h3></div>' +\n        '<div class=\"modal-body\"><p id=\"__cMsg\" style=\"font-size:13px\"></p>' +\n          '<div id=\"__cTypeWrap\" class=\"field hidden\" style=\"margin-top:12px\">' +\n            '<label>Type <b id=\"__cTypeName\"></b> to confirm</label>' +\n            '<input type=\"text\" id=\"__cType\" autocomplete=\"off\" spellcheck=\"false\"></div>' +\n        '</div>' +\n        '<div class=\"modal-foot\"><button type=\"button\" class=\"btn btn-default\" id=\"__cNo\">Cancel</button>' +\n          '<button type=\"button\" class=\"btn btn-danger\" id=\"__cYes\">Confirm</button></div>' +\n      '</div>';\n    document.body.appendChild(d);\n\n    $('#__cNo').addEventListener('click', function () { pending = null; closeModal(d); });\n    $('#__cYes').addEventListener('click', function () {\n        var p = pending; pending = null; closeModal(d);\n        if (!p) return;\n        if (p.tagName === 'A') { window.location.href = p.href; }\n        else { p.__ok = true; p.click(); }\n    });\n    $('#__cType').addEventListener('input', function () {\n        $('#__cYes').disabled = this.value !== $('#__cTypeName').textContent;\n    });\n    return d;\n}\nvar confirmEl = null;\ndocument.addEventListener('click', function (e) {\n    var t = e.target.closest('[data-confirm]');\n    if (!t || t.__ok) { if (t) t.__ok = false; return; }\n    e.preventDefault();\n    e.stopPropagation();\n    confirmEl = confirmEl || buildConfirm();\n    pending = t;\n    $('#__cMsg').textContent = t.getAttribute('data-confirm');\n    var typeName = t.getAttribute('data-confirm-type');\n    var wrap = $('#__cTypeWrap'), inp = $('#__cType');\n    if (typeName) {\n        wrap.classList.remove('hidden');\n        $('#__cTypeName').textContent = typeName;\n        inp.value = '';\n        $('#__cYes').disabled = true;\n    } else {\n        wrap.classList.add('hidden');\n        $('#__cYes').disabled = false;\n    }\n    openModal('__confirm');\n}, true);\n\n// \u2500\u2500\u2500 Global keys \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\ndocument.addEventListener('keydown', function (e) {\n    if (e.key === 'Escape') {\n        var pal = $('#palette');\n        if (pal && pal.classList.contains('open')) { closePalette(); return; }\n        var open = $('.modal.open');\n        if (open) { pending = null; closeModal(open); return; }\n    }\n    var mod = e.ctrlKey || e.metaKey;\n    if (mod && (e.key === 'k' || e.key === 'K')) { e.preventDefault(); togglePalette(); }\n    if (!mod && !e.altKey && e.key === '/' && !/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) {\n        e.preventDefault(); togglePalette();\n    }\n});\n\n// \u2500\u2500\u2500 Sidebar (mobile) \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\nvar navToggle = $('#navToggle'), scrim = $('#scrim');\nif (navToggle) navToggle.addEventListener('click', function () { document.body.classList.toggle('nav-open'); });\nif (scrim) scrim.addEventListener('click', function () { document.body.classList.remove('nav-open'); });\n\n// \u2500\u2500\u2500 Theme & language \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\nfunction swapParam(key, val) {\n    var u = new URL(window.location.href);\n    u.searchParams.set(key, val);\n    window.location.href = u.toString();\n}\nvar themeSel = $('#themeSel');\nif (themeSel) themeSel.addEventListener('change', function () {\n    // Apply instantly so the change feels immediate, then persist server-side.\n    document.documentElement.setAttribute('data-theme', this.value);\n    swapParam('set_theme', this.value);\n});\nvar langSel = $('#langSel');\nif (langSel) langSel.addEventListener('change', function () { swapParam('set_lang', this.value); });\n\n// \u2500\u2500\u2500 Sidebar table filter \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\nvar tblFilter = $('#tblFilter');\nif (tblFilter) {\n    tblFilter.addEventListener('input', function () {\n        var q = this.value.toLowerCase().trim(), n = 0;\n        $$('#tblList .tree-item').forEach(function (el) {\n            var hit = (el.getAttribute('data-name') || '').toLowerCase().indexOf(q) !== -1;\n            el.style.display = hit ? '' : 'none';\n            if (hit) n++;\n        });\n        var c = $('#tblCount'); if (c) c.textContent = n;\n    });\n}\n\n// \u2500\u2500\u2500 Table selection \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\nvar selAll = $('#selAll');\nfunction updateSelCount() {\n    var n = $$('.sel-tbl:checked').length, el = $('#selCount');\n    if (el) el.textContent = n ? (n + ' selected') : 'None selected';\n}\nif (selAll) selAll.addEventListener('change', function () {\n    $$('.sel-tbl').forEach(function (c) { c.checked = selAll.checked; });\n    updateSelCount();\n});\n$$('.sel-tbl').forEach(function (c) { c.addEventListener('change', updateSelCount); });\n\n// Bulk buttons must not fire with an empty selection.\nvar tablesForm = $('#tablesForm');\nif (tablesForm) tablesForm.addEventListener('submit', function (e) {\n    if (!$$('.sel-tbl:checked').length) {\n        e.preventDefault();\n        toast('Select at least one table first.', 'error');\n    }\n});\n\n// \u2500\u2500\u2500 Copy buttons \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\ndocument.addEventListener('click', function (e) {\n    var b = e.target.closest('[data-copy]');\n    if (!b) return;\n    var src = $(b.getAttribute('data-copy'));\n    if (!src) return;\n    var text = src.textContent;\n    var done = function () { toast('Copied to clipboard', 'ok', 1600); };\n    if (navigator.clipboard && window.isSecureContext) {\n        navigator.clipboard.writeText(text).then(done, function () { toast('Copy failed', 'error'); });\n    } else {\n        var ta = document.createElement('textarea');\n        ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';\n        document.body.appendChild(ta); ta.select();\n        try { document.execCommand('copy'); done(); } catch (_) { toast('Copy failed', 'error'); }\n        ta.remove();\n    }\n});\n\n// \u2500\u2500\u2500 Lazy PostgreSQL table counts \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\n// A PostgreSQL connection can only see its own database's catalog, so counts for\n// the other databases are fetched here instead of blocking the page render.\nvar lazyCounts = $$('.js-tblcount');\nif (lazyCounts.length) {\n    var queue = lazyCounts.slice();\n    var runNext = function () {\n        var el = queue.shift();\n        if (!el) return;\n        fetch('?action=db_table_count&name=' + encodeURIComponent(el.getAttribute('data-db')),\n              { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })\n            .then(function (r) { return r.json(); })\n            .then(function (d) {\n                el.classList.remove('skeleton');\n                el.textContent = (d && d.ok && d.tables !== null) ? d.tables : '\u2014';\n                if (!d || !d.ok) el.classList.add('faint');\n            })\n            .catch(function () { el.classList.remove('skeleton'); el.textContent = '\u2014'; })\n            .then(runNext);\n    };\n    // Two at a time: responsive without opening a burst of connections.\n    runNext(); runNext();\n}\n\n// \u2500\u2500\u2500 Connection / tunnel health \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\nvar connDot = $('#connDot');\nif (connDot) {\n    var pollConn = function () {\n        fetch('?action=tunnel_status', { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })\n            .then(function (r) { return r.json(); })\n            .then(function (d) {\n                if (!d || !d.ok) return;\n                if (d.ssh) {\n                    var up = d.status && d.status.up;\n                    connDot.classList.toggle('warn', !up);\n                    connDot.title = up ? ('SSH tunnel up on port ' + d.status.port) : 'SSH tunnel is down \u2014 it will be rebuilt on the next request';\n                }\n            })\n            .catch(function () {});\n    };\n    if (CTX.loggedIn) setInterval(pollConn, 30000);\n}\n\n// \u2500\u2500\u2500 Command palette \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\nvar pal = $('#palette'), palInput = $('#palInput'), palList = $('#palList');\nvar palItems = [], palFiltered = [], palSel = 0, palLoaded = false;\n\nvar STATIC_ACTIONS = [\n    { type: 'action', name: 'Databases',    url: '?page=databases', ico: 'server' },\n    { type: 'action', name: 'SQL console',  url: '?page=sql',       ico: 'terminal' },\n    { type: 'action', name: 'Global search', url: '?page=search',   ico: 'scan-search' },\n    { type: 'action', name: 'Import SQL',   url: '?page=import',    ico: 'import' },\n    { type: 'action', name: 'Export',       url: '?page=export',    ico: 'download' }\n];\n\nfunction togglePalette() {\n    if (!pal) return;\n    pal.classList.contains('open') ? closePalette() : openPalette();\n}\nfunction openPalette() {\n    if (!pal) return;\n    pal.classList.add('open');\n    palInput.value = '';\n    palInput.focus();\n    if (!palLoaded) {\n        palLoaded = true;\n        var q = '?action=palette' + (CTX.db ? '&db=' + encodeURIComponent(CTX.db) : '') +\n                (CTX.schema ? '&schema=' + encodeURIComponent(CTX.schema) : '');\n        fetch(q, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })\n            .then(function (r) { return r.json(); })\n            .then(function (d) {\n                palItems = STATIC_ACTIONS.concat((d && d.items) || []);\n                renderPalette();\n            })\n            .catch(function () { palItems = STATIC_ACTIONS; renderPalette(); });\n    }\n    renderPalette();\n}\nfunction closePalette() { if (pal) pal.classList.remove('open'); }\n\n// Subsequence match, so \"usr\" finds \"users\" and \"ordit\" finds \"order_items\".\nfunction fuzzy(needle, hay) {\n    if (!needle) return { score: 0, marks: null };\n    var n = needle.toLowerCase(), h = hay.toLowerCase();\n    var exact = h.indexOf(n);\n    if (exact !== -1) {\n        return { score: 1000 - exact - (h.length - n.length) * 0.1,\n                 marks: [[exact, exact + n.length]] };\n    }\n    var i = 0, marks = [], score = 0, last = -2;\n    for (var j = 0; j < h.length && i < n.length; j++) {\n        if (h[j] === n[i]) {\n            marks.push([j, j + 1]);\n            score += (j === last + 1) ? 6 : 1;\n            last = j; i++;\n        }\n    }\n    return i === n.length ? { score: score, marks: marks } : null;\n}\nfunction markup(text, marks) {\n    if (!marks) return escapeHtml(text);\n    var out = '', pos = 0;\n    marks.forEach(function (m) {\n        out += escapeHtml(text.slice(pos, m[0])) + '<mark>' + escapeHtml(text.slice(m[0], m[1])) + '</mark>';\n        pos = m[1];\n    });\n    return out + escapeHtml(text.slice(pos));\n}\nfunction escapeHtml(s) {\n    return String(s).replace(/[&<>\"']/g, function (c) {\n        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '\"': '&quot;', \"'\": '&#39;' }[c];\n    });\n}\nvar ICON_FOR = { database: 'database', schema: 'git-branch', table: 'table', action: null };\n\nfunction renderPalette() {\n    if (!palList) return;\n    var q = palInput.value.trim();\n    palFiltered = [];\n    palItems.forEach(function (it) {\n        var m = fuzzy(q, it.name);\n        if (m) palFiltered.push({ it: it, score: m.score + (it.type === 'action' ? 2 : 0), marks: m.marks });\n    });\n    palFiltered.sort(function (a, b) { return b.score - a.score; });\n    palFiltered = palFiltered.slice(0, 60);\n    palSel = 0;\n\n    if (!palFiltered.length) {\n        palList.innerHTML = '<div class=\"pal-empty\">' + (palItems.length ? 'No matches' : 'Loading&hellip;') + '</div>';\n        return;\n    }\n    palList.innerHTML = palFiltered.map(function (r, i) {\n        var it = r.it;\n        var ic = it.ico || ICON_FOR[it.type] || 'chevron-right';\n        return '<a class=\"pal-item' + (i === 0 ? ' sel' : '') + '\" href=\"' + escapeHtml(it.url) + '\" data-i=\"' + i + '\">' +\n               icon(ic) + '<span class=\"nm\">' + markup(it.name, r.marks) + '</span>' +\n               '<span class=\"ctx\">' + escapeHtml(it.context || it.type) + '</span></a>';\n    }).join('');\n}\nfunction movePalette(d) {\n    var items = $$('.pal-item', palList);\n    if (!items.length) return;\n    items[palSel].classList.remove('sel');\n    palSel = (palSel + d + items.length) % items.length;\n    items[palSel].classList.add('sel');\n    items[palSel].scrollIntoView({ block: 'nearest' });\n}\nif (palInput) {\n    palInput.addEventListener('input', renderPalette);\n    palInput.addEventListener('keydown', function (e) {\n        if (e.key === 'ArrowDown') { e.preventDefault(); movePalette(1); }\n        else if (e.key === 'ArrowUp') { e.preventDefault(); movePalette(-1); }\n        else if (e.key === 'Enter') {\n            e.preventDefault();\n            var el = $$('.pal-item', palList)[palSel];\n            if (el) window.location.href = el.href;\n        }\n    });\n}\nvar palBtn = $('#palBtn');\nif (palBtn) palBtn.addEventListener('click', togglePalette);\nif (pal) pal.addEventListener('click', function (e) { if (e.target === pal) closePalette(); });\n\n// \u2500\u2500\u2500 Inline cell editing \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\nvar grid = $('#dataGrid');\nif (grid && grid.getAttribute('data-haspk') === '1') {\n    grid.addEventListener('dblclick', function (e) {\n        var td = e.target.closest('td.cell');\n        if (!td || td.querySelector('input')) return;\n        var tr = td.closest('tr');\n        var col = td.getAttribute('data-col');\n        if (!col || !tr) return;\n\n        var wasNull = !!td.querySelector('.badge-null');\n        var original = wasNull ? '' : td.textContent.replace(/\u2026$/, '');\n        var input = document.createElement('input');\n        input.className = 'cell-input';\n        input.value = original;\n        td.textContent = '';\n        td.appendChild(input);\n        input.focus();\n        input.select();\n\n        var done = false;\n        var finish = function (save) {\n            if (done) return;\n            done = true;\n            var val = input.value;\n            if (!save || val === original) { restore(wasNull ? null : original); return; }\n            td.classList.add('saving');\n            var body = new URLSearchParams();\n            body.set('csrf_token', CSRF);\n            body.set('table', grid.getAttribute('data-table'));\n            body.set('column', col);\n            body.set('value', val);\n            body.set('keys', tr.getAttribute('data-keys'));\n            fetch('?action=cell_update', {\n                method: 'POST', credentials: 'same-origin',\n                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },\n                body: body.toString()\n            })\n            .then(function (r) { return r.json(); })\n            .then(function (d) {\n                td.classList.remove('saving');\n                if (d && d.ok) {\n                    restore(val);\n                    td.classList.add('saved');\n                    setTimeout(function () { td.classList.remove('saved'); }, 950);\n                    toast('Saved', 'ok', 1500);\n                } else {\n                    restore(wasNull ? null : original);\n                    toast((d && d.error) || 'Update failed', 'error');\n                }\n            })\n            .catch(function () {\n                td.classList.remove('saving');\n                restore(wasNull ? null : original);\n                toast('Network error', 'error');\n            });\n        };\n        var restore = function (v) {\n            td.textContent = '';\n            if (v === null) {\n                var b = document.createElement('span');\n                b.className = 'badge badge-null';\n                b.textContent = 'NULL';\n                td.appendChild(b);\n            } else {\n                td.textContent = v;\n            }\n        };\n        input.addEventListener('blur', function () { finish(true); });\n        input.addEventListener('keydown', function (ev) {\n            if (ev.key === 'Enter') { ev.preventDefault(); finish(true); }\n            else if (ev.key === 'Escape') { ev.preventDefault(); finish(false); }\n        });\n    });\n    $$('#dataGrid td.cell').forEach(function (td) { td.classList.add('cell-edit'); });\n}\n\n// \u2500\u2500\u2500 Browse filter rows \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\nvar filterToggle = $('#filterToggle'), filterBox = $('#filterBox');\nif (filterToggle && filterBox) {\n    filterToggle.addEventListener('click', function () {\n        filterBox.classList.toggle('hidden');\n        if (!filterBox.classList.contains('hidden')) {\n            if (!$('.filter-row', filterBox)) addFilterRow();\n            var f = filterBox.querySelector('select'); if (f) f.focus();\n        }\n    });\n}\nvar COLS = D.cols || [];\nvar OPS  = D.ops || [];\nfunction addFilterRow() {\n    var wrap = $('#filterRows');\n    if (!wrap) return;\n    var i = $$('.filter-row', wrap).length + Date.now() % 1000;\n    var row = document.createElement('div');\n    row.className = 'row filter-row';\n    row.style.cssText = 'margin-bottom:8px;align-items:center';\n    row.innerHTML =\n        '<select name=\"where[' + i + '][col]\" class=\"input-sm\" style=\"flex:2\">' +\n            COLS.map(function (c) { return '<option value=\"' + escapeHtml(c) + '\">' + escapeHtml(c) + '</option>'; }).join('') + '</select>' +\n        '<select name=\"where[' + i + '][op]\" class=\"input-sm\" style=\"flex:1.4\">' +\n            OPS.map(function (o) { return '<option value=\"' + escapeHtml(o) + '\">' + escapeHtml(o) + '</option>'; }).join('') + '</select>' +\n        '<input type=\"text\" name=\"where[' + i + '][val]\" class=\"input-sm\" style=\"flex:2\" placeholder=\"value\">' +\n        '<button type=\"button\" class=\"btn btn-ghost btn-icon rm-filter\" style=\"flex:0\">' + icon('x') + '</button>';\n    wrap.appendChild(row);\n}\nvar addFilterBtn = $('#addFilter');\nif (addFilterBtn) addFilterBtn.addEventListener('click', addFilterRow);\ndocument.addEventListener('click', function (e) {\n    var b = e.target.closest('.rm-filter');\n    if (b) b.closest('.filter-row').remove();\n});\n\n// \u2500\u2500\u2500 Structure: edit column modal \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\n$$('.edit-col').forEach(function (b) {\n    b.addEventListener('click', function () {\n        var type = b.getAttribute('data-type') || '';\n        var m = type.match(/^([^(]+)\\(([^)]*)\\)/);\n        $('#ecOld').value = b.getAttribute('data-name');\n        $('#ecName').value = b.getAttribute('data-name');\n        $('#ecType').value = m ? m[1].trim() : type;\n        $('#ecLen').value = m ? m[2] : '';\n        $('#ecDflt').value = b.getAttribute('data-default') || '';\n        $('#ecNull').checked = b.getAttribute('data-null') === '1';\n        openModal('mEditCol');\n    });\n});\n\n// \u2500\u2500\u2500 Create-table builder \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\nvar ctCols = $('#ctCols');\nif (ctCols) {\n    var TYPES = ['INT', 'BIGINT', 'VARCHAR', 'TEXT', 'BOOLEAN', 'DECIMAL', 'DATE', 'DATETIME', 'TIMESTAMP', 'JSON', 'UUID', 'FLOAT'];\n    function ctRow(name, type, len) {\n        var d = document.createElement('div');\n        d.className = 'row ct-row';\n        d.style.cssText = 'margin-bottom:8px;align-items:center';\n        d.innerHTML =\n            '<input type=\"text\" class=\"input-sm ct-name\" placeholder=\"column\" style=\"flex:2\" value=\"' + escapeHtml(name || '') + '\">' +\n            '<select class=\"input-sm ct-type\" style=\"flex:1.4\">' +\n                TYPES.map(function (t) { return '<option' + (t === type ? ' selected' : '') + '>' + t + '</option>'; }).join('') + '</select>' +\n            '<input type=\"text\" class=\"input-sm ct-len\" placeholder=\"len\" style=\"flex:.7\" value=\"' + escapeHtml(len || '') + '\">' +\n            '<label class=\"check\" style=\"flex:0;white-space:nowrap\"><input type=\"checkbox\" class=\"ct-null\"> null</label>' +\n            '<label class=\"check\" style=\"flex:0;white-space:nowrap\"><input type=\"checkbox\" class=\"ct-pk\"> pk</label>' +\n            '<button type=\"button\" class=\"btn btn-ghost btn-icon ct-rm\" style=\"flex:0\">' + icon('x') + '</button>';\n        ctCols.appendChild(d);\n        d.addEventListener('input', ctPreview);\n        d.addEventListener('change', ctPreview);\n        ctPreview();\n    }\n    function ctSql() {\n        var name = ($('#ctName').value || '').trim();\n        if (!name) return '';\n        var q = CTX.type === 'mysql' ? '`' : '\"';\n        var qi = function (s) { return q + String(s).split(q).join(q + q) + q; };\n        var defs = [], pks = [];\n        $$('.ct-row', ctCols).forEach(function (r) {\n            var n = $('.ct-name', r).value.trim();\n            if (!n) return;\n            var t = $('.ct-type', r).value;\n            var l = $('.ct-len', r).value.trim();\n            var isPk = $('.ct-pk', r).checked;\n            var nullable = $('.ct-null', r).checked;\n            var def = qi(n) + ' ' + t + (l ? '(' + l + ')' : '');\n            if (isPk) {\n                if (t === 'INT' || t === 'BIGINT') {\n                    def = qi(n) + ' ' + (CTX.type === 'pgsql' ? (t === 'BIGINT' ? 'BIGSERIAL' : 'SERIAL')\n                                       : t + ' AUTO_INCREMENT');\n                }\n                pks.push(qi(n));\n                def += ' NOT NULL';\n            } else {\n                def += nullable ? ' NULL' : ' NOT NULL';\n            }\n            defs.push('  ' + def);\n        });\n        if (!defs.length) return '';\n        if (pks.length) defs.push('  PRIMARY KEY (' + pks.join(', ') + ')');\n        return 'CREATE TABLE ' + qi(name) + ' (\\n' + defs.join(',\\n') + '\\n)';\n    }\n    function ctPreview() {\n        var p = $('#ctPreview');\n        if (p) p.textContent = ctSql() || '-- add a name and at least one column';\n    }\n    $('#ctAdd').addEventListener('click', function () { ctRow('', 'VARCHAR', '255'); });\n    $('#ctName').addEventListener('input', ctPreview);\n    ctCols.addEventListener('click', function (e) {\n        var b = e.target.closest('.ct-rm');\n        if (b) { b.closest('.ct-row').remove(); ctPreview(); }\n    });\n    ctRow('id', 'INT', '');\n    var firstPk = $('.ct-pk', ctCols); if (firstPk) firstPk.checked = true;\n    ctRow('name', 'VARCHAR', '255');\n    ctPreview();\n\n    $('#createTableForm').addEventListener('submit', function (e) {\n        var sql = ctSql();\n        if (!sql) { e.preventDefault(); toast('Add a table name and at least one column.', 'error'); return; }\n        $('#createTableSql').value = sql;\n    });\n}\n\n// \u2500\u2500\u2500 SQL console \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\nvar sqlInput = $('#sqlInput'), sqlHi = $('#sqlHighlight');\nif (sqlInput && sqlHi) {\n    var KEYWORDS = ('SELECT FROM WHERE INSERT UPDATE DELETE SET VALUES INTO JOIN LEFT RIGHT INNER OUTER FULL CROSS ON ' +\n        'GROUP BY ORDER HAVING LIMIT OFFSET UNION ALL DISTINCT AS AND OR NOT NULL IS IN LIKE ILIKE BETWEEN EXISTS CASE ' +\n        'WHEN THEN ELSE END CREATE TABLE ALTER DROP TRUNCATE INDEX UNIQUE PRIMARY KEY FOREIGN REFERENCES DEFAULT ' +\n        'CONSTRAINT ADD COLUMN RENAME TO VIEW DATABASE SCHEMA GRANT REVOKE BEGIN COMMIT ROLLBACK TRANSACTION WITH ' +\n        'RECURSIVE RETURNING USING NATURAL ASC DESC EXPLAIN ANALYZE VACUUM SHOW DESCRIBE PRAGMA IF ELSIF LOOP ' +\n        'INT INTEGER BIGINT SMALLINT VARCHAR CHAR TEXT DATE DATETIME TIMESTAMP BOOLEAN DECIMAL NUMERIC FLOAT DOUBLE ' +\n        'JSON JSONB UUID SERIAL BLOB BYTEA').split(/\\s+/);\n    var KW = {};\n    KEYWORDS.forEach(function (k) { KW[k] = 1; });\n    var FUNCS = /\\b(COUNT|SUM|AVG|MIN|MAX|COALESCE|NULLIF|CAST|CONCAT|SUBSTRING|LENGTH|LOWER|UPPER|TRIM|ROUND|NOW|DATE_TRUNC|EXTRACT|ARRAY_AGG|STRING_AGG|JSON_AGG|ROW_NUMBER|RANK|DENSE_RANK)\\b/i;\n\n    function highlight(src) {\n        var out = '', i = 0;\n        while (i < src.length) {\n            var c = src[i], two = src.substr(i, 2);\n            if (two === '--' || c === '#') {\n                var e = src.indexOf('\\n', i); if (e === -1) e = src.length;\n                out += '<span class=\"tok-com\">' + escapeHtml(src.slice(i, e)) + '</span>'; i = e; continue;\n            }\n            if (two === '/*') {\n                var e2 = src.indexOf('*/', i + 2); e2 = e2 === -1 ? src.length : e2 + 2;\n                out += '<span class=\"tok-com\">' + escapeHtml(src.slice(i, e2)) + '</span>'; i = e2; continue;\n            }\n            if (c === \"'\" || c === '\"' || c === '`') {\n                var j = i + 1;\n                while (j < src.length) {\n                    if (src[j] === '\\\\') { j += 2; continue; }\n                    if (src[j] === c) { j++; break; }\n                    j++;\n                }\n                var cls = c === \"'\" ? 'tok-str' : 'tok-fn';\n                out += '<span class=\"' + cls + '\">' + escapeHtml(src.slice(i, j)) + '</span>'; i = j; continue;\n            }\n            if (/[0-9]/.test(c) && !/[A-Za-z_]/.test(src[i - 1] || '')) {\n                var k = i;\n                while (k < src.length && /[0-9.]/.test(src[k])) k++;\n                out += '<span class=\"tok-num\">' + escapeHtml(src.slice(i, k)) + '</span>'; i = k; continue;\n            }\n            if (/[A-Za-z_]/.test(c)) {\n                var m = i;\n                while (m < src.length && /[A-Za-z0-9_$]/.test(src[m])) m++;\n                var word = src.slice(i, m);\n                if (KW[word.toUpperCase()]) out += '<span class=\"tok-key\">' + escapeHtml(word) + '</span>';\n                else if (FUNCS.test(word) && src[m] === '(') out += '<span class=\"tok-fn\">' + escapeHtml(word) + '</span>';\n                else out += escapeHtml(word);\n                i = m; continue;\n            }\n            out += escapeHtml(c); i++;\n        }\n        // Trailing newline keeps the mirrored <pre> the same height as the textarea.\n        return out + '\\n';\n    }\n    var syncHi = function () {\n        sqlHi.innerHTML = highlight(sqlInput.value);\n        sqlHi.scrollTop = sqlInput.scrollTop;\n        sqlHi.scrollLeft = sqlInput.scrollLeft;\n    };\n    sqlInput.addEventListener('input', syncHi);\n    sqlInput.addEventListener('scroll', function () {\n        sqlHi.scrollTop = sqlInput.scrollTop;\n        sqlHi.scrollLeft = sqlInput.scrollLeft;\n    });\n    syncHi();\n\n    // History (local to this browser)\n    var HKEY = 'dabiro.sqlhistory';\n    function histLoad() { try { return JSON.parse(localStorage.getItem(HKEY) || '[]'); } catch (_) { return []; } }\n    function histRender() {\n        var sel = $('#sqlHistory');\n        if (!sel) return;\n        var h = histLoad();\n        sel.innerHTML = '<option value=\"\">History (' + h.length + ')&hellip;</option>' +\n            h.map(function (q) {\n                return '<option value=\"' + escapeHtml(q) + '\">' + escapeHtml(q.slice(0, 70).replace(/\\s+/g, ' ')) + '</option>';\n            }).join('');\n    }\n    histRender();\n    var histSel = $('#sqlHistory');\n    if (histSel) histSel.addEventListener('change', function () {\n        if (this.value) { sqlInput.value = this.value; syncHi(); sqlInput.focus(); }\n    });\n    var histClear = $('#sqlHistClear');\n    if (histClear) histClear.addEventListener('click', function () {\n        localStorage.removeItem(HKEY); histRender(); toast('History cleared', 'ok', 1500);\n    });\n\n    var sqlForm = $('#sqlForm');\n    sqlForm.addEventListener('submit', function () {\n        var q = sqlInput.value.trim();\n        if (!q) return;\n        var h = histLoad().filter(function (x) { return x !== q; });\n        h.unshift(q);\n        try { localStorage.setItem(HKEY, JSON.stringify(h.slice(0, 40))); } catch (_) {}\n    });\n\n    sqlInput.addEventListener('keydown', function (e) {\n        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {\n            e.preventDefault();\n            var btn = sqlForm.querySelector('[name=execute_sql]');\n            btn.click();\n            return;\n        }\n        if (e.key === 'Tab' && !autoOpen) {\n            e.preventDefault();\n            var s = this.selectionStart, en = this.selectionEnd;\n            this.value = this.value.slice(0, s) + '  ' + this.value.slice(en);\n            this.selectionStart = this.selectionEnd = s + 2;\n            syncHi();\n        }\n    });\n\n    // Very light formatter: newline before major clauses.\n    var fmtBtn = $('#sqlFormat');\n    if (fmtBtn) fmtBtn.addEventListener('click', function () {\n        var s = sqlInput.value.replace(/\\s+/g, ' ').trim();\n        s = s.replace(/\\s*\\b(FROM|WHERE|LEFT JOIN|RIGHT JOIN|INNER JOIN|JOIN|GROUP BY|ORDER BY|HAVING|LIMIT|OFFSET|UNION ALL|UNION|VALUES|SET|RETURNING)\\b/gi,\n                      function (m, kw) { return '\\n' + kw.toUpperCase(); });\n        s = s.replace(/,\\s*/g, ',\\n  ');\n        sqlInput.value = s;\n        syncHi();\n    });\n\n    // Autocomplete over the live schema.\n    var schema = null, autoBox = $('#sqlAuto'), autoOpen = false, autoItems = [], autoSel = 0, autoStart = 0;\n    fetch('?action=schema_map' + (CTX.db ? '&db=' + encodeURIComponent(CTX.db) : '') +\n          (CTX.schema ? '&schema=' + encodeURIComponent(CTX.schema) : ''),\n          { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })\n        .then(function (r) { return r.json(); })\n        .then(function (d) { if (d && d.ok) schema = d.tables; })\n        .catch(function () {});\n\n    function currentWord() {\n        var pos = sqlInput.selectionStart;\n        var before = sqlInput.value.slice(0, pos);\n        var m = before.match(/[A-Za-z0-9_$.]*$/);\n        return { word: m ? m[0] : '', start: pos - (m ? m[0].length : 0) };\n    }\n    function closeAuto() { autoOpen = false; autoBox.classList.remove('open'); }\n    function showAuto() {\n        if (!schema) return;\n        var cw = currentWord();\n        if (cw.word.length < 1) { closeAuto(); return; }\n        autoStart = cw.start;\n        var cands = [];\n        var dot = cw.word.lastIndexOf('.');\n        if (dot > 0) {\n            var tbl = cw.word.slice(0, dot), frag = cw.word.slice(dot + 1).toLowerCase();\n            (schema[tbl] || []).forEach(function (c) {\n                if (c.toLowerCase().indexOf(frag) === 0) cands.push({ v: tbl + '.' + c, l: c, t: 'column' });\n            });\n        } else {\n            var frag2 = cw.word.toLowerCase();\n            Object.keys(schema).forEach(function (t) {\n                if (t.toLowerCase().indexOf(frag2) === 0) cands.push({ v: t, l: t, t: 'table' });\n            });\n            var seen = {};\n            Object.keys(schema).forEach(function (t) {\n                schema[t].forEach(function (c) {\n                    if (!seen[c] && c.toLowerCase().indexOf(frag2) === 0) { seen[c] = 1; cands.push({ v: c, l: c, t: 'column' }); }\n                });\n            });\n            KEYWORDS.forEach(function (k) {\n                if (k.toLowerCase().indexOf(frag2) === 0) cands.push({ v: k, l: k, t: 'keyword' });\n            });\n        }\n        cands = cands.slice(0, 12);\n        if (!cands.length) { closeAuto(); return; }\n        autoItems = cands; autoSel = 0;\n        autoBox.innerHTML = cands.map(function (c, i) {\n            return '<div class=\"auto-item' + (i === 0 ? ' sel' : '') + '\" data-i=\"' + i + '\">' +\n                   escapeHtml(c.l) + '<span class=\"t\">' + c.t + '</span></div>';\n        }).join('');\n        // Position near the caret line rather than following exact glyph metrics.\n        var lines = sqlInput.value.slice(0, autoStart).split('\\n');\n        var top = Math.min(lines.length * 20.8 + 14, sqlInput.clientHeight - 10);\n        autoBox.style.top = top + 'px';\n        autoBox.style.left = '18px';\n        autoOpen = true;\n        autoBox.classList.add('open');\n    }\n    function applyAuto(i) {\n        var c = autoItems[i];\n        if (!c) return;\n        var pos = sqlInput.selectionStart;\n        sqlInput.value = sqlInput.value.slice(0, autoStart) + c.v + sqlInput.value.slice(pos);\n        sqlInput.selectionStart = sqlInput.selectionEnd = autoStart + c.v.length;\n        closeAuto();\n        syncHi();\n        sqlInput.focus();\n    }\n    autoBox.addEventListener('mousedown', function (e) {\n        var it = e.target.closest('.auto-item');\n        if (it) { e.preventDefault(); applyAuto(+it.getAttribute('data-i')); }\n    });\n    sqlInput.addEventListener('input', function () { if (autoOpen) showAuto(); });\n    sqlInput.addEventListener('blur', function () { setTimeout(closeAuto, 140); });\n    sqlInput.addEventListener('keydown', function (e) {\n        if ((e.ctrlKey || e.metaKey) && e.key === ' ') { e.preventDefault(); showAuto(); return; }\n        if (!autoOpen) return;\n        var items = $$('.auto-item', autoBox);\n        if (e.key === 'ArrowDown') {\n            e.preventDefault(); items[autoSel].classList.remove('sel');\n            autoSel = (autoSel + 1) % items.length; items[autoSel].classList.add('sel');\n            items[autoSel].scrollIntoView({ block: 'nearest' });\n        } else if (e.key === 'ArrowUp') {\n            e.preventDefault(); items[autoSel].classList.remove('sel');\n            autoSel = (autoSel - 1 + items.length) % items.length; items[autoSel].classList.add('sel');\n            items[autoSel].scrollIntoView({ block: 'nearest' });\n        } else if (e.key === 'Enter' || e.key === 'Tab') {\n            e.preventDefault(); applyAuto(autoSel);\n        } else if (e.key === 'Escape') {\n            e.preventDefault(); closeAuto();\n        }\n    });\n}\n\n// Multi-statement result tabs\n$$('.result-tab').forEach(function (t) {\n    t.addEventListener('click', function () {\n        var i = t.getAttribute('data-rt');\n        $$('.result-tab').forEach(function (x) { x.classList.toggle('active', x === t); });\n        $$('.rt-pane').forEach(function (p) { p.classList.toggle('hidden', p.getAttribute('data-rt') !== i); });\n    });\n});\n\n// \u2500\u2500\u2500 Insert/edit: NULL checkbox disables its input \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\n$$('.null-box').forEach(function (cb) {\n    var sync = function () {\n        var field = cb.closest('.flex').querySelector('input[type=text], textarea');\n        if (!field) return;\n        field.disabled = cb.checked;\n        field.style.opacity = cb.checked ? '.45' : '';\n    };\n    cb.addEventListener('change', sync);\n    sync();\n});\n\n// \u2500\u2500\u2500 Login screen \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\nvar loginForm = $('#loginForm');\nif (loginForm) {\n    var DEFAULT_PORTS = { mysql: '3306', pgsql: '5432', sqlite: '' };\n\n    $$('.tab[data-pane]').forEach(function (tab) {\n        tab.addEventListener('click', function () {\n            var pane = tab.getAttribute('data-pane');\n            $$('.tab[data-pane]').forEach(function (t) { t.classList.toggle('active', t === tab); });\n            $('#pane-ssh').classList.toggle('on', pane === 'ssh');\n            $('#pane-saved').classList.toggle('on', pane === 'saved');\n            // The URL parser only makes sense for a direct connection - a\n            // connection string cannot describe a bastion hop.\n            var holder = $('#pane-uri-holder');\n            if (holder) holder.classList.toggle('on', pane === 'direct');\n            loginForm.classList.toggle('hidden', pane === 'saved');\n            $('#useSsh').value = pane === 'ssh' ? '1' : '0';\n\n            // Widen into two columns for the SSH pane so the card keeps fitting\n            // on screen instead of growing past the fold.\n            var card = document.querySelector('.login-card');\n            if (card) card.classList.toggle('wide', pane === 'ssh');\n            var note = $('#ssh-note');\n            if (note) note.classList.toggle('on', pane === 'ssh');\n\n            var hl = $('#hostLabel');\n            if (hl) hl.textContent = pane === 'ssh' ? 'Database host (as seen from the bastion)' : (D.i18n && D.i18n.host_label) || 'Host';\n            if (pane === 'ssh' && $('#dbHost').value === '') $('#dbHost').value = 'localhost';\n        });\n    });\n\n    var dbType = $('#dbType');\n    function syncType() {\n        var t = dbType.value;\n        var isSqlite = t === 'sqlite';\n        $('#portField').classList.toggle('hidden', isSqlite);\n        $('#credRow').classList.toggle('hidden', isSqlite);\n        $('#dbPort').placeholder = DEFAULT_PORTS[t] || '';\n        var hl = $('#hostLabel');\n        if (hl && isSqlite) hl.textContent = 'Path to the .sqlite file';\n        else if (hl && $('#useSsh').value !== '1') hl.textContent = (D.i18n && D.i18n.host_label) || 'Host';\n        if (t === 'pgsql' && $('#dbUser').value === 'root') $('#dbUser').value = 'postgres';\n        if (t === 'mysql' && $('#dbUser').value === 'postgres') $('#dbUser').value = 'root';\n    }\n    dbType.addEventListener('change', syncType);\n    syncType();\n\n    var sshAuth = $('#sshAuth');\n    if (sshAuth) {\n        var syncAuth = function () {\n            $('#sshKeyBox').classList.toggle('hidden', sshAuth.value !== 'key');\n            $('#sshPassBox').classList.toggle('hidden', sshAuth.value !== 'password');\n        };\n        sshAuth.addEventListener('change', syncAuth);\n        syncAuth();\n        $('#sshKeyMode').addEventListener('change', function () {\n            var paste = this.value === 'paste';\n            var ta = $('#sshKey');\n            ta.placeholder = paste ? '-----BEGIN OPENSSH PRIVATE KEY-----' : '/home/www-data/.ssh/id_ed25519';\n            ta.rows = paste ? 3 : 1;\n        });\n    }\n\n    var uri = $('#uriInput');\n    if (uri) uri.addEventListener('input', function () {\n        var v = this.value.trim();\n        if (!v) return;\n        try {\n            // sqlite:///path has an empty authority; treat the rest as a file path.\n            var sq = v.match(/^sqlite:\\/\\/(.*)$/i);\n            if (sq) {\n                dbType.value = 'sqlite'; syncType();\n                $('#dbHost').value = sq[1].replace(/^\\//, '/');\n                return;\n            }\n            var u = new URL(v);\n            var p = u.protocol.replace(':', '').toLowerCase();\n            if (/^postgres|^pg/.test(p)) dbType.value = 'pgsql';\n            else if (/^mysql|^mariadb/.test(p)) dbType.value = 'mysql';\n            syncType();\n            if (u.hostname) $('#dbHost').value = decodeURIComponent(u.hostname);\n            if (u.port) $('#dbPort').value = u.port;\n            if (u.username) $('#dbUser').value = decodeURIComponent(u.username);\n            if (u.password) $('#dbPass').value = decodeURIComponent(u.password);\n            var path = u.pathname.replace(/^\\//, '');\n            if (path) $('#dbName').value = decodeURIComponent(path);\n            if (/sslmode=require|ssl=true/i.test(u.search)) $('#dbSsl').checked = true;\n            toast('Connection details filled in', 'ok', 1800);\n        } catch (_) {}\n    });\n\n    // Show progress: opening a tunnel can take a few seconds.\n    loginForm.addEventListener('submit', function () {\n        var b = loginForm.querySelector('[name=login]');\n        if (!b) return;\n        var lbl = b.querySelector('.btn-label');\n        if (lbl) lbl.textContent = $('#useSsh').value === '1' ? 'Opening SSH tunnel\u2026' : 'Connecting\u2026';\n        var ic = b.querySelector('.ico use');\n        if (ic) ic.setAttribute('href', '#i-loader-circle');\n        b.querySelector('.ico').classList.add('ico-spin');\n        // Disable on the next task, not now: this button is the submitter, and a\n        // disabled control is dropped from the form data set. Disabling it here\n        // loses \"login=1\", so the server never sees a login attempt and silently\n        // re-renders the login page - an unexplained loop.\n        setTimeout(function () { b.disabled = true; }, 0);\n    });\n\n    // \u2500\u2500 Vault \u2500\u2500\n    function vaultCall(op, extra) {\n        var body = new URLSearchParams();\n        body.set('csrf_token', CSRF);\n        body.set('op', op);\n        Object.keys(extra || {}).forEach(function (k) { body.set(k, extra[k]); });\n        return fetch('?action=vault', {\n            method: 'POST', credentials: 'same-origin',\n            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },\n            body: body.toString()\n        }).then(function (r) { return r.json(); });\n    }\n    var unlockBtn = $('#vaultUnlock');\n    if (unlockBtn) {\n        var renderVault = function (profiles) {\n            var list = $('#vaultList');\n            var names = Object.keys(profiles || {});\n            if (!names.length) {\n                list.innerHTML = '<div class=\"empty\" style=\"padding:24px\"><span>No saved connections yet.</span></div>';\n                return;\n            }\n            list.innerHTML = names.map(function (n) {\n                var p = profiles[n];\n                return '<div class=\"flex\" style=\"padding:9px;border:1px solid var(--border);border-radius:var(--r-sm);margin-bottom:7px\">' +\n                    icon(p.ssh_enabled ? 'shield-check' : 'database') +\n                    '<div class=\"grow\" style=\"min-width:0\"><div style=\"font-weight:650\">' + escapeHtml(n) + '</div>' +\n                    '<div class=\"small faint ellipsis\">' + escapeHtml((p.user || '') + '@' + (p.host || '') + (p.dbname ? ' / ' + p.dbname : '')) + '</div></div>' +\n                    '<button type=\"button\" class=\"btn btn-primary btn-sm v-go\" data-n=\"' + escapeHtml(n) + '\">Connect</button>' +\n                    '<button type=\"button\" class=\"btn btn-ghost btn-icon btn-sm v-del\" data-n=\"' + escapeHtml(n) + '\">' + icon('trash-2') + '</button></div>';\n            }).join('');\n        };\n        unlockBtn.addEventListener('click', function () {\n            var m = $('#vaultMaster').value;\n            if (!m) { toast('Enter your master password.', 'error'); return; }\n            vaultCall('list', { master: m }).then(function (d) {\n                if (!d.ok) { toast(d.error || 'Could not unlock.', 'error'); return; }\n                renderVault(d.profiles);\n                toast('Unlocked', 'ok', 1500);\n            }).catch(function () { toast('Request failed', 'error'); });\n        });\n        $('#vaultList').addEventListener('click', function (e) {\n            var go = e.target.closest('.v-go'), del = e.target.closest('.v-del');\n            var m = $('#vaultMaster').value;\n            if (go) {\n                go.disabled = true; go.textContent = 'Connecting\u2026';\n                vaultCall('connect', { master: m, name: go.getAttribute('data-n') }).then(function (d) {\n                    if (d.ok) window.location.href = d.redirect;\n                    else { toast(d.error || 'Connection failed', 'error'); go.disabled = false; go.textContent = 'Connect'; }\n                });\n            } else if (del) {\n                vaultCall('delete', { master: m, name: del.getAttribute('data-n') }).then(function (d) {\n                    if (d.ok) { unlockBtn.click(); toast('Deleted', 'ok', 1500); }\n                    else toast(d.error || 'Delete failed', 'error');\n                });\n            }\n        });\n    }\n    var saveBtn = $('#saveProfileBtn');\n    if (saveBtn) saveBtn.addEventListener('click', function () {\n        var name = $('#saveName').value.trim(), master = $('#saveMaster').value;\n        if (!name || !master) { toast('A name and master password are both required.', 'error'); return; }\n        var p = {\n            type: dbType.value, host: $('#dbHost').value, port: $('#dbPort').value,\n            user: $('#dbUser').value, pass: $('#dbPass').value, dbname: $('#dbName').value,\n            ssl: $('#dbSsl').checked,\n            ssh_enabled: $('#useSsh').value === '1',\n            ssh_host: $('#sshHost') ? $('#sshHost').value : '', ssh_port: $('#sshPort') ? $('#sshPort').value : 22,\n            ssh_user: $('#sshUser') ? $('#sshUser').value : '', ssh_auth: sshAuth ? sshAuth.value : 'agent',\n            ssh_pass: $('#sshPass') ? $('#sshPass').value : '', ssh_key: $('#sshKey') ? $('#sshKey').value : '',\n            ssh_key_pass: $('#sshKeyPass') ? $('#sshKeyPass').value : '',\n            ssh_key_is_path: $('#sshKeyMode') ? $('#sshKeyMode').value === 'path' : false,\n            ssh_local_port: $('#sshLocalPort') ? $('#sshLocalPort').value : ''\n        };\n        saveBtn.disabled = true;\n        vaultCall('save', { master: master, name: name, profile: JSON.stringify(p) }).then(function (d) {\n            saveBtn.disabled = false;\n            toast(d.ok ? 'Connection saved' : (d.error || 'Save failed'), d.ok ? 'ok' : 'error');\n        }).catch(function () { saveBtn.disabled = false; toast('Save failed', 'error'); });\n    });\n}\n\n})();\n";

function ico(name, cls = '', size = null) {
    const style = size ? ` style="width:${parseInt(size, 10)}px;height:${parseInt(size, 10)}px"` : '';
    return `<svg class="ico ${h(cls)}"${style} aria-hidden="true"><use href="#i-${h(name)}"></use></svg>`;
}

/** Link builder that carries the current database/schema, like PHP's ctx_url(). */
function ctxUrl(V, params) {
    const base = { db: V.selectedDb };
    if (V.selectedSchema) base.schema = V.selectedSchema;
    return '?' + qs(Object.assign(base, params));
}

function alertBox(kind, iconName, msg, dismissible = false) {
    const parts = String(msg).split('\n\n');
    const body = `<div class="grow"><div>${h(parts[0])}</div>` +
                 (parts[1] ? `<pre>${h(parts[1])}</pre>` : '') + '</div>';
    const close = dismissible
        ? `<button type="button" class="close" onclick="this.parentElement.remove()">${ico('x')}</button>` : '';
    return `<div class="alert alert-${kind}">${ico(iconName)}${body}${close}</div>`;
}

function layoutHead(V) {
    const title = V.selectedTable || V.selectedDb || t(V.lang, 'app_name');
    return `<!DOCTYPE html>
<html lang="${h(V.lang)}" dir="${V.lang === 'ar' ? 'rtl' : 'ltr'}" data-theme="${h(V.theme)}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="light dark">
<meta name="robots" content="noindex, nofollow">
<title>${h(title)} &middot; ${h(t(V.lang, 'app_name'))}</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%232f6fed' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><ellipse cx='12' cy='5' rx='9' ry='3'/><path d='M3 5V19A9 3 0 0 0 21 19V5'/><path d='M3 12A9 3 0 0 0 21 12'/></svg>">
<style>
${STYLES}
</style>
</head>
<body>
${SPRITE}
<div id="toasts" aria-live="polite" aria-atomic="false"></div>`;
}

function layoutFoot(V) {
    const boot = {
        csrf: V.csrf,
        ctx: { db: V.selectedDb, schema: V.selectedSchema, table: V.selectedTable,
               page: V.page, loggedIn: V.loggedIn, type: V.dbType },
        cols: V.cols || [],
        ops: V.ops || [],
        i18n: { host_label: t(V.lang, 'host_label') },
    };
    return `
<script>window.__DABIRO__ = ${JSON.stringify(boot).replace(/</g, '\\u003c')};</script>
<script>
${CLIENT_JS}
</script>
</body>
</html>`;
}

// ─── Login ────────────────────────────────────────────────────────────────────
function renderLogin(V) {
    const sshWhy = SSH2 ? null : 'The ssh2 package is not installed. Run: npm install ssh2';
    const vaultWhy = Vault.available();

    return layoutHead(V) + `
<div class="login-wrap">
  <div class="login-card">
    <div class="login-head">
      <div class="mark">${ico('database')}</div>
      <h1>${h(t(V.lang, 'app_name'))}</h1>
      <p>${h(t(V.lang, 'app_tagline'))}</p>
    </div>

    ${V.loopWarning ? `<div class="alert alert-warn">${ico('triangle-alert')}
      <div class="grow"><b>You were signed in, but the session did not stick.</b>
        <div style="margin-top:4px">${h(V.loopWarning)}</div></div></div>` : ''}
    ${V.error ? alertBox('error', 'circle-alert', V.error) : ''}

    <div class="tabs" style="margin-bottom:12px" role="tablist">
      <button type="button" class="tab active hov" data-pane="direct" role="tab">${ico('plug-zap')} Direct</button>
      <button type="button" class="tab hov" data-pane="ssh" role="tab">${ico('shield-check')} SSH Tunnel</button>
      <button type="button" class="tab hov" data-pane="saved" role="tab">${ico('bookmark')} Saved</button>
    </div>

    <div class="pane" id="pane-saved">
      ${vaultWhy ? alertBox('warn', 'triangle-alert', vaultWhy) : `
        <div class="subpanel">
          <div class="t">${ico('lock')} Master password</div>
          <div class="flex">
            <input type="password" id="vaultMaster" class="input input-sm grow" placeholder="Unlocks your saved connections" autocomplete="off">
            <button type="button" class="btn btn-default btn-sm hov" id="vaultUnlock">${ico('key-round')} Unlock</button>
          </div>
          <div class="hint">Connections are encrypted with AES-256-GCM on this server. The master password is never stored &mdash; if you forget it, the saved connections cannot be recovered.</div>
        </div>
        <div id="vaultList"></div>`}
    </div>

    <div class="pane on" id="pane-uri-holder">
      <details class="subpanel" style="margin-bottom:10px">
        <summary style="cursor:pointer;font-size:11.5px;font-weight:700;display:flex;align-items:center;gap:6px">
          ${ico('link')} Paste a connection URL instead
        </summary>
        <input type="text" id="uriInput" class="input input-sm" style="margin-top:8px"
               placeholder="postgres://user:pass@host:5432/dbname?sslmode=require" autocomplete="off">
        <div class="hint">Fills in the fields below. Also accepts <code>mysql://</code> and <code>sqlite:///path/to.db</code>.</div>
      </details>
    </div>

    <form method="post" id="loginForm" autocomplete="off">
      <input type="hidden" name="csrf_token" value="${h(V.csrf)}">
      <input type="hidden" name="use_ssh" id="useSsh" value="0">

      <div class="login-grid">
      <div class="pane col-ssh" id="pane-ssh">
        ${sshWhy ? alertBox('warn', 'triangle-alert', sshWhy) : ''}
        <div class="subpanel">
          <div class="t">${ico('shield-check')} SSH bastion</div>
          <div class="row">
            <div class="field" style="flex:3"><label for="sshHost">SSH host</label>
              <input type="text" name="ssh_host" id="sshHost" class="input-sm" placeholder="127.0.0.1"></div>
            <div class="field" style="flex:1;min-width:80px"><label for="sshPort">Port</label>
              <input type="number" name="ssh_port" id="sshPort" class="input-sm" value="22"></div>
          </div>
          <div class="field"><label for="sshUser">SSH username</label>
            <input type="text" name="ssh_user" id="sshUser" class="input-sm" placeholder="ubuntu"></div>
          <div class="field"><label for="sshAuth">Authentication</label>
            <select name="ssh_auth" id="sshAuth" class="input-sm">
              <option value="agent">Use this server's SSH agent</option>
              <option value="key">Private key</option>
              <option value="password">Password</option>
            </select></div>

          <div id="sshKeyBox" class="hidden">
            <div class="field"><label for="sshKeyMode">Key source</label>
              <select name="ssh_key_mode" id="sshKeyMode" class="input-sm">
                <option value="paste">Paste the key</option>
                <option value="path">Path to a key file on this server</option>
              </select></div>
            <div class="field">
              <textarea name="ssh_key" id="sshKey" class="input-sm" rows="2"
                        placeholder="-----BEGIN OPENSSH PRIVATE KEY-----" spellcheck="false"></textarea></div>
            <div class="field"><label for="sshKeyPass">Key passphrase <span class="faint">(if the key has one)</span></label>
              <input type="password" name="ssh_key_pass" id="sshKeyPass" class="input-sm" autocomplete="new-password"></div>
          </div>

          <div id="sshPassBox" class="field hidden">
            <label for="sshPass">SSH password</label>
            <input type="password" name="ssh_pass" id="sshPass" class="input-sm" autocomplete="new-password">
            <div class="hint">Handled natively by the ssh2 client &mdash; nothing extra to install on either end.</div>
          </div>

          <div class="field" style="margin-bottom:0">
            <label for="sshLocalPort">Local port <span class="faint">(optional)</span></label>
            <input type="number" name="ssh_local_port" id="sshLocalPort" class="input-sm" placeholder="auto">
            <div class="hint">Leave blank to pick a free port automatically.</div>
          </div>
        </div>
      </div>

      <div class="col-db">
      <div class="field">
        <label for="dbType">${h(t(V.lang, 'database_type_label'))}</label>
        <select name="db_type" id="dbType">
          <option value="mysql">MySQL / MariaDB</option>
          <option value="pgsql">PostgreSQL</option>
          <option value="sqlite">SQLite</option>
        </select>
      </div>

      <div class="row">
        <div class="field" style="flex:3"><label for="dbHost" id="hostLabel">${h(t(V.lang, 'host_label'))}</label>
          <input type="text" name="db_host" id="dbHost" value="localhost" required></div>
        <div class="field" style="flex:1;min-width:84px" id="portField"><label for="dbPort">${h(t(V.lang, 'port_label'))}</label>
          <input type="number" name="db_port" id="dbPort" placeholder="3306"></div>
      </div>

      <div class="row" id="credRow">
        <div class="field"><label for="dbUser">${h(t(V.lang, 'username_label'))}</label>
          <input type="text" name="db_user" id="dbUser" value="root" autocomplete="username"></div>
        <div class="field"><label for="dbPass">${h(t(V.lang, 'password_label'))}</label>
          <input type="password" name="db_pass" id="dbPass" autocomplete="current-password"></div>
      </div>

      <div class="row" style="align-items:flex-end">
        <div class="field" style="flex:2"><label for="dbName">${h(t(V.lang, 'database_name_label'))} <span class="faint">(optional)</span></label>
          <input type="text" name="db_name" id="dbName" placeholder="Leave blank to browse all"></div>
        <div class="field" style="flex:1;min-width:120px">
          <label class="toggle-box" for="dbSsl">
            <span class="flex" style="gap:6px;font-size:12px;font-weight:600">${ico('lock')} SSL</span>
            <span class="switch"><input type="checkbox" name="db_ssl" id="dbSsl" value="1"><span class="track"></span></span>
          </label></div>
      </div>

      <div class="pane" id="ssh-note">
        <div class="alert alert-info" style="font-size:11.5px;margin-top:4px">${ico('info')}
          <div>These are resolved <b>from the bastion</b> &mdash; the same as
          <code>ssh -L &lt;local&gt;:&lt;db host&gt;:&lt;db port&gt; user@bastion</code>. If the database runs on the
          SSH server itself, enter <code>localhost</code>.</div></div>
      </div>
      </div><!-- /.col-db -->
      </div><!-- /.login-grid -->

      <button type="submit" name="login" value="1" class="btn btn-primary btn-block hov" style="margin-top:6px;padding:9px">
        <span class="btn-label">${h(t(V.lang, 'connect_button'))}</span>${ico('arrow-right')}
      </button>

      ${vaultWhy ? '' : `
      <details class="subpanel" style="margin-top:12px;margin-bottom:0">
        <summary style="cursor:pointer;font-size:11.5px;font-weight:700;display:flex;align-items:center;gap:6px">
          ${ico('save')} Save these settings for next time</summary>
        <div class="row" style="margin-top:8px">
          <input type="text" id="saveName" class="input-sm" placeholder="Name, e.g. Production">
          <input type="password" id="saveMaster" class="input-sm" placeholder="Master password" autocomplete="new-password">
        </div>
        <button type="button" class="btn btn-default btn-sm hov" id="saveProfileBtn" style="margin-top:8px">
          ${ico('save')} Save connection</button>
        <div class="hint">Stored encrypted at <code>${h(DATA_DIR)}</code>. Keep that path outside any web root.</div>
      </details>`}
    </form>
  </div>
</div>` + layoutFoot(V);
}

// ─── Authenticated shell ──────────────────────────────────────────────────────
function renderShell(V, body) {
    const engineLabel = { mysql: 'MySQL', pgsql: 'PostgreSQL', sqlite: 'SQLite' }[V.dbType] || V.dbType;
    const macish = /Mac/.test(V.ua || '');

    const schemaNav = V.navSchemas.length ? `
      <div class="nav-group">
        <div class="nav-head">${ico('git-branch')} ${h(t(V.lang, 'schemas'))}</div>
        ${V.navSchemas.map((s) => `
          <a href="?${qs({ page: 'tables', db: V.selectedDb, schema: s })}"
             class="tree-item hov ${s === V.selectedSchema ? 'active' : ''}">
            ${ico('git-branch')}<span class="lbl">${h(s)}</span></a>`).join('')}
      </div>` : '';

    const tableNav = V.selectedDb ? `
      <div class="nav-group nav-group-tables">
        <div class="nav-head">${ico('table-2')}
          <span class="ellipsis">${h(V.selectedDb)}</span>
          <span class="right" id="tblCount">${V.navTables.length}</span></div>
        ${V.navTables.length > 7 ? `<div style="padding:2px 4px 6px">
          <input type="search" id="tblFilter" class="input-sm" placeholder="Filter tables&hellip;" aria-label="Filter tables"></div>` : ''}
        <div id="tblList">
          ${V.navTables.map((tb) => `
            <a href="${h(ctxUrl(V, { page: 'browse', table: tb }))}"
               class="tree-item hov ${tb === V.selectedTable ? 'active' : ''}"
               data-name="${h(tb)}" title="${h(tb)}">
              ${ico('table', 'ico-table')}<span class="lbl">${h(tb)}</span></a>`).join('')}
        </div>
        ${V.navTables.length ? '' : '<div class="tree-item faint" style="cursor:default">No tables</div>'}
      </div>` : '';

    return layoutHead(V) + `
<div class="scrim" id="scrim"></div>
<div class="app">
  <aside class="sidebar" id="sidebar">
    <div class="brand hov">${ico('database', 'ico-lg ico-database')}
      <span>${h(t(V.lang, 'app_name'))}</span>
      <span class="brand-tag">${h(engineLabel)}</span></div>

    <nav class="side-scroll">
      <a href="?page=databases" class="nav-link hov ${V.page === 'databases' ? 'active' : ''}">
        ${ico('server', 'ico-server')} <span>${h(t(V.lang, 'databases'))}</span></a>
      <a href="${h(ctxUrl(V, { page: 'sql' }))}" class="nav-link hov ${V.page === 'sql' ? 'active' : ''}">
        ${ico('terminal', 'ico-terminal')} <span>${h(t(V.lang, 'sql_console'))}</span></a>
      <a href="${h(ctxUrl(V, { page: 'search' }))}" class="nav-link hov ${V.page === 'search' ? 'active' : ''}">
        ${ico('scan-search', 'ico-search')} <span>${h(t(V.lang, 'global_search'))}</span></a>
      <a href="${h(ctxUrl(V, { page: 'import' }))}" class="nav-link hov ${V.page === 'import' ? 'active' : ''}">
        ${ico('import', 'ico-import')} <span>${h(t(V.lang, 'import_data'))}</span></a>
      <a href="${h(ctxUrl(V, { page: 'export' }))}" class="nav-link hov ${V.page === 'export' ? 'active' : ''}">
        ${ico('download', 'ico-download')} <span>${h(t(V.lang, 'export_data'))}</span></a>
      ${schemaNav}${tableNav}
    </nav>

    <div class="side-foot">
      <div class="conn-chip" id="connChip" title="${h(V.connLabel)}">
        <span class="dot" id="connDot"></span>
        <span class="txt grow">${h(V.connLabel)}</span>
        ${V.hasSsh ? `<span title="Tunnelled over SSH">${ico('shield-check')}</span>` : ''}
      </div>
      <form method="post">
        <input type="hidden" name="csrf_token" value="${h(V.csrf)}">
        <button type="submit" name="logout" value="1" class="btn btn-default btn-sm btn-block hov">
          ${ico('log-out', 'ico-log-out')} ${h(t(V.lang, 'logout'))}</button>
      </form>
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <button type="button" class="btn btn-ghost btn-icon sidebar-toggle hov" id="navToggle" aria-label="Toggle navigation">${ico('panel-left')}</button>
      <nav class="crumbs" aria-label="Breadcrumb">
        <a href="?page=databases" class="hov">${ico('server')} <span class="hide-sm">${h(t(V.lang, 'databases'))}</span></a>
        ${V.selectedDb ? `<span class="sep">/</span>
          <a href="?${qs({ page: 'tables', db: V.selectedDb, schema: V.selectedSchema })}">${h(V.selectedDb)}</a>` : ''}
        ${V.dbType === 'pgsql' && V.selectedSchema ? `<span class="sep">/</span><span class="badge">${h(V.selectedSchema)}</span>` : ''}
        ${V.selectedTable ? `<span class="sep">/</span><strong>${h(V.selectedTable)}</strong>` : ''}
      </nav>
      <div class="topbar-right">
        <button type="button" class="btn btn-default btn-sm hov" id="palBtn" title="Command palette">
          ${ico('command')}<span class="hide-sm">Search</span>
          <kbd class="hide-sm">${macish ? '&#8984;K' : 'Ctrl K'}</kbd></button>
        <select id="themeSel" class="input-sm hide-sm" style="width:auto" aria-label="Theme">
          ${Object.entries(THEMES).map(([k, v]) =>
            `<option value="${h(k)}" ${V.theme === k ? 'selected' : ''}>${h(v)}</option>`).join('')}
        </select>
        <select id="langSel" class="input-sm hide-sm" style="width:auto" aria-label="Language">
          ${Object.entries(LANGS).map(([k, v]) =>
            `<option value="${h(k)}" ${V.lang === k ? 'selected' : ''}>${h(v)}</option>`).join('')}
        </select>
      </div>
    </header>

    <main class="content">
      ${V.connError ? alertBox('error', 'circle-alert', 'Connection lost. ' + V.connError) : ''}
      ${V.success ? alertBox('ok', 'circle-check', V.success, true) : ''}
      ${V.error ? alertBox('error', 'circle-alert', V.error, true) : ''}
      ${body}
    </main>
  </div>
</div>

<div id="palette" role="dialog" aria-modal="true" aria-label="Command palette">
  <div class="pal-box">
    <div class="pal-input-wrap">${ico('search', 'ico-lg')}
      <input type="text" id="palInput" placeholder="Jump to a database, schema, table or action&hellip;" autocomplete="off" spellcheck="false"></div>
    <div class="pal-list" id="palList"></div>
    <div class="pal-foot"><span><kbd>&uarr;</kbd><kbd>&darr;</kbd> navigate</span><span><kbd>&#9166;</kbd> open</span><span><kbd>esc</kbd> close</span></div>
  </div>
</div>` + layoutFoot(V);
}

function emptyState(iconName, title, sub, extra = '') {
    return `<div class="empty">${ico(iconName, '', 34)}<p>${h(title)}</p><span>${h(sub)}</span>${extra}</div>`;
}

// ─── Pages ────────────────────────────────────────────────────────────────────
const BROWSE_OPS = ['=', '!=', '>', '<', '>=', '<=', 'LIKE %%', 'STARTS', 'ENDS', 'NOT LIKE', 'IN', 'IS NULL', 'IS NOT NULL'];

async function pageDatabases(V, db) {
    const stats = await db.getDatabasesWithStats();
    const list = Object.values(stats);
    const totSize = list.reduce((a, s) => a + Number(s.size || 0), 0);
    const version = await db.serverVersion();
    const engineLabel = { mysql: 'MySQL', pgsql: 'PostgreSQL', sqlite: 'SQLite' }[db.getType()] || db.getType();

    const rows = list.map((s) => {
        let tcell;
        if (s.tables !== null && s.tables !== undefined) tcell = `<span class="badge">${formatNum(s.tables)}</span>`;
        else if (s.lazy_count) tcell = `<span class="badge skeleton js-tblcount" data-db="${h(s.name)}">00</span>`;
        else tcell = '<span class="faint">&mdash;</span>';
        return `<tr>
          <td><a href="?${qs({ page: 'tables', db: s.name })}" class="flex hov" style="gap:7px;font-weight:600">
            ${ico('database', 'ico-database')}${h(s.name)}</a></td>
          <td class="num">${tcell}</td>
          <td class="num"><b>${formatBytes(s.size)}</b></td>
          <td class="num hide-sm muted">${s.data_size === null ? '&mdash;' : formatBytes(s.data_size)}</td>
          <td class="num hide-sm muted">${s.index_size === null ? '&mdash;' : formatBytes(s.index_size)}</td>
          <td class="acts">
            <a href="?${qs({ page: 'tables', db: s.name })}" class="btn btn-default btn-sm hov">${ico('table-2')} ${h(t(V.lang, 'tables'))}</a>
            <a href="?${qs({ page: 'sql', db: s.name })}" class="btn btn-default btn-sm hov">${ico('terminal')} SQL</a>
            <a href="?${qs({ page: 'export', db: s.name })}" class="btn btn-default btn-sm hov">${ico('download')}</a>
          </td></tr>`;
    }).join('');

    return `
  <div class="page-head">
    <div><h2>${h(t(V.lang, 'databases'))}</h2>
      <div class="sub">${list.length} databases &middot; ${formatBytes(totSize)}${version ? ` &middot; ${h(engineLabel + ' ' + version)}` : ''}</div></div>
    <div class="acts">${db.getType() !== 'sqlite'
      ? `<button class="btn btn-primary hov" data-modal="mCreateDb">${ico('plus')} ${h(t(V.lang, 'create_database'))}</button>` : ''}</div>
  </div>
  <div class="card"><div class="tbl-wrap"><table class="tbl">
    <thead><tr><th>${h(t(V.lang, 'database_name_label'))}</th><th class="num">${h(t(V.lang, 'tables'))}</th>
      <th class="num">${h(t(V.lang, 'total_size'))}</th><th class="num hide-sm">${h(t(V.lang, 'data_size'))}</th>
      <th class="num hide-sm">${h(t(V.lang, 'index_size'))}</th><th class="acts">${h(t(V.lang, 'actions'))}</th></tr></thead>
    <tbody>${rows || `<tr><td colspan="6">${emptyState('server', 'No databases visible', 'This account may not have permission to list databases.')}</td></tr>`}</tbody>
  </table></div></div>

  <div class="modal" id="mCreateDb"><div class="modal-box"><form method="post">
    <input type="hidden" name="csrf_token" value="${h(V.csrf)}">
    <div class="modal-head"><h3>${h(t(V.lang, 'create_database'))}</h3>
      <button type="button" class="btn btn-ghost btn-icon close-modal">${ico('x')}</button></div>
    <div class="modal-body"><div class="field"><label for="newDbName">${h(t(V.lang, 'database_name_label'))}</label>
      <input type="text" name="new_db_name" id="newDbName" required placeholder="my_database" pattern="[A-Za-z0-9_$-]+">
      <div class="hint">Letters, digits, underscore and hyphen.</div></div></div>
    <div class="modal-foot"><button type="button" class="btn btn-default close-modal">${h(t(V.lang, 'cancel'))}</button>
      <button type="submit" name="create_database" value="1" class="btn btn-primary hov">${ico('plus')} ${h(t(V.lang, 'create_database'))}</button></div>
  </form></div></div>`;
}

async function pageTables(V, db) {
    const ts = await db.getTablesWithStats();
    const sum = { rows: 0, data: 0, idx: 0, total: 0, free: 0 };
    let estimated = false;
    for (const x of ts) {
        sum.rows += Number(x.Rows || 0);
        sum.data += Number(x.Data_length || 0);
        sum.idx += Number(x.Index_length || 0);
        sum.total += Number(x.Total_length || 0);
        sum.free += Number(x.Data_free || 0);
        if (!x.Rows_exact) estimated = true;
    }

    const body = ts.map((x) => `<tr>
      <td class="pick"><input type="checkbox" name="selected" value="${h(x.Name)}" class="sel-tbl"></td>
      <td><a href="${h(ctxUrl(V, { page: 'browse', table: x.Name }))}" class="flex hov" style="gap:7px;font-weight:600">
        ${ico(x.Is_view ? 'eye' : 'table', 'ico-table')}${h(x.Name)}</a></td>
      <td class="hide-sm"><span class="badge">${h(x.Engine || '—')}</span></td>
      <td class="num">${x.Rows === null ? '<span class="faint">&mdash;</span>'
        : (x.Rows_exact ? '' : '<span class="approx">~</span>') + formatNum(x.Rows)}</td>
      <td class="num hide-sm muted">${formatBytes(x.Data_length)}</td>
      <td class="num hide-sm muted">${formatBytes(x.Index_length)}</td>
      <td class="num"><b>${formatBytes(x.Total_length)}</b></td>
      <td class="num hide-sm">${Number(x.Data_free) > 0
        ? `<span class="badge badge-warn">${formatBytes(x.Data_free)}</span>` : '<span class="faint">&mdash;</span>'}</td>
      <td class="acts">
        <a href="${h(ctxUrl(V, { page: 'browse', table: x.Name }))}" class="btn btn-default btn-sm hov">${ico('eye')} ${h(t(V.lang, 'browse'))}</a>
        <a href="${h(ctxUrl(V, { page: 'structure', table: x.Name }))}" class="btn btn-default btn-sm hov">${ico('columns-3')}</a>
        <a href="${h(ctxUrl(V, { page: 'operations', table: x.Name }))}" class="btn btn-default btn-sm hov">${ico('settings', 'ico-settings')}</a>
      </td></tr>`).join('');

    const schemaPicker = db.getType() === 'pgsql' ? `
      <select class="input-sm" style="width:auto" onchange="location.href=this.value" aria-label="Schema">
        ${V.navSchemas.map((s) => `<option value="?${qs({ page: 'tables', db: V.selectedDb, schema: s })}" ${s === V.selectedSchema ? 'selected' : ''}>${h(s)}</option>`).join('')}
      </select>
      <button class="btn btn-default hov" data-modal="mCreateSchema">${ico('git-branch')} New schema</button>` : '';

    return `
  <div class="page-head">
    <div><h2>${h(V.selectedDb)}${db.getType() === 'pgsql' ? `<span class="faint" style="font-weight:400">.${h(V.selectedSchema)}</span>` : ''}</h2>
      <div class="sub">${ts.length} tables &middot; ${estimated ? '~' : ''}${formatNum(sum.rows)} rows &middot; ${formatBytes(sum.total)}</div></div>
    <div class="acts">${schemaPicker}
      <button class="btn btn-primary hov" data-modal="mCreateTable">${ico('plus')} ${h(t(V.lang, 'create_table'))}</button></div>
  </div>

  <form method="post" id="tablesForm">
    <input type="hidden" name="csrf_token" value="${h(V.csrf)}">
    <div class="card"><div class="tbl-wrap"><table class="tbl">
      <thead><tr><th class="pick"><input type="checkbox" id="selAll" aria-label="Select all"></th>
        <th>${h(t(V.lang, 'table_name'))}</th><th class="hide-sm">${h(t(V.lang, 'engine'))}</th>
        <th class="num">${h(t(V.lang, 'records'))}</th><th class="num hide-sm">${h(t(V.lang, 'data_size'))}</th>
        <th class="num hide-sm">${h(t(V.lang, 'index_size'))}</th><th class="num">${h(t(V.lang, 'total_size'))}</th>
        <th class="num hide-sm">${h(t(V.lang, 'overhead'))}</th><th class="acts">${h(t(V.lang, 'actions'))}</th></tr></thead>
      <tbody>${body || `<tr><td colspan="9">${emptyState('table-2', 'No tables yet', 'Create one, or run some SQL to get started.')}</td></tr>`}</tbody>
      ${ts.length ? `<tfoot><tr><td></td><td>${ts.length} tables</td><td class="hide-sm"></td>
        <td class="num">${estimated ? '~' : ''}${formatNum(sum.rows)}</td>
        <td class="num hide-sm">${formatBytes(sum.data)}</td><td class="num hide-sm">${formatBytes(sum.idx)}</td>
        <td class="num">${formatBytes(sum.total)}</td><td class="num hide-sm">${formatBytes(sum.free)}</td><td></td></tr></tfoot>` : ''}
    </table></div>
    ${ts.length ? `<div class="flex flex-wrap" style="padding:10px 14px;background:var(--surface-2);border-top:1px solid var(--border)">
      <span class="small muted" id="selCount">None selected</span>
      <span class="right flex">
        <button type="submit" name="bulk_action" value="optimize" class="btn btn-default btn-sm hov" data-confirm="Optimise the selected tables?">${ico('wrench')} Optimise</button>
        <button type="submit" name="bulk_action" value="truncate" class="btn btn-default btn-sm hov" data-confirm="Delete ALL rows from the selected tables? This cannot be undone.">${ico('circle-x')} ${h(t(V.lang, 'truncate_selected'))}</button>
        <button type="submit" name="bulk_action" value="drop" class="btn btn-danger-soft btn-sm hov" data-confirm="Permanently DROP the selected tables? This cannot be undone.">${ico('trash-2', 'ico-trash-2')} ${h(t(V.lang, 'drop_selected'))}</button>
      </span></div>` : ''}
    </div>
  </form>

  <div class="modal" id="mCreateTable"><div class="modal-box wide"><form method="post" id="createTableForm">
    <input type="hidden" name="csrf_token" value="${h(V.csrf)}">
    <input type="hidden" name="create_table_sql" id="createTableSql">
    <div class="modal-head"><h3>${h(t(V.lang, 'create_table'))}</h3>
      <button type="button" class="btn btn-ghost btn-icon close-modal">${ico('x')}</button></div>
    <div class="modal-body">
      <div class="field"><label for="ctName">${h(t(V.lang, 'table_name'))}</label>
        <input type="text" id="ctName" required placeholder="users" pattern="[A-Za-z0-9_$-]+"></div>
      <div class="flex" style="margin:14px 0 8px"><strong style="font-size:12.5px">${h(t(V.lang, 'columns'))}</strong>
        <button type="button" class="btn btn-default btn-sm right hov" id="ctAdd">${ico('plus')} ${h(t(V.lang, 'add_column'))}</button></div>
      <div id="ctCols"></div>
      <details style="margin-top:12px"><summary class="small muted" style="cursor:pointer">Preview SQL</summary>
        <pre id="ctPreview" class="mono small" style="margin-top:8px;padding:10px;background:var(--surface-2);border-radius:var(--r-sm);white-space:pre-wrap"></pre></details>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-default close-modal">${h(t(V.lang, 'cancel'))}</button>
      <button type="submit" name="create_table" value="1" class="btn btn-primary hov">${ico('plus')} ${h(t(V.lang, 'create_table'))}</button></div>
  </form></div></div>

  ${db.getType() === 'pgsql' ? `<div class="modal" id="mCreateSchema"><div class="modal-box"><form method="post">
    <input type="hidden" name="csrf_token" value="${h(V.csrf)}">
    <div class="modal-head"><h3>Create schema</h3><button type="button" class="btn btn-ghost btn-icon close-modal">${ico('x')}</button></div>
    <div class="modal-body"><div class="field"><label for="nsName">Schema name</label>
      <input type="text" name="new_schema_name" id="nsName" required placeholder="analytics" pattern="[A-Za-z0-9_$-]+"></div></div>
    <div class="modal-foot"><button type="button" class="btn btn-default close-modal">${h(t(V.lang, 'cancel'))}</button>
      <button type="submit" name="create_schema" value="1" class="btn btn-primary hov">${ico('plus')} Create</button></div>
  </form></div></div>` : ''}`;
}

function buildWhere(db, query, cols, colNames) {
    const clauses = [], params = [];
    // where[i][col] arrives as an object map from express' extended parser.
    const raw = query.where && typeof query.where === 'object' ? Object.values(query.where) : [];
    for (const w of raw) {
        if (!w || typeof w !== 'object') continue;
        const c = w.col || '', op = w.op || '=', v = w.val === undefined ? '' : String(w.val);
        if (!c || !colNames.includes(c) || !BROWSE_OPS.includes(op)) continue;
        const q = db.quoteId(c);
        if (op === 'IS NULL') clauses.push(`${q} IS NULL`);
        else if (op === 'IS NOT NULL') clauses.push(`${q} IS NOT NULL`);
        else if (op === 'LIKE %%') { clauses.push(`${q} LIKE ?`); params.push(`%${v}%`); }
        else if (op === 'STARTS') { clauses.push(`${q} LIKE ?`); params.push(`${v}%`); }
        else if (op === 'ENDS') { clauses.push(`${q} LIKE ?`); params.push(`%${v}`); }
        else if (op === 'NOT LIKE') { clauses.push(`${q} NOT LIKE ?`); params.push(`%${v}%`); }
        else if (op === 'IN') {
            const parts = v.split(',').map((s) => s.trim()).filter(Boolean);
            if (parts.length) {
                clauses.push(`${q} IN (${parts.map(() => '?').join(',')})`);
                params.push(...parts);
            }
        } else { clauses.push(`${q} ${op} ?`); params.push(v); }
    }

    const simpleQ = String(query.search || '').trim();
    const simpleF = String(query.search_field || '');
    if (simpleQ && !clauses.length) {
        if (colNames.includes(simpleF)) {
            clauses.push(`${db.quoteId(simpleF)} LIKE ?`); params.push(`%${simpleQ}%`);
        } else {
            const or = [];
            for (const c of cols) {
                if (/char|text|varchar|enum|json|uuid|citext/i.test(String(c.Type || ''))) {
                    or.push(`${db.quoteId(c.Field)} LIKE ?`); params.push(`%${simpleQ}%`);
                }
            }
            if (or.length) clauses.push('(' + or.join(' OR ') + ')');
        }
    }
    return { where: clauses.length ? ' WHERE ' + clauses.join(' AND ') : '', params, count: clauses.length, raw };
}

async function pageBrowse(V, db, query) {
    const table = V.selectedTable;
    const cols = await db.getColumns(table);
    const colNames = cols.map((c) => c.Field);
    V.cols = colNames;
    V.ops = BROWSE_OPS;

    const pk = await db.getPrimaryKey(table);
    const limit = Math.max(1, Math.min(1000, parseInt(query.limit, 10) || 50));
    const sortCol = colNames.includes(query.sort) ? query.sort : '';
    const sortDir = String(query.dir || 'ASC').toUpperCase() === 'DESC' ? 'DESC' : 'ASC';

    const f = buildWhere(db, query, cols, colNames);
    const orderSql = sortCol ? ` ORDER BY ${db.quoteId(sortCol)} ${sortDir}` : '';

    const cnt = await db.getRowCountInfo(table, f.where, f.params);
    const pages = Math.max(1, Math.ceil(cnt.n / limit));
    const curP = Math.min(Math.max(1, parseInt(query.p, 10) || 1), pages);
    const offset = (curP - 1) * limit;

    let rows = [], err = null;
    try {
        rows = await db.all(`SELECT * FROM ${db.qualify(table)}${f.where}${orderSql} LIMIT ${limit} OFFSET ${offset}`, f.params);
    } catch (e) { err = e.message; }

    const head = cols.map((c) => {
        const nd = (sortCol === c.Field && sortDir === 'ASC') ? 'DESC' : 'ASC';
        return `<th><a href="${h(ctxUrl(V, { page: 'browse', table, sort: c.Field, dir: nd, limit }))}">
          ${h(c.Field)}${sortCol === c.Field ? ico(sortDir === 'ASC' ? 'arrow-up' : 'arrow-down') : ''}
          ${pk.includes(c.Field) ? ico('key-round') : ''}</a></th>`;
    }).join('');

    const bodyRows = rows.map((row) => {
        let keys = {};
        if (pk.length) for (const k of pk) if (k in row) keys[k] = row[k];
        if (!Object.keys(keys).length) keys = row;
        const kq = {};
        for (const [k, v] of Object.entries(keys)) kq['where_' + k] = v;
        const editUrl = ctxUrl(V, Object.assign({ page: 'edit', table }, kq));
        const delUrl = ctxUrl(V, Object.assign({ action: 'delete', table, csrf_token: V.csrf, page: 'browse' }, kq));

        const tds = cols.map((c) => {
            const v = row[c.Field];
            const numeric = typeof v === 'number';
            let inner;
            if (v === null || v === undefined) inner = '<span class="badge badge-null">NULL</span>';
            else {
                const tr = truncateCell(String(v));
                inner = tr.truncated
                    ? `<span title="${h(String(v).slice(0, 2000))}">${h(tr.text)}<span class="faint">&hellip;</span></span>`
                    : h(tr.text);
            }
            return `<td class="cell${numeric ? ' cell-num' : ''}" data-col="${h(c.Field)}">${inner}</td>`;
        }).join('');

        return `<tr data-keys="${h(JSON.stringify(keys))}">
          <td class="pick" style="white-space:nowrap">
            <a href="${h(editUrl)}" class="btn btn-ghost btn-icon btn-sm hov" title="Edit row">${ico('square-pen')}</a>
            <a href="${h(delUrl)}" class="btn btn-ghost btn-icon btn-sm hov" data-confirm="Delete this row? This cannot be undone." title="Delete row">${ico('trash-2', 'ico-trash-2')}</a>
          </td>${tds}</tr>`;
    }).join('');

    const filterRows = f.raw.map((w, i) => `
      <div class="row filter-row" style="margin-bottom:8px;align-items:center">
        <select name="where[${i}][col]" class="input-sm" style="flex:2">
          ${colNames.map((c) => `<option value="${h(c)}" ${w.col === c ? 'selected' : ''}>${h(c)}</option>`).join('')}</select>
        <select name="where[${i}][op]" class="input-sm" style="flex:1.4">
          ${BROWSE_OPS.map((o) => `<option value="${h(o)}" ${w.op === o ? 'selected' : ''}>${h(o)}</option>`).join('')}</select>
        <input type="text" name="where[${i}][val]" class="input-sm" style="flex:2" value="${h(w.val || '')}" placeholder="value">
        <button type="button" class="btn btn-ghost btn-icon rm-filter" style="flex:0">${ico('x')}</button>
      </div>`).join('');

    const pg = (p) => h(ctxUrl(V, { page: 'browse', table, p, sort: sortCol, dir: sortDir, limit }));

    return `
  ${err ? alertBox('error', 'circle-alert', err) : ''}
  <div class="page-head">
    <div><h2>${h(table)}</h2>
      <div class="sub">${cnt.exact ? '' : '~'}${formatNum(cnt.n)} rows &middot; page ${curP} of ${formatNum(pages)}
        ${pk.length ? '' : `&middot; <span class="badge badge-warn" title="Without a primary key, Dabiro matches rows on all their column values and inline editing is disabled.">${ico('triangle-alert')} no primary key</span>`}</div></div>
    <div class="acts">
      <a href="${h(ctxUrl(V, { page: 'insert', table }))}" class="btn btn-primary hov">${ico('plus')} ${h(t(V.lang, 'insert_record'))}</a>
      <button class="btn btn-default hov" id="filterToggle">${ico('funnel')} ${h(t(V.lang, 'filter'))}${f.count ? ` <span class="badge badge-accent">${f.count}</span>` : ''}</button>
      <a href="${h(ctxUrl(V, { page: 'structure', table }))}" class="btn btn-default hov">${ico('columns-3')} ${h(t(V.lang, 'structure'))}</a>
      <a href="${h(ctxUrl(V, { page: 'operations', table }))}" class="btn btn-default hov">${ico('settings', 'ico-settings')}</a>
    </div>
  </div>

  <div class="card ${f.raw.length ? '' : 'hidden'}" id="filterBox">
    <div class="card-head"><h3>${ico('funnel')} ${h(t(V.lang, 'filter'))}</h3></div>
    <form method="get" class="card-body">
      <input type="hidden" name="page" value="browse">
      <input type="hidden" name="db" value="${h(V.selectedDb)}">
      <input type="hidden" name="schema" value="${h(V.selectedSchema)}">
      <input type="hidden" name="table" value="${h(table)}">
      <input type="hidden" name="limit" value="${limit}">
      <div id="filterRows">${filterRows}</div>
      <div class="flex" style="margin-top:8px">
        <button type="button" class="btn btn-default btn-sm hov" id="addFilter">${ico('plus')} ${h(t(V.lang, 'add_condition'))}</button>
        <span class="right flex">
          <a href="${h(ctxUrl(V, { page: 'browse', table }))}" class="btn btn-ghost btn-sm">${h(t(V.lang, 'clear'))}</a>
          <button type="submit" class="btn btn-primary btn-sm hov">${ico('funnel')} Apply</button></span>
      </div>
    </form>
  </div>

  <div class="card">
    <div class="tbl-wrap"><table class="tbl" id="dataGrid" data-table="${h(table)}" data-haspk="${pk.length ? '1' : '0'}">
      <thead><tr><th class="pick" style="width:76px">${h(t(V.lang, 'actions'))}</th>${head}</tr></thead>
      <tbody>${bodyRows || `<tr><td colspan="${cols.length + 1}">${emptyState('scan-search',
        f.count ? 'No rows match the filter' : 'This table is empty',
        f.count ? 'Try relaxing a condition.' : 'Insert a row to get started.')}</td></tr>`}</tbody>
    </table></div>
    <div class="flex flex-wrap" style="padding:10px 14px;background:var(--surface-2);border-top:1px solid var(--border);gap:12px">
      <label class="flex small muted" style="gap:6px">${h(t(V.lang, 'rows_per_page'))}
        <select class="input-sm" style="width:auto" onchange="location.href=this.value">
          ${[25, 50, 100, 250, 500].map((l) => `<option value="${h(ctxUrl(V, { page: 'browse', table, limit: l, sort: sortCol, dir: sortDir }))}" ${l === limit ? 'selected' : ''}>${l}</option>`).join('')}
        </select></label>
      ${pk.length ? `<span class="small faint hide-sm">${ico('info')} Double-click a cell to edit it inline</span>` : ''}
      <span class="right flex" style="gap:4px">
        <a class="btn btn-default btn-sm hov" ${curP <= 1 ? 'aria-disabled="true"' : `href="${pg(1)}"`}>${ico('chevrons-left')}</a>
        <a class="btn btn-default btn-sm hov" ${curP <= 1 ? 'aria-disabled="true"' : `href="${pg(curP - 1)}"`}>${ico('chevron-left')}</a>
        <span class="small mono" style="padding:0 8px">${formatNum(curP)} / ${formatNum(pages)}</span>
        <a class="btn btn-default btn-sm hov" ${curP >= pages ? 'aria-disabled="true"' : `href="${pg(curP + 1)}"`}>${ico('chevron-right')}</a>
        <a class="btn btn-default btn-sm hov" ${curP >= pages ? 'aria-disabled="true"' : `href="${pg(pages)}"`}>${ico('chevrons-right')}</a>
      </span>
    </div>
  </div>`;
}

const TYPE_LIST = ['INT', 'BIGINT', 'SMALLINT', 'DECIMAL', 'NUMERIC', 'FLOAT', 'DOUBLE', 'VARCHAR', 'CHAR', 'TEXT',
    'LONGTEXT', 'DATE', 'DATETIME', 'TIMESTAMP', 'TIME', 'BOOLEAN', 'JSON', 'JSONB', 'UUID', 'BLOB', 'BYTEA'];

async function pageStructure(V, db) {
    const table = V.selectedTable;
    const cols = await db.getColumns(table);
    const idxs = await db.getIndexes(table);
    const fks = await db.getForeignKeys(table, V.selectedDb);
    const create = await db.getCreateTable(table);

    const colRows = cols.map((c, i) => `<tr>
      <td class="faint">${i + 1}</td>
      <td><b>${h(c.Field)}</b>${c.Comment ? `<div class="small faint">${h(c.Comment)}</div>` : ''}</td>
      <td><span class="badge badge-accent mono">${h(c.Type)}</span></td>
      <td>${c.Null === 'YES' ? '<span class="badge">NULL</span>' : '<span class="badge badge-warn">NOT NULL</span>'}</td>
      <td class="mono small">${c.Default === null || c.Default === undefined ? '<span class="faint">&mdash;</span>' : h(c.Default)}</td>
      <td>${c.Key === 'PRI' ? `<span class="badge badge-accent">${ico('key-round')} PK</span>` : (c.Key ? `<span class="badge">${h(c.Key)}</span>` : '')}</td>
      <td class="hide-sm small faint">${h(c.Extra || '')}</td>
      <td class="acts">
        <button type="button" class="btn btn-ghost btn-icon btn-sm hov edit-col"
          data-name="${h(c.Field)}" data-type="${h(c.Type)}" data-null="${c.Null === 'YES' ? '1' : '0'}"
          data-default="${h(c.Default || '')}" title="Edit column">${ico('square-pen')}</button>
        <a href="${h(ctxUrl(V, { action: 'drop_column', page: 'structure', table, col: c.Field, csrf_token: V.csrf }))}"
           class="btn btn-ghost btn-icon btn-sm hov" data-confirm="Drop column &quot;${h(c.Field)}&quot;? All data in it is lost." title="Drop column">${ico('trash-2', 'ico-trash-2')}</a>
      </td></tr>`).join('');

    const idxRows = idxs.map((ix) => `<tr>
      <td class="mono">${h(ix.name)}</td>
      <td>${ix.columns.map((c) => `<span class="badge mono">${h(c)}</span>`).join(' ')}</td>
      <td>${ix.primary ? '<span class="badge badge-accent">PRIMARY</span>'
           : ix.unique ? '<span class="badge badge-ok">UNIQUE</span>' : '<span class="badge">INDEX</span>'}
          ${ix.type ? `<span class="small faint">${h(ix.type)}</span>` : ''}</td>
      <td class="acts"><a href="${h(ctxUrl(V, { action: 'drop_index', page: 'structure', table, index: ix.name, csrf_token: V.csrf }))}"
        class="btn btn-ghost btn-icon btn-sm hov" data-confirm="Drop index &quot;${h(ix.name)}&quot;?">${ico('trash-2', 'ico-trash-2')}</a></td>
      </tr>`).join('');

    return `
  <div class="page-head">
    <div><h2>${h(table)}</h2><div class="sub">${cols.length} columns &middot; ${idxs.length} indexes &middot; ${fks.length} foreign keys</div></div>
    <div class="acts">
      <a href="${h(ctxUrl(V, { page: 'browse', table }))}" class="btn btn-default hov">${ico('eye')} ${h(t(V.lang, 'browse'))}</a>
      <a href="${h(ctxUrl(V, { page: 'operations', table }))}" class="btn btn-default hov">${ico('settings', 'ico-settings')} ${h(t(V.lang, 'operations'))}</a></div>
  </div>

  <div class="card">
    <div class="card-head"><h3>${ico('columns-3')} ${h(t(V.lang, 'columns'))}</h3>
      <span class="right"><button class="btn btn-primary btn-sm hov" data-modal="mAddCol">${ico('plus')} ${h(t(V.lang, 'add_column'))}</button></span></div>
    <div class="tbl-wrap"><table class="tbl">
      <thead><tr><th>#</th><th>Name</th><th>Type</th><th>Null</th><th>Default</th><th>Key</th><th class="hide-sm">Extra</th><th class="acts"></th></tr></thead>
      <tbody>${colRows}</tbody></table></div>
  </div>

  <div class="card">
    <div class="card-head"><h3>${ico('key-round')} ${h(t(V.lang, 'indexes'))}</h3>
      <span class="right"><button class="btn btn-default btn-sm hov" data-modal="mAddIdx">${ico('plus')} ${h(t(V.lang, 'add_index'))}</button></span></div>
    ${idxs.length ? `<div class="tbl-wrap"><table class="tbl">
      <thead><tr><th>Name</th><th>Columns</th><th>Type</th><th class="acts"></th></tr></thead>
      <tbody>${idxRows}</tbody></table></div>`
      : '<div class="empty" style="padding:24px"><span>No indexes on this table.</span></div>'}
  </div>

  ${fks.length ? `<div class="card">
    <div class="card-head"><h3>${ico('waypoints')} ${h(t(V.lang, 'foreign_keys'))}</h3></div>
    <div class="tbl-wrap"><table class="tbl">
      <thead><tr><th>Constraint</th><th>Column</th><th>References</th></tr></thead>
      <tbody>${fks.map((f) => `<tr><td class="mono small">${h(f.name)}</td>
        <td><span class="badge mono">${h(f.col)}</span></td>
        <td>${ico('arrow-right')} <a href="${h(ctxUrl(V, { page: 'browse', table: f.ref_table }))}" class="mono">${h(f.ref_table)}.${h(f.ref_col)}</a></td></tr>`).join('')}
      </tbody></table></div></div>` : ''}

  ${create ? `<div class="card">
    <div class="card-head"><h3>${ico('file-code-2')} Definition</h3>
      <span class="right"><button type="button" class="btn btn-ghost btn-sm hov" data-copy="#createSql">${ico('copy')} Copy</button></span></div>
    <pre id="createSql" class="mono small" style="padding:14px;overflow-x:auto;white-space:pre">${h(create)}</pre></div>` : ''}

  <div class="modal" id="mAddCol"><div class="modal-box"><form method="post">
    <input type="hidden" name="csrf_token" value="${h(V.csrf)}">
    <input type="hidden" name="table" value="${h(table)}">
    <div class="modal-head"><h3>${h(t(V.lang, 'add_column'))}</h3><button type="button" class="btn btn-ghost btn-icon close-modal">${ico('x')}</button></div>
    <div class="modal-body">
      <div class="row"><div class="field"><label>Name</label><input type="text" name="col_name" required></div>
        <div class="field"><label>Type</label><input type="text" name="col_type" list="typeList" value="VARCHAR" required></div></div>
      <div class="row"><div class="field"><label>Length / values</label><input type="text" name="col_len" placeholder="255"></div>
        <div class="field"><label>Default</label><input type="text" name="col_dflt"></div></div>
      ${db.getType() === 'mysql' ? `<div class="field"><label>Position</label><select name="col_pos">
        <option value="">At the end</option><option value="FIRST">First</option>
        ${cols.map((c) => `<option value="${h(c.Field)}">After ${h(c.Field)}</option>`).join('')}</select></div>` : ''}
      <label class="check"><input type="checkbox" name="col_null" value="1" checked> Allow NULL</label>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-default close-modal">${h(t(V.lang, 'cancel'))}</button>
      <button type="submit" name="add_column" value="1" class="btn btn-primary hov">${ico('plus')} Add</button></div>
  </form></div></div>

  <div class="modal" id="mEditCol"><div class="modal-box"><form method="post">
    <input type="hidden" name="csrf_token" value="${h(V.csrf)}">
    <input type="hidden" name="table" value="${h(table)}">
    <input type="hidden" name="old_col_name" id="ecOld">
    <div class="modal-head"><h3>Edit column</h3><button type="button" class="btn btn-ghost btn-icon close-modal">${ico('x')}</button></div>
    <div class="modal-body">
      <div class="row"><div class="field"><label>Name</label><input type="text" name="col_name" id="ecName" required></div>
        <div class="field"><label>Type</label><input type="text" name="col_type" id="ecType" list="typeList"></div></div>
      <div class="row"><div class="field"><label>Length</label><input type="text" name="col_len" id="ecLen"></div>
        <div class="field"><label>Default</label><input type="text" name="col_dflt" id="ecDflt"></div></div>
      <label class="check"><input type="checkbox" name="col_null" value="1" id="ecNull"> Allow NULL</label>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-default close-modal">${h(t(V.lang, 'cancel'))}</button>
      <button type="submit" name="edit_column" value="1" class="btn btn-primary hov">${ico('check')} Save</button></div>
  </form></div></div>

  <div class="modal" id="mAddIdx"><div class="modal-box"><form method="post">
    <input type="hidden" name="csrf_token" value="${h(V.csrf)}">
    <input type="hidden" name="table" value="${h(table)}">
    <div class="modal-head"><h3>${h(t(V.lang, 'add_index'))}</h3><button type="button" class="btn btn-ghost btn-icon close-modal">${ico('x')}</button></div>
    <div class="modal-body">
      <div class="row"><div class="field"><label>Name</label><input type="text" name="index_name" placeholder="auto"></div>
        <div class="field"><label>Type</label><select name="index_type"><option>INDEX</option><option>UNIQUE</option><option>PRIMARY KEY</option></select></div></div>
      <div class="field"><label>Columns</label>
        <div style="max-height:190px;overflow-y:auto;border:1px solid var(--border);border-radius:var(--r-sm);padding:8px">
          ${cols.map((c) => `<label class="check" style="display:flex;padding:3px 0">
            <input type="checkbox" name="index_columns" value="${h(c.Field)}"> ${h(c.Field)}
            <span class="badge mono right">${h(c.Type)}</span></label>`).join('')}
        </div></div>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-default close-modal">${h(t(V.lang, 'cancel'))}</button>
      <button type="submit" name="add_index" value="1" class="btn btn-primary hov">${ico('plus')} Create</button></div>
  </form></div></div>

  <datalist id="typeList">${TYPE_LIST.map((x) => `<option value="${x}">`).join('')}</datalist>`;
}

async function pageRecord(V, db, query, isEdit) {
    const table = V.selectedTable;
    const cols = await db.getColumns(table);
    const keys = extractRowKeys(query);
    let vals = null, err = null;

    if (isEdit && Object.keys(keys).length) {
        const id = await rowIdentity(db, table, keys);
        try { vals = await db.one(`SELECT * FROM ${db.qualify(table)} WHERE ${id.where} LIMIT 1`, id.params); }
        catch (e) { err = e.message; }
    }

    if (isEdit && !vals) {
        return `<div class="page-head"><div><h2>${h(t(V.lang, 'edit_record'))}</h2><div class="sub">${h(table)}</div></div>
          <div class="acts"><a href="${h(ctxUrl(V, { page: 'browse', table }))}" class="btn btn-default hov">${ico('arrow-left')} ${h(t(V.lang, 'back_to_table'))}</a></div></div>
          ${alertBox('warn', 'triangle-alert', err || 'That row could not be found. It may have been deleted.')}`;
    }

    const fields = cols.map((c) => {
        const v = isEdit && vals ? vals[c.Field] : null;
        const type = String(c.Type || '').toLowerCase();
        const long = /text|json|blob|bytea/.test(type);
        const nullable = c.Null === 'YES';
        const auto = String(c.Extra || '').includes('auto');
        const val = v === null || v === undefined ? '' : String(v);
        const input = long
            ? `<textarea name="field[${h(c.Field)}]" id="f_${h(c.Field)}" rows="3" class="grow" spellcheck="false">${h(val)}</textarea>`
            : `<input type="text" name="field[${h(c.Field)}]" id="f_${h(c.Field)}" class="grow" value="${h(val)}" ${auto ? 'placeholder="auto-generated"' : ''} spellcheck="false">`;
        return `<div class="field">
          <label for="f_${h(c.Field)}">${h(c.Field)} <span class="badge mono">${h(c.Type)}</span>
            ${auto ? '<span class="badge badge-accent">auto</span>' : ''}
            ${nullable ? '' : '<span class="badge badge-warn">required</span>'}</label>
          <div class="flex" style="align-items:flex-start">${input}
            ${nullable ? `<label class="check" style="padding-top:8px;white-space:nowrap">
              <input type="checkbox" name="field_null[${h(c.Field)}]" value="1" class="null-box" ${isEdit && v === null ? 'checked' : ''}> NULL</label>` : ''}
          </div></div>`;
    }).join('');

    return `
  <div class="page-head">
    <div><h2>${h(isEdit ? t(V.lang, 'edit_record') : t(V.lang, 'insert_record'))}</h2><div class="sub">${h(table)}</div></div>
    <div class="acts"><a href="${h(ctxUrl(V, { page: 'browse', table }))}" class="btn btn-default hov">${ico('arrow-left')} ${h(t(V.lang, 'back_to_table'))}</a></div>
  </div>
  <div class="card"><form method="post" class="card-body">
    <input type="hidden" name="csrf_token" value="${h(V.csrf)}">
    <input type="hidden" name="table" value="${h(table)}">
    <input type="hidden" name="is_edit" value="${isEdit ? '1' : '0'}">
    <input type="hidden" name="row_keys" value="${h(JSON.stringify(keys))}">
    ${fields}
    <div class="flex" style="margin-top:14px">
      <button type="submit" name="save_record" value="1" class="btn btn-primary hov">${ico('check')} ${h(t(V.lang, 'save'))}</button>
      <button type="submit" name="save_record" value="1" class="btn btn-default hov"><input type="hidden" name="then" value="back">${ico('arrow-left')} Save &amp; go back</button>
      <a href="${h(ctxUrl(V, { page: 'browse', table }))}" class="btn btn-ghost">${h(t(V.lang, 'cancel'))}</a>
    </div>
  </form></div>`;
}

function pageOperations(V, db) {
    const table = V.selectedTable;
    const optimizeSql = db.getType() === 'pgsql' ? 'VACUUM ANALYZE' : (db.getType() === 'mysql' ? 'OPTIMIZE TABLE' : 'VACUUM');
    const hidden = `<input type="hidden" name="csrf_token" value="${h(V.csrf)}">
      <input type="hidden" name="table" value="${h(table)}">`;

    return `
  <div class="page-head">
    <div><h2>${h(t(V.lang, 'operations'))}</h2><div class="sub">${h(table)}</div></div>
    <div class="acts"><a href="${h(ctxUrl(V, { page: 'browse', table }))}" class="btn btn-default hov">${ico('arrow-left')} ${h(t(V.lang, 'back_to_table'))}</a></div>
  </div>

  <div class="card"><div class="card-head"><h3>${ico('text-cursor-input')} ${h(t(V.lang, 'rename_table'))}</h3></div>
    <form method="post" class="card-body">${hidden}
      <input type="hidden" name="operation_action" value="rename_table">
      <div class="field"><label>New name</label><input type="text" name="new_table_name" value="${h(table)}" required></div>
      <button class="btn btn-primary hov">${ico('check')} Rename</button></form></div>

  <div class="card"><div class="card-head"><h3>${ico('copy')} ${h(t(V.lang, 'copy_table'))}</h3></div>
    <form method="post" class="card-body">${hidden}
      <input type="hidden" name="operation_action" value="copy_table">
      <div class="field"><label>New table name</label><input type="text" name="copy_table_name" value="${h(table + '_copy')}" required></div>
      <label class="check"><input type="checkbox" name="copy_data" value="1" checked> Copy the rows too</label>
      <div class="mt"><button class="btn btn-primary hov">${ico('copy')} Copy</button></div></form></div>

  ${db.getType() === 'mysql' ? `<div class="card"><div class="card-head"><h3>${ico('settings', 'ico-settings')} Engine &amp; collation</h3></div>
    <form method="post" class="card-body">${hidden}
      <input type="hidden" name="operation_action" value="alter_options">
      <div class="row">
        <div class="field"><label>Storage engine</label><select name="table_engine"><option value="">Leave unchanged</option>
          <option>InnoDB</option><option>MyISAM</option><option>MEMORY</option><option>ARCHIVE</option></select></div>
        <div class="field"><label>Collation</label><input type="text" name="table_collation" placeholder="utf8mb4_unicode_ci"></div>
        <div class="field"><label>AUTO_INCREMENT</label><input type="number" name="table_auto_increment" placeholder="1000"></div></div>
      <button class="btn btn-primary hov">${ico('check')} Apply</button></form></div>` : ''}

  <div class="card"><div class="card-head"><h3>${ico('wrench')} Maintenance</h3></div>
    <form method="post" class="card-body">${hidden}
      <input type="hidden" name="operation_action" value="optimize_table">
      <p class="small muted" style="margin-bottom:10px">Reclaims free space and refreshes planner statistics (<code>${h(optimizeSql)}</code>).</p>
      <button class="btn btn-default hov">${ico('wrench')} Optimise table</button></form></div>

  <div class="card danger"><div class="card-head"><h3>${ico('shield-alert')} Danger zone</h3></div>
    <div class="card-body flex flex-wrap">
      <form method="post">${hidden}<input type="hidden" name="operation_action" value="truncate_table">
        <button class="btn btn-danger-soft hov" data-confirm="Delete every row in &quot;${h(table)}&quot;? The table stays, the data does not.">Empty table</button></form>
      <form method="post">${hidden}<input type="hidden" name="operation_action" value="drop_table">
        <button class="btn btn-danger hov" data-confirm="Permanently drop &quot;${h(table)}&quot;? This cannot be undone." data-confirm-type="${h(table)}">${ico('trash-2', 'ico-trash-2')} Drop table</button></form>
    </div></div>`;
}

function pageSql(V, db, batches, sqlText) {
    const tabs = batches && batches.length > 1 ? `
      <div class="result-tabs" role="tablist">${batches.map((b, i) => `
        <button type="button" class="result-tab ${i === 0 ? 'active' : ''} ${b.error ? 'err' : ''}" data-rt="${i}">
          ${b.error ? '&#9888; ' : ''}#${i + 1}
          <span class="faint">${b.rows !== null ? b.rows.length + ' rows' : b.affected + ' affected'}</span></button>`).join('')}
      </div>` : '';

    const panes = (batches || []).map((b, i) => {
        let inner;
        if (b.error) {
            inner = `<div style="padding:14px"><div class="alert alert-error" style="margin:0">${ico('circle-alert')}<div>${h(b.error)}</div></div></div>`;
        } else if (b.rows === null) {
            inner = `<div style="padding:16px" class="flex">${ico('circle-check')} <b>${formatNum(b.affected)}</b> row(s) affected.</div>`;
        } else if (!b.rows.length) {
            inner = emptyState('scan-search', 'No rows returned', '');
        } else {
            const cols = Object.keys(b.rows[0]);
            inner = `<div class="tbl-wrap"><table class="tbl">
              <thead><tr>${cols.map((c) => `<th>${h(c)}</th>`).join('')}</tr></thead>
              <tbody>${b.rows.map((r) => `<tr>${cols.map((c) => {
                  const v = r[c];
                  if (v === null || v === undefined) return '<td class="cell"><span class="badge badge-null">NULL</span></td>';
                  const tr = truncateCell(String(v));
                  return `<td class="cell">${h(tr.text)}${tr.truncated ? '<span class="faint">&hellip;</span>' : ''}</td>`;
              }).join('')}</tr>`).join('')}</tbody></table></div>`;
        }
        return `<div class="rt-pane ${i === 0 ? '' : 'hidden'}" data-rt="${i}">
          <div class="card-head"><h3 class="mono small ellipsis" style="max-width:60%">${h(String(b.sql).slice(0, 120))}</h3>
            <span class="right">${b.ms} ms${b.rows !== null ? ` &middot; ${b.rows.length} rows` : ''}
              ${b.truncated ? '<span class="badge badge-warn">first 1000 shown</span>' : ''}</span></div>
          ${inner}</div>`;
    }).join('');

    return `
  <div class="page-head"><div><h2>${h(t(V.lang, 'sql_console'))}</h2>
    <div class="sub">${V.selectedDb ? h(V.selectedDb) + (db.getType() === 'pgsql' ? '.' + h(V.selectedSchema) : '') : 'No database selected'}</div></div></div>

  <div class="card"><form method="post" id="sqlForm">
    <input type="hidden" name="csrf_token" value="${h(V.csrf)}">
    <div class="sql-editor">
      <div class="sql-stack">
        <pre id="sqlHighlight" aria-hidden="true"></pre>
        <textarea name="sql" id="sqlInput" spellcheck="false" autocapitalize="off" autocomplete="off"
          placeholder="SELECT * FROM …    &#10;&#10;Ctrl+Enter to run · Ctrl+Space for suggestions">${h(sqlText || '')}</textarea>
        <div id="sqlAuto"></div>
      </div>
      <div class="sql-bar">
        <button type="submit" name="execute_sql" value="1" class="btn btn-primary btn-sm hov">${ico('play')} ${h(t(V.lang, 'execute_query'))} <kbd>Ctrl&nbsp;&#9166;</kbd></button>
        <button type="submit" name="export_query" value="1" class="btn btn-default btn-sm hov">${ico('download')} ${h(t(V.lang, 'export_query'))}</button>
        <button type="button" class="btn btn-ghost btn-sm hov" id="sqlFormat">${ico('braces')} Format</button>
        <span class="right flex">
          <select id="sqlHistory" class="input-sm" style="width:auto;max-width:190px"><option value="">History&hellip;</option></select>
          <button type="button" class="btn btn-ghost btn-icon btn-sm hov" id="sqlHistClear" title="Clear history">${ico('trash-2', 'ico-trash-2')}</button>
        </span>
      </div>
    </div>
  </form></div>

  ${batches ? `<div class="card">${tabs}${panes}</div>` : ''}`;
}

async function pageExport(V, db) {
    const dbs = Object.keys(await db.getDatabasesWithStats());
    const tables = await db.getTables();
    return `
  <div class="page-head"><div><h2>${h(t(V.lang, 'export_data'))}</h2>
    <div class="sub">Streamed straight to the browser, so table size is not limited by process memory</div></div></div>
  <div class="card"><form method="post" class="card-body">
    <input type="hidden" name="csrf_token" value="${h(V.csrf)}">
    <div class="row">
      <div class="field"><label for="expDb">${h(t(V.lang, 'select_database'))}</label>
        <select name="export_db_name" id="expDb">${dbs.map((d) => `<option value="${h(d)}" ${d === V.selectedDb ? 'selected' : ''}>${h(d)}</option>`).join('')}</select></div>
      <div class="field"><label for="expFmt">${h(t(V.lang, 'export_format'))}</label>
        <select name="export_db_format" id="expFmt">
          <option value="sql">SQL dump (.sql)</option><option value="json">JSON (.json)</option>
          <option value="csv">CSV (.csv)</option><option value="xml">XML (.xml)</option></select></div>
    </div>
    <div class="field"><label>Tables <span class="faint">(none selected exports everything)</span></label>
      <div style="max-height:210px;overflow-y:auto;border:1px solid var(--border);border-radius:var(--r-sm);padding:8px">
        ${tables.length ? tables.map((x) => `<label class="check" style="display:flex;padding:3px 0">
          <input type="checkbox" name="export_tables" value="${h(x)}"> ${h(x)}</label>`).join('')
          : '<span class="faint small">No tables in this database.</span>'}
      </div></div>
    <label class="check"><input type="checkbox" name="export_data" value="1" checked> Include row data (uncheck for structure only)</label>
    <div class="mt"><button type="submit" name="export_database" value="1" class="btn btn-primary hov">${ico('download', 'ico-download')} ${h(t(V.lang, 'download_database'))}</button></div>
  </form></div>`;
}

function pageImport(V) {
    return `
  <div class="page-head"><div><h2>${h(t(V.lang, 'import_data'))}</h2>
    <div class="sub">Into <b>${h(V.selectedDb || 'the current database')}</b></div></div></div>
  <div class="card"><form method="post" enctype="multipart/form-data" class="card-body">
    <input type="hidden" name="csrf_token" value="${h(V.csrf)}">
    <div class="field"><label for="sqlFile">SQL file</label>
      <input type="file" name="sql_file" id="sqlFile" accept=".sql,.txt,text/plain" required>
      <div class="hint">Up to 64 MB. Statements are split correctly around strings, comments and <code>DELIMITER</code> blocks.</div></div>
    <label class="check"><input type="checkbox" name="import_stop_on_error" value="1" checked> Stop and roll back on the first error</label>
    <div class="mt"><button type="submit" name="import_sql" value="1" class="btn btn-primary hov">${ico('import', 'ico-import')} Import</button></div>
  </form></div>`;
}

async function pageSearch(V, db, term, sdb) {
    const dbs = Object.keys(await db.getDatabasesWithStats());
    let results = {}, scanned = 0, searchErr = null;

    if (term && sdb) {
        const r = await db.selectDatabase(sdb);
        if (r !== true) searchErr = r;
        else {
            for (const tb of await db.getTables()) {
                scanned++;
                try {
                    const or = [], bind = [];
                    for (const c of await db.getColumns(tb)) {
                        if (/char|text|varchar|enum|json|uuid|citext/i.test(String(c.Type || ''))) {
                            or.push(`${db.quoteId(c.Field)} LIKE ?`); bind.push(`%${term}%`);
                        }
                    }
                    if (!or.length) continue;
                    const found = await db.all(`SELECT * FROM ${db.qualify(tb)} WHERE ${or.join(' OR ')} LIMIT 10`, bind);
                    if (found.length) results[tb] = found;
                } catch (_) {}
            }
        }
    }

    const cards = Object.entries(results).map(([tb, found]) => {
        const cols = Object.keys(found[0]);
        return `<div class="card">
          <div class="card-head"><h3>${ico('table', 'ico-table')} ${h(tb)}</h3>
            <span class="right"><span class="badge">${found.length} shown</span>
              <a href="?${qs({ page: 'browse', db: sdb, schema: V.selectedSchema, table: tb, search: term })}" class="btn btn-default btn-sm hov">${ico('eye')} Browse</a></span></div>
          <div class="tbl-wrap" style="max-height:320px"><table class="tbl">
            <thead><tr>${cols.map((c) => `<th>${h(c)}</th>`).join('')}</tr></thead>
            <tbody>${found.map((r) => `<tr>${cols.map((c) => {
                const v = r[c];
                if (v === null || v === undefined) return '<td class="cell"><span class="badge badge-null">NULL</span></td>';
                const tr = truncateCell(String(v), 90);
                return `<td class="cell">${h(tr.text)}${tr.truncated ? '<span class="faint">&hellip;</span>' : ''}</td>`;
            }).join('')}</tr>`).join('')}</tbody></table></div></div>`;
    }).join('');

    let resultBlock = '';
    if (searchErr) resultBlock = alertBox('error', 'circle-alert', searchErr);
    else if (term) {
        resultBlock = `<div class="stat-row" style="margin-bottom:14px">
          <span><b>${Object.keys(results).length}</b> of <b>${scanned}</b> tables matched &ldquo;${h(term)}&rdquo;</span></div>` +
          (Object.keys(results).length ? cards
            : `<div class="card">${emptyState('scan-search', 'Nothing found', 'No text column contains that value.')}</div>`);
    }

    return `
  <div class="page-head"><div><h2>${h(t(V.lang, 'global_search'))}</h2>
    <div class="sub">Searches every text-like column in every table</div></div></div>
  <div class="card"><form method="post" class="card-body">
    <input type="hidden" name="csrf_token" value="${h(V.csrf)}">
    <div class="row">
      <div class="field" style="flex:3"><label for="st">Search for</label>
        <input type="text" name="search_term" id="st" value="${h(term || '')}" placeholder="Keyword&hellip;" required autofocus></div>
      <div class="field"><label for="sd">${h(t(V.lang, 'select_database'))}</label>
        <select name="search_database" id="sd">${dbs.map((d) => `<option value="${h(d)}" ${d === sdb ? 'selected' : ''}>${h(d)}</option>`).join('')}</select></div>
    </div>
    <button class="btn btn-primary hov">${ico('search', 'ico-search')} ${h(t(V.lang, 'search'))}</button>
  </form></div>
  ${resultBlock}`;
}

// ─── SQL utilities ────────────────────────────────────────────────────────────
/** Split a script into statements, honouring quotes, comments and DELIMITER. */
function splitSql(sql) {
    const out = [];
    let buf = '', delim = ';', i = 0;
    const len = sql.length;

    while (i < len) {
        const ch = sql[i], two = sql.substr(i, 2);

        if (buf.trim() === '' && /^delimiter\s/i.test(sql.substr(i, 10))) {
            let eol = sql.indexOf('\n', i);
            if (eol === -1) eol = len;
            const nd = sql.slice(i + 10, eol).trim();
            if (nd) delim = nd;
            i = eol + 1; buf = '';
            continue;
        }
        if (two === '--' && (i + 2 >= len || /[\s]/.test(sql[i + 2]))) {
            const eol = sql.indexOf('\n', i);
            i = eol === -1 ? len : eol + 1;
            continue;
        }
        if (ch === '#') {
            const eol = sql.indexOf('\n', i);
            i = eol === -1 ? len : eol + 1;
            continue;
        }
        if (two === '/*') {
            const end = sql.indexOf('*/', i + 2);
            i = end === -1 ? len : end + 2;
            continue;
        }
        if (ch === "'" || ch === '"' || ch === '`') {
            const quote = ch;
            buf += ch; i++;
            while (i < len) {
                const c = sql[i];
                if (c === '\\' && quote !== '`' && i + 1 < len) { buf += c + sql[i + 1]; i += 2; continue; }
                buf += c; i++;
                if (c === quote) {
                    if (i < len && sql[i] === quote) { buf += sql[i]; i++; continue; }
                    break;
                }
            }
            continue;
        }
        if (sql.substr(i, delim.length) === delim) {
            if (buf.trim()) out.push(buf.trim());
            buf = ''; i += delim.length;
            continue;
        }
        buf += ch; i++;
    }
    if (buf.trim()) out.push(buf.trim());
    return out;
}

function extractRowKeys(src) {
    const out = {};
    for (const [k, v] of Object.entries(src || {})) {
        if (k.startsWith('where_')) out[k.slice(6)] = v;
    }
    return out;
}

/** Prefer the primary key; emit IS NULL rather than `= NULL`. */
async function rowIdentity(db, table, values) {
    const pk = await db.getPrimaryKey(table);
    let use = {};
    if (pk.length) {
        for (const c of pk) if (c in values) use[c] = values[c];
        if (Object.keys(use).length !== pk.length) use = {};
    }
    if (!Object.keys(use).length) use = values;

    const parts = [], params = [];
    for (const [col, val] of Object.entries(use)) {
        const q = db.quoteId(col);
        if (val === null || val === undefined) parts.push(`${q} IS NULL`);
        else { parts.push(`${q} = ?`); params.push(val); }
    }
    return { where: parts.length ? parts.join(' AND ') : '1=0', params, hasPk: pk.length > 0 };
}

// ─── Export ───────────────────────────────────────────────────────────────────
async function exportDump(res, db, tables, format, basename, withData) {
    const send = (mime, ext) => {
        res.setHeader('Content-Type', `${mime}; charset=utf-8`);
        res.setHeader('Content-Disposition', `attachment; filename="${basename}.${ext}"`);
        res.setHeader('X-Content-Type-Options', 'nosniff');
    };
    // Backpressure-aware write so a huge dump cannot balloon memory.
    const write = (s) => new Promise((resolve) => { res.write(s) ? resolve() : res.once('drain', resolve); });

    const eachRow = async (table, cb) => {
        if (!withData) return;
        const rows = await db.all(`SELECT * FROM ${db.qualify(table)}`).catch(() => []);
        for (const r of rows) await cb(r);
    };

    if (format === 'json') {
        send('application/json', 'json');
        await write('{\n');
        let first = true;
        for (const tb of tables) {
            if (!first) await write(',\n');
            first = false;
            await write('  ' + JSON.stringify(tb) + ': [\n');
            let rf = true;
            await eachRow(tb, async (r) => { await write((rf ? '    ' : ',\n    ') + JSON.stringify(r)); rf = false; });
            await write('\n  ]');
        }
        await write('\n}\n');
        return res.end();
    }

    if (format === 'csv') {
        send('text/csv', 'csv');
        await write('﻿');
        const cell = (v) => {
            if (v === null || v === undefined) return '';
            const s = String(v);
            return /[",\n\r]/.test(s) ? '"' + s.split('"').join('""') + '"' : s;
        };
        for (const tb of tables) {
            if (tables.length > 1) await write(`# table: ${tb}\n`);
            let header = false;
            await eachRow(tb, async (r) => {
                if (!header) { await write(Object.keys(r).map(cell).join(',') + '\n'); header = true; }
                await write(Object.values(r).map(cell).join(',') + '\n');
            });
            if (!header) {
                const cols = (await db.getColumns(tb)).map((c) => c.Field);
                if (cols.length) await write(cols.map(cell).join(',') + '\n');
            }
            if (tables.length > 1) await write('\n');
        }
        return res.end();
    }

    if (format === 'xml') {
        send('application/xml', 'xml');
        await write(`<?xml version="1.0" encoding="UTF-8"?>\n<database name="${h(db.getDatabase())}">\n`);
        for (const tb of tables) {
            await write(`  <table name="${h(tb)}">\n`);
            await eachRow(tb, async (r) => {
                await write('    <row>\n');
                for (const [k, v] of Object.entries(r)) {
                    let tag = String(k).replace(/[^A-Za-z0-9_.-]/g, '_');
                    if (!tag || /^\d/.test(tag)) tag = 'c_' + tag;
                    await write(v === null || v === undefined
                        ? `      <${tag} xsi:nil="true"/>\n`
                        : `      <${tag}>${h(v)}</${tag}>\n`);
                }
                await write('    </row>\n');
            });
            await write('  </table>\n');
        }
        await write('</database>\n');
        return res.end();
    }

    // SQL
    send('application/sql', 'sql');
    const quoteVal = (v) => {
        if (v === null || v === undefined) return 'NULL';
        if (typeof v === 'number') return String(v);
        if (typeof v === 'boolean') return v ? 'TRUE' : 'FALSE';
        if (v instanceof Date) return "'" + v.toISOString() + "'";
        if (Buffer.isBuffer(v)) return "'" + v.toString('hex') + "'";
        const s = String(v);
        // Standard SQL escaping: double the single quotes. Backslash escaping is
        // avoided because it is unsafe under NO_BACKSLASH_ESCAPES.
        return "'" + s.split("'").join("''") + "'";
    };

    await write('-- Dabiro SQL dump\n');
    await write(`-- Engine:   ${db.getType()} ${await db.serverVersion()}\n`);
    await write(`-- Database: ${db.getDatabase()}\n`);
    if (db.getType() === 'pgsql') await write(`-- Schema:   ${db.getSchema()}\n`);
    await write(`-- Date:     ${new Date().toISOString()}\n\n`);
    if (db.getType() === 'mysql') await write('SET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n');

    for (const tb of tables) {
        await write(`--\n-- Table: ${tb}\n--\n\n`);
        const create = await db.getCreateTable(tb);
        if (create) {
            await write(`DROP TABLE IF EXISTS ${db.quoteId(tb)};\n`);
            await write(String(create).replace(/[;\s]+$/, '') + ';\n\n');
        }
        let cols = null;
        await eachRow(tb, async (r) => {
            if (cols === null) cols = Object.keys(r).map((c) => db.quoteId(c)).join(', ');
            await write(`INSERT INTO ${db.quoteId(tb)} (${cols}) VALUES (${Object.values(r).map(quoteVal).join(', ')});\n`);
        });
        await write('\n');
    }
    if (db.getType() === 'mysql') await write('SET FOREIGN_KEY_CHECKS=1;\n');
    res.end();
}

// ─── Express app ──────────────────────────────────────────────────────────────
const app = express();
app.disable('x-powered-by');
app.use(express.urlencoded({ extended: true, limit: '64mb' }));
app.use(express.json({ limit: '64mb' }));
app.use(cookieParser());
/**
 * Only mark the session cookie Secure when the *browser's* connection really is
 * HTTPS. Getting this wrong locks people out: a Secure cookie is silently
 * dropped by the browser on a plain-HTTP page, so every request starts a new
 * session and the login screen comes back forever.
 *
 * Proxy headers are honoured only when DABIRO_TRUST_PROXY=1, because anyone can
 * send X-Forwarded-Proto and a proxy that sets it while the browser is still on
 * http:// causes exactly that lockout.
 */
const TRUST_PROXY = process.env.DABIRO_TRUST_PROXY === '1';
app.set('trust proxy', TRUST_PROXY ? 1 : false);

function requestIsHttps(req) {
    if (req.connection && req.connection.encrypted) return true;
    if (TRUST_PROXY) {
        const xfp = String(req.headers['x-forwarded-proto'] || '').split(',')[0].trim().toLowerCase();
        if (xfp === 'https') return true;
        if (String(req.headers['x-forwarded-ssl'] || '').toLowerCase() === 'on') return true;
        if (/proto=https/i.test(String(req.headers.forwarded || ''))) return true;
    }
    return false;
}

app.use(session({
    name: 'dabiro.sid',
    secret: SESSION_SECRET,
    resave: false,
    saveUninitialized: false,
    // 'auto' defers to the trust-proxy setting above rather than guessing.
    cookie: { httpOnly: true, sameSite: 'lax', maxAge: SESSION_TIMEOUT, secure: 'auto', path: '/' },
}));

const LOGIN_PROBE = 'dabiro_probe';

/** Never Secure - it has to come back even when the session cookie cannot. */
function loginProbeSet(res) {
    res.cookie(LOGIN_PROBE, String(Date.now()), {
        maxAge: 120000, httpOnly: true, sameSite: 'lax', path: '/', secure: false,
    });
}
function loginProbeClear(req, res) {
    if (req.cookies && req.cookies[LOGIN_PROBE]) res.clearCookie(LOGIN_PROBE, { path: '/' });
}

/** @returns {string|null} why the session is not surviving the redirect. */
function loginLoopDiagnosis(req) {
    const stamp = parseInt((req.cookies || {})[LOGIN_PROBE], 10) || 0;
    if (!stamp || Date.now() - stamp > 120000) return null;

    const reasons = [];
    const secureIssued = requestIsHttps(req);
    const reallyEncrypted = !!(req.connection && req.connection.encrypted);

    if (secureIssued && !reallyEncrypted) {
        reasons.push('Dabiro thinks this connection is HTTPS (from a proxy header), so the session cookie '
            + 'is marked Secure - but your browser is on plain HTTP and therefore throws it away. '
            + 'Either use HTTPS, or unset DABIRO_TRUST_PROXY.');
    } else if (!secureIssued && req.headers['x-forwarded-proto']) {
        reasons.push('A proxy is forwarding this request. If your browser is on HTTPS, set '
            + 'DABIRO_TRUST_PROXY=1 so the session cookie is issued correctly.');
    }
    if (!process.env.SESSION_SECRET) {
        reasons.push('SESSION_SECRET is not set, so every restart of the server invalidates all sessions.');
    }
    if (!reasons.length) {
        reasons.push('Your browser did not send the session cookie back. Check that cookies are not blocked '
            + 'for this site, and that you are not switching between hostnames (for example localhost and '
            + '127.0.0.1) between the login and the redirect.');
    }
    return reasons.join(' ');
}
const upload = multer({ storage: multer.memoryStorage(), limits: { fileSize: 64 * 1024 * 1024 } });

function isLoggedIn(req) {
    // A session from an older build has loggedIn set but no db config; treat it
    // as logged out rather than limping along half-populated.
    if (!req.session || !req.session.loggedIn) return false;
    if (!req.session.db || !req.session.db.type) { req.session.loggedIn = false; return false; }
    return true;
}
function sessionSsh(req) { return req.session.ssh && req.session.ssh.enabled ? req.session.ssh : null; }

function defaultPort(type) { return ({ mysql: 3306, pgsql: 5432, sqlite: 0 })[type] || 0; }

async function doLogin(req, cfg, httpRes) {
    let host = cfg.host, port = cfg.port, ssh = null;

    if (cfg.ssh && cfg.ssh.enabled) {
        ssh = Object.assign({}, cfg.ssh, {
            targetHost: cfg.host || '127.0.0.1',
            targetPort: parseInt(cfg.port, 10) || defaultPort(cfg.type),
        });
        const tr = await ensureTunnel(ssh);
        if (!tr.ok) return tr.error;
        host = '127.0.0.1';
        port = tr.port;
    }

    const db = new Db();
    const res = await db.connect(cfg.type, host, cfg.user, cfg.pass, cfg.dbname, port, cfg.ssl);
    if (res !== true) {
        if (ssh) closeTunnel(ssh);
        return res;
    }
    const schema = db.getSchema();
    await db.close();

    await new Promise((r) => req.session.regenerate(() => r()));
    if (httpRes) loginProbeSet(httpRes);   // lets the next request detect a login loop
    req.session.loggedIn = true;
    req.session.db = { type: cfg.type, host: cfg.host, port: cfg.port, user: cfg.user,
                       pass: cfg.pass, name: cfg.dbname, ssl: !!cfg.ssl };
    req.session.ssh = ssh ? Object.assign({ enabled: true }, ssh) : { enabled: false };
    req.session.schema = schema;
    return true;
}

/**
 * One connection per request. Re-asserts the SSH tunnel first, so a tunnel that
 * died since the previous request is rebuilt transparently - and we always use
 * the port ensureTunnel() just returned, never a stale one.
 */
async function getConnection(req) {
    if (!isLoggedIn(req)) return { db: null, error: null };
    const c = req.session.db;
    let host = c.host, port = c.port;

    const ssh = sessionSsh(req);
    if (ssh) {
        const tr = await ensureTunnel(ssh);
        if (!tr.ok) return { db: null, error: tr.error };
        host = '127.0.0.1';
        port = tr.port;
    }

    const db = new Db();
    const r = await db.connect(c.type, host, c.user, c.pass, c.name, port, c.ssl);
    if (r !== true) return { db: null, error: r };
    if (req.session.schema) await db.selectSchema(req.session.schema);
    return { db, error: null };
}

async function focusConnection(req, db, database, schema) {
    if (database) {
        const r = await db.selectDatabase(database);
        if (r !== true) return r;
    }
    if (db.getType() !== 'pgsql') return null;

    const available = await db.getSchemas();
    let s = schema;
    if (!s || !available.includes(s)) s = available.includes('public') ? 'public' : (available[0] || '');
    if (!s) return null;

    const r = await db.selectSchema(s);
    if (r !== true) return r;
    req.session.schema = s;
    return null;
}

function getLang(req) {
    const v = req.query.set_lang || req.cookies.dabiro_lang || 'en';
    return LANGS[v] ? v : 'en';
}
function getTheme(req) {
    const v = req.query.set_theme || req.cookies.dabiro_theme || 'light';
    return THEMES[v] ? v : 'light';
}

const AJAX_ACTIONS = ['get_tables', 'db_table_count', 'schema_map', 'palette', 'tunnel_status', 'cell_update'];

// ─── Main handler ─────────────────────────────────────────────────────────────
app.all('*', upload.single('sql_file'), async (req, res) => {
    const body = req.body || {};
    const query = req.query || {};
    const lang = getLang(req);
    const theme = getTheme(req);

    if (query.set_lang && LANGS[query.set_lang]) {
        res.cookie('dabiro_lang', query.set_lang, { maxAge: 365 * 86400 * 1000, path: '/', httpOnly: false, sameSite: 'lax' });
    }
    if (query.set_theme && THEMES[query.set_theme]) {
        res.cookie('dabiro_theme', query.set_theme, { maxAge: 365 * 86400 * 1000, path: '/', httpOnly: false, sameSite: 'lax' });
    }

    const csrf = csrfToken(req);
    const action = String(query.action || '');
    let error = null, success = null, connError = null;
    let db = null;

    const V = {
        lang, theme, csrf, ua: req.headers['user-agent'] || '',
        page: String(query.page || (isLoggedIn(req) ? 'databases' : 'login')),
        selectedDb: String(query.db || (req.session.db && req.session.db.name) || ''),
        selectedSchema: String(query.schema || req.session.schema || ''),
        selectedTable: String(query.table || ''),
        loggedIn: isLoggedIn(req),
        dbType: (req.session.db && req.session.db.type) || '',
        navTables: [], navSchemas: [], hasSsh: !!sessionSsh(req),
        connLabel: req.session.db ? `${req.session.db.user}@${req.session.db.host}` : '',
        error: null, success: null, connError: null,
    };

    const finish = async (html) => {
        if (db) await db.close();
        res.setHeader('Content-Type', 'text/html; charset=utf-8');
        res.setHeader('X-Content-Type-Options', 'nosniff');
        res.setHeader('Referrer-Policy', 'same-origin');
        res.send(html);
    };
    const jsonOut = async (data, code = 200) => {
        if (db) await db.close();
        res.status(code).json(data);
    };

    try {
        // ── Login ──
        if (body.login !== undefined) {
            if (!validateCsrf(req, body.csrf_token)) {
                error = 'Security token validation failed. Please reload the page and try again.';
            } else {
                const cfg = {
                    type: body.db_type, host: String(body.db_host || '').trim(),
                    port: String(body.db_port || '').trim(), user: body.db_pass !== undefined ? body.db_user : '',
                    pass: body.db_pass, dbname: String(body.db_name || '').trim(), ssl: !!body.db_ssl,
                    ssh: { enabled: false },
                };
                cfg.user = body.db_user;
                if (body.use_ssh === '1') {
                    cfg.ssh = {
                        enabled: true,
                        host: String(body.ssh_host || '').trim(),
                        port: parseInt(body.ssh_port, 10) || 22,
                        user: String(body.ssh_user || '').trim(),
                        auth: body.ssh_auth || 'agent',
                        password: body.ssh_pass || '',
                        key: body.ssh_key || '',
                        keyPass: body.ssh_key_pass || '',
                        keyIsPath: body.ssh_key_mode === 'path',
                        localPort: parseInt(body.ssh_local_port, 10) || 0,
                    };
                }
                const r = await doLogin(req, cfg, res);
                if (r === true) {
                    return res.redirect(cfg.dbname ? '?' + qs({ page: 'tables', db: cfg.dbname }) : '?page=databases');
                }
                error = r;
            }
        }

        // ── Logout ──
        if (body.logout !== undefined && validateCsrf(req, body.csrf_token)) {
            const ssh = sessionSsh(req);
            if (ssh) closeTunnel(ssh);
            return req.session.destroy(() => res.redirect('/'));
        }

        // ── Vault (pre-auth) ──
        if (action === 'vault') {
            if (!validateCsrf(req, body.csrf_token || query.csrf_token)) {
                return jsonOut({ ok: false, error: 'Invalid security token.' }, 403);
            }
            const why = Vault.available();
            if (why) return jsonOut({ ok: false, error: why }, 400);

            const op = body.op || query.op;
            const master = String(body.master || '');

            if (op === 'list') {
                const data = Vault.load(master);
                if (data === null) return jsonOut({ ok: false, error: 'Wrong master password.' }, 403);
                const safe = {};
                for (const [n, p] of Object.entries(data)) {
                    const c = Object.assign({}, p);
                    delete c.pass; delete c.ssh_pass; delete c.ssh_key; delete c.ssh_key_pass;
                    c.has_secrets = true;
                    safe[n] = c;
                }
                return jsonOut({ ok: true, profiles: safe, exists: Vault.exists() });
            }
            if (op === 'save') {
                const data = Vault.exists() ? Vault.load(master) : {};
                if (data === null) return jsonOut({ ok: false, error: 'Wrong master password.' }, 403);
                const name = String(body.name || '').trim();
                if (!name) return jsonOut({ ok: false, error: 'A profile name is required.' }, 400);
                try { data[name] = JSON.parse(String(body.profile || '{}')); } catch (_) { data[name] = {}; }
                return jsonOut({ ok: Vault.save(master, data) });
            }
            if (op === 'delete') {
                const data = Vault.load(master);
                if (data === null) return jsonOut({ ok: false, error: 'Wrong master password.' }, 403);
                delete data[String(body.name || '')];
                return jsonOut({ ok: Vault.save(master, data) });
            }
            if (op === 'connect') {
                const data = Vault.load(master);
                if (data === null) return jsonOut({ ok: false, error: 'Wrong master password.' }, 403);
                const p = data[String(body.name || '')];
                if (!p) return jsonOut({ ok: false, error: 'No such profile.' }, 404);
                const r = await doLogin(req, {
                    type: p.type || 'mysql', host: p.host || '', port: p.port || '',
                    user: p.user || '', pass: p.pass || '', dbname: p.dbname || '', ssl: !!p.ssl,
                    ssh: p.ssh_enabled ? {
                        enabled: true, host: p.ssh_host || '', port: parseInt(p.ssh_port, 10) || 22,
                        user: p.ssh_user || '', auth: p.ssh_auth || 'agent', password: p.ssh_pass || '',
                        key: p.ssh_key || '', keyPass: p.ssh_key_pass || '', keyIsPath: !!p.ssh_key_is_path,
                        localPort: parseInt(p.ssh_local_port, 10) || 0,
                    } : { enabled: false },
                }, res);
                return jsonOut(r === true
                    ? { ok: true, redirect: p.dbname ? '?' + qs({ page: 'tables', db: p.dbname }) : '?page=databases' }
                    : { ok: false, error: r });
            }
            return jsonOut({ ok: false, error: 'Unknown vault operation.' }, 400);
        }

        // ── Not connected → login screen ──
        if (!isLoggedIn(req)) {
            V.error = error;
            V.loopWarning = loginLoopDiagnosis(req);
            return finish(renderLogin(V));
        }
        loginProbeClear(req, res);

        // ── Connect ──
        const conn = await getConnection(req);
        db = conn.db;
        connError = conn.error;

        if (!db) {
            if (AJAX_ACTIONS.includes(action)) return jsonOut({ ok: false, error: connError || 'No connection.' }, 502);
            V.connError = connError || 'The database connection could not be re-established.';
            V.error = error;
            return finish(renderShell(V, `<div class="card">${emptyState('unplug', 'No database connection',
                'Dabiro could not reach the server for this request.',
                '<div style="margin-top:14px"><a href="?" class="btn btn-default hov">Retry</a></div>')}</div>`));
        }

        const focusErr = await focusConnection(req, db, V.selectedDb, V.selectedSchema);
        if (focusErr) error = focusErr;

        // Follow the table when the URL names one but no schema.
        if (db.getType() === 'pgsql' && V.selectedTable && !query.schema) {
            const owner = await db.findSchemaForTable(V.selectedTable);
            if (owner && owner !== db.getSchema()) {
                await db.selectSchema(owner);
                req.session.schema = owner;
            }
        }
        V.selectedSchema = db.getSchema();

        // ── JSON endpoints ──
        if (AJAX_ACTIONS.includes(action)) {
            if (action === 'get_tables') {
                return jsonOut(await db.getTables(query.db, query.schema));
            }
            if (action === 'db_table_count') {
                const name = String(query.name || '');
                const c = req.session.db;
                let host = c.host, port = c.port;
                const ssh = sessionSsh(req);
                if (ssh) {
                    const tr = await ensureTunnel(ssh);
                    if (!tr.ok) return jsonOut({ ok: false }, 502);
                    host = '127.0.0.1'; port = tr.port;
                }
                const probe = new Db();
                if (await probe.connect(c.type, host, c.user, c.pass, name, port, c.ssl) !== true) {
                    return jsonOut({ ok: false, name });
                }
                const n = await probe.countTables();
                await probe.close();
                return jsonOut({ ok: true, name, tables: n });
            }
            if (action === 'schema_map') {
                const map = {};
                for (const tb of await db.getTables()) {
                    map[tb] = (await db.getColumns(tb)).map((c) => c.Field);
                }
                return jsonOut({ ok: true, database: db.getDatabase(), schema: db.getSchema(), tables: map });
            }
            if (action === 'palette') {
                const items = [];
                for (const n of Object.keys(await db.getDatabasesWithStats())) {
                    items.push({ type: 'database', name: n, url: '?' + qs({ page: 'tables', db: n }) });
                }
                if (V.selectedDb) {
                    for (const s of await db.getSchemas()) {
                        items.push({ type: 'schema', name: s, context: V.selectedDb,
                                     url: '?' + qs({ page: 'tables', db: V.selectedDb, schema: s }) });
                    }
                    for (const tb of await db.getTables()) {
                        items.push({ type: 'table', name: tb, context: V.selectedDb,
                                     url: '?' + qs({ page: 'browse', db: V.selectedDb, schema: V.selectedSchema, table: tb }) });
                    }
                }
                return jsonOut({ ok: true, items });
            }
            if (action === 'tunnel_status') {
                const ssh = sessionSsh(req);
                return jsonOut({ ok: true, ssh: !!ssh, status: ssh ? tunnelStatus(ssh) : { up: false } });
            }
            if (action === 'cell_update') {
                if (!validateCsrf(req, body.csrf_token)) return jsonOut({ ok: false, error: 'Invalid security token.' }, 403);
                const tb = String(body.table || ''), col = String(body.column || '');
                if (!tb || !col) return jsonOut({ ok: false, error: 'Missing table or column.' }, 400);
                let keys = {};
                try { keys = JSON.parse(String(body.keys || '{}')); } catch (_) {}
                const id = await rowIdentity(db, tb, keys);
                if (!id.hasPk) return jsonOut({ ok: false, error: 'This table has no primary key, so rows cannot be edited inline.' }, 400);
                const isNull = body.is_null === '1';
                try {
                    const r = await db.run(
                        `UPDATE ${db.qualify(tb)} SET ${db.quoteId(col)} = ${isNull ? 'NULL' : '?'} WHERE ${id.where}`,
                        isNull ? id.params : [body.value, ...id.params]);
                    return jsonOut({ ok: true, affected: r.affected });
                } catch (e) { return jsonOut({ ok: false, error: e.message }, 400); }
            }
        }

        // ── Mutating actions ──
        const csrfOk = () => {
            if (validateCsrf(req, body.csrf_token)) return true;
            error = 'Security token validation failed. Please reload the page and try again.';
            return false;
        };
        const csrfOkGet = () => validateCsrf(req, query.csrf_token);

        if (body.create_database !== undefined && csrfOk()) {
            const n = String(body.new_db_name || '').trim();
            if (n) {
                try { await db.run('CREATE DATABASE ' + db.quoteId(n)); success = `Database "${n}" created.`; }
                catch (e) { error = e.message; }
            }
        }

        if (body.create_schema !== undefined && csrfOk() && db.getType() === 'pgsql') {
            const n = String(body.new_schema_name || '').trim();
            if (n) {
                try { await db.run('CREATE SCHEMA ' + db.quoteId(n)); success = `Schema "${n}" created.`; }
                catch (e) { error = e.message; }
            }
        }

        if (body.create_table !== undefined && csrfOk()) {
            const sql = String(body.create_table_sql || '').trim();
            if (sql) {
                try { await db.run(sql); success = 'Table created.'; }
                catch (e) { error = e.message; }
            }
        }

        if (body.add_column !== undefined && csrfOk()) {
            const tb = String(body.table || ''), name = String(body.col_name || '').trim();
            if (tb && name) {
                const len = String(body.col_len || '').trim();
                const type = String(body.col_type || '') + (len ? `(${len})` : '');
                const nul = body.col_null ? 'NULL' : 'NOT NULL';
                const dflt = String(body.col_dflt || '').trim();
                let sql = `ALTER TABLE ${db.qualify(tb)} ADD COLUMN ${db.quoteId(name)} ${type} ${nul}`;
                const params = [];
                if (dflt) { sql += ' DEFAULT ' + "'" + dflt.split("'").join("''") + "'"; }
                const pos = String(body.col_pos || '');
                if (pos && db.getType() === 'mysql') sql += pos === 'FIRST' ? ' FIRST' : ' AFTER ' + db.quoteId(pos);
                try { await db.run(sql, params); success = `Column "${name}" added.`; }
                catch (e) { error = e.message; }
            }
        }

        if (body.edit_column !== undefined && csrfOk()) {
            const tb = String(body.table || ''), oldN = String(body.old_col_name || '').trim(),
                  newN = String(body.col_name || '').trim();
            if (tb && oldN && newN) {
                const len = String(body.col_len || '').trim();
                const type = String(body.col_type || '') + (len ? `(${len})` : '');
                const nullable = !!body.col_null;
                const dflt = String(body.col_dflt || '').trim();
                const q = (s) => "'" + s.split("'").join("''") + "'";
                const qt = db.qualify(tb);
                try {
                    if (db.getType() === 'mysql') {
                        let sql = `ALTER TABLE ${qt} CHANGE COLUMN ${db.quoteId(oldN)} ${db.quoteId(newN)} ${type} ${nullable ? 'NULL' : 'NOT NULL'}`;
                        if (dflt) sql += ' DEFAULT ' + q(dflt);
                        await db.run(sql);
                    } else {
                        if (oldN !== newN) await db.run(`ALTER TABLE ${qt} RENAME COLUMN ${db.quoteId(oldN)} TO ${db.quoteId(newN)}`);
                        if (db.getType() === 'pgsql') {
                            const qc = db.quoteId(newN);
                            if (type.trim()) await db.run(`ALTER TABLE ${qt} ALTER COLUMN ${qc} TYPE ${type} USING ${qc}::${type}`);
                            await db.run(`ALTER TABLE ${qt} ALTER COLUMN ${qc} ${nullable ? 'DROP NOT NULL' : 'SET NOT NULL'}`);
                            await db.run(`ALTER TABLE ${qt} ALTER COLUMN ${qc} ${dflt ? 'SET DEFAULT ' + q(dflt) : 'DROP DEFAULT'}`);
                        }
                    }
                    success = 'Column updated.';
                } catch (e) { error = e.message; }
            }
        }

        if (action === 'drop_column' && csrfOkGet()) {
            const tb = String(query.table || ''), col = String(query.col || '');
            if (tb && col) {
                try { await db.run(`ALTER TABLE ${db.qualify(tb)} DROP COLUMN ${db.quoteId(col)}`); success = `Column "${col}" dropped.`; }
                catch (e) { error = e.message; }
            }
        }

        if (body.add_index !== undefined && csrfOk()) {
            const tb = String(body.table || ''), cols = arr(body.index_columns);
            if (tb && cols.length) {
                const qt = db.qualify(tb);
                const list = cols.map((c) => db.quoteId(c)).join(', ');
                const type = String(body.index_type || 'INDEX');
                const name = String(body.index_name || '').trim() || `idx_${tb}_${cols.join('_')}`;
                try {
                    if (type === 'PRIMARY KEY') await db.run(`ALTER TABLE ${qt} ADD PRIMARY KEY (${list})`);
                    else if (type === 'UNIQUE') await db.run(`CREATE UNIQUE INDEX ${db.quoteId(name)} ON ${qt} (${list})`);
                    else await db.run(`CREATE INDEX ${db.quoteId(name)} ON ${qt} (${list})`);
                    success = 'Index created.';
                } catch (e) { error = e.message; }
            }
        }

        if (action === 'drop_index' && csrfOkGet()) {
            const tb = String(query.table || ''), idx = String(query.index || '');
            if (tb && idx) {
                try {
                    if (idx === 'PRIMARY' && db.getType() === 'mysql') await db.run(`ALTER TABLE ${db.qualify(tb)} DROP PRIMARY KEY`);
                    else if (db.getType() === 'mysql') await db.run(`ALTER TABLE ${db.qualify(tb)} DROP INDEX ${db.quoteId(idx)}`);
                    else await db.run('DROP INDEX ' + (db.getType() === 'pgsql' ? db.quoteId(db.getSchema()) + '.' : '') + db.quoteId(idx));
                    success = `Index "${idx}" dropped.`;
                } catch (e) { error = e.message; }
            }
        }

        if (body.operation_action !== undefined && csrfOk()) {
            const op = String(body.operation_action), tb = String(body.table || '');
            if (tb) {
                const qt = db.qualify(tb);
                try {
                    if (op === 'rename_table') {
                        const n = String(body.new_table_name || '').trim();
                        if (n && n !== tb) {
                            await db.run(`ALTER TABLE ${qt} RENAME TO ${db.quoteId(n)}`);
                            await db.close(); db = null;
                            return res.redirect('?' + qs({ page: 'browse', db: V.selectedDb, schema: V.selectedSchema, table: n }));
                        }
                    } else if (op === 'copy_table') {
                        const target = String(body.copy_table_name || '').trim();
                        const withData = !!body.copy_data;
                        if (target) {
                            const qn = db.qualify(target);
                            if (db.getType() === 'mysql') {
                                await db.run(`CREATE TABLE ${qn} LIKE ${qt}`);
                                if (withData) await db.run(`INSERT INTO ${qn} SELECT * FROM ${qt}`);
                            } else if (db.getType() === 'pgsql') {
                                await db.run(`CREATE TABLE ${qn} (LIKE ${qt} INCLUDING ALL)`);
                                if (withData) await db.run(`INSERT INTO ${qn} SELECT * FROM ${qt}`);
                            } else {
                                await db.run(`CREATE TABLE ${qn} AS SELECT * FROM ${qt}${withData ? '' : ' WHERE 0'}`);
                            }
                            success = `Table copied to "${target}".`;
                        }
                    } else if (op === 'alter_options' && db.getType() === 'mysql') {
                        const eng = String(body.table_engine || '');
                        const col = String(body.table_collation || '').trim();
                        const ai = String(body.table_auto_increment || '');
                        if (['InnoDB', 'MyISAM', 'MEMORY', 'ARCHIVE'].includes(eng)) await db.run(`ALTER TABLE ${qt} ENGINE = ${eng}`);
                        if (col && /^[A-Za-z0-9_]+$/.test(col)) await db.run(`ALTER TABLE ${qt} COLLATE = ${col}`);
                        if (ai && /^\d+$/.test(ai)) await db.run(`ALTER TABLE ${qt} AUTO_INCREMENT = ${parseInt(ai, 10)}`);
                        success = 'Table options updated.';
                    } else if (op === 'optimize_table') {
                        if (db.getType() === 'mysql') await db.run(`OPTIMIZE TABLE ${qt}`);
                        else if (db.getType() === 'pgsql') await db.run(`VACUUM ANALYZE ${qt}`);
                        else await db.run('VACUUM');
                        success = 'Table optimised.';
                    } else if (op === 'truncate_table') {
                        await db.run(db.getType() === 'sqlite' ? `DELETE FROM ${qt}` : `TRUNCATE TABLE ${qt}`);
                        success = `Table "${tb}" emptied.`;
                    } else if (op === 'drop_table') {
                        await db.run(`DROP TABLE ${qt}`);
                        await db.close(); db = null;
                        return res.redirect('?' + qs({ page: 'tables', db: V.selectedDb, schema: V.selectedSchema }));
                    }
                } catch (e) { error = e.message; }
            }
        }

        if (body.bulk_action !== undefined && csrfOk()) {
            const sel = arr(body.selected), op = String(body.bulk_action);
            let done = 0;
            try {
                for (const tb of sel) {
                    const qt = db.qualify(tb);
                    if (op === 'drop') await db.run(`DROP TABLE ${qt}`);
                    else if (op === 'truncate') await db.run(db.getType() === 'sqlite' ? `DELETE FROM ${qt}` : `TRUNCATE TABLE ${qt}`);
                    else if (op === 'optimize') {
                        if (db.getType() === 'mysql') await db.run(`OPTIMIZE TABLE ${qt}`);
                        else if (db.getType() === 'pgsql') await db.run(`VACUUM ANALYZE ${qt}`);
                    }
                    done++;
                }
                if (done) {
                    const verb = { drop: 'dropped', truncate: 'emptied', optimize: 'optimised' }[op] || 'processed';
                    success = `${done} table(s) ${verb}.`;
                } else error = 'No tables were selected.';
            } catch (e) {
                error = e.message + (done ? ` (${done} table(s) processed before the error.)` : '');
            }
        }

        if (action === 'delete' && csrfOkGet()) {
            const tb = String(query.table || '');
            const keys = extractRowKeys(query);
            if (tb && Object.keys(keys).length) {
                const id = await rowIdentity(db, tb, keys);
                try {
                    const r = await db.run(`DELETE FROM ${db.qualify(tb)} WHERE ${id.where}`, id.params);
                    success = `${r.affected} record(s) deleted.`;
                } catch (e) { error = e.message; }
            }
        }

        if (body.save_record !== undefined && csrfOk()) {
            const tb = String(body.table || '');
            const isEdit = body.is_edit === '1';
            const fields = body.field && typeof body.field === 'object' ? body.field : {};
            const nulls = body.field_null && typeof body.field_null === 'object' ? body.field_null : {};
            if (tb && Object.keys(fields).length) {
                try {
                    if (isEdit) {
                        const sets = [], params = [];
                        for (const [col, val] of Object.entries(fields)) {
                            if (nulls[col]) sets.push(`${db.quoteId(col)} = NULL`);
                            else { sets.push(`${db.quoteId(col)} = ?`); params.push(val); }
                        }
                        let keys = {};
                        try { keys = JSON.parse(String(body.row_keys || '{}')); } catch (_) {}
                        const id = await rowIdentity(db, tb, keys);
                        const r = await db.run(`UPDATE ${db.qualify(tb)} SET ${sets.join(', ')} WHERE ${id.where}`,
                                               [...params, ...id.params]);
                        success = `${r.affected} record(s) updated.`;
                    } else {
                        const cols = [], ph = [], params = [];
                        for (const [col, val] of Object.entries(fields)) {
                            cols.push(db.quoteId(col));
                            if (nulls[col]) ph.push('NULL');
                            else { ph.push('?'); params.push(val); }
                        }
                        await db.run(`INSERT INTO ${db.qualify(tb)} (${cols.join(', ')}) VALUES (${ph.join(', ')})`, params);
                        success = 'Record inserted.';
                    }
                    if (body.then === 'back') {
                        await db.close(); db = null;
                        return res.redirect('?' + qs({ page: 'browse', db: V.selectedDb, schema: V.selectedSchema, table: tb }));
                    }
                } catch (e) { error = e.message; }
            }
        }

        // ── SQL console ──
        let batches = null;
        if (body.execute_sql !== undefined || body.export_query !== undefined) {
            if (!validateCsrf(req, body.csrf_token)) {
                error = 'Security token validation failed.';
            } else {
                const raw = String(body.sql || '').trim();
                if (body.export_query !== undefined) {
                    if (db) { await db.close(); db = null; }
                    res.setHeader('Content-Type', 'application/sql; charset=utf-8');
                    res.setHeader('Content-Disposition',
                        `attachment; filename="query_${new Date().toISOString().replace(/[-:T]/g, '').slice(0, 15)}.sql"`);
                    return res.send(`-- Dabiro query export\n-- ${new Date().toISOString()}\n\n${raw}\n`);
                }
                if (raw) {
                    batches = [];
                    for (const stmt of splitSql(raw)) {
                        const t0 = Date.now();
                        const entry = { sql: stmt, rows: null, affected: 0, error: null, ms: 0, truncated: false };
                        try {
                            const r = await db.run(stmt);
                            entry.ms = Date.now() - t0;
                            if (r.isSelect) {
                                entry.rows = r.rows.slice(0, 1000);
                                entry.truncated = r.rows.length > 1000;
                            } else entry.affected = r.affected;
                        } catch (e) {
                            entry.ms = Date.now() - t0;
                            entry.error = e.message;
                        }
                        batches.push(entry);
                        if (entry.error) break;
                    }
                }
            }
        }

        // ── Import ──
        if (body.import_sql !== undefined && csrfOk()) {
            if (!req.file) {
                error = 'No file was selected.';
            } else {
                const stmts = splitSql(req.file.buffer.toString('utf8'));
                const stopOnError = body.import_stop_on_error === '1';
                let ok = 0;
                const errs = [];
                const canTx = db.getType() !== 'sqlite';
                try { if (canTx) await db.run('BEGIN'); } catch (_) {}
                for (let i = 0; i < stmts.length; i++) {
                    try { await db.run(stmts[i]); ok++; }
                    catch (e) {
                        errs.push(`Statement ${i + 1}: ${e.message}`);
                        if (stopOnError) break;
                    }
                }
                try {
                    if (canTx) await db.run(errs.length && stopOnError ? 'ROLLBACK' : 'COMMIT');
                } catch (_) {}
                if (errs.length && stopOnError) error = 'Import aborted and rolled back. ' + errs.slice(0, 3).join(' | ');
                else if (errs.length) error = `${ok} statement(s) applied, ${errs.length} failed. ` + errs.slice(0, 3).join(' | ');
                else success = `Imported ${ok} statement(s) successfully.`;
            }
        }

        // ── Export ──
        if (body.export_database !== undefined && validateCsrf(req, body.csrf_token)) {
            const expDb = String(body.export_db_name || V.selectedDb || '');
            const fmt = String(body.export_db_format || 'sql');
            const chosen = arr(body.export_tables);
            const withData = body.export_data === '1';
            if (expDb) await db.selectDatabase(expDb);
            if (V.selectedSchema) await db.selectSchema(V.selectedSchema);
            const tables = chosen.length ? chosen : await db.getTables();
            const stamp = new Date().toISOString().replace(/[:T]/g, '-').slice(0, 19);
            const base = (expDb || 'export').replace(/[^A-Za-z0-9_.-]/g, '_') + '_' + stamp;
            await exportDump(res, db, tables, fmt, base, withData);
            await db.close(); db = null;
            return;
        }

        // ── Render ──
        V.error = error;
        V.success = success;
        V.connError = connError;
        if (db.getType() === 'pgsql') V.navSchemas = await db.getSchemas();
        if (V.selectedDb) V.navTables = await db.getTables();

        let content;
        switch (V.page) {
            case 'databases':  content = await pageDatabases(V, db); break;
            case 'tables':     content = await pageTables(V, db); break;
            case 'browse':     content = await pageBrowse(V, db, query); break;
            case 'structure':  content = await pageStructure(V, db); break;
            case 'insert':     content = await pageRecord(V, db, query, false); break;
            case 'edit':       content = await pageRecord(V, db, query, true); break;
            case 'operations': content = pageOperations(V, db); break;
            case 'sql':        content = pageSql(V, db, batches, body.sql || query.pre_sql || ''); break;
            case 'export':     content = await pageExport(V, db); break;
            case 'import':     content = pageImport(V); break;
            case 'search':     content = await pageSearch(V, db,
                                    String(body.search_term || '').trim(),
                                    String(body.search_database || V.selectedDb || '')); break;
            default:
                content = `<div class="card">${emptyState('circle-help', 'Page not found',
                    `"${V.page}" is not a Dabiro page.`,
                    '<div style="margin-top:14px"><a href="?page=databases" class="btn btn-primary hov">Go to databases</a></div>')}</div>`;
        }

        return finish(renderShell(V, content));

    } catch (e) {
        if (db) { try { await db.close(); } catch (_) {} db = null; }
        console.error('[dabiro]', e);
        if (AJAX_ACTIONS.includes(action)) {
            return res.status(500).json({ ok: false, error: e.message });
        }
        V.error = e.message;
        return res.status(500).send(isLoggedIn(req)
            ? renderShell(V, `<div class="card">${emptyState('circle-alert', 'Something went wrong', e.message)}</div>`)
            : renderLogin(V));
    }
});

app.listen(PORT, HOST, () => {
    console.log(`Dabiro v${VERSION} (Node.js) listening on http://${HOST}:${PORT}`);
    if (!SSH2) console.log('[dabiro] ssh2 is not installed - the SSH Tunnel tab will be disabled.');
    console.log(`[dabiro] data directory: ${DATA_DIR}`);
});

module.exports = app;
