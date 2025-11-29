<?php
/**
 * Database Admin Tool - Professional Edition
 * A single-file database management system with modern, responsive UI
 * Version: 1.0.1
 * Kenneth D'silva (Modracx), Copyright (c) Novenber 2025
 * Licensed under the MIT License – https://opensource.org/licenses/MIT
 */

// Configuration
define('DB_ADMIN_VERSION', '1.0.1');
define('SESSION_TIMEOUT', 3600);

// Start session
session_start();

// Helper Functions
function h($str)
{
    return htmlspecialchars($str, ENT_QUOTES, 'utf-8');
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

// Database Connection Class
class DbConnection
{
    private $pdo = null;
    private $type = '';

    public function connect($type, $host, $user, $pass, $dbname = '')
    {
        $this->type = $type;

        try {
            switch ($type) {
                case 'mysql':
                    $dsn = "mysql:host=$host" . ($dbname ? ";dbname=$dbname" : '');
                    $this->pdo = new PDO($dsn, $user, $pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    ]);
                    break;

                case 'pgsql':
                    $dsn = "pgsql:host=$host" . ($dbname ? ";dbname=$dbname" : ';dbname=postgres');
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

    public function getPdo()
    {
        return $this->pdo;
    }

    public function getType()
    {
        return $this->type;
    }

    public function query($sql)
    {
        return $this->pdo->query($sql);
    }

    public function prepare($sql)
    {
        return $this->pdo->prepare($sql);
    }

    public function getDatabases()
    {
        switch ($this->type) {
            case 'mysql':
                return $this->query("SHOW DATABASES")->fetchAll(PDO::FETCH_COLUMN);
            case 'pgsql':
                return $this->query("SELECT datname FROM pg_database WHERE datistemplate = false ORDER BY datname")->fetchAll(PDO::FETCH_COLUMN);
            case 'sqlite':
                return ['main'];
        }
        return [];
    }

    public function getTables($database = null)
    {
        if ($database && $this->type != 'sqlite') {
            $this->pdo->exec("USE `$database`");
        }

        switch ($this->type) {
            case 'mysql':
                return $this->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            case 'pgsql':
                return $this->query("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename")->fetchAll(PDO::FETCH_COLUMN);
            case 'sqlite':
                return $this->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
        }
        return [];
    }

    public function getColumns($table)
    {
        switch ($this->type) {
            case 'mysql':
                return $this->query("SHOW COLUMNS FROM `$table`")->fetchAll();
            case 'pgsql':
                return $this->query("SELECT column_name as \"Field\", data_type as \"Type\", is_nullable as \"Null\", column_default as \"Default\" FROM information_schema.columns WHERE table_name = '$table'")->fetchAll();
            case 'sqlite':
                return $this->query("PRAGMA table_info(`$table`)")->fetchAll();
        }
        return [];
    }

    public function getTableData($table, $limit = 50, $offset = 0, $where = '')
    {
        $whereClause = $where ? " WHERE $where" : '';
        $sql = "SELECT * FROM `$table`$whereClause LIMIT $limit OFFSET $offset";
        return $this->query($sql)->fetchAll();
    }

    public function getRowCount($table, $where = '')
    {
        $whereClause = $where ? " WHERE $where" : '';
        $sql = "SELECT COUNT(*) as count FROM `$table`$whereClause";
        $result = $this->query($sql)->fetch();
        return $result['count'];
    }
}

// Authentication
function is_logged_in()
{
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function login($type, $host, $user, $pass, $dbname = '')
{
    $db = new DbConnection();
    $result = $db->connect($type, $host, $user, $pass, $dbname);

    if ($result === true) {
        $_SESSION['logged_in'] = true;
        $_SESSION['db_type'] = $type;
        $_SESSION['db_host'] = $host;
        $_SESSION['db_user'] = $user;
        $_SESSION['db_pass'] = $pass;
        $_SESSION['db_name'] = $dbname;
        $_SESSION['last_activity'] = time();
        return true;
    }
    return $result;
}

function logout()
{
    session_destroy();
    redirect('?');
}

function get_connection()
{
    if (!is_logged_in()) return null;

    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        logout();
    }
    $_SESSION['last_activity'] = time();

    $db = new DbConnection();
    $db->connect(
        $_SESSION['db_type'],
        $_SESSION['db_host'],
        $_SESSION['db_user'],
        $_SESSION['db_pass'],
        $_SESSION['db_name']
    );
    return $db;
}

// Handle login
if (isset($_POST['login'])) {
    $result = login(
        get_post('db_type'),
        get_post('db_host'),
        get_post('db_user'),
        get_post('db_pass'),
        get_post('db_name')
    );

    if ($result === true) {
        redirect('?page=databases');
    } else {
        $error = $result;
    }
}

// Handle logout
if (get_get('action') == 'logout') {
    logout();
}

// Handle AJAX request for getting tables
if (get_get('action') == 'get_tables' && get_get('db')) {
    $db = get_connection();
    $database = get_get('db');

    try {
        $tables = $db->getTables($database);
        header('Content-Type: application/json');
        echo json_encode($tables);
        exit;
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([]);
        exit;
    }
}

// Handle create table
if (isset($_POST['create_table'])) {
    $db = get_connection();
    $create_sql = get_post('create_table_sql');
    $target_db = get_post('create_table_db', $_SESSION['db_name'] ?? '');
    if ($db && $create_sql) {
        try {
            if ($db->getType() != 'sqlite' && $target_db) {
                $db->getPdo()->query("USE `" . addslashes($target_db) . "`");
            }
            $db->getPdo()->exec($create_sql);
            $success_message = 'Table created successfully.';
        } catch (Exception $e) {
            $error_message = 'Create table failed: ' . $e->getMessage();
        }
    }
}

// Handle add column to existing table
if (isset($_POST['add_column'])) {
    $db = get_connection();
    $table = get_post('add_column_table');
    $col_name = get_post('new_col_name');
    $col_type = get_post('new_col_type');
    $col_length = get_post('new_col_length');
    $col_null = get_post('new_col_null') ? true : false;
    $col_default = get_post('new_col_default');
    $col_extra = get_post('new_col_extra');

    if ($db && $table && $col_name && $col_type) {
        try {
            $sql = "ALTER TABLE `" . addslashes($table) . "` ADD COLUMN `" . addslashes($col_name) . "` " . $col_type;
            if ($col_length) $sql .= "(" . addslashes($col_length) . ")";
            $sql .= $col_null ? " NULL" : " NOT NULL";
            if ($col_default !== '') {
                if (strtoupper($col_default) === 'NULL') {
                    $sql .= " DEFAULT NULL";
                } else {
                    $sql .= " DEFAULT " . $db->getPdo()->quote($col_default);
                }
            }
            if ($col_extra === 'AUTO_INCREMENT') {
                $sql .= " AUTO_INCREMENT";
            }
            $db->getPdo()->exec($sql);
            $success_message = "Column '$col_name' added to table '$table' successfully";
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
}

// Handle SQL query export
if (isset($_POST['export_query'])) {
    $sql = get_post('sql');

    if ($sql) {
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="query_' . date('Y-m-d_H-i-s') . '.sql"');

        echo "-- SQL Query Export\n";
        echo "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
        echo "-- Database: " . ($_SESSION['db_name'] ?? 'N/A') . "\n\n";
        echo $sql;
        exit;
    }
}

// Handle full database export
if (isset($_POST['export_database'])) {
    $db = get_connection();
    $export_db_name = get_post('export_db_name');
    $export_db_format = get_post('export_db_format', 'sql');

    if ($export_db_name && $db) {
        // Switch to selected database
        if ($db->getType() != 'sqlite') {
            $db->getPdo()->query("USE `$export_db_name`");
        }

        $tables = $db->getTables($export_db_name);
        $timestamp = date('Y-m-d_H-i-s');

        if ($export_db_format == 'sql') {
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $export_db_name . '_' . $timestamp . '.sql"');

            echo "-- Database Export: $export_db_name\n";
            echo "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
            echo "-- Server: " . $_SESSION['db_host'] . "\n";
            echo "-- Database Type: " . $db->getType() . "\n\n";

            foreach ($tables as $table) {
                echo "-- --------------------------------------------------------\n";
                echo "-- Table: $table\n";
                echo "-- --------------------------------------------------------\n\n";

                // Get CREATE TABLE statement for MySQL
                if ($db->getType() == 'mysql') {
                    try {
                        $create = $db->query("SHOW CREATE TABLE `$table`")->fetch();
                        echo $create['Create Table'] . ";\n\n";
                    } catch (Exception $e) {
                        echo "-- Could not get CREATE TABLE statement\n\n";
                    }
                }

                // Export data
                $data = $db->query("SELECT * FROM `$table`")->fetchAll();
                if (!empty($data)) {
                    foreach ($data as $row) {
                        $values = array_map(function ($v) use ($db) {
                            return $v === null ? 'NULL' : $db->getPdo()->quote($v);
                        }, array_values($row));
                        echo "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
                    }
                    echo "\n";
                }
            }
            exit;
        } elseif ($export_db_format == 'json') {
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $export_db_name . '_' . $timestamp . '.json"');

            $export_data = [
                'database' => $export_db_name,
                'exported_at' => date('Y-m-d H:i:s'),
                'server' => $_SESSION['db_host'],
                'type' => $db->getType(),
                'tables' => []
            ];

            foreach ($tables as $table) {
                $data = $db->query("SELECT * FROM `$table`")->fetchAll();
                $export_data['tables'][$table] = $data;
            }

            echo json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

// Handle enhanced table export (download)
if (isset($_POST['export_table_download'])) {
    $db = get_connection();
    $export_database = get_post('export_database');
    $export_table = get_post('export_table');
    $export_format = get_post('export_format');

    if ($export_database && $export_table && $db) {
        // Switch to selected database
        if ($db->getType() != 'sqlite') {
            $db->getPdo()->query("USE `$export_database`");
        }

        $timestamp = date('Y-m-d_H-i-s');
        $data = $db->query("SELECT * FROM `$export_table`")->fetchAll();

        if ($export_format == 'sql') {
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $export_database . '_' . $export_table . '_' . $timestamp . '.sql"');

            echo "-- Table Export: $export_database.$export_table\n";
            echo "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
            echo "-- Total Records: " . count($data) . "\n\n";

            // Get CREATE TABLE for MySQL
            if ($db->getType() == 'mysql') {
                try {
                    $create = $db->query("SHOW CREATE TABLE `$export_table`")->fetch();
                    echo $create['Create Table'] . ";\n\n";
                } catch (Exception $e) {
                }
            }

            foreach ($data as $row) {
                $values = array_map(function ($v) use ($db) {
                    return $v === null ? 'NULL' : $db->getPdo()->quote($v);
                }, array_values($row));
                echo "INSERT INTO `$export_table` VALUES (" . implode(', ', $values) . ");\n";
            }
            exit;
        } elseif ($export_format == 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $export_database . '_' . $export_table . '_' . $timestamp . '.csv"');

            $output = fopen('php://output', 'w');
            // Add BOM for Excel compatibility
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

            if (!empty($data)) {
                fputcsv($output, array_keys($data[0]));
                foreach ($data as $row) {
                    fputcsv($output, $row);
                }
            }
            fclose($output);
            exit;
        } elseif ($export_format == 'json') {
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $export_database . '_' . $export_table . '_' . $timestamp . '.json"');

            $export_data = [
                'database' => $export_database,
                'table' => $export_table,
                'exported_at' => date('Y-m-d H:i:s'),
                'total_records' => count($data),
                'data' => $data
            ];

            echo json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        } elseif ($export_format == 'xml') {
            header('Content-Type: application/xml; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $export_database . '_' . $export_table . '_' . $timestamp . '.xml"');

            echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            echo "<export>\n";
            echo "  <database>" . htmlspecialchars($export_database) . "</database>\n";
            echo "  <table>" . htmlspecialchars($export_table) . "</table>\n";
            echo "  <exported_at>" . date('Y-m-d H:i:s') . "</exported_at>\n";
            echo "  <records>\n";

            foreach ($data as $row) {
                echo "    <record>\n";
                foreach ($row as $key => $value) {
                    echo "      <" . htmlspecialchars($key) . ">" . htmlspecialchars($value ?? '') . "</" . htmlspecialchars($key) . ">\n";
                }
                echo "    </record>\n";
            }

            echo "  </records>\n";
            echo "</export>";
            exit;
        }
    }
}

// Handle SQL query execution
if (isset($_POST['execute_sql'])) {
    $db = get_connection();
    $sql = get_post('sql');
    $sql_result = null;
    $sql_error = null;

    try {
        $start_time = microtime(true);
        $stmt = $db->query($sql);
        $execution_time = round((microtime(true) - $start_time) * 1000, 2);

        if ($stmt) {
            $sql_result = $stmt->fetchAll();
            $sql_affected = $stmt->rowCount();
        }
    } catch (Exception $e) {
        $sql_error = $e->getMessage();
    }
}

// Handle record deletion
if (get_get('action') == 'delete' && get_get('table')) {
    $db = get_connection();
    $table = get_get('table');
    $conditions = [];

    foreach ($_GET as $key => $value) {
        if (strpos($key, 'where_') === 0) {
            $field = substr($key, 6);
            $conditions[] = "`$field` = " . $db->getPdo()->quote($value);
        }
    }

    if (!empty($conditions)) {
        $sql = "DELETE FROM `$table` WHERE " . implode(' AND ', $conditions);
        try {
            $db->query($sql);
            $success_message = "Record deleted successfully";
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
}

// Handle record insert/update
if (isset($_POST['save_record'])) {
    $db = get_connection();
    $table = get_post('table');
    $fields = get_post('field', []);
    $is_edit = get_post('is_edit') == '1';

    try {
        if ($is_edit) {
            $sets = [];
            $where = [];
            foreach ($fields as $name => $value) {
                if (strpos($name, 'old_') === 0) {
                    $field = substr($name, 4);
                    $where[] = "`$field` = " . $db->getPdo()->quote($value);
                } else {
                    $sets[] = "`$name` = " . $db->getPdo()->quote($value);
                }
            }
            $sql = "UPDATE `$table` SET " . implode(', ', $sets) . " WHERE " . implode(' AND ', $where);
        } else {
            $columns = [];
            $values = [];
            foreach ($fields as $name => $value) {
                $columns[] = "`$name`";
                $values[] = $db->getPdo()->quote($value);
            }
            $sql = "INSERT INTO `$table` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ")";
        }

        $db->query($sql);
        $success_message = $is_edit ? "Record updated successfully" : "Record inserted successfully";
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Handle Create Database
if (isset($_POST['create_database'])) {
    $db = get_connection();
    $new_db_name = get_post('new_db_name');

    try {
        if ($db->getType() == 'mysql') {
            $db->query("CREATE DATABASE `$new_db_name`");
            $success_message = "Database '$new_db_name' created successfully";
        } elseif ($db->getType() == 'pgsql') {
            $db->query("CREATE DATABASE \"$new_db_name\"");
            $success_message = "Database '$new_db_name' created successfully";
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Handle Drop Database
if (get_get('action') == 'drop_database' && get_get('db')) {
    $db = get_connection();
    $database = get_get('db');

    try {
        if ($db->getType() == 'mysql') {
            $db->query("DROP DATABASE `$database`");
            $success_message = "Database '$database' dropped successfully";
        } elseif ($db->getType() == 'pgsql') {
            $db->query("DROP DATABASE \"$database\"");
            $success_message = "Database '$database' dropped successfully";
        }
        redirect('?page=databases');
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Handle Drop Table
if (get_get('action') == 'drop_table' && get_get('table')) {
    $db = get_connection();
    $database = get_get('db');
    $table = get_get('table');

    try {
        $db->query("DROP TABLE `$table`");
        $success_message = "Table '$table' dropped successfully";
        redirect("?page=tables&db=" . urlencode($database));
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Handle Truncate Table
if (get_get('action') == 'truncate_table' && get_get('table')) {
    $db = get_connection();
    $table = get_get('table');

    try {
        if ($db->getType() == 'sqlite') {
            $db->query("DELETE FROM `$table`");
        } else {
            $db->query("TRUNCATE TABLE `$table`");
        }
        $success_message = "Table '$table' truncated successfully";
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Handle Rename Table
if (isset($_POST['rename_table'])) {
    $db = get_connection();
    $database = get_post('database');
    $old_name = get_post('old_table_name');
    $new_name = get_post('new_table_name');

    try {
        if ($db->getType() == 'mysql') {
            $db->query("RENAME TABLE `$old_name` TO `$new_name`");
        } elseif ($db->getType() == 'pgsql') {
            $db->query("ALTER TABLE \"$old_name\" RENAME TO \"$new_name\"");
        } elseif ($db->getType() == 'sqlite') {
            $db->query("ALTER TABLE `$old_name` RENAME TO `$new_name`");
        }
        $success_message = "Table renamed from '$old_name' to '$new_name' successfully";
        redirect("?page=browse&db=" . urlencode($database) . "&table=" . urlencode($new_name));
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Handle Copy Table
if (isset($_POST['copy_table'])) {
    $db = get_connection();
    $source_db = get_post('source_db');
    $source_table = get_post('source_table');
    $target_db = get_post('target_db');
    $target_table = get_post('target_table');
    $copy_data = get_post('copy_data') == '1';

    try {
        if ($db->getType() == 'mysql') {
            // Switch to target database
            if ($target_db != $source_db) {
                $db->query("USE `$target_db`");
            }

            // Create table structure
            $db->query("CREATE TABLE `$target_table` LIKE `$source_db`.`$source_table`");

            // Copy data if requested
            if ($copy_data) {
                $db->query("INSERT INTO `$target_table` SELECT * FROM `$source_db`.`$source_table`");
            }

            $success_message = "Table copied successfully" . ($copy_data ? " with data" : " (structure only)");
        } elseif ($db->getType() == 'pgsql') {
            // PostgreSQL copy
            $db->query("CREATE TABLE \"$target_table\" (LIKE \"$source_table\" INCLUDING ALL)");
            if ($copy_data) {
                $db->query("INSERT INTO \"$target_table\" SELECT * FROM \"$source_table\"");
            }
            $success_message = "Table copied successfully" . ($copy_data ? " with data" : " (structure only)");
        } elseif ($db->getType() == 'sqlite') {
            // SQLite copy
            $create_sql = $db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='$source_table'")->fetch();
            if ($create_sql) {
                $new_create = str_replace("CREATE TABLE `$source_table`", "CREATE TABLE `$target_table`", $create_sql['sql']);
                $db->query($new_create);
                if ($copy_data) {
                    $db->query("INSERT INTO `$target_table` SELECT * FROM `$source_table`");
                }
                $success_message = "Table copied successfully" . ($copy_data ? " with data" : " (structure only)");
            }
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Handle Move Table
if (isset($_POST['move_table'])) {
    $db = get_connection();
    $source_db = get_post('source_db');
    $source_table = get_post('source_table');
    $target_db = get_post('target_db');
    $target_table = get_post('target_table');

    try {
        if ($db->getType() == 'mysql') {
            // Move table in MySQL
            if ($source_db == $target_db && $source_table != $target_table) {
                // Just rename within same database
                $db->query("RENAME TABLE `$source_table` TO `$target_table`");
            } else {
                // Move to different database
                $db->query("CREATE TABLE `$target_db`.`$target_table` LIKE `$source_db`.`$source_table`");
                $db->query("INSERT INTO `$target_db`.`$target_table` SELECT * FROM `$source_db`.`$source_table`");
                $db->query("DROP TABLE `$source_db`.`$source_table`");
            }
            $success_message = "Table moved successfully from '$source_db.$source_table' to '$target_db.$target_table'";
            redirect("?page=browse&db=" . urlencode($target_db) . "&table=" . urlencode($target_table));
        } else {
            $error_message = "Move table is only supported for MySQL databases";
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Handle Bulk Actions
if (isset($_POST['bulk_action']) && isset($_POST['selected'])) {
    $db = get_connection();
    $action = get_post('bulk_action');
    $selected = $_POST['selected'];
    $database = get_post('database', '');
    $success_count = 0;
    $errors = [];

    foreach ($selected as $item) {
        try {
            switch ($action) {
                case 'drop':
                    if ($database) {
                        // Drop tables
                        $db->query("DROP TABLE `$item`");
                        $success_count++;
                    } else {
                        // Drop databases
                        if ($db->getType() == 'mysql') {
                            $db->query("DROP DATABASE `$item`");
                        } elseif ($db->getType() == 'pgsql') {
                            $db->query("DROP DATABASE \"$item\"");
                        }
                        $success_count++;
                    }
                    break;

                case 'truncate':
                    // Truncate tables
                    if ($db->getType() == 'sqlite') {
                        $db->query("DELETE FROM `$item`");
                    } else {
                        $db->query("TRUNCATE TABLE `$item`");
                    }
                    $success_count++;
                    break;
            }
        } catch (Exception $e) {
            $errors[] = "$item: " . $e->getMessage();
        }
    }

    if ($success_count > 0) {
        $success_message = ucfirst($action) . " completed successfully on $success_count item(s)";
    }
    if (!empty($errors)) {
        $error_message = "Errors occurred: " . implode("; ", $errors);
    }
}

// Handle Global Search
$global_search_results = [];
if (isset($_POST['global_search'])) {
    $db = get_connection();
    $search_term = get_post('search_term');
    $search_database = get_post('search_database');
    $search_tables = get_post('search_tables', []);

    if ($search_term && $search_database) {
        $db->getPdo()->query("USE `$search_database`");

        if (empty($search_tables)) {
            // Search all tables if none selected
            $search_tables = $db->getTables($search_database);
        }

        foreach ($search_tables as $table) {
            try {
                $columns = $db->getColumns($table);
                $where_clauses = [];

                // Build WHERE clause for all text columns
                foreach ($columns as $column) {
                    $type = strtolower($column['type']);
                    if (preg_match('~char|text|enum|set~', $type)) {
                        $where_clauses[] = "`{$column['name']}` LIKE " . $db->getPdo()->quote("%$search_term%");
                    }
                }

                if (!empty($where_clauses)) {
                    $sql = "SELECT * FROM `$table` WHERE " . implode(' OR ', $where_clauses) . " LIMIT 100";
                    $results = $db->query($sql);

                    if ($results && $results->rowCount() > 0) {
                        $global_search_results[$table] = [
                            'count' => $results->rowCount(),
                            'data' => $results->fetchAll()
                        ];
                    }
                }
            } catch (Exception $e) {
                // Skip tables that can't be searched
                continue;
            }
        }
    }
}

// Page routing
$page = get_get('page', 'login');
$db = is_logged_in() ? get_connection() : null;
// Get current theme and language from session/cookie
$current_theme = isset($_COOKIE['dbadmin_theme']) ? $_COOKIE['dbadmin_theme'] : 'light';
$current_lang = isset($_COOKIE['dbadmin_lang']) ? $_COOKIE['dbadmin_lang'] : 'en';

// Handle theme change
if (get_get('set_theme')) {
    $theme = get_get('set_theme');
    if (in_array($theme, ['light', 'dark', 'blue', 'green', 'purple', 'sunset', 'slate'])) {
        setcookie('dbadmin_theme', $theme, time() + (365 * 24 * 60 * 60), '/');
        $current_theme = $theme;
    }
}

// Handle language change
if (get_get('set_lang')) {
    $lang = get_get('set_lang');
    if (in_array($lang, ['en', 'es', 'fr', 'de', 'pt', 'zh', 'ja', 'ar', 'it', 'ru', 'tr', 'hi', 'ko'])) {
        setcookie('dbadmin_lang', $lang, time() + (365 * 24 * 60 * 60), '/');
        $current_lang = $lang;
    }
}

$theme_options = [
    'light' => 'Light',
    'dark' => 'Dark',
    'blue' => 'Blue',
    'green' => 'Green',
    'purple' => 'Purple',
    'sunset' => 'Sunset',
    'slate' => 'Slate'
];

$language_options = [
    'en' => 'English',
    'es' => 'Español',
    'fr' => 'Français',
    'de' => 'Deutsch',
    'pt' => 'Português',
    'zh' => '中文',
    'ja' => '日本語',
    'ar' => 'العربية',
    'it' => 'Italiano',
    'ru' => 'Русский',
    'tr' => 'Türkçe',
    'hi' => 'हिन्दी',
    'ko' => '한국어'
];

?><!DOCTYPE html><html lang="<?php echo $current_lang; ?>" data-theme="<?php echo $current_theme; ?>"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Dabiro v<?php echo DB_ADMIN_VERSION; ?></title><link rel="icon" type="image/svg+xml" href="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHZpZXdCb3g9IjAgMCA4MCA4MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTI5LjE0NTUgMzQuNjMxNlY0MS44MTU4VjU5LjI2MzJMNDkgNDcuMjg5NVYzOC4wNTI2TDE5Ljk4MTggMjEuMjg5NUwyNi40NzI3IDE3LjUyNjNMNTYuMjU0NSAzNC42MzE2VjUxLjA1MjZMMjkuMTQ1NSA2NS4wNzg5TDI2LjQ3MjcgNjcuNDczN0wyMS41MDkxIDY1LjA3ODlWMzYuNzQzMkwxOC4wNzI3IDM0LjQ2MDVMMTQuNjM2NCAzMi4xNzc5VjYxLjMxNThMNyA1Ny4yMTA1VjI3LjEwNTNMMTEuOTYzNiAyNC43MTA1TDI5LjE0NTUgMzQuNjMxNloiIGZpbGw9InVybCgjcGFpbnQwX2xpbmVhcl8yXzI3KSIvPgo8cGF0aCBkPSJNMzkuMDcyNyA3NUwzMi45NjM2IDcxLjIzNjhMNjMuMTI3MyA1NC44MTU4VjI5LjE1NzlMMzQuNDkwOSAxMy4wNzg5TDM5LjA3MjcgMTBMNzAgMjcuMTA1M1Y1OS4yNjMyTDM5LjA3MjcgNzVaIiBmaWxsPSJ1cmwoI3BhaW50MV9saW5lYXJfMl8yNykiLz4KPGxpbmUgeTE9Ii0wLjUiIHgyPSI1Ljk2NzYyIiB5Mj0iLTAuNSIgdHJhbnNmb3JtPSJtYXRyaXgoMC44ODg2MzUgLTAuNDU4NjE2IDAuNTM0NjcgMC44NDUwNjEgNDcuOTA5MSAzOC43MzY4KSIgc3Ryb2tlPSJ3aGl0ZSIvPgo8bGluZSB5MT0iLTAuNSIgeDI9IjUuOTQ0MTQiIHkyPSItMC41IiB0cmFuc2Zvcm09Im1hdHJpeCgwLjg2Nzc0MiAwLjQ5NzAxNSAtMC41NzQ2NjIgMC44MTgzOTEgNjEuNTQ1NSA1NC40NzM3KSIgc3Ryb2tlPSJ3aGl0ZSIvPgo8bGluZSB5MT0iLTAuNSIgeDI9IjUuOTQ0MTQiIHkyPSItMC41IiB0cmFuc2Zvcm09Im1hdHJpeCgwLjg2Nzc0MiAwLjQ5NzAxNSAtMC41NzQ2NjIgMC44MTgzOTEgMTAuMDMwMyAzMC41MjYzKSIgc3Ryb2tlPSJ3aGl0ZSIvPgo8ZGVmcz4KPGxpbmVhckdyYWRpZW50IGlkPSJwYWludDBfbGluZWFyXzJfMjciIHgxPSIzOC41IiB5MT0iMTAiIHgyPSIzOC41IiB5Mj0iNzUiIGdyYWRpZW50VW5pdHM9InVzZXJTcGFjZU9uVXNlIj4KPHN0b3Agb2Zmc2V0PSIwLjEzOTQyMyIgc3RvcC1jb2xvcj0iI0ZGMDAwMCIvPgo8c3RvcCBvZmZzZXQ9IjAuODk0MjMxIiBzdG9wLWNvbG9yPSIjMDAxNUZGIi8+CjwvbGluZWFyR3JhZGllbnQ+CjxsaW5lYXJHcmFkaWVudCBpZD0icGFpbnQxX2xpbmVhcl8yXzI3IiB4MT0iMzguNSIgeTE9IjEwIiB4Mj0iMzguNSIgeTI9Ijc1IiBncmFkaWVudFVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+CjxzdG9wIG9mZnNldD0iMC4xMzk0MjMiIHN0b3AtY29sb3I9IiNGRjAwMDAiLz4KPHN0b3Agb2Zmc2V0PSIwLjg5NDIzMSIgc3RvcC1jb2xvcj0iIzAwMTVGRiIvPgo8L2xpbmVhckdyYWRpZW50Pgo8L2RlZnM+Cjwvc3ZnPgo="><link rel="icon" type="image/x-icon" href="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHZpZXdCb3g9IjAgMCA4MCA4MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTI5LjE0NTUgMzQuNjMxNlY0MS44MTU4VjU5LjI2MzJMNDkgNDcuMjg5NVYzOC4wNTI2TDE5Ljk4MTggMjEuMjg5NUwyNi40NzI3IDE3LjUyNjNMNTYuMjU0NSAzNC42MzE2VjUxLjA1MjZMMjkuMTQ1NSA2NS4wNzg5TDI2LjQ3MjcgNjcuNDczN0wyMS41MDkxIDY1LjA3ODlWMzYuNzQzMkwxOC4wNzI3IDM0LjQ2MDVMMTQuNjM2NCAzMi4xNzc5VjYxLjMxNThMNyA1Ny4yMTA1VjI3LjEwNTNMMTEuOTYzNiAyNC43MTA1TDI5LjE0NTUgMzQuNjMxNloiIGZpbGw9InVybCgjcGFpbnQwX2xpbmVhcl8yXzI3KSIvPgo8cGF0aCBkPSJNMzkuMDcyNyA3NUwzMi45NjM2IDcxLjIzNjhMNjMuMTI3MyA1NC44MTU4VjI5LjE1NzlMMzQuNDkwOSAxMy4wNzg5TDM5LjA3MjcgMTBMNzAgMjcuMTA1M1Y1OS4yNjMyTDM5LjA3MjcgNzVaIiBmaWxsPSJ1cmwoI3BhaW50MV9saW5lYXJfMl8yNykiLz4KPGxpbmUgeTE9Ii0wLjUiIHgyPSI1Ljk2NzYyIiB5Mj0iLTAuNSIgdHJhbnNmb3JtPSJtYXRyaXgoMC44ODg2MzUgLTAuNDU4NjE2IDAuNTM0NjcgMC44NDUwNjEgNDcuOTA5MSAzOC43MzY4KSIgc3Ryb2tlPSJ3aGl0ZSIvPgo8bGluZSB5MT0iLTAuNSIgeDI9IjUuOTQ0MTQiIHkyPSItMC41IiB0cmFuc2Zvcm09Im1hdHJpeCgwLjg2Nzc0MiAwLjQ5NzAxNSAtMC41NzQ2NjIgMC44MTgzOTEgNjEuNTQ1NSA1NC40NzM3KSIgc3Ryb2tlPSJ3aGl0ZSIvPgo8bGluZSB5MT0iLTAuNSIgeDI9IjUuOTQ0MTQiIHkyPSItMC41IiB0cmFuc2Zvcm09Im1hdHJpeCgwLjg2Nzc0MiAwLjQ5NzAxNSAtMC41NzQ2NjIgMC44MTgzOTEgMTAuMDMwMyAzMC41MjYzKSIgc3Ryb2tlPSJ3aGl0ZSIvPgo8ZGVmcz4KPGxpbmVhckdyYWRpZW50IGlkPSJwYWludDBfbGluZWFyXzJfMjciIHgxPSIzOC41IiB5MT0iMTAiIHgyPSIzOC41IiB5Mj0iNzUiIGdyYWRpZW50VW5pdHM9InVzZXJTcGFjZU9uVXNlIj4KPHN0b3Agb2Zmc2V0PSIwLjEzOTQyMyIgc3RvcC1jb2xvcj0iI0ZGMDAwMCIvPgo8c3RvcCBvZmZzZXQ9IjAuODk0MjMxIiBzdG9wLWNvbG9yPSIjMDAxNUZGIi8+CjwvbGluZWFyR3JhZGllbnQ+CjxsaW5lYXJHcmFkaWVudCBpZD0icGFpbnQxX2xpbmVhcl8yXzI3IiB4MT0iMzguNSIgeTE9IjEwIiB4Mj0iMzguNSIgeTI9Ijc1IiBncmFkaWVudFVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+CjxzdG9wIG9mZnNldD0iMC4xMzk0MjMiIHN0b3AtY29sb3I9IiNGRjAwMDAiLz4KPHN0b3Agb2Zmc2V0PSIwLjg5NDIzMSIgc3RvcC1jb2xvcj0iIzAwMTVGRiIvPgo8L2xpbmVhckdyYWRpZW50Pgo8L2RlZnM+Cjwvc3ZnPgo="><link rel="apple-touch-icon" href="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHZpZXdCb3g9IjAgMCA4MCA4MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHBhdGggZD0iTTI5LjE0NTUgMzQuNjMxNlY0MS44MTU4VjU5LjI2MzJMNDkgNDcuMjg5NVYzOC4wNTI2TDE5Ljk4MTggMjEuMjg5NUwyNi40NzI3IDE3LjUyNjNMNTYuMjU0NSAzNC42MzE2VjUxLjA1MjZMMjkuMTQ1NSA2NS4wNzg5TDI2LjQ3MjcgNjcuNDczN0wyMS41MDkxIDY1LjA3ODlWMzYuNzQzMkwxOC4wNzI3IDM0LjQ2MDVMMTQuNjM2NCAzMi4xNzc5VjYxLjMxNThMNyA1Ny4yMTA1VjI3LjEwNTNMMTEuOTYzNiAyNC43MTA1TDI5LjE0NTUgMzQuNjMxNloiIGZpbGw9InVybCgjcGFpbnQwX2xpbmVhcl8yXzI3KSIvPgo8cGF0aCBkPSJNMzkuMDcyNyA3NUwzMi45NjM2IDcxLjIzNjhMNjMuMTI3MyA1NC44MTU4VjI5LjE1NzlMMzQuNDkwOSAxMy4wNzg5TDM5LjA3MjcgMTBMNzAgMjcuMTA1M1Y1OS4yNjMyTDM5LjA3MjcgNzVaIiBmaWxsPSJ1cmwoI3BhaW50MV9saW5lYXJfMl8yNykiLz4KPGxpbmUgeTE9Ii0wLjUiIHgyPSI1Ljk2NzYyIiB5Mj0iLTAuNSIgdHJhbnNmb3JtPSJtYXRyaXgoMC44ODg2MzUgLTAuNDU4NjE2IDAuNTM0NjcgMC44NDUwNjEgNDcuOTA5MSAzOC43MzY4KSIgc3Ryb2tlPSJ3aGl0ZSIvPgo8bGluZSB5MT0iLTAuNSIgeDI9IjUuOTQ0MTQiIHkyPSItMC41IiB0cmFuc2Zvcm09Im1hdHJpeCgwLjg2Nzc0MiAwLjQ5NzAxNSAtMC41NzQ2NjIgMC44MTgzOTEgNjEuNTQ1NSA1NC40NzM3KSIgc3Ryb2tlPSJ3aGl0ZSIvPgo8bGluZSB5MT0iLTAuNSIgeDI9IjUuOTQ0MTQiIHkyPSItMC41IiB0cmFuc2Zvcm09Im1hdHJpeCgwLjg2Nzc0MiAwLjQ5NzAxNSAtMC41NzQ2NjIgMC44MTgzOTEgMTAuMDMwMyAzMC41MjYzKSIgc3Ryb2tlPSJ3aGl0ZSIvPgo8ZGVmcz4KPGxpbmVhckdyYWRpZW50IGlkPSJwYWludDBfbGluZWFyXzJfMjciIHgxPSIzOC41IiB5MT0iMTAiIHgyPSIzOC41IiB5Mj0iNzUiIGdyYWRpZW50VW5pdHM9InVzZXJTcGFjZU9uVXNlIj4KPHN0b3Agb2Zmc2V0PSIwLjEzOTQyMyIgc3RvcC1jb2xvcj0iI0ZGMDAwMCIvPgo8c3RvcCBvZmZzZXQ9IjAuODk0MjMxIiBzdG9wLWNvbG9yPSIjMDAxNUZGIi8+CjwvbGluZWFyR3JhZGllbnQ+CjxsaW5lYXJHcmFkaWVudCBpZD0icGFpbnQxX2xpbmVhcl8yXzI3IiB4MT0iMzguNSIgeTE9IjEwIiB4Mj0iMzguNSIgeTI9Ijc1IiBncmFkaWVudFVuaXRzPSJ1c2VyU3BhY2VPblVzZSI+CjxzdG9wIG9mZnNldD0iMC4xMzk0MjMiIHN0b3AtY29sb3I9IiNGRjAwMDAiLz4KPHN0b3Agb2Zmc2V0PSIwLjg5NDIzMSIgc3RvcC1jb2xvcj0iIzAwMTVGRiIvPgo8L2xpbmVhckdyYWRpZW50Pgo8L2RlZnM+Cjwvc3ZnPgo="><style> @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');:root,[data-theme="light"]{--primary:#2563eb;--primary-dark:#1d4ed8;--primary-light:#3b82f6;--secondary:#7c3aed;--success:#10b981;--danger:#ef4444;--warning:#f59e0b;--info:#06b6d4;--dark:#0f172a;--dark-light:#1e293b;--dark-lighter:#334155;--light:#f8fafc;--light-dark:#e2e8f0;--border:#cbd5e1;--text:#1e293b;--text-light:#64748b;--text-lighter:#94a3b8;--sidebar-width:280px;--header-height:70px;--shadow-sm:0 1px 2px 0 rgba(0,0,0,0.05);--shadow-md:0 4px 6px -1px rgba(0,0,0,0.1);--shadow-lg:0 10px 15px -3px rgba(0,0,0,0.1);--shadow-xl:0 20px 25px -5px rgba(0,0,0,0.1);--radius:16px;--radius-sm:8px;--transition:all 0.3s cubic-bezier(0.4,0,0.2,1);--bg-gradient:linear-gradient(135deg,#667eea 0%,#764ba2 100%);--card-bg:rgba(255,255,255,0.98);--sidebar-bg:white;--main-bg:#f1f5f9;--input-bg:white;--table-header-bg:var(--light);--table-row-hover:var(--light)}[data-theme="dark"]{--primary:#3b82f6;--primary-dark:#2563eb;--primary-light:#60a5fa;--secondary:#8b5cf6;--success:#22c55e;--danger:#f87171;--warning:#fbbf24;--info:#22d3ee;--dark:#f8fafc;--dark-light:#e2e8f0;--dark-lighter:#cbd5e1;--light:#1e293b;--light-dark:#334155;--border:#475569;--text:#f1f5f9;--text-light:#cbd5e1;--text-lighter:#94a3b8;--shadow-sm:0 1px 2px 0 rgba(0,0,0,0.3);--shadow-md:0 4px 6px -1px rgba(0,0,0,0.4);--shadow-lg:0 10px 15px -3px rgba(0,0,0,0.4);--shadow-xl:0 20px 25px -5px rgba(0,0,0,0.4);--bg-gradient:linear-gradient(135deg,#1e293b 0%,#0f172a 100%);--card-bg:#1e293b;--sidebar-bg:#0f172a;--main-bg:#0f172a;--input-bg:#334155;--table-header-bg:#334155;--table-row-hover:#334155}[data-theme="blue"]{--primary:#0ea5e9;--primary-dark:#0284c7;--primary-light:#38bdf8;--secondary:#6366f1;--bg-gradient:linear-gradient(135deg,#0ea5e9 0%,#6366f1 100%);--card-bg:rgba(255,255,255,0.98);--sidebar-bg:#f0f9ff;--main-bg:#e0f2fe;--input-bg:#ffffff;--table-header-bg:#e0f2fe;--table-row-hover:#bae6fd}[data-theme="green"]{--primary:#10b981;--primary-dark:#059669;--primary-light:#34d399;--secondary:#14b8a6;--bg-gradient:linear-gradient(135deg,#10b981 0%,#14b8a6 100%);--card-bg:rgba(255,255,255,0.98);--sidebar-bg:#ecfdf5;--main-bg:#d1fae5;--input-bg:#ffffff;--table-header-bg:#d1fae5;--table-row-hover:#bbf7d0}[data-theme="purple"]{--primary:#a855f7;--primary-dark:#9333ea;--primary-light:#c084fc;--secondary:#ec4899;--bg-gradient:linear-gradient(135deg,#a855f7 0%,#ec4899 100%);--card-bg:rgba(255,255,255,0.98);--sidebar-bg:#fdf4ff;--main-bg:#f5e6ff;--input-bg:#ffffff;--table-header-bg:#f3e8ff;--table-row-hover:#e9d5ff}[data-theme="sunset"]{--primary:#fb923c;--primary-dark:#ea580c;--primary-light:#fdba74;--secondary:#f87171;--bg-gradient:linear-gradient(135deg,#fb923c 0%,#f43f5e 100%);--card-bg:rgba(255,255,255,0.98);--sidebar-bg:#fff7ed;--main-bg:#ffe4d6;--input-bg:#ffffff;--table-header-bg:#ffe7d6;--table-row-hover:#ffd4b8}[data-theme="slate"]{--primary:#475569;--primary-dark:#334155;--primary-light:#94a3b8;--secondary:#0ea5e9;--bg-gradient:linear-gradient(135deg,#475569 0%,#0f172a 100%);--card-bg:#1f2937;--sidebar-bg:#111827;--main-bg:#0f172a;--input-bg:#273449;--text:#e2e8f0;--text-light:#cbd5f5;--text-lighter:#94a3b8;--border:#2f3b52;--table-header-bg:#2b374a;--table-row-hover:#1c2533}*{margin:0;padding:0;box-sizing:border-box}body{font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:var(--bg-gradient);color:var(--text);line-height:1.6;min-height:100vh;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}.hidden{display:none !important}.column-option-disabled{opacity:0.5}.login-container{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}.login-box{background:var(--card-bg);backdrop-filter:blur(20px);padding:28px;border-radius:var(--radius);box-shadow:var(--shadow-xl);max-width:420px;width:100%;animation:slideUp 0.5s ease;border:1px solid var(--border)}@keyframes slideUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}.logo{text-align:center;margin-bottom:24px;display:flex;flex-direction:column;align-items:center;justify-content:center}.logo-icon{width:80px;height:80px;margin:0 auto 12px;background:linear-gradient(135deg,var(--primary),var(--secondary));border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;position:relative;box-shadow:var(--shadow-lg)}.logo h1{font-size:22px;font-weight:800;color:var(--dark);margin-bottom:6px;letter-spacing:-0.5px}.logo p{color:var(--text-light);font-size:13px;font-weight:500}.app-container{display:flex;min-height:100vh}.mobile-menu-toggle{display:none;position:fixed;top:20px;left:20px;z-index:1000;background:var(--card-bg);border:1px solid var(--border);padding:12px;border-radius:var(--radius-sm);box-shadow:var(--shadow-md);cursor:pointer}.mobile-menu-toggle span{display:block;width:24px;height:2px;background:var(--text);margin:5px 0;transition:var(--transition)}.sidebar{width:var(--sidebar-width);background:var(--sidebar-bg);position:fixed;height:100vh;box-shadow:var(--shadow-lg);z-index:100;border-right:1px solid var(--border);transition:var(--transition);display:flex;flex-direction:column;overflow:hidden}.sidebar-header{padding:24px;border-bottom:1px solid var(--light-dark);flex-shrink:0}.sidebar-logo{display:flex;align-items:center;gap:12px}.sidebar-logo-icon{width:40px;height:40px;background:linear-gradient(135deg,var(--primary),var(--secondary));border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;position:relative}.sidebar-title h2{font-size:18px;font-weight:700;color:var(--dark);line-height:1.2}.sidebar-title p{font-size:12px;color:var(--text-light);margin-top:2px}.sidebar-nav{padding:24px 0;flex:1;overflow-y:auto;overflow-x:hidden}.sidebar-nav::-webkit-scrollbar{width:6px}.sidebar-nav::-webkit-scrollbar-track{background:var(--light)}.sidebar-nav::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px}.sidebar-nav::-webkit-scrollbar-thumb:hover{background:var(--text-light)}.nav-item{display:flex;align-items:center;padding:14px 24px;color:var(--text);text-decoration:none;transition:var(--transition);border-left:3px solid transparent;font-weight:500;font-size:15px}.nav-item:hover{background:var(--light);color:var(--primary);border-left-color:var(--primary)}.nav-item.active{background:linear-gradient(90deg,rgba(37,99,235,0.1),transparent);color:var(--primary);border-left-color:var(--primary);font-weight:600}.nav-divider{height:1px;background:var(--light-dark);margin:12px 0}.nav-section{margin:8px 0}.nav-section-header{display:flex;align-items:center;padding:12px 24px;color:var(--dark);font-weight:600;font-size:14px;cursor:pointer;transition:var(--transition);gap:8px}.nav-section-header:hover{background:var(--light)}.nav-arrow{transition:transform 0.3s ease;font-size:10px;color:var(--text-light)}.nav-section-header.collapsed .nav-arrow{transform:rotate(-90deg)}.nav-section-content{max-height:0;overflow:hidden;transition:max-height 0.3s ease}.nav-section-content.active{max-height:500px;overflow-y:auto;overflow-x:hidden}.nav-section-content::-webkit-scrollbar{width:4px}.nav-section-content::-webkit-scrollbar-track{background:transparent}.nav-section-content::-webkit-scrollbar-thumb{background:var(--border);border-radius:2px}.nav-section-content::-webkit-scrollbar-thumb:hover{background:var(--text-light)}.nav-sub-item{display:flex;align-items:center;padding:10px 24px 10px 44px;color:var(--text);text-decoration:none;transition:var(--transition);border-left:3px solid transparent;font-size:13px}.nav-sub-item:hover{background:var(--light);color:var(--primary)}.nav-sub-item.active{background:linear-gradient(90deg,rgba(37,99,235,0.08),transparent);color:var(--primary);border-left-color:var(--primary);font-weight:500}.sidebar-footer{padding:20px 24px;border-top:1px solid var(--light-dark);background:var(--sidebar-bg);flex-shrink:0}.user-info{display:flex;align-items:center;padding:16px;background:var(--light);border-radius:var(--radius-sm);margin-bottom:16px}.user-avatar{width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--secondary));display:flex;align-items:center;justify-content:center;font-weight:700;color:white;margin-right:12px;font-size:16px}.user-details{flex:1;min-width:0}.user-details .name{font-size:14px;font-weight:600;color:var(--dark);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.user-details .role{font-size:12px;color:var(--text-light);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.main-content{flex:1;margin-left:var(--sidebar-width);background:var(--main-bg);min-height:100vh;transition:var(--transition)}.top-bar{background:var(--card-bg);height:var(--header-height);display:flex;align-items:center;justify-content:space-between;padding:0 30px;box-shadow:var(--shadow-sm);position:sticky;top:0;z-index:50;border-bottom:1px solid var(--border)}.breadcrumb{display:flex;align-items:center;gap:8px;color:var(--text-light);font-size:14px;font-weight:500}.breadcrumb a{color:var(--primary);text-decoration:none;transition:var(--transition)}.breadcrumb a:hover{color:var(--primary-dark)}.breadcrumb .separator{color:var(--border)}.system-info{display:flex;align-items:center;gap:20px}.top-bar-preferences{display:flex;align-items:center;gap:12px}.preference-select{padding:8px 12px;border:1px solid var(--border);border-radius:var(--radzius-sm);background:var(--input-bg);color:var(--text);font-size:13px;min-width:140px;transition:var(--transition)}.preference-select:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(37,99,235,0.12)}.system-time{color:var(--text-light);font-size:14px;font-weight:500;font-variant-numeric:tabular-nums}.content-area{padding:30px}.page-header{margin-bottom:24px}.page-header h1{font-size:28px;font-weight:800;color:var(--dark);margin-bottom:6px;letter-spacing:-0.5px}.page-header p{color:var(--text-light);font-size:14px;margin:0}.card{background:var(--card-bg);border-radius:var(--radius);box-shadow:var(--shadow-sm);padding:24px;margin-bottom:20px;transition:var(--transition);border:1px solid var(--border)}.card:hover{box-shadow:var(--shadow-md)}.card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;padding-bottom:16px;border-bottom:2px solid var(--light-dark)}.card-title{font-size:16px;font-weight:700;color:var(--dark);margin:0}.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px}.grid-card{background:var(--card-bg);border-radius:var(--radius);padding:24px;box-shadow:var(--shadow-sm);text-decoration:none;color:var(--text);transition:var(--transition);border:2px solid var(--border);position:relative;overflow:hidden;display:flex;align-items:center;gap:16px}.grid-card::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,var(--primary),var(--secondary));transform:scaleX(0);transition:var(--transition)}.grid-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-lg);border-color:var(--primary)}.grid-card:hover::before{transform:scaleX(1)}.grid-card h3{font-size:18px;font-weight:700;color:var(--dark);margin-bottom:8px}.grid-card p{font-size:14px;color:var(--text-light)}.list-view{display:flex;flex-direction:column;gap:12px}.list-view .grid-card{padding:16px 24px;transform:none}.list-view .grid-card:hover{transform:translateX(4px)}.list-view .grid-card h3{margin-bottom:0;font-size:16px}.list-view .grid-card p{display:none}.view-toggle{display:flex;gap:8px;background:var(--card-bg);padding:6px;border-radius:var(--radius-sm);border:2px solid var(--border)}.view-toggle-btn{padding:8px 12px;border:none;background:transparent;color:var(--text-light);cursor:pointer;border-radius:6px;transition:var(--transition);font-weight:600;font-size:13px;display:flex;align-items:center;gap:8px}.view-toggle-btn svg{width:16px;height:16px}.view-toggle-btn:hover{color:var(--primary);background:var(--table-row-hover)}.view-toggle-btn.active{background:var(--primary);color:white}.page-header-row{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px;gap:16px}.page-header-row .page-header{margin-bottom:0;flex:1}.form-group{margin-bottom:24px}label{display:block;margin-bottom:8px;font-weight:600;color:var(--text);font-size:14px}input[type="text"],input[type="password"],input[type="number"],input[type="file"],select,textarea{width:100%;padding:14px 16px;border:2px solid var(--border);border-radius:var(--radius-sm);font-size:14px;font-family:inherit;transition:var(--transition);background:var(--input-bg);color:var(--text)}input:focus,select:focus,textarea:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 4px rgba(37,99,235,0.1)}textarea{font-family:'Courier New',monospace;resize:vertical;min-height:140px}.btn{display:inline-flex;align-items:center;justify-content:center;padding:12px 24px;border:none;border-radius:var(--radius-sm);font-size:14px;font-weight:600;text-decoration:none;cursor:pointer;transition:var(--transition);gap:8px;font-family:inherit;height:44px;line-height:1;white-space:nowrap}.btn-primary{background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:white;box-shadow:0 4px 14px 0 rgba(37,99,235,0.3)}.btn-primary:hover{transform:translateY(-2px);box-shadow:0 6px 20px 0 rgba(37,99,235,0.4)}.btn-success{background:linear-gradient(135deg,var(--success),#059669);color:white;box-shadow:0 4px 14px 0 rgba(16,185,129,0.3)}.btn-success:hover{transform:translateY(-2px);box-shadow:0 6px 20px 0 rgba(16,185,129,0.4)}.btn-danger{background:linear-gradient(135deg,var(--danger),#dc2626);color:white;box-shadow:0 4px 14px 0 rgba(239,68,68,0.3)}.btn-danger:hover{transform:translateY(-2px);box-shadow:0 6px 20px 0 rgba(239,68,68,0.4)}.btn-secondary{background:var(--card-bg);color:var(--text);border:2px solid var(--border)}.btn-secondary:hover{background:var(--table-row-hover);border-color:var(--primary);color:var(--primary)}.btn-sm{padding:8px 16px;font-size:13px;height:36px}.btn-group{display:flex;gap:12px;margin-bottom:24px;flex-wrap:wrap;align-items:center}.loader-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.4);display:none;align-items:center;justify-content:center;z-index:9999;backdrop-filter:blur(2px)}.loader-overlay.active{display:flex}.loader-content{display:flex;flex-direction:column;align-items:center;gap:20px}.spinner{width:50px;height:50px;border:4px solid rgba(255,255,255,0.3);border-top:4px solid white;border-radius:50%;animation:spin 1s linear infinite}@keyframes spin{0%{transform:rotate(0deg)}100%{transform:rotate(360deg)}}.loader-text{color:white;font-weight:600;font-size:16px;animation:pulse 1.5s ease-in-out infinite}@keyframes pulse{0%,100%{opacity:1}50%{opacity:0.6}}.toast-container{position:fixed;top:20px;right:20px;z-index:10000;pointer-events:none}@media (max-width:640px){.toast-container{left:20px;right:20px;top:10px}}.toast{background:white;border-radius:var(--radius-sm);padding:16px 24px;margin-bottom:12px;box-shadow:var(--shadow-lg);display:flex;align-items:center;gap:12px;min-width:320px;animation:slideIn 0.3s ease;pointer-events:auto;border-left:4px solid}@media (max-width:640px){.toast{min-width:auto;width:100%}}@keyframes slideIn{from{opacity:0;transform:translateX(400px)}to{opacity:1;transform:translateX(0)}}@keyframes slideOut{from{opacity:1;transform:translateX(0)}to{opacity:0;transform:translateX(400px)}}.toast.removing{animation:slideOut 0.3s ease}.toast-success{border-left-color:var(--success);background:#d1fae5;color:#065f46}.toast-error{border-left-color:var(--danger);background:#fee2e2;color:#991b1b}.toast-info{border-left-color:var(--info);background:#cffafe;color:#164e63}.toast-warning{border-left-color:var(--warning);background:#fef3c7;color:#78350f}.toast-icon{font-weight:700;font-size:20px;flex-shrink:0}.toast-message{flex:1;font-weight:500}.toast-close{background:none;border:none;font-size:20px;cursor:pointer;opacity:0.7;transition:opacity 0.2s;padding:0;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:inherit}.toast-close:hover{opacity:1}.table-container{background:var(--card-bg);border-radius:var(--radius);box-shadow:var(--shadow-sm);overflow:hidden;border:1px solid var(--border);margin-bottom:24px;width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}table{width:100%;border-collapse:collapse;min-width:600px}thead{background:var(--table-header-bg);color:var(--text)}th{padding:18px 16px;text-align:left;font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:0.5px;border:1px solid var(--border)}td{padding:16px;border:1px solid var(--light-dark);font-size:14px}tbody tr{transition:var(--transition)}tbody tr:hover{background:var(--table-row-hover)}.null-value{color:#9ca3af;font-style:italic;font-size:13px}.empty-value{color:#d1d5db;font-style:italic;font-size:13px}.alert{padding:18px 24px;border-radius:var(--radius-sm);margin-bottom:24px;display:flex;align-items:flex-start;gap:12px;animation:slideDown 0.3s ease;font-weight:500}@keyframes slideDown{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}.alert-success{background:#d1fae5;color:#065f46;border-left:4px solid var(--success)}.alert-error{background:#fee2e2;color:#991b1b;border-left:4px solid var(--danger)}.pagination{display:flex;justify-content:center;align-items:center;gap:8px;margin-top:30px;flex-wrap:wrap}.pagination a{padding:12px 18px;background:var(--card-bg);color:var(--text);text-decoration:none;border-radius:var(--radius-sm);border:2px solid var(--border);font-weight:600;transition:var(--transition);font-size:14px}.pagination a:hover{border-color:var(--primary);color:var(--primary);background:var(--table-row-hover)}.pagination a.active{background:var(--primary);color:white;border-color:var(--primary)}.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;margin-bottom:24px}.stat-card{background:var(--card-bg);border-radius:var(--radius);padding:24px;box-shadow:var(--shadow-sm);position:relative;overflow:hidden;border:1px solid var(--border)}.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:linear-gradient(90deg,var(--primary),var(--secondary))}.stat-card .stat-value{font-size:36px;font-weight:800;color:var(--dark);margin-bottom:8px;letter-spacing:-1px}.stat-card .stat-label{font-size:14px;color:var(--text-light);font-weight:600;text-transform:uppercase;letter-spacing:0.5px}@media (max-width:1024px){:root{--sidebar-width:260px}.content-area{padding:20px}.top-bar{flex-wrap:wrap;gap:10px}.top-bar-preferences{width:100%;justify-content:flex-start}}@media (max-width:768px){.mobile-menu-toggle{display:block}.sidebar{transform:translateX(-100%)}.sidebar.active{transform:translateX(0)}.main-content{margin-left:0}.top-bar{padding:0 20px 0 70px}.login-box{padding:40px 30px}.grid{grid-template-columns:1fr}.stats-grid{grid-template-columns:1fr}.btn-group{flex-direction:column}.btn-group .btn{width:100%}.page-header h1{font-size:24px}.breadcrumb{font-size:12px}.system-info{gap:10px}.system-time{font-size:12px}.page-header-row{flex-direction:column;align-items:stretch}.view-toggle{width:100%}.view-toggle-btn{flex:1;justify-content:center}}@media (max-width:480px){.content-area{padding:15px}.card{padding:20px}.table-container{font-size:13px;margin:-24px -24px -20px -24px;border-radius:0;border-left:none;border-right:none}th,td{padding:12px 8px}}.table-container::-webkit-scrollbar{height:6px}.table-container::-webkit-scrollbar-track{background:var(--light);border-radius:3px}.table-container::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px}.table-container::-webkit-scrollbar-thumb:hover{background:var(--text-light)}.browse-table-container{display:block;overflow-x:auto;-webkit-overflow-scrolling:touch;width:100%;max-width:100%;overflow-y:visible}#data-table{width:100%;min-width:600px;table-layout:auto;border-collapse:collapse}.dropdown{position:relative;display:inline-block}.dropdown-toggle{background:var(--input-bg);color:var(--text);border:2px solid var(--border);padding:12px 24px;border-radius:var(--radius-sm);cursor:pointer;font-weight:600;font-size:14px;display:inline-flex;align-items:center;justify-content:center;gap:8px;transition:var(--transition);height:44px;line-height:1;white-space:nowrap}.dropdown-toggle:hover{border-color:var(--primary);color:var(--primary);background:var(--table-row-hover)}.dropdown-toggle::after{content:'▼';font-size:10px}.dropdown-menu{position:absolute;top:80%;right:0;background:var(--card-bg);border:2px solid var(--border);border-radius:var(--radius-sm);box-shadow:var(--shadow-lg);min-width:200px;z-index:1000;display:none;margin-top:8px;overflow:hidden}.dropdown.open .dropdown-menu{display:block}.dropdown:hover .dropdown-menu,.dropdown-menu:hover{display:block}.dropdown-item{display:block;padding:12px 20px;color:var(--text);text-decoration:none;transition:var(--transition);border-bottom:1px solid var(--light-dark);font-size:14px;font-weight:500;background:var(--card-bg)}.dropdown-item:last-child{border-bottom:none}.dropdown-item:hover{background:var(--table-row-hover);color:var(--primary)}.dropdown-item.danger:hover{background:#fee2e2;color:var(--danger)}.dropdown-item.active{background:var(--primary);color:white}.dropdown-item.active:hover{background:var(--primary-dark);color:white}.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);z-index:2000;align-items:center;justify-content:center;padding:20px}.modal.active{display:flex}.modal-content{background:var(--card-bg);border-radius:var(--radius);max-width:600px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:var(--shadow-xl);animation:modalSlideUp 0.3s ease}@keyframes modalSlideUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}.modal-header{padding:20px 24px;border-bottom:2px solid var(--light-dark);display:flex;align-items:center;justify-content:space-between}.modal-header h3{font-size:18px;font-weight:700;color:var(--dark);margin:0}.modal-close{background:none;border:none;font-size:28px;color:var(--text-light);cursor:pointer;line-height:1;padding:0;width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:50%;transition:var(--transition)}.modal-close:hover{background:var(--light);color:var(--danger)}.modal-body{padding:24px}.modal-footer{padding:16px 24px;border-top:2px solid var(--light-dark);display:flex;gap:12px;justify-content:flex-end}.text-center{text-align:center}.mb-0{margin-bottom:0}.mb-2{margin-bottom:16px}.mb-3{margin-bottom:24px}.mt-2{margin-top:16px}.mt-3{margin-top:24px}</style></head><body><!-- Loader Overlay --><div class="loader-overlay" id="loaderOverlay"><div class="loader-content"><div class="spinner"></div><div class="loader-text" id="loaderText">Processing...</div></div></div><!-- Toast Container --><div class="toast-container" id="toastContainer"></div><?php if (!is_logged_in()): ?><!-- Login Page --><div class="login-container"><div class="login-box"><div class="logo"> <div class="logo-icon" style="width:56px;height:56px;margin:0;display:flex;align-items:center;justify-content:center;"><svg width="48" height="48" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M29.1455 34.6316V41.8158V59.2632L49 47.2895V38.0526L19.9818 21.2895L26.4727 17.5263L56.2545 34.6316V51.0526L29.1455 65.0789L26.4727 67.4737L21.5091 65.0789V36.7432L18.0727 34.4605L14.6364 32.1779V61.3158L7 57.2105V27.1053L11.9636 24.7105L29.1455 34.6316Z" fill="url(#paint0_linear_2_27)"/><path d="M39.0727 75L32.9636 71.2368L63.1273 54.8158V29.1579L34.4909 13.0789L39.0727 10L70 27.1053V59.2632L39.0727 75Z" fill="url(#paint1_linear_2_27)"/><line y1="-0.5" x2="5.96762" y2="-0.5" transform="matrix(0.888635 -0.458616 0.53467 0.845061 47.9091 38.7368)" stroke="white"/><line y1="-0.5" x2="5.94414" y2="-0.5" transform="matrix(0.867742 0.497015 -0.574662 0.818391 61.5455 54.4737)" stroke="white"/><line y1="-0.5" x2="5.94414" y2="-0.5" transform="matrix(0.867742 0.497015 -0.574662 0.818391 10.0303 30.5263)" stroke="white"/><defs><linearGradient id="paint0_linear_2_27" x1="38.5" y1="10" x2="38.5" y2="75" gradientUnits="userSpaceOnUse"><stop offset="0.139423" stop-color="#FF0000"/><stop offset="0.894231" stop-color="#0015FF"/></linearGradient><linearGradient id="paint1_linear_2_27" x1="38.5" y1="10" x2="38.5" y2="75" gradientUnits="userSpaceOnUse"><stop offset="0.139423" stop-color="#FF0000"/><stop offset="0.894231" stop-color="#0015FF"/></linearGradient></defs></svg></div><h1 data-i18n="app_name">Dabiro</h1><p data-i18n="app_tagline">Professional database management interface</p></div><?php if (isset($error)): ?><div class="alert alert-error"><span><?php echo h($error); ?></span></div><?php endif; ?><form method="post" onsubmit="showLoader('Connecting to database...')"><div class="form-group" style="display:grid;grid-template-columns:1fr;gap:10px;text-align:left"><div style="display:flex;align-items:center;gap:12px;justify-content:center;flex-direction:column"><label data-i18n="database_type_label" style="width:100%;text-align:left">Database Type</label><select name="db_type" required style="width:100%"><option value="mysql">MySQL / MariaDB</option><option value="pgsql">PostgreSQL</option><option value="sqlite">SQLite</option></select></div></div><div class="form-group" style="display:grid;grid-template-columns:1fr;gap:10px"><div><label data-i18n="host_label">Host / File Path</label><input type="text" name="db_host" value="localhost" required placeholder="localhost" data-i18n-placeholder="host_placeholder"></div></div><div class="form-group" style="display:grid;grid-template-columns:1fr;gap:10px"><div><label data-i18n="username_label">Username</label><input type="text" name="db_user" value="root" placeholder="root" data-i18n-placeholder="username_placeholder"></div></div><div class="form-group" style="display:grid;grid-template-columns:1fr;gap:10px"><div><label data-i18n="password_label">Password</label><input type="password" name="db_pass" placeholder="Enter password" data-i18n-placeholder="password_placeholder"></div></div><div class="form-group" style="display:grid;grid-template-columns:1fr;gap:10px"><div><label data-i18n="database_name_label">Database Name</label><input type="text" name="db_name" placeholder="(optional)" data-i18n-placeholder="database_placeholder"></div></div><div class="form-group" style="display:grid;grid-template-columns:1fr;gap:10px"><div><button type="submit" name="login" class="btn btn-primary" style="width:100%;padding:12px 10px"><span data-i18n="connect_button">Connect</span></button></div></div></form><!-- Theme & Language Switcher on Login Page --><div style="display: flex; gap: 10px; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border); justify-content:space-between; align-items:center"><div class="dropdown" data-dropdown style="flex: 1; margin-right:6px;"><button class="dropdown-toggle" style="width:100%; text-align:left; padding:8px 12px;">Theme</button><div class="dropdown-menu" style="min-width:100%; bottom:100%; top:auto"><?php foreach ($theme_options as $value => $label): ?><a href="?<?php echo http_build_query(array_merge($_GET, ['set_theme' => $value])); ?>" class="dropdown-item <?php echo $current_theme == $value ? 'active' : ''; ?>" data-theme-option="<?php echo $value; ?>"><?php echo $label; ?></a><?php endforeach; ?></div></div><div class="dropdown" data-dropdown style="flex: 1; margin-left:6px;"><button class="dropdown-toggle" style="width:100%; text-align:left; padding:8px 12px;">Language</button><div class="dropdown-menu" style="min-width:100%; bottom:100%; top:auto"><?php foreach ($language_options as $value => $label): ?><a href="?<?php echo http_build_query(array_merge($_GET, ['set_lang' => $value])); ?>" class="dropdown-item <?php echo $current_lang == $value ? 'active' : ''; ?>" data-language-option="<?php echo $value; ?>"><?php echo $label; ?></a><?php endforeach; ?></div></div></div></div></div><?php else: ?><!-- Mobile Menu Toggle --><button class="mobile-menu-toggle" onclick="toggleSidebar()"><span></span><span></span><span></span></button><!-- App Container --><div class="app-container"><!-- Sidebar --><aside class="sidebar" id="sidebar"><div class="sidebar-header"><div class="sidebar-logo"><div class="sidebar-logo-icon"><svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M29.1455 34.6316V41.8158V59.2632L49 47.2895V38.0526L19.9818 21.2895L26.4727 17.5263L56.2545 34.6316V51.0526L29.1455 65.0789L26.4727 67.4737L21.5091 65.0789V36.7432L18.0727 34.4605L14.6364 32.1779V61.3158L7 57.2105V27.1053L11.9636 24.7105L29.1455 34.6316Z" fill="url(#paint0_linear_2_27)"/><path d="M39.0727 75L32.9636 71.2368L63.1273 54.8158V29.1579L34.4909 13.0789L39.0727 10L70 27.1053V59.2632L39.0727 75Z" fill="url(#paint1_linear_2_27)"/><line y1="-0.5" x2="5.96762" y2="-0.5" transform="matrix(0.888635 -0.458616 0.53467 0.845061 47.9091 38.7368)" stroke="white"/><line y1="-0.5" x2="5.94414" y2="-0.5" transform="matrix(0.867742 0.497015 -0.574662 0.818391 61.5455 54.4737)" stroke="white"/><line y1="-0.5" x2="5.94414" y2="-0.5" transform="matrix(0.867742 0.497015 -0.574662 0.818391 10.0303 30.5263)" stroke="white"/><defs><linearGradient id="paint0_linear_2_27" x1="38.5" y1="10" x2="38.5" y2="75" gradientUnits="userSpaceOnUse"><stop offset="0.139423" stop-color="#FF0000"/><stop offset="0.894231" stop-color="#0015FF"/></linearGradient><linearGradient id="paint1_linear_2_27" x1="38.5" y1="10" x2="38.5" y2="75" gradientUnits="userSpaceOnUse"><stop offset="0.139423" stop-color="#FF0000"/><stop offset="0.894231" stop-color="#0015FF"/></linearGradient></defs></svg></div><div class="sidebar-title"><h2>Dabiro</h2><p>v<?php echo DB_ADMIN_VERSION; ?></p></div></div></div><nav class="sidebar-nav"><a href="?page=databases" class="nav-item <?php echo $page == 'databases' ? 'active' : ''; ?>" data-i18n="databases">
                        Databases
                    </a><?php
                    // Only show database and tables when on tables page or when db is selected
                    $current_db = get_get('db', '');

                    // Show selected database and its tables only if we have a database in the URL
                    if ($current_db && $page != 'databases'):
                        $db_tables = [];
                        try {
                            $db_tables = $db->getTables($current_db);
                        } catch (Exception $e) {
                            // Can't access this database
                        }
                    ?><div class="nav-section"><div class="nav-section-header" onclick="toggleNav(this)"><span class="nav-arrow">▼</span><?php echo h($current_db); ?></div><div class="nav-section-content active"><?php foreach ($db_tables as $tbl): ?><a href="?page=browse&db=<?php echo urlencode($current_db); ?>&table=<?php echo urlencode($tbl); ?>"
                                        class="nav-sub-item <?php echo (get_get('table') == $tbl ? 'active' : ''); ?>"><?php echo h($tbl); ?></a><?php endforeach; ?></div></div><?php endif; ?><div class="nav-divider"></div><a href="?page=search" class="nav-item <?php echo $page == 'search' ? 'active' : ''; ?>" data-i18n="global_search">
                        Global Search
                    </a><a href="?page=sql" class="nav-item <?php echo $page == 'sql' ? 'active' : ''; ?>" data-i18n="sql_console">
                        SQL Console
                    </a><a href="?page=import" class="nav-item <?php echo $page == 'import' ? 'active' : ''; ?>" data-i18n="import_data">
                        Import Data
                    </a><a href="?page=export" class="nav-item <?php echo $page == 'export' ? 'active' : ''; ?>" data-i18n="export_data">
                        Export Data
                    </a></nav><div class="sidebar-footer"><!-- Theme & Language Switcher --><div style="display: flex; gap: 8px; margin-bottom: 12px;"><div class="dropdown" data-dropdown style="flex: 1;"><button class="dropdown-toggle" style="width: 100%; padding: 8px 12px; font-size: 12px;" data-i18n="theme">Theme</button><div class="dropdown-menu" style="bottom: 100%; top: auto; min-width: 100%;"><?php foreach ($theme_options as $value => $label): ?><a href="?<?php echo http_build_query(array_merge($_GET, ['set_theme' => $value])); ?>"
                                        class="dropdown-item <?php echo $current_theme == $value ? 'active' : ''; ?>"
                                        data-theme-option="<?php echo $value; ?>"><?php echo $label; ?></a><?php endforeach; ?></div></div><div class="dropdown" data-dropdown style="flex: 1;"><button class="dropdown-toggle" data-i18n="language">Language</button><div class="dropdown-menu" style="bottom: 100%; top: auto; min-width: 100%;"><?php foreach ($language_options as $value => $label): ?><a href="?<?php echo http_build_query(array_merge($_GET, ['set_lang' => $value])); ?>"
                                        class="dropdown-item <?php echo $current_lang == $value ? 'active' : ''; ?>"
                                        data-language-option="<?php echo $value; ?>"><?php echo $label; ?></a><?php endforeach; ?></div></div></div><div class="user-info"><div class="user-avatar"><?php echo strtoupper(substr($_SESSION['db_user'], 0, 2)); ?></div><div class="user-details"><div class="name"><?php echo h($_SESSION['db_user']); ?></div><div class="role"><?php echo h($_SESSION['db_type']); ?> &bull; <?php echo h($_SESSION['db_host']); ?></div></div></div><a href="?action=logout" class="btn btn-danger" style="width: 100%;" data-i18n="logout">
                        Logout
                    </a></div></aside><!-- Main Content --><main class="main-content"><div class="top-bar"><div class="breadcrumb"><?php
                        $breadcrumbs = [];
                        if ($page == 'databases') {
                            $breadcrumbs = ['Home', 'Databases'];
                        } elseif ($page == 'tables' && $db_name = get_get('db')) {
                            $breadcrumbs = ['<a href="?page=databases">Home</a>', '<a href="?page=databases">Databases</a>', h($db_name)];
                        } elseif ($page == 'browse' && $table = get_get('table')) {
                            $breadcrumbs = ['<a href="?page=databases">Home</a>', '<a href="?page=tables&db=' . urlencode(get_get('db')) . '">' . h(get_get('db')) . '</a>', h($table)];
                        } elseif ($page == 'structure' && $table = get_get('table')) {
                            $breadcrumbs = ['<a href="?page=databases">Home</a>', '<a href="?page=tables&db=' . urlencode(get_get('db')) . '">' . h(get_get('db')) . '</a>', '<a href="?page=browse&db=' . urlencode(get_get('db')) . '&table=' . urlencode($table) . '">' . h($table) . '</a>', 'Structure'];
                        } elseif ($page == 'insert' && $table = get_get('table')) {
                            $breadcrumbs = ['<a href="?page=databases">Home</a>', '<a href="?page=tables&db=' . urlencode(get_get('db')) . '">' . h(get_get('db')) . '</a>', '<a href="?page=browse&db=' . urlencode(get_get('db')) . '&table=' . urlencode($table) . '">' . h($table) . '</a>', 'Insert'];
                        } elseif ($page == 'edit' && $table = get_get('table')) {
                            $breadcrumbs = ['<a href="?page=databases">Home</a>', '<a href="?page=tables&db=' . urlencode(get_get('db')) . '">' . h(get_get('db')) . '</a>', '<a href="?page=browse&db=' . urlencode(get_get('db')) . '&table=' . urlencode($table) . '">' . h($table) . '</a>', 'Edit'];
                        } elseif ($page == 'sql') {
                            $breadcrumbs = ['Home', 'SQL Console'];
                        } elseif ($page == 'import') {
                            $breadcrumbs = ['Home', 'Import'];
                        } elseif ($page == 'export') {
                            $breadcrumbs = ['Home', 'Export'];
                        }
                        echo implode(' <span class="separator">&rsaquo;</span> ', $breadcrumbs);
                        ?></div><div class="top-bar-preferences"><div class="dropdown" data-dropdown style="width:160px;"><button class="dropdown-toggle">Theme</button><div class="dropdown-menu"><?php foreach ($theme_options as $value => $label): ?><a href="?<?php echo http_build_query(array_merge($_GET, ['set_theme' => $value])); ?>" class="dropdown-item <?php echo $current_theme == $value ? 'active' : ''; ?>" data-theme-option="<?php echo $value; ?>"><?php echo $label; ?></a><?php endforeach; ?></div></div><div class="dropdown" data-dropdown style="width:160px; margin-left:8px;"><button class="dropdown-toggle">Language</button><div class="dropdown-menu"><?php foreach ($language_options as $value => $label): ?><a href="?<?php echo http_build_query(array_merge($_GET, ['set_lang' => $value])); ?>" class="dropdown-item <?php echo $current_lang == $value ? 'active' : ''; ?>" data-language-option="<?php echo $value; ?>"><?php echo $label; ?></a><?php endforeach; ?></div></div></div><div class="system-info"><div class="system-time" id="systemTime"></div></div></div><div class="content-area"><?php if ($page == 'databases'): ?><!-- Databases Page --><?php
                        $databases = $db->getDatabases();
                        ?><div class="page-header-row"><div class="page-header"><h1 data-i18n="databases">Databases</h1><p data-i18n="databases_subtitle">Select a database to view its tables and data</p></div><div class="view-toggle"><button class="view-toggle-btn active" data-view="grid" onclick="toggleView('grid')" data-i18n="grid_view"><svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/></svg> <span>Grid</span></button><button class="view-toggle-btn" data-view="list" onclick="toggleView('list')" data-i18n="list_view"><svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="5" width="18" height="3" rx="1.5"/><rect x="3" y="10.5" width="18" height="3" rx="1.5"/><rect x="3" y="16" width="18" height="3" rx="1.5"/></svg> <span>List</span></button></div></div><?php if (isset($success_message)): ?><div class="alert alert-success"><span><?php echo h($success_message); ?></span></div><?php endif; ?><?php if (isset($error_message)): ?><div class="alert alert-error"><span><?php echo h($error_message); ?></span></div><?php endif; ?><form id="bulk-action-form" method="post" onsubmit="showLoader('Processing...')"><input type="hidden" name="bulk_action" id="bulk-action"><div class="btn-group" style="flex-wrap: wrap;"><div style="display: flex; gap: 12px; flex: 1; min-width: 300px;"><label style="display: flex; align-items: center; gap: 8px; padding: 12px 24px; background: var(--card-bg); color: var(--text); border: 2px solid var(--border); border-radius: var(--radius-sm); cursor: pointer; height: 44px;"><input type="checkbox" onchange="selectAll(this)" style="width: auto; margin: 0;"><span style="font-weight: 600; font-size: 14px;" data-i18n="select_all">Select All</span></label><input type="text" placeholder="Search databases..." data-i18n-placeholder="search_databases_placeholder" onkeyup="searchItems(this)" style="flex: 1; padding: 12px 16px; border: 2px solid var(--border); border-radius: var(--radius-sm); font-size: 14px; height: 44px;"></div><div style="display: flex; gap: 12px; align-items:center;"><?php if ($_SESSION['db_type'] != 'sqlite'): ?><button type="button" class="btn btn-success" onclick="openModal('createDatabaseModal')" data-i18n="create_database">Create Database</button><div class="dropdown"><button type="button" class="dropdown-toggle action-dropdown-toggle" disabled><span data-i18n="actions">Actions</span><span class="selection-count"></span></button><div class="dropdown-menu"><a href="javascript:void(0)" onclick="performAction('drop')" class="dropdown-item danger" data-i18n="drop_selected">Drop Selected</a></div></div><?php endif; ?></div></div><?php
                        // DB server and web server info
                        $db_server_info = 'N/A';
                        try {
                            if (isset($db) && $db) {
                                if ($db->getType() == 'mysql') {
                                    $db_server_info = $db->getPdo()->query('SELECT VERSION()')->fetchColumn();
                                } elseif ($db->getType() == 'pgsql') {
                                    $db_server_info = $db->getPdo()->query('SELECT version()')->fetchColumn();
                                } elseif ($db->getType() == 'sqlite') {
                                    $db_server_info = $db->getPdo()->query('select sqlite_version()')->fetchColumn();
                                }
                            }
                        } catch (Exception $e) {}
                        $web_server_info = $_SERVER['SERVER_SOFTWARE'] ?? php_uname();
                        $php_version = PHP_VERSION;
                        ?>
                        <div class="stats-grid"><div class="stat-card"><div class="stat-value"><?php echo count($databases); ?></div><div class="stat-label" data-i18n="total_databases">Total Databases</div></div><div class="stat-card"><div class="stat-value"><?php echo strtoupper($_SESSION['db_type']); ?></div><div class="stat-label" data-i18n="database_type_stat">Database Type</div></div><div class="stat-card"><div class="stat-value"><?php echo h($_SESSION['db_host']); ?></div><div class="stat-label" data-i18n="server_host">Server Host</div></div><div class="stat-card"><div style="font-size:14px; font-weight:700; margin-bottom:6px;">Server Info</div><div style="font-size:13px; color:var(--text-light); line-height:1.4;"><strong>DB:</strong> <?php echo h($db_server_info); ?><br><strong>Web:</strong> <?php echo h($web_server_info); ?><br><strong>PHP:</strong> <?php echo h($php_version); ?></div></div></div><div class="grid" id="databasesContainer"><?php foreach ($databases as $database): $db_tables_count = 0; $db_size = 'N/A'; $db_collation = 'N/A'; $db_type = $_SESSION['db_type']; try { if ($db->getType() == 'mysql') { $info = $db->getPdo()->query("SELECT SUM(DATA_LENGTH + INDEX_LENGTH) as size, COUNT(*) as tables, MAX(TABLE_COLLATION) as coll FROM information_schema.TABLES WHERE TABLE_SCHEMA='".addslashes($database)."'")->fetch(PDO::FETCH_ASSOC); if ($info) { $db_size = $info['size'] ? round($info['size']/1024,2).' KB' : '0 KB'; $db_tables_count = $info['tables'] ?? 0; $db_collation = $info['coll'] ?? 'N/A'; } } elseif ($db->getType() == 'sqlite') { $file = $database; if (file_exists($file)) { $db_size = round(filesize($file)/1024,2).' KB'; } $db_tables_count = count($db->getTables($database)); } elseif ($db->getType() == 'pgsql') { $info = $db->getPdo()->query("SELECT pg_database_size('".addslashes($database)."') as size")->fetch(PDO::FETCH_ASSOC); if ($info) { $db_size = isset($info['size']) ? round($info['size']/1024,2).' KB' : 'N/A'; } $db_tables_count = count($db->getTables($database)); } } catch (Exception $e) {} ?><div class="grid-card list-item" style="display: flex; align-items: center; gap: 12px;"><?php if ($_SESSION['db_type'] != 'sqlite'): ?><label style="display: flex; align-items: center; padding-top: 4px;"><input type="checkbox" name="selected[]" value="<?php echo h($database); ?>" onchange="updateActionButton()" style="width: auto; margin: 0;"></label><?php endif; ?><div style="flex: 1; display:flex; align-items:center; gap:12px;"><a href="?page=tables&db=<?php echo urlencode($database); ?>" style="text-decoration: none; color: inherit; display: block; flex:1;"><h3 style="margin-bottom: 4px; display:flex; align-items:center; gap:10px;"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/></svg><?php echo h($database); ?></h3><div class="db-meta" style="color: var(--text-light); font-size: 13px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;"><span>Tables: <?php echo $db_tables_count; ?></span><span>Size: <?php echo $db_size; ?></span><span>Collation: <?php echo h($db_collation); ?></span><span>Type: <?php echo h(strtoupper($db_type)); ?></span></div></a></div></div><?php endforeach; ?></div></form><?php elseif ($page == 'tables'): ?><!-- Tables Page --><?php
                        $database = get_get('db');
                        $tables = $db->getTables($database);
                        $_SESSION['db_name'] = $database;
                        ?><div class="page-header-row"><div class="page-header"><h1>Tables in <?php echo h($database); ?></h1><p><?php echo count($tables); ?> tables found</p></div><div class="view-toggle"><button class="view-toggle-btn active" data-view="grid" onclick="toggleView('grid')">
                                    Grid
                                </button><button class="view-toggle-btn" data-view="list" onclick="toggleView('list')">
                                    List
                                </button></div></div><?php if (isset($success_message)): ?><div class="alert alert-success"><span><?php echo h($success_message); ?></span></div><?php endif; ?><?php if (isset($error_message)): ?><div class="alert alert-error"><span><?php echo h($error_message); ?></span></div><?php endif; ?><form id="bulk-action-form" method="post" onsubmit="showLoader('Processing...')"><input type="hidden" name="bulk_action" id="bulk-action"><input type="hidden" name="database" value="<?php echo h($database); ?>"><div class="btn-group" style="flex-wrap: wrap;"><div style="display: flex; gap: 12px; flex: 1; min-width: 400px; flex-wrap: wrap;"><a href="?page=databases" class="btn btn-secondary" data-i18n="back_to_databases">Back to Databases</a><button type="button" class="btn btn-success" onclick="openModal('createTableModal')">Create Table</button><select onchange="switchDatabase(this)" style="padding: 12px 16px; border: 2px solid var(--border); border-radius: var(--radius-sm); font-size: 14px; font-weight: 600; height: 44px; background: var(--input-bg); color: var(--text); cursor: pointer; min-width: 150px;"><?php foreach ($db->getDatabases() as $db_name): ?><option value="<?php echo h($db_name); ?>" <?php echo $db_name == $database ? 'selected' : ''; ?>><?php echo h($db_name); ?></option><?php endforeach; ?></select><label style="display: flex; align-items: center; gap: 8px; padding: 12px 24px; background: var(--card-bg); color: var(--text); border: 2px solid var(--border); border-radius: var(--radius-sm); cursor: pointer; height: 44px;"><input type="checkbox" onchange="selectAll(this)" style="width: auto; margin: 0;"><span style="font-weight: 600; font-size: 14px;" data-i18n="select_all">Select All</span></label><input type="text" placeholder="Search tables..." data-i18n-placeholder="search_tables_placeholder" onkeyup="searchItems(this)" style="flex: 1; min-width: 200px; padding: 12px 16px; border: 2px solid var(--border); border-radius: var(--radius-sm); font-size: 14px; height: 44px;"></div><div style="display: flex; gap: 12px; flex-wrap: wrap;"><div class="dropdown"><button type="button" class="dropdown-toggle action-dropdown-toggle" disabled><span data-i18n="actions">Actions</span><span class="selection-count"></span></button><div class="dropdown-menu"><a href="javascript:void(0)" onclick="performAction('truncate')" class="dropdown-item" data-i18n="truncate_selected">Truncate Selected</a><a href="javascript:void(0)" onclick="performAction('drop')" class="dropdown-item danger" data-i18n="drop_selected">Drop Selected</a></div></div><div class="dropdown"><button type="button" class="dropdown-toggle"><span data-i18n="operations">Operations</span></button><div class="dropdown-menu"><a href="javascript:void(0)" onclick="window.location.href='?page=table_operations&db=<?php echo urlencode($database); ?>&table=' + getFirstSelectedTable() + '&op=rename'" class="dropdown-item" data-i18n="rename_table">Rename Table</a><a href="javascript:void(0)" onclick="window.location.href='?page=table_operations&db=<?php echo urlencode($database); ?>&table=' + getFirstSelectedTable() + '&op=copy'" class="dropdown-item" data-i18n="copy_table">Copy Table</a><?php if ($db->getType() == 'mysql'): ?><a href="javascript:void(0)" onclick="window.location.href='?page=table_operations&db=<?php echo urlencode($database); ?>&table=' + getFirstSelectedTable() + '&op=move'" class="dropdown-item" data-i18n="move_table">Move Table</a><?php endif; ?></div></div></div></div><div class="grid" id="tablesContainer"><?php foreach ($tables as $table): $tbl_stats = ['size'=>'N/A','rows'=>'N/A','engine'=>'N/A','collation'=>'N/A','columns'=>0]; try { if ($db->getType()=='mysql') { $info = $db->getPdo()->query("SELECT ENGINE, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA='".addslashes($database)."' AND TABLE_NAME='".addslashes($table)."'")->fetch(PDO::FETCH_ASSOC); if ($info) { $tbl_stats['engine']=$info['ENGINE']?:'N/A'; $tbl_stats['rows']=$info['TABLE_ROWS']?:'N/A'; $size=((int)$info['DATA_LENGTH']+(int)$info['INDEX_LENGTH']); $tbl_stats['size']=$size?round($size/1024,2).' KB':'0 KB'; $tbl_stats['collation']=$info['TABLE_COLLATION']?:'N/A'; } $cols = $db->getColumns($table); $tbl_stats['columns']=count($cols); } elseif ($db->getType()=='sqlite') { try{ $tbl_stats['rows']=$db->getPdo()->query("SELECT COUNT(*) FROM `".$table."`")->fetchColumn(); }catch(Exception$e){} $cols=$db->getColumns($table); $tbl_stats['columns']=count($cols); } elseif ($db->getType()=='pgsql') { $info=$db->getPdo()->query("SELECT pg_total_relation_size(quote_ident('$table')) as size,(SELECT reltuples::bigint FROM pg_class WHERE relname = '$table') as rows")->fetch(PDO::FETCH_ASSOC); if($info){$tbl_stats['size']=isset($info['size'])?round($info['size']/1024,2).' KB':'N/A'; $tbl_stats['rows']=$info['rows']??'N/A';} $cols=$db->getColumns($table); $tbl_stats['columns']=count($cols); } }catch(Exception$e){} ?><div class="grid-card list-item"><label style="display: flex; align-items: center;"><input type="checkbox" name="selected[]" value="<?php echo h($table); ?>" onchange="updateActionButton()" style="width: auto; margin: 0;"></label><div style="flex: 1; display:flex; align-items:center; gap:12px;"><a href="?page=browse&db=<?php echo urlencode($database); ?>&table=<?php echo urlencode($table); ?>" style="text-decoration: none; color: inherit; display: block; flex:1;"><h3 style="margin-bottom: 4px; display:flex; align-items:center; gap:8px;"><svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/></svg><?php echo h($table); ?></h3><div class="db-meta" style="color: var(--text-light); font-size: 13px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;"><span>Rows: <?php echo $tbl_stats['rows']; ?></span><span>Columns: <?php echo $tbl_stats['columns']; ?></span><span>Size: <?php echo $tbl_stats['size']; ?></span><span>Collation: <?php echo h($tbl_stats['collation']); ?></span><span>Engine: <?php echo h($tbl_stats['engine']); ?></span></div></a></div></div><?php endforeach; ?></div></form><?php elseif ($page == 'browse'): ?><!-- Browse Table --><?php
                        $database = get_get('db');
                        $table = get_get('table');

                        // Get filter parameters
                        $search_column = get_get('search_column', '');
                        $search_value = get_get('search_value', '');
                        $search_condition = get_get('search_condition', 'like');
                        $column_searches = get_get('col_search', []);
                        $sort_column = get_get('sort_column', '');
                        $sort_order = get_get('sort_order', 'ASC');
                        $limit = (int)get_get('limit', 50);
                        $offset = (int)get_get('offset', 0);

                        // Check if any filter is active
                        $filter_active = ($search_column && $search_value) || (!empty(array_filter($column_searches))) || $sort_column;

                        // Get all columns
                        $columns = $db->getColumns($table);
                        $all_column_names = array_map(function ($col) {
                            return $col['Field'] ?? $col['name'] ?? $col['column_name'];
                        }, $columns);

                        // Table stats: size, engine/type, collation, rows
                        $table_stats = [
                            'size' => 'N/A',
                            'engine' => 'N/A',
                            'collation' => 'N/A',
                            'rows' => 'N/A'
                        ];
                        try {
                            if ($db->getType() == 'mysql') {
                                $info = $db->getPdo()->query("SELECT ENGINE, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = '" . addslashes($database) . "' AND TABLE_NAME = '" . addslashes($table) . "'")->fetch(PDO::FETCH_ASSOC);
                                if ($info) {
                                    $table_stats['engine'] = $info['ENGINE'] ?? 'N/A';
                                    $table_stats['collation'] = $info['TABLE_COLLATION'] ?? 'N/A';
                                    $table_stats['rows'] = $info['TABLE_ROWS'] ?? 'N/A';
                                    $size = ((int)$info['DATA_LENGTH'] + (int)$info['INDEX_LENGTH']);
                                    $table_stats['size'] = $size > 0 ? round($size / 1024, 2) . ' KB' : '0 KB';
                                }
                            } elseif ($db->getType() == 'sqlite') {
                                $file = $_SESSION['db_host'] ?? '';
                                if ($file && file_exists($file)) {
                                    $table_stats['size'] = round(filesize($file) / 1024, 2) . ' KB';
                                }
                                try { $table_stats['rows'] = $db->getPdo()->query("SELECT COUNT(*) FROM `" . $table . "`")->fetchColumn(); } catch (Exception $e) {}
                            } elseif ($db->getType() == 'pgsql') {
                                $info = $db->getPdo()->query("SELECT pg_total_relation_size(quote_ident('$table')) as size, (SELECT reltuples::bigint FROM pg_class WHERE relname = '$table') as rows")->fetch(PDO::FETCH_ASSOC);
                                if ($info) {
                                    $table_stats['size'] = isset($info['size']) ? round($info['size'] / 1024, 2) . ' KB' : 'N/A';
                                    $table_stats['rows'] = $info['rows'] ?? 'N/A';
                                }
                            }
                        } catch (Exception $e) {
                            // ignore
                        }

                        // Build WHERE clause
                        $where_clauses = [];

                        // Global column search
                        $search_condition = get_get('search_condition', 'like');
                        if ($search_column && $search_value && in_array($search_column, $all_column_names)) {
                            $val = $search_value;
                            switch (strtolower($search_condition)) {
                                case '=':
                                    $where_clauses[] = "`$search_column` = " . $db->getPdo()->quote($val);
                                    break;
                                case '!=':
                                    $where_clauses[] = "`$search_column` != " . $db->getPdo()->quote($val);
                                    break;
                                case 'like_start':
                                    $where_clauses[] = "`$search_column` LIKE " . $db->getPdo()->quote($val . '%');
                                    break;
                                case 'like_end':
                                    $where_clauses[] = "`$search_column` LIKE " . $db->getPdo()->quote('%' . $val);
                                    break;
                                case 'regexp':
                                    $where_clauses[] = "`$search_column` REGEXP " . $db->getPdo()->quote($val);
                                    break;
                                default:
                                    $where_clauses[] = "`$search_column` LIKE " . $db->getPdo()->quote('%' . $val . '%');
                            }
                        }

                        // Column-level searches
                        if (is_array($column_searches)) {
                            foreach ($column_searches as $col => $val) {
                                if ($val !== '' && in_array($col, $all_column_names)) {
                                    $where_clauses[] = "`$col` LIKE " . $db->getPdo()->quote("%$val%");
                                }
                            }
                        }

                        $where = !empty($where_clauses) ? ' WHERE ' . implode(' AND ', $where_clauses) : '';

                        // Build ORDER BY clause
                        $order = '';
                        if ($sort_column && in_array($sort_column, $all_column_names)) {
                            $order = " ORDER BY `$sort_column` $sort_order";
                        }

                        // Get data
                        $sql = "SELECT * FROM `$table`" . $where . $order . " LIMIT $limit OFFSET $offset";

                        try {
                            $stmt = $db->query($sql);
                            $data = $stmt ? $stmt->fetchAll() : [];
                        } catch (Exception $e) {
                            $data = [];
                            $error_message = "Query error: " . $e->getMessage();
                        }

                        // Get total count
                        try {
                            $count_sql = "SELECT COUNT(*) FROM `$table`" . $where;
                            $total = $db->getPdo()->query($count_sql)->fetchColumn();
                        } catch (Exception $e) {
                            $total = 0;
                        }
                        $total_pages = ceil($total / $limit);
                        $current_page = floor($offset / $limit) + 1;
                        ?><div class="page-header"><h1><?php echo h($table); ?></h1><p>Viewing records <?php echo $offset + 1; ?>-<?php echo min($offset + $limit, $total); ?> of <?php echo $total; ?></p></div><!-- Quick Actions --><?php if (isset($success_message)): ?><div class="alert alert-success"><span><?php echo h($success_message); ?></span></div><?php endif; ?><?php if (isset($error_message)): ?><div class="alert alert-error"><span><?php echo h($error_message); ?></span></div><?php endif; ?><div class="btn-group"><a href="?page=tables&db=<?php echo urlencode($database); ?>" class="btn btn-secondary">Back to Tables</a><a href="?page=insert&db=<?php echo urlencode($database); ?>&table=<?php echo urlencode($table); ?>" class="btn btn-success">Insert Record</a><a href="?page=structure&db=<?php echo urlencode($database); ?>&table=<?php echo urlencode($table); ?>" class="btn btn-secondary">View Structure</a><div class="dropdown"><button class="dropdown-toggle">Table Operations</button><div class="dropdown-menu"><a href="?page=table_operations&db=<?php echo urlencode($database); ?>&table=<?php echo urlencode($table); ?>&op=rename" class="dropdown-item">Rename Table</a><a href="?page=table_operations&db=<?php echo urlencode($database); ?>&table=<?php echo urlencode($table); ?>&op=copy" class="dropdown-item">Copy Table</a><?php if ($db->getType() == 'mysql'): ?><a href="?page=table_operations&db=<?php echo urlencode($database); ?>&table=<?php echo urlencode($table); ?>&op=move" class="dropdown-item">Move Table</a><?php endif; ?><a href="?action=truncate_table&db=<?php echo urlencode($database); ?>&table=<?php echo urlencode($table); ?>" class="dropdown-item danger" onclick="return confirm('Are you sure you want to delete all records from \'<?php echo h($table); ?>\'?')">Truncate Table</a><a href="?action=drop_table&db=<?php echo urlencode($database); ?>&table=<?php echo urlencode($table); ?>" class="dropdown-item danger" onclick="return confirm('Are you sure you want to drop table \'<?php echo h($table); ?>\'? This cannot be undone!')">Drop Table</a></div></div></div><!-- Filters Section --><div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 20px;"><!-- Search in Column --><div class="card"><div class="card-header"><div class="card-title">Search in Column</div></div><form method="get" style="padding: 16px;"><input type="hidden" name="page" value="browse"><input type="hidden" name="db" value="<?php echo h($database); ?>"><input type="hidden" name="table" value="<?php echo h($table); ?>"><input type="hidden" name="limit" value="<?php echo $limit; ?>"><?php if ($sort_column): ?><input type="hidden" name="sort_column" value="<?php echo h($sort_column); ?>"><input type="hidden" name="sort_order" value="<?php echo h($sort_order); ?>"><?php endif; ?><label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 13px;">Select Column</label><select name="search_column" style="width: 100%; padding: 10px; border: 2px solid var(--border); border-radius: var(--radius-sm); font-size: 13px; margin-bottom: 12px;"><option value="">-- Choose column --</option><?php foreach ($all_column_names as $idx => $col): ?><option value="<?php echo h($col); ?>" data-column-index="<?php echo $idx; ?>" <?php echo $search_column == $col ? 'selected' : ''; ?>><?php echo h($col); ?></option><?php endforeach; ?></select>
<label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 13px;">Condition</label>
<select name="search_condition" style="width: 100%; padding: 10px; border: 2px solid var(--border); border-radius: var(--radius-sm); font-size: 13px; margin-bottom: 12px;">
    <option value="like" <?php echo $search_condition == 'like' ? 'selected' : ''; ?>>Contains (%like%)</option>
    <option value="=" <?php echo $search_condition == '=' ? 'selected' : ''; ?>>Equals (=)</option>
    <option value="!=" <?php echo $search_condition == '!=' ? 'selected' : ''; ?>>Not Equals (!=)</option>
    <option value="like_start" <?php echo $search_condition == 'like_start' ? 'selected' : ''; ?>>Starts With (like%)</option>
    <option value="like_end" <?php echo $search_condition == 'like_end' ? 'selected' : ''; ?>>Ends With (%like)</option>
    <option value="regexp" <?php echo $search_condition == 'regexp' ? 'selected' : ''; ?>>Regex (REGEXP)</option>
</select><label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 13px;">Search Value</label><input type="text" name="search_value" value="<?php echo h($search_value); ?>" placeholder="Enter search term..." style="width: 100%; padding: 10px; border: 2px solid var(--border); border-radius: var(--radius-sm); font-size: 13px; margin-bottom: 12px;"><button type="submit" class="btn btn-primary" style="width: 100%;" data-i18n="search">Search</button></form></div><!-- Sort By --><div class="card"><div class="card-header"><div class="card-title">Sort By</div></div><form method="get" style="padding: 16px;"><input type="hidden" name="page" value="browse"><input type="hidden" name="db" value="<?php echo h($database); ?>"><input type="hidden" name="table" value="<?php echo h($table); ?>"><input type="hidden" name="limit" value="<?php echo $limit; ?>"><?php if ($search_column && $search_value): ?><input type="hidden" name="search_column" value="<?php echo h($search_column); ?>"><input type="hidden" name="search_value" value="<?php echo h($search_value); ?>"><?php endif; ?><label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 13px;">Column</label><select name="sort_column" style="width: 100%; padding: 10px; border: 2px solid var(--border); border-radius: var(--radius-sm); font-size: 13px; margin-bottom: 12px;"><option value="">-- No sorting --</option><?php foreach ($all_column_names as $idx => $col): ?><option value="<?php echo h($col); ?>" data-column-index="<?php echo $idx; ?>" <?php echo $sort_column == $col ? 'selected' : ''; ?>><?php echo h($col); ?></option><?php endforeach; ?></select><label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 13px;">Order</label><select name="sort_order" style="width: 100%; padding: 10px; border: 2px solid var(--border); border-radius: var(--radius-sm); font-size: 13px; margin-bottom: 12px;"><option value="ASC" <?php echo $sort_order == 'ASC' ? 'selected' : ''; ?>>Ascending (A-Z, 0-9)</option><option value="DESC" <?php echo $sort_order == 'DESC' ? 'selected' : ''; ?>>Descending (Z-A, 9-0)</option></select><button type="submit" class="btn btn-primary" style="width: 100%;">Apply Sort</button></form></div><!-- Limit Rows --><div class="card"><div class="card-header"><div class="card-title">Rows Per Page</div></div><form method="get" style="padding: 16px;"><input type="hidden" name="page" value="browse"><input type="hidden" name="db" value="<?php echo h($database); ?>"><input type="hidden" name="table" value="<?php echo h($table); ?>"><?php if ($sort_column): ?><input type="hidden" name="sort_column" value="<?php echo h($sort_column); ?>"><input type="hidden" name="sort_order" value="<?php echo h($sort_order); ?>"><?php endif; ?><?php if ($search_column && $search_value): ?><input type="hidden" name="search_column" value="<?php echo h($search_column); ?>"><input type="hidden" name="search_value" value="<?php echo h($search_value); ?>"><?php endif; ?><label style="display: block; font-weight: 600; margin-bottom: 8px; font-size: 13px;">Select Limit</label><select name="limit" style="width: 100%; padding: 10px; border: 2px solid var(--border); border-radius: var(--radius-sm); font-size: 13px; margin-bottom: 12px;"><option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10 rows</option><option value="25" <?php echo $limit == 25 ? 'selected' : ''; ?>>25 rows</option><option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50 rows</option><option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100 rows</option><option value="500" <?php echo $limit == 500 ? 'selected' : ''; ?>>500 rows</option><option value="1000" <?php echo $limit == 1000 ? 'selected' : ''; ?>>1000 rows</option></select><button type="submit" class="btn btn-primary" style="width: 100%;">Apply Limit</button></form></div></div><?php if ($filter_active): ?><div style="margin-bottom: 16px; padding: 12px 16px; background: #fef3c7; border: 1px solid #fbbf24; border-radius: 8px; display: flex; align-items: center; justify-content: space-between;"><span style="font-weight: 600; color: #92400e;">Filters Active</span><a href="?page=browse&db=<?php echo urlencode($database); ?>&table=<?php echo urlencode($table); ?>" class="btn btn-secondary" style="padding: 6px 16px; font-size: 13px;">Clear All Filters</a></div><?php endif; ?><?php if (!empty($data)): ?><!-- Column Filter --><div class="card" style="margin-bottom: 20px;"><div class="card-header" onclick="this.nextElementSibling.classList.toggle('hidden')" style="cursor: pointer;"><div class="card-title">Column Filter (click to toggle)</div></div><div style="padding: 16px; display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px;"><label style="display: flex; align-items: center; gap: 8px;"><input type="checkbox" onclick="toggleAllColumns(this)" checked><strong data-i18n="select_all">Select All</strong></label><?php foreach ($all_column_names as $idx => $col): ?><label style="display: flex; align-items: center; gap: 8px;"><input type="checkbox" class="column-toggle" data-column="<?php echo $idx; ?>" checked onchange="toggleColumn(<?php echo $idx; ?>)"><?php echo h($col); ?></label><?php endforeach; ?></div></div><div class="table-container browse-table-container"><table id="data-table"><thead><tr><?php foreach ($all_column_names as $col_index => $col): ?><th class="col-<?php echo $col_index; ?>" data-column-name="<?php echo h($col); ?>"><?php echo h($col); ?></th><?php endforeach; ?><th style="width: 180px;" data-i18n="actions">Actions</th></tr></thead><tbody><?php foreach ($data as $row): ?><tr><?php foreach ($all_column_names as $col_index => $col_name): ?><?php $value = $row[$col_name] ?? null; ?><td class="col-<?php echo $col_index; ?>" data-column-name="<?php echo h($col_name); ?>"><?php
                                                        if ($value === null) {
                                                            echo '<span class="null-value">NULL</span>';
                                                        } elseif ($value === '') {
                                                            echo '<span class="empty-value">empty</span>';
                                                        } else {
                                                            echo h(substr($value, 0, 100));
                                                        }
                                                        ?></td><?php endforeach; ?><td><?php
                                                    $delete_params = ['action' => 'delete', 'table' => $table, 'db' => $database];
                                                    foreach ($row as $key => $value) {
                                                        $delete_params['where_' . $key] = $value;
                                                    }
                                                    ?><a href="?page=edit&db=<?php echo urlencode($database); ?>&table=<?php echo urlencode($table); ?>&<?php echo http_build_query(array_combine(array_map(function ($k) {
                                                                                                                                                            return 'edit_' . $k;
                                                                                                                                                        }, array_keys($row)), $row)); ?>" class="btn btn-secondary btn-sm">Edit</a><a href="?<?php echo http_build_query($delete_params); ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this record?')">Delete</a></td></tr><?php endforeach; ?></tbody></table></div><?php if ($total_pages > 1): ?><div class="pagination"><?php if ($current_page > 1): ?><a href="?page=browse&db=<?php echo urlencode($database); ?>&table=<?php echo urlencode($table); ?>&offset=<?php echo ($current_page - 2) * $limit; ?>">Previous</a><?php endif; ?><?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?><a href="?page=browse&db=<?php echo urlencode($database); ?>&table=<?php echo urlencode($table); ?>&offset=<?php echo ($i - 1) * $limit; ?>" class="<?php echo $i == $current_page ? 'active' : ''; ?>"><?php echo $i; ?></a><?php endfor; ?><?php if ($current_page < $total_pages): ?><a href="?page=browse&db=<?php echo urlencode($database); ?>&table=<?php echo urlencode($table); ?>&offset=<?php echo $current_page * $limit; ?>">Next</a><?php endif; ?></div><?php endif; ?><?php else: ?><div class="card text-center"><p style="color: var(--text-light); font-size: 18px;">No records found in this table</p></div><?php endif; ?><?php elseif ($page == 'structure'): ?><!-- Table Structure --><?php
                        $database = get_get('db');
                        $table = get_get('table');
                        $columns = $db->getColumns($table);
                        ?><div class="page-header"><h1>Table Structure</h1><p><?php echo h($table); ?> - <?php echo count($columns); ?> columns</p></div><div class="btn-group" style="align-items:center;gap:8px;"><a href="?page=browse&db=<?php echo urlencode($database); ?>&table=<?php echo urlencode($table); ?>" class="btn btn-secondary">Back to Table</a><button type="button" class="btn btn-success" style="display:inline-block;margin-left:8px;" onclick="openModal('addColumnModal')">Add Column</button></div><div class="table-container structure-scroll" style="border:1px solid var(--border);border-radius:8px;padding:8px;"> <table style="min-width:700px;"><thead><tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr></thead><tbody><?php foreach ($columns as $col): ?><tr><td><strong><?php echo h($col['Field'] ?? $col['name'] ?? $col['column_name']); ?></strong></td><td><code><?php echo h($col['Type'] ?? $col['type'] ?? $col['data_type']); ?></code></td><td><?php echo h($col['Null'] ?? $col['notnull'] ?? $col['is_nullable']); ?></td><td><?php echo h($col['Default'] ?? $col['dflt_value'] ?? $col['column_default'] ?? 'NULL'); ?></td></tr><?php endforeach; ?></tbody></table></div>
<style>
/* Make only the data table body scrollable (keep header fixed) */
.structure-scroll{max-height:calc(100vh - 180px);overflow:auto;}
.browse-table-container{max-height:calc(100vh - 260px);overflow:auto;}
#data-table{display:block;width:100%;border-collapse:collapse;table-layout:fixed;}
#data-table thead, #data-table tbody{display:block;}
#data-table tbody{max-height:calc(100vh - 360px);overflow:auto;}
#data-table th, #data-table td{padding:8px;border-bottom:1px solid var(--border);white-space:nowrap;text-overflow:ellipsis;overflow:hidden;max-width:260px;}
#data-table thead th{position:sticky;top:0;background:var(--bg);z-index:3}
#data-table th:first-child, #data-table td:first-child{min-width:180px;max-width:320px;}
#data-table th:last-child, #data-table td:last-child{width:180px;min-width:140px;max-width:220px;}
.structure-scroll table{border-collapse:collapse;width:100%;}
</style><?php elseif ($page == 'insert' || $page == 'edit'): ?><!-- Insert/Edit Record --><?php
                        $database = get_get('db');
                        $table = get_get('table');
                        $columns = $db->getColumns($table);
                        $is_edit = ($page == 'edit');
                        $edit_data = [];

                        if ($is_edit) {
                            foreach ($_GET as $key => $value) {
                                if (strpos($key, 'edit_') === 0) {
                                    $edit_data[substr($key, 5)] = $value;
                                }
                            }
                        }
                        ?><div class="page-header"><h1><?php echo $is_edit ? 'Edit Record' : 'Insert Record'; ?></h1><p><?php echo h($table); ?></p></div><?php if (isset($success_message)): ?><div class="alert alert-success"><span><?php echo h($success_message); ?></span></div><?php endif; ?><?php if (isset($error_message)): ?><div class="alert alert-error"><span><?php echo h($error_message); ?></span></div><?php endif; ?><div class="btn-group"><a href="?page=browse&db=<?php echo urlencode($database); ?>&table=<?php echo urlencode($table); ?>" class="btn btn-secondary">Back to Table</a></div><div class="card"><form method="post" onsubmit="showLoader('Saving record...')"><input type="hidden" name="table" value="<?php echo h($table); ?>"><input type="hidden" name="is_edit" value="<?php echo $is_edit ? '1' : '0'; ?>"><?php foreach ($columns as $col): ?><?php
                                    $field_name = $col['Field'] ?? $col['name'] ?? $col['column_name'];
                                    $value = $is_edit ? ($edit_data[$field_name] ?? '') : '';
                                    ?><div class="form-group"><label><?php echo h($field_name); ?></label><input type="text" name="field[<?php echo h($field_name); ?>]" value="<?php echo h($value); ?>"></div><?php if ($is_edit): ?><input type="hidden" name="field[old_<?php echo h($field_name); ?>]" value="<?php echo h($value); ?>"><?php endif; ?><?php endforeach; ?><div class="btn-group"><button type="submit" name="save_record" class="btn btn-success">Save Record</button><a href="?page=browse&db=<?php echo urlencode($database); ?>&table=<?php echo urlencode($table); ?>" class="btn btn-secondary">Cancel</a></div></form></div><?php elseif ($page == 'sql'): ?><!-- SQL Console --><div class="page-header"><h1>SQL Console</h1><p>Execute custom SQL queries and view results</p></div><div class="btn-group"><a href="?page=databases" class="btn btn-secondary">Back to Databases</a></div><?php if (isset($sql_error)): ?><div class="alert alert-error"><span><?php echo h($sql_error); ?></span></div><?php endif; ?><div class="card"><div class="card-header"><div class="card-title">Query Editor</div></div><form method="post" id="sql-form" onsubmit="showLoader('Executing query...')"><div class="form-group"><textarea name="sql" id="sql-query" rows="10" placeholder="SELECT * FROM table_name LIMIT 10;"><?php echo h(get_post('sql', get_get('pre_sql', ''))); ?></textarea></div><div style="display: flex; gap: 12px; flex-wrap: wrap;"><button type="submit" name="execute_sql" class="btn btn-primary">Execute Query</button><button type="submit" name="export_query" class="btn btn-secondary">Export Query as SQL</button></div></form></div><?php if (isset($sql_result)): ?><div class="card"><div class="card-header"><div class="card-title">Query Results</div><div style="color: var(--text-light); font-size: 14px; font-weight: 600;">
                                        Execution Time: <?php echo $execution_time; ?>ms &bull; Rows: <?php echo count($sql_result); ?></div></div><?php if (!empty($sql_result)): ?><div class="table-container"><table><thead><tr><?php foreach (array_keys($sql_result[0]) as $col): ?><th><?php echo h($col); ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach ($sql_result as $row): ?><tr><?php foreach ($row as $value): ?><td><?php echo h($value); ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p style="color: var(--success); font-size: 16px; font-weight: 600;">Query executed successfully! <?php echo isset($sql_affected) ? "$sql_affected rows affected." : ""; ?></p><?php endif; ?></div><?php endif; ?><?php elseif ($page == 'import'): ?><!-- Import --><div class="page-header"><h1>Import Data</h1><p>Upload and execute SQL files</p></div><div class="btn-group"><a href="?page=databases" class="btn btn-secondary">Back to Databases</a></div><div class="card"><div class="card-header"><div class="card-title">SQL File Import</div></div><form method="post" enctype="multipart/form-data" onsubmit="showLoader('Importing SQL file...')"><div class="form-group"><label>Select SQL File</label><input type="file" name="sql_file" accept=".sql"></div><button type="submit" name="import_sql" class="btn btn-primary">Import SQL</button></form></div><?php if (isset($_POST['import_sql']) && isset($_FILES['sql_file'])): ?><?php
                            try {
                                $sql_content = file_get_contents($_FILES['sql_file']['tmp_name']);
                                $db->getPdo()->exec($sql_content);
                                echo '<div class="alert alert-success"><span>SQL file imported successfully!</span></div>';
                            } catch (Exception $e) {
                                echo '<div class="alert alert-error"><span>' . h($e->getMessage()) . '</span></div>';
                            }
                            ?><?php endif; ?><?php elseif ($page == 'export'): ?><!-- Export --><?php
                        $all_databases = $db->getDatabases();
                        $selected_database = get_get('db', $_SESSION['db_name'] ?? '');
                        $tables = $selected_database ? $db->getTables($selected_database) : [];
                        ?><div class="page-header"><h1 data-i18n="export_data">Export Data</h1><p data-i18n="export_description">Export databases or tables to various file formats</p></div><div class="btn-group"><a href="?page=databases" class="btn btn-secondary" data-i18n="back_to_databases">Back to Databases</a></div><!-- Export Entire Database --><div class="card" style="margin-bottom: 20px;"><div class="card-header"><div class="card-title" data-i18n="export_entire_database">Export Entire Database</div></div><form method="post" style="padding: 20px;" onsubmit="showLoader('Preparing export...')"><div class="form-group"><label data-i18n="select_database">Select Database</label><select name="export_db_name" required><option value="" data-i18n="choose_database">-- Choose a database --</option><?php foreach ($all_databases as $db_name): ?><option value="<?php echo h($db_name); ?>" <?php echo $db_name == $selected_database ? 'selected' : ''; ?>><?php echo h($db_name); ?></option><?php endforeach; ?></select></div><div class="form-group"><label data-i18n="export_format">Export Format</label><select name="export_db_format" required><option value="sql">SQL (.sql)</option><option value="json">JSON (.json)</option></select></div><button type="submit" name="export_database" class="btn btn-primary" data-i18n="download_database">Download Database</button></form></div><!-- Export Single Table --><div class="card"><div class="card-header"><div class="card-title" data-i18n="export_single_table">Export Single Table</div></div><form method="post" id="export-form" style="padding: 20px;" onsubmit="showLoader('Preparing export...')"><div class="form-group"><label data-i18n="select_database">Select Database</label><select name="export_database" id="export-database" required onchange="loadExportTables(this.value)"><option value="" data-i18n="choose_database">-- Choose a database --</option><?php foreach ($all_databases as $db_name): ?><option value="<?php echo h($db_name); ?>" <?php echo $db_name == $selected_database ? 'selected' : ''; ?>><?php echo h($db_name); ?></option><?php endforeach; ?></select></div><div class="form-group"><label data-i18n="select_table">Select Table</label><select name="export_table" id="export-table" required><option value="" data-i18n="choose_table">-- Choose a table --</option><?php foreach ($tables as $table): ?><option value="<?php echo h($table); ?>"><?php echo h($table); ?></option><?php endforeach; ?></select></div><div class="form-group"><label data-i18n="export_format">Export Format</label><select name="export_format" required><option value="sql">SQL (.sql)</option><option value="csv">CSV (.csv)</option><option value="json">JSON (.json)</option><option value="xml">XML (.xml)</option></select></div><button type="submit" name="export_table_download" class="btn btn-primary" data-i18n="download_table">Download Table</button></form></div><?php elseif ($page == 'search'): ?><!-- Global Search --><?php
                        $search_database = get_post('search_database', get_get('db', ''));
                        $all_databases = $db->getDatabases();
                        $search_tables_list = [];
                        if ($search_database) {
                            $search_tables_list = $db->getTables($search_database);
                        }
                        ?><div class="page-header"><h1>Global Search</h1><p>Search for data across tables</p></div><div class="btn-group"><a href="?page=databases" class="btn btn-secondary">Back to Databases</a></div><div class="card"><div class="card-header"><div class="card-title">Search Configuration</div></div><form method="post" onsubmit="showLoader('Searching...')"><div class="form-group"><label>Search Term</label><input type="text" name="search_term" value="<?php echo h(get_post('search_term', '')); ?>" placeholder="Enter text to search for..." required autofocus></div><div class="form-group"><label>Select Database</label><select name="search_database" id="search_database" onchange="this.form.submit()" required><option value="">-- Choose a database --</option><?php foreach ($all_databases as $db_name): ?><option value="<?php echo h($db_name); ?>" <?php echo $db_name == $search_database ? 'selected' : ''; ?>><?php echo h($db_name); ?></option><?php endforeach; ?></select></div><?php if (!empty($search_tables_list)): ?><div class="form-group"><label>Select Tables (leave empty to search all)</label><div style="max-height: 200px; overflow-y: auto; border: 2px solid var(--border); border-radius: var(--radius-sm); padding: 12px;"><label style="display: block; margin-bottom: 8px;"><input type="checkbox" id="select-all-tables" onclick="toggleAllTables(this)"><strong data-i18n="select_all">Select All</strong></label><?php foreach ($search_tables_list as $tbl): ?><label style="display: block; margin-bottom: 6px;"><input type="checkbox" name="search_tables[]" value="<?php echo h($tbl); ?>" class="table-checkbox"><?php echo h($tbl); ?></label><?php endforeach; ?></div></div><?php endif; ?><button type="submit" name="global_search" class="btn btn-primary" <?php echo empty($search_database) ? 'disabled' : ''; ?> data-i18n="search">Search</button></form></div><?php if (!empty($global_search_results)): ?><div class="card"><div class="card-header"><div class="card-title">Search Results</div></div><p style="margin-bottom: 16px; font-weight: 600;">Found matches in <?php echo count($global_search_results); ?> table(s)</p><?php foreach ($global_search_results as $table_name => $result): ?><div style="margin-bottom: 30px;"><h3 style="font-size: 18px; font-weight: 700; margin-bottom: 12px; color: var(--dark);"><?php echo h($table_name); ?><span style="font-size: 14px; font-weight: 500; color: var(--text-light);">(<?php echo $result['count']; ?> matches)</span><a href="?page=browse&db=<?php echo urlencode($search_database); ?>&table=<?php echo urlencode($table_name); ?>" class="btn btn-sm" style="margin-left: 12px; font-size: 13px;">View Table</a></h3><div class="table-container"><table><thead><tr><?php if (!empty($result['data'])): ?><?php foreach (array_keys($result['data'][0]) as $column): ?><th><?php echo h($column); ?></th><?php endforeach; ?><?php endif; ?></tr></thead><tbody><?php foreach ($result['data'] as $row): ?><tr><?php foreach ($row as $value): ?><td><?php echo h($value); ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div></div><?php endforeach; ?></div><?php elseif (isset($_POST['global_search'])): ?><div class="alert" style="background: #fef3c7; border-color: #f59e0b; color: #92400e;"><span>No matches found for "<?php echo h(get_post('search_term')); ?>"</span></div><?php endif; ?><?php elseif ($page == 'table_operations'): ?><!-- Table Operations --><?php
                        $database = get_get('db');
                        $table = get_get('table');
                        $operation = get_get('op');
                        $databases = $db->getDatabases();
                        ?><div class="page-header"><h1>Table Operations</h1><p><?php echo h($table); ?> in <?php echo h($database); ?></p></div><div class="btn-group"><a href="?page=browse&db=<?php echo urlencode($database); ?>&table=<?php echo urlencode($table); ?>" class="btn btn-secondary">Back to Table</a></div><?php if (isset($success_message)): ?><div class="alert alert-success"><span><?php echo h($success_message); ?></span></div><?php endif; ?><?php if (isset($error_message)): ?><div class="alert alert-error"><span><?php echo h($error_message); ?></span></div><?php endif; ?><?php if ($operation == 'rename' || !$operation): ?><!-- Rename Table --><div class="card"><div class="card-header"><div class="card-title">Rename Table</div></div><form method="post" onsubmit="showLoader('Renaming table...')"><input type="hidden" name="database" value="<?php echo h($database); ?>"><input type="hidden" name="old_table_name" value="<?php echo h($table); ?>"><div class="form-group"><label>Current Table Name</label><input type="text" value="<?php echo h($table); ?>" disabled></div><div class="form-group"><label>New Table Name</label><input type="text" name="new_table_name" value="<?php echo h($table); ?>" required></div><button type="submit" name="rename_table" class="btn btn-primary">Rename Table</button></form></div><?php endif; ?><?php if ($operation == 'copy' || !$operation): ?><!-- Copy Table --><div class="card"><div class="card-header"><div class="card-title">Copy Table</div></div><form method="post" onsubmit="showLoader('Copying table...')"><input type="hidden" name="source_db" value="<?php echo h($database); ?>"><input type="hidden" name="source_table" value="<?php echo h($table); ?>"><div class="form-group"><label>Source Table</label><input type="text" value="<?php echo h($database); ?>.<?php echo h($table); ?>" disabled></div><div class="form-group"><label>Target Database</label><select name="target_db" required><?php foreach ($databases as $db_name): ?><option value="<?php echo h($db_name); ?>" <?php echo $db_name == $database ? 'selected' : ''; ?>><?php echo h($db_name); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>New Table Name</label><input type="text" name="target_table" value="<?php echo h($table); ?>_copy" required></div><div class="form-group"><label style="display: flex; align-items: center; gap: 8px; cursor: pointer;"><input type="checkbox" name="copy_data" value="1" checked style="width: auto;"><span>Copy data (uncheck for structure only)</span></label></div><button type="submit" name="copy_table" class="btn btn-primary">Copy Table</button></form></div><?php endif; ?><?php if (($operation == 'move' || !$operation) && $db->getType() == 'mysql'): ?><!-- Move Table --><div class="card"><div class="card-header"><div class="card-title">Move Table</div></div><form method="post" onsubmit="showLoader('Moving table...')"><input type="hidden" name="source_db" value="<?php echo h($database); ?>"><input type="hidden" name="source_table" value="<?php echo h($table); ?>"><div class="form-group"><label>Source Table</label><input type="text" value="<?php echo h($database); ?>.<?php echo h($table); ?>" disabled></div><div class="form-group"><label>Target Database</label><select name="target_db" required><?php foreach ($databases as $db_name): ?><option value="<?php echo h($db_name); ?>" <?php echo $db_name == $database ? 'selected' : ''; ?>><?php echo h($db_name); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>New Table Name</label><input type="text" name="target_table" value="<?php echo h($table); ?>" required></div><div class="alert alert-error"><span><strong>Warning:</strong> Moving a table will delete it from the source database!</span></div><button type="submit" name="move_table" class="btn btn-danger" onclick="return confirm('Are you sure you want to move this table? It will be deleted from the source database!')">Move Table</button></form></div><?php endif; ?><?php endif; ?></div></main></div><!-- Create Database Modal --><div id="createDatabaseModal" class="modal"><div class="modal-content"><div class="modal-header"><h3>Create New Database</h3><button class="modal-close" onclick="closeModal('createDatabaseModal')">&times;</button></div><form method="post" onsubmit="showLoader('Creating database...')"><div class="modal-body"><div class="form-group"><label>Database Name</label><input type="text" name="new_db_name" required placeholder="Enter database name"></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('createDatabaseModal')">Cancel</button><button type="submit" name="create_database" class="btn btn-success">Create Database</button></div></form></div></div><!-- Create Table Modal --><div id="createTableModal" class="modal"><div class="modal-content"><div class="modal-header"><h3>Create Table</h3><button class="modal-close" onclick="closeModal('createTableModal')">&times;</button></div><form method="post" onsubmit="if(!prepareCreateTable()) return false; showLoader('Creating table...');"><div class="modal-body"><div class="form-group"><label>Database</label><select name="create_table_db"><?php foreach ($db->getDatabases() as $d): ?><option value="<?php echo h($d); ?>" <?php echo $d == $_SESSION['db_name'] ? 'selected' : ''; ?>><?php echo h($d); ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Table Name</label><input type="text" id="create_table_name" name="create_table_name" placeholder="table_name" required></div><div class="form-group"><label>Columns</label><div id="columnsBuilder" style="display:grid;gap:8px;border:1px solid var(--border);padding:12px;border-radius:8px;background:var(--input-bg);"></div><div style="margin-top:8px;display:flex;gap:8px;justify-content:space-between;align-items:center;"><div style="font-size:13px;color:var(--text-light)">Add or reorder columns before creating the table</div><div><button type="button" class="btn btn-secondary btn-sm" onclick="addColumnRow()">Add Column</button><button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('columnsBuilder').innerHTML='';columnIndex=0;addColumnRow();" style="margin-left:8px;">Reset</button></div></div></div><input type="hidden" name="create_table_sql" id="create_table_sql"></div><div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('createTableModal')">Cancel</button><button type="submit" name="create_table" class="btn btn-success">Create Table</button></div></form></div></div>

<!-- Add Column Modal -->
<div id="addColumnModal" class="modal"><div class="modal-content"><div class="modal-header"><h3>Add Column</h3><button class="modal-close" onclick="closeModal('addColumnModal')">&times;</button></div><form method="post" onsubmit="showLoader('Adding column...')"><div class="modal-body"><input type="hidden" name="add_column_table" value="<?php echo h(get_get('table','')); ?>"><div class="form-group"><label>Column Name</label><input type="text" name="new_col_name" required></div><div class="form-group"><label>Type</label><select name="new_col_type"><option>INT</option><option>VARCHAR</option><option>TEXT</option><option>DATE</option><option>DATETIME</option><option>DECIMAL</option><option>BOOLEAN</option></select></div><div class="form-group"><label>Length / Params (optional)</label><input type="text" name="new_col_length" placeholder="e.g. 255 or 10,2"></div><div class="form-group"><label><input type="checkbox" name="new_col_null" value="1"> Nullable</label></div><div class="form-group"><label>Default (optional)</label><input type="text" name="new_col_default" placeholder="NULL or default value"></div><div class="form-group"><label>Extra</label><select name="new_col_extra"><option value="">None</option><option value="AUTO_INCREMENT">AUTO_INCREMENT</option></select></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('addColumnModal')">Cancel</button><button type="submit" name="add_column" class="btn btn-success">Add Column</button></div></form></div></div>

<script>
// column builder helper functions
function jsQuote(val){return "'"+String(val).replace(/'/g,"\\'")+"'";}
let columnIndex = 0;
function addColumnRow(data){data = data || {}; const container = document.getElementById('columnsBuilder'); const idx = columnIndex++; const row = document.createElement('div'); row.className = 'column-row'; row.style.display='flex'; row.style.gap='8px'; row.style.alignItems='center'; row.innerHTML = `
    <input class="col-name" placeholder="name" style="flex:1;padding:8px;border:2px solid var(--border);border-radius:6px;" value="${data.name||''}">
    <select class="col-type" style="width:120px;padding:8px;border:2px solid var(--border);border-radius:6px;">
      <option value="INT">INT</option>
      <option value="VARCHAR">VARCHAR</option>
      <option value="TEXT">TEXT</option>
      <option value="DATE">DATE</option>
      <option value="DATETIME">DATETIME</option>
      <option value="DECIMAL">DECIMAL</option>
      <option value="BOOLEAN">BOOLEAN</option>
    </select>
    <input class="col-length" placeholder="length" style="width:100px;padding:8px;border:2px solid var(--border);border-radius:6px;" value="${data.length||''}">
    <label style="display:flex;align-items:center;gap:6px"><input type="checkbox" class="col-null" ${data.null?'checked':''}> NULL</label>
    <input class="col-default" placeholder="default" style="width:120px;padding:8px;border:2px solid var(--border);border-radius:6px;" value="${data.default||''}">
    <select class="col-extra" style="width:140px;padding:8px;border:2px solid var(--border);border-radius:6px;">
      <option value="">None</option>
      <option value="AUTO_INCREMENT">AUTO_INCREMENT</option>
      <option value="PRIMARY_KEY">PRIMARY KEY</option>
    </select>
    <button type="button" onclick="this.parentElement.remove()" class="btn btn-danger btn-sm">Remove</button>
  `;
 container.appendChild(row);
}
// add initial row
addColumnRow();

function prepareCreateTable(){const dbSelect=document.querySelector('select[name="create_table_db"]');const dbType=dbSelect?dbSelect.value:'';const tableName=document.getElementById('create_table_name').value.trim();if(!tableName){alert('Table name required');return false;}const rows = document.querySelectorAll('.column-row');const cols=[];let primaryKeys=[];rows.forEach(r=>{const name = r.querySelector('.col-name').value.trim(); if(!name) return; const type = r.querySelector('.col-type').value; const len = r.querySelector('.col-length').value.trim(); const isNull = r.querySelector('.col-null').checked; const def = r.querySelector('.col-default').value; const extra = r.querySelector('.col-extra').value; let colDef = "`"+name+"` "+type+(len?('('+len+')'):''); colDef += isNull? ' NULL':' NOT NULL'; if(def!==''){ if(def.toUpperCase()==='NULL'){ colDef += ' DEFAULT NULL'; } else { colDef += ' DEFAULT '+ jsQuote(def); } } if(extra==='AUTO_INCREMENT'){ colDef += ' AUTO_INCREMENT'; } if(extra==='PRIMARY_KEY'){ primaryKeys.push(name); } cols.push(colDef); }); if(cols.length===0){alert('Add at least one column');return false;} if(primaryKeys.length>0){ cols.push('PRIMARY KEY (`'+primaryKeys.join('`,`')+'`)'); } let sql = 'CREATE TABLE `'+tableName+'` ('+cols.join(', ')+')'; if(dbType==='mysql'){ sql += ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'; } document.getElementById('create_table_sql').value = sql; return true; }
</script><script> function showLoader(message='Processing...'){document.getElementById('loaderText').textContent=message;document.getElementById('loaderOverlay').classList.add('active');}function hideLoader(){document.getElementById('loaderOverlay').classList.remove('active');}function showToast(message,type='info',duration=4000){const container=document.getElementById('toastContainer');const toast=document.createElement('div');toast.className='toast toast-'+type;const icons={'success': '✓','error': '✕','info': 'ⓘ','warning': '⚠'};toast.innerHTML=`<span class="toast-icon">${icons[type] || '•'}</span><span class="toast-message">${message}</span><button class="toast-close" onclick="this.parentElement.remove()">×</button>`;container.appendChild(toast);if(duration>0){setTimeout(()=>{toast.classList.add('removing');setTimeout(()=>toast.remove(),300);},duration);}return toast;}function toggleSidebar(){document.getElementById('sidebar').classList.toggle('active');}function updateTime(){const systemTimeEl=document.getElementById('systemTime');if(!systemTimeEl){return;}const now=new Date();const hours=String(now.getHours()).padStart(2,'0');const minutes=String(now.getMinutes()).padStart(2,'0');const seconds=String(now.getSeconds()).padStart(2,'0');const timeString=hours+':'+minutes+':'+seconds;systemTimeEl.textContent=timeString;}updateTime();setInterval(updateTime,1000);document.addEventListener('click',function(event){const sidebar=document.getElementById('sidebar');const toggle=document.querySelector('.mobile-menu-toggle');if(sidebar && toggle){if(!sidebar.contains(event.target)&& !toggle.contains(event.target)){sidebar.classList.remove('active');}}});function toggleView(view){const databasesContainer=document.getElementById('databasesContainer');const tablesContainer=document.getElementById('tablesContainer');const container=databasesContainer || tablesContainer;if(!container)return;if(view==='list'){container.classList.remove('grid');container.classList.add('list-view');}else{container.classList.remove('list-view');container.classList.add('grid');}const buttons=document.querySelectorAll('.view-toggle-btn');buttons.forEach(btn=>{if(btn.getAttribute('data-view')===view){btn.classList.add('active');}else{btn.classList.remove('active');}});localStorage.setItem('dbadmin_view',view);}document.addEventListener('DOMContentLoaded',function(){const savedView=localStorage.getItem('dbadmin_view')|| 'grid';const databasesContainer=document.getElementById('databasesContainer');const tablesContainer=document.getElementById('tablesContainer');if(databasesContainer || tablesContainer){toggleView(savedView);}});function openModal(modalId){const modal=document.getElementById(modalId);if(modal){modal.classList.add('active');}}function closeModal(modalId){const modal=document.getElementById(modalId);if(modal){modal.classList.remove('active');}}document.addEventListener('click',function(event){if(event.target.classList.contains('modal')){event.target.classList.remove('active');}});document.addEventListener('keydown',function(event){if(event.key==='Escape'){const activeModal=document.querySelector('.modal.active');if(activeModal){activeModal.classList.remove('active');}}});function toggleNav(header){header.classList.toggle('collapsed');const content=header.nextElementSibling;content.classList.toggle('active');}function selectAll(checkbox){const checkboxes=document.querySelectorAll('input[name="selected[]"]');checkboxes.forEach(cb=>cb.checked=checkbox.checked);updateActionButton();}function updateActionButton(){const checked=document.querySelectorAll('input[name="selected[]"]:checked');const actionBtn=document.querySelector('.action-dropdown-toggle');if(actionBtn){actionBtn.disabled=checked.length===0;const count=document.querySelector('.selection-count');if(count){count.textContent=checked.length>0 ? `(${checked.length}selected)` : '';}}}function performAction(action){const checked=Array.from(document.querySelectorAll('input[name="selected[]"]:checked'));if(checked.length===0){showToast('Please select at least one item','warning');return;}const items=checked.map(cb=>cb.value).join(',');let confirmMsg='';if(action==='drop'){confirmMsg=`Drop these items: ${items}? This cannot be undone!`;}else if(action==='truncate'){confirmMsg=`Delete all records from: ${items}? This cannot be undone!`;}if(confirmMsg && !confirm(confirmMsg)){return;}showLoader('Processing '+action+'...');const form=document.getElementById('bulk-action-form');if(form){document.getElementById('bulk-action').value=action;setTimeout(()=>{form.submit();},500);}}function getFirstSelectedTable(){const checked=document.querySelector('input[name="selected[]"]:checked');if(!checked){alert('Please select a table first');return '';}return checked.value;}function searchItems(searchInput){const searchTerm=searchInput.value.toLowerCase();const container=document.getElementById('databasesContainer')|| document.getElementById('tablesContainer');if(!container)return;const cards=container.querySelectorAll('.grid-card');cards.forEach(card=>{const text=card.textContent.toLowerCase();if(text.includes(searchTerm)){card.style.display='';}else{card.style.display='none';}});}function switchDatabase(select){const db=select.value;if(db){showLoader('Loading database...');window.location.href='?page=tables&db='+encodeURIComponent(db);}}function toggleAllTables(checkbox){const tableCheckboxes=document.querySelectorAll('.table-checkbox');tableCheckboxes.forEach(cb=>{cb.checked=checkbox.checked;});}function updateColumnSelectAvailability(selectName,columnIndex,isVisible){document.querySelectorAll('select[name="'+selectName+'"]').forEach(select=>{const option=select.querySelector('option[data-column-index="'+columnIndex+'"]');if(!option){return;}option.disabled=!isVisible;option.classList.toggle('column-option-disabled',!isVisible);if(!isVisible && option.selected){select.value='';}});}function toggleColumn(columnIndex){const index=parseInt(columnIndex,10);const checkbox=document.querySelector('.column-toggle[data-column="'+index+'"]');const isVisible=!checkbox || checkbox.checked;document.querySelectorAll('.col-'+index).forEach(cell=>{cell.style.display=isVisible ? '' : 'none';});updateColumnSelectAvailability('search_column',index,isVisible);updateColumnSelectAvailability('sort_column',index,isVisible);}function toggleAllColumns(checkbox){const columnToggles=document.querySelectorAll('.column-toggle');columnToggles.forEach(toggle=>{toggle.checked=checkbox.checked;toggleColumn(toggle.dataset.column);});}document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('.column-toggle').forEach(toggle=>{toggleColumn(toggle.dataset.column);});});function loadExportTables(database){const tableSelect=document.getElementById('export-table');if(!database || !tableSelect){return;}tableSelect.innerHTML='<option value="">Loading tables...</option>';tableSelect.disabled=true;fetch('?action=get_tables&db='+encodeURIComponent(database)).then(response=>response.json()).then(tables=>{tableSelect.innerHTML='<option value="">--Choose a table--</option>';tables.forEach(table=>{const option=document.createElement('option');option.value=table;option.textContent=table;tableSelect.appendChild(option);});tableSelect.disabled=false;showToast('Tables loaded successfully','success',2000);}).catch(error=>{console.error('Error loading tables:',error);tableSelect.innerHTML='<option value="">Error loading tables</option>';tableSelect.disabled=false;showToast('Error loading tables','error');});}const translations={en:{app_name: "Dabiro",app_tagline: "Professional database management interface",database_type_label: "Database Type",host_label: "Host/File Path",username_label: "Username",password_label: "Password",database_name_label: "Database Name(optional)",connect_button: "Connect to Database",select_all: "Select All",search_databases_placeholder: "Search databases...",search_tables_placeholder: "Search tables...",grid_view: "Grid",list_view: "List",databases_subtitle: "Select a database to view its tables and data",create_database: "Create Database",actions: "Actions",drop_selected: "Drop Selected",truncate_selected: "Truncate Selected",operations: "Operations",rename_table: "Rename Table",copy_table: "Copy Table",move_table: "Move Table",total_databases: "Total Databases",database_type_stat: "Database Type",server_host: "Server Host",host_placeholder: "localhost",username_placeholder: "root",password_placeholder: "Enter password",database_placeholder: "Leave empty to see all databases",databases: "Databases",tables: "Tables",browse: "Browse",structure: "Structure",sql_console: "SQL Console",import_data: "Import Data",export_data: "Export Data",global_search: "Global Search",logout: "Logout",theme: "Theme",language: "Language",select_database: "Select Database",select_table: "Select Table",choose_database: "--Choose a database--",choose_table: "--Choose a table--",export_format: "Export Format",download_database: "Download Database",download_table: "Download Table",export_entire_database: "Export Entire Database",export_single_table: "Export Single Table",export_description: "Export databases or tables to various file formats",back_to_databases: "Back to Databases",search_in_column: "Search in Column",sort_by: "Sort By",rows_per_page: "Rows Per Page",insert_record: "Insert Record",edit_record: "Edit Record",delete: "Delete",edit: "Edit",save: "Save",cancel: "Cancel",search: "Search",apply: "Apply"},es:{app_name: "Dabiro",app_tagline: "Interfaz profesional de administración de bases de datos",database_type_label: "Tipo de Base de Datos",host_label: "Host/Ruta de Archivo",username_label: "Usuario",password_label: "Contraseña",database_name_label: "Base de Datos(opcional)",connect_button: "Conectar a la Base de Datos",select_all: "Seleccionar Todo",search_databases_placeholder: "Buscar bases de datos...",search_tables_placeholder: "Buscar tablas...",grid_view: "Cuadrícula",list_view: "Lista",databases_subtitle: "Selecciona una base de datos para ver sus tablas y datos",create_database: "Crear Base de Datos",actions: "Acciones",drop_selected: "Eliminar Seleccionadas",truncate_selected: "Truncar Seleccionadas",operations: "Operaciones",rename_table: "Renombrar Tabla",copy_table: "Copiar Tabla",move_table: "Mover Tabla",total_databases: "Bases de Datos Totales",database_type_stat: "Tipo de Base de Datos",server_host: "Servidor",host_placeholder: "localhost",username_placeholder: "root",password_placeholder: "Introducir contraseña",database_placeholder: "Déjalo vacío para ver todas las bases",databases: "Bases de Datos",tables: "Tablas",browse: "Explorar",structure: "Estructura",sql_console: "Consola SQL",import_data: "Importar Datos",export_data: "Exportar Datos",global_search: "Búsqueda Global",logout: "Cerrar Sesión",theme: "Tema",language: "Idioma",select_database: "Seleccionar Base de Datos",select_table: "Seleccionar Tabla",choose_database: "--Elegir base de datos--",choose_table: "--Elegir tabla--",export_format: "Formato de Exportación",download_database: "Descargar Base de Datos",download_table: "Descargar Tabla",export_entire_database: "Exportar Base de Datos Completa",export_single_table: "Exportar Tabla Individual",export_description: "Exportar bases de datos o tablas a varios formatos",back_to_databases: "Volver a Bases de Datos",search_in_column: "Buscar en Columna",sort_by: "Ordenar Por",rows_per_page: "Filas por Página",insert_record: "Insertar Registro",edit_record: "Editar Registro",delete: "Eliminar",edit: "Editar",save: "Guardar",cancel: "Cancelar",search: "Buscar",apply: "Aplicar"},fr:{app_name: "Dabiro",app_tagline: "Interface professionnelle de gestion de bases de données",database_type_label: "Type de Base de Données",host_label: "Hôte/Chemin du Fichier",username_label: "Nom d'utilisateur",password_label: "Mot de passe",database_name_label: "Nom de la Base(optionnel)",connect_button: "Se Connecter à la Base",select_all: "Tout Sélectionner",search_databases_placeholder: "Rechercher des bases...",search_tables_placeholder: "Rechercher des tables...",grid_view: "Grille",list_view: "Liste",databases_subtitle: "Sélectionnez une base pour voir ses tables et données",create_database: "Créer une Base",actions: "Actions",drop_selected: "Supprimer la sélection",truncate_selected: "Tronquer la sélection",operations: "Opérations",rename_table: "Renommer la Table",copy_table: "Copier la Table",move_table: "Déplacer la Table",total_databases: "Bases Totales",database_type_stat: "Type de Base",server_host: "Serveur",host_placeholder: "localhost",username_placeholder: "root",password_placeholder: "Entrer le mot de passe",database_placeholder: "Laisser vide pour voir toutes les bases",databases: "Bases de Données",tables: "Tables",browse: "Parcourir",structure: "Structure",sql_console: "Console SQL",import_data: "Importer Données",export_data: "Exporter Données",global_search: "Recherche Globale",logout: "Déconnexion",theme: "Thème",language: "Langue",select_database: "Sélectionner Base de Données",select_table: "Sélectionner Table",choose_database: "--Choisir une base--",choose_table: "--Choisir une table--",export_format: "Format d'Export",download_database: "Télécharger Base",download_table: "Télécharger Table",export_entire_database: "Exporter Base Complète",export_single_table: "Exporter Table Unique",export_description: "Exporter bases de données ou tables vers différents formats",back_to_databases: "Retour aux Bases",search_in_column: "Rechercher dans Colonne",sort_by: "Trier Par",rows_per_page: "Lignes par Page",insert_record: "Insérer Enregistrement",edit_record: "Modifier Enregistrement",delete: "Supprimer",edit: "Modifier",save: "Enregistrer",cancel: "Annuler",search: "Rechercher",apply: "Appliquer"},de:{app_name: "Dabiro",app_tagline: "Professionelle Oberfläche zur Datenbankverwaltung",database_type_label: "Datenbanktyp",host_label: "Host/Dateipfad",username_label: "Benutzername",password_label: "Passwort",database_name_label: "Datenbankname(optional)",connect_button: "Mit Datenbank verbinden",select_all: "Alle auswählen",search_databases_placeholder: "Datenbanken durchsuchen...",search_tables_placeholder: "Tabellen durchsuchen...",grid_view: "Raster",list_view: "Liste",databases_subtitle: "Wähle eine Datenbank,um Tabellen und Daten zu sehen",create_database: "Datenbank erstellen",actions: "Aktionen",drop_selected: "Ausgewählte löschen",truncate_selected: "Ausgewählte leeren",operations: "Operationen",rename_table: "Tabelle umbenennen",copy_table: "Tabelle kopieren",move_table: "Tabelle verschieben",total_databases: "Gesamtzahl Datenbanken",database_type_stat: "Datenbanktyp",server_host: "Server",host_placeholder: "localhost",username_placeholder: "root",password_placeholder: "Passwort eingeben",database_placeholder: "Leer lassen,um alle Datenbanken zu sehen",databases: "Datenbanken",tables: "Tabellen",browse: "Durchsuchen",structure: "Struktur",sql_console: "SQL Konsole",import_data: "Daten Importieren",export_data: "Daten Exportieren",global_search: "Globale Suche",logout: "Abmelden",theme: "Design",language: "Sprache",select_database: "Datenbank Auswählen",select_table: "Tabelle Auswählen",choose_database: "--Datenbank wählen--",choose_table: "--Tabelle wählen--",export_format: "Exportformat",download_database: "Datenbank Herunterladen",download_table: "Tabelle Herunterladen",export_entire_database: "Gesamte Datenbank Exportieren",export_single_table: "Einzelne Tabelle Exportieren",export_description: "Datenbanken oder Tabellen in verschiedene Formate exportieren",back_to_databases: "Zurück zu Datenbanken",search_in_column: "In Spalte Suchen",sort_by: "Sortieren Nach",rows_per_page: "Zeilen pro Seite",insert_record: "Datensatz Einfügen",edit_record: "Datensatz Bearbeiten",delete: "Löschen",edit: "Bearbeiten",save: "Speichern",cancel: "Abbrechen",search: "Suchen",apply: "Anwenden"},pt:{app_name: "Dabiro",app_tagline: "Interface profissional para gestão de bancos de dados",database_type_label: "Tipo de Banco",host_label: "Host/Caminho do Arquivo",username_label: "Usuário",password_label: "Senha",database_name_label: "Nome do Banco(opcional)",connect_button: "Conectar ao Banco",select_all: "Selecionar Tudo",search_databases_placeholder: "Buscar bancos...",search_tables_placeholder: "Buscar tabelas...",grid_view: "Grade",list_view: "Lista",databases_subtitle: "Selecione um banco para ver suas tabelas e dados",create_database: "Criar Banco",actions: "Ações",drop_selected: "Excluir Selecionados",truncate_selected: "Truncar Selecionados",operations: "Operações",rename_table: "Renomear Tabela",copy_table: "Copiar Tabela",move_table: "Mover Tabela",total_databases: "Total de Bancos",database_type_stat: "Tipo de Banco",server_host: "Servidor",host_placeholder: "localhost",username_placeholder: "root",password_placeholder: "Digite a senha",database_placeholder: "Deixe vazio para ver todos os bancos",databases: "Bancos de Dados",tables: "Tabelas",browse: "Navegar",structure: "Estrutura",sql_console: "Console SQL",import_data: "Importar Dados",export_data: "Exportar Dados",global_search: "Busca Global",logout: "Sair",theme: "Tema",language: "Idioma",select_database: "Selecionar Banco",select_table: "Selecionar Tabela",choose_database: "--Escolher banco--",choose_table: "--Escolher tabela--",export_format: "Formato de Exportação",download_database: "Baixar Banco",download_table: "Baixar Tabela",export_entire_database: "Exportar Banco Completo",export_single_table: "Exportar Tabela Individual",export_description: "Exportar bancos ou tabelas para vários formatos",back_to_databases: "Voltar aos Bancos",search_in_column: "Buscar na Coluna",sort_by: "Ordenar Por",rows_per_page: "Linhas por Página",insert_record: "Inserir Registro",edit_record: "Editar Registro",delete: "Excluir",edit: "Editar",save: "Salvar",cancel: "Cancelar",search: "Buscar",apply: "Aplicar"},zh:{app_name: "Dabiro",app_tagline: "专业的数据库管理界面",database_type_label: "数据库类型",host_label: "主机/文件路径",username_label: "用户名",password_label: "密码",database_name_label: "数据库名称（可选）",connect_button: "连接数据库",select_all: "全选",search_databases_placeholder: "搜索数据库...",search_tables_placeholder: "搜索数据表...",grid_view: "网格",list_view: "列表",databases_subtitle: "选择一个数据库以查看其数据表和数据",create_database: "创建数据库",actions: "操作",drop_selected: "删除所选",truncate_selected: "清空所选",operations: "更多操作",rename_table: "重命名数据表",copy_table: "复制数据表",move_table: "移动数据表",total_databases: "数据库总数",database_type_stat: "数据库类型",server_host: "服务器",host_placeholder: "localhost",username_placeholder: "root",password_placeholder: "输入密码",database_placeholder: "留空可查看全部数据库",databases: "数据库",tables: "表",browse: "浏览",structure: "结构",sql_console: "SQL控制台",import_data: "导入数据",export_data: "导出数据",global_search: "全局搜索",logout: "退出",theme: "主题",language: "语言",select_database: "选择数据库",select_table: "选择表",choose_database: "--选择数据库--",choose_table: "--选择表--",export_format: "导出格式",download_database: "下载数据库",download_table: "下载表",export_entire_database: "导出整个数据库",export_single_table: "导出单个表",export_description: "将数据库或表导出为各种文件格式",back_to_databases: "返回数据库",search_in_column: "在列中搜索",sort_by: "排序方式",rows_per_page: "每页行数",insert_record: "插入记录",edit_record: "编辑记录",delete: "删除",edit: "编辑",save: "保存",cancel: "取消",search: "搜索",apply: "应用"},ja:{app_name: "Dabiro",app_tagline: "プロフェッショナルなデータベース管理インターフェース",database_type_label: "データベース種類",host_label: "ホスト/ファイルパス",username_label: "ユーザー名",password_label: "パスワード",database_name_label: "データベース名（任意）",connect_button: "データベースに接続",select_all: "すべて選択",search_databases_placeholder: "データベースを検索...",search_tables_placeholder: "テーブルを検索...",grid_view: "グリッド",list_view: "リスト",databases_subtitle: "テーブルとデータを見るデータベースを選択してください",create_database: "データベースを作成",actions: "アクション",drop_selected: "選択を削除",truncate_selected: "選択を空にする",operations: "操作",rename_table: "テーブル名を変更",copy_table: "テーブルをコピー",move_table: "テーブルを移動",total_databases: "総データベース数",database_type_stat: "データベース種類",server_host: "サーバー",host_placeholder: "localhost",username_placeholder: "root",password_placeholder: "パスワードを入力",database_placeholder: "空白で全てのデータベースを表示",databases: "データベース",tables: "テーブル",browse: "閲覧",structure: "構造",sql_console: "SQLコンソール",import_data: "データインポート",export_data: "データエクスポート",global_search: "グローバル検索",logout: "ログアウト",theme: "テーマ",language: "言語",select_database: "データベースを選択",select_table: "テーブルを選択",choose_database: "--データベースを選択--",choose_table: "--テーブルを選択--",export_format: "エクスポート形式",download_database: "データベースをダウンロード",download_table: "テーブルをダウンロード",export_entire_database: "データベース全体をエクスポート",export_single_table: "単一テーブルをエクスポート",export_description: "データベースまたはテーブルを様々な形式でエクスポート",back_to_databases: "データベースに戻る",search_in_column: "列内を検索",sort_by: "並び替え",rows_per_page: "1ページの行数",insert_record: "レコードを挿入",edit_record: "レコードを編集",delete: "削除",edit: "編集",save: "保存",cancel: "キャンセル",search: "検索",apply: "適用"},ar:{app_name: "Dabiro",app_tagline: "واجهة احترافية لإدارة قواعد البيانات",database_type_label: "نوع قاعدة البيانات",host_label: "المضيف/مسار الملف",username_label: "اسم المستخدم",password_label: "كلمة المرور",database_name_label: "اسم القاعدة(اختياري)",connect_button: "الاتصال بقاعدة البيانات",select_all: "تحديد الكل",search_databases_placeholder: "ابحث في قواعد البيانات...",search_tables_placeholder: "ابحث في الجداول...",grid_view: "شبكي",list_view: "قائمة",databases_subtitle: "اختر قاعدة بيانات لعرض جداولها وبياناتها",create_database: "إنشاء قاعدة بيانات",actions: "إجراءات",drop_selected: "حذف المحدد",truncate_selected: "تفريغ المحدد",operations: "عمليات",rename_table: "إعادة تسمية الجدول",copy_table: "نسخ الجدول",move_table: "نقل الجدول",total_databases: "إجمالي القواعد",database_type_stat: "نوع القاعدة",server_host: "الخادم",host_placeholder: "localhost",username_placeholder: "root",password_placeholder: "أدخل كلمة المرور",database_placeholder: "اتركه فارغًا لعرض كل القواعد",databases: "قواعد البيانات",tables: "الجداول",browse: "تصفح",structure: "الهيكل",sql_console: "وحدة SQL",import_data: "استيراد البيانات",export_data: "تصدير البيانات",global_search: "البحث الشامل",logout: "تسجيل الخروج",theme: "السمة",language: "اللغة",select_database: "اختر قاعدة البيانات",select_table: "اختر الجدول",choose_database: "--اختر قاعدة بيانات--",choose_table: "--اختر جدول--",export_format: "صيغة التصدير",download_database: "تحميل قاعدة البيانات",download_table: "تحميل الجدول",export_entire_database: "تصدير قاعدة البيانات بالكامل",export_single_table: "تصدير جدول واحد",export_description: "تصدير قواعد البيانات أو الجداول إلى تنسيقات مختلفة",back_to_databases: "العودة إلى قواعد البيانات",search_in_column: "البحث في العمود",sort_by: "ترتيب حسب",rows_per_page: "صفوف لكل صفحة",insert_record: "إدراج سجل",edit_record: "تعديل السجل",delete: "حذف",edit: "تعديل",save: "حفظ",cancel: "إلغاء",search: "بحث",apply: "تطبيق"},it:{app_name: "Dabiro",app_tagline: "Interfaccia professionale per la gestione dei database",database_type_label: "Tipo di Database",host_label: "Host/Percorso File",username_label: "Nome utente",password_label: "Password",database_name_label: "Nome Database(opzionale)",connect_button: "Connetti al Database",select_all: "Seleziona tutto",search_databases_placeholder: "Cerca database...",search_tables_placeholder: "Cerca tabelle...",grid_view: "Griglia",list_view: "Lista",databases_subtitle: "Seleziona un database per vedere tabelle e dati",create_database: "Crea Database",actions: "Azioni",drop_selected: "Elimina selezionati",truncate_selected: "Svuota selezionati",operations: "Operazioni",rename_table: "Rinomina Tabella",copy_table: "Copia Tabella",move_table: "Sposta Tabella",total_databases: "Database Totali",database_type_stat: "Tipo di Database",server_host: "Server",host_placeholder: "localhost",username_placeholder: "root",password_placeholder: "Inserisci la password",database_placeholder: "Lascia vuoto per vedere tutti i database",databases: "Database",tables: "Tabelle",browse: "Esplora",structure: "Struttura",sql_console: "Console SQL",import_data: "Importa Dati",export_data: "Esporta Dati",global_search: "Ricerca Globale",logout: "Disconnetti",theme: "Tema",language: "Lingua",select_database: "Seleziona Database",select_table: "Seleziona Tabella",choose_database: "--Scegli un database--",choose_table: "--Scegli una tabella--",export_format: "Formato di Esportazione",download_database: "Scarica Database",download_table: "Scarica Tabella",export_entire_database: "Esporta l'intero Database",export_single_table: "Esporta Singola Tabella",export_description: "Esporta database o tabelle in vari formati",back_to_databases: "Torna ai Database",search_in_column: "Cerca nella Colonna",sort_by: "Ordina per",rows_per_page: "Righe per Pagina",insert_record: "Inserisci Record",edit_record: "Modifica Record",delete: "Elimina",edit: "Modifica",save: "Salva",cancel: "Annulla",search: "Cerca",apply: "Applica"},ru:{app_name: "Dabiro",app_tagline: "Профессиональный интерфейс управления базами данных",database_type_label: "Тип базы данных",host_label: "Хост/путь к файлу",username_label: "Имя пользователя",password_label: "Пароль",database_name_label: "Название базы(необязательно)",connect_button: "Подключиться к базе",select_all: "Выбрать все",search_databases_placeholder: "Поиск по базам данных...",search_tables_placeholder: "Поиск по таблицам...",grid_view: "Сетка",list_view: "Список",databases_subtitle: "Выберите базу данных,чтобы увидеть таблицы и данные",create_database: "Создать базу данных",actions: "Действия",drop_selected: "Удалить выбранные",truncate_selected: "Очистить выбранные",operations: "Операции",rename_table: "Переименовать таблицу",copy_table: "Копировать таблицу",move_table: "Переместить таблицу",total_databases: "Всего баз данных",database_type_stat: "Тип базы",server_host: "Сервер",host_placeholder: "localhost",username_placeholder: "root",password_placeholder: "Введите пароль",database_placeholder: "Оставьте пустым,чтобы увидеть все базы",databases: "Базы данных",tables: "Таблицы",browse: "Просмотр",structure: "Структура",sql_console: "SQL консоль",import_data: "Импорт данных",export_data: "Экспорт данных",global_search: "Глобальный поиск",logout: "Выйти",theme: "Тема",language: "Язык",select_database: "Выберите базу",select_table: "Выберите таблицу",choose_database: "--Выберите базу--",choose_table: "--Выберите таблицу--",export_format: "Формат экспорта",download_database: "Скачать базу",download_table: "Скачать таблицу",export_entire_database: "Экспорт всей базы",export_single_table: "Экспорт одной таблицы",export_description: "Экспортируйте базы или таблицы в различные форматы",back_to_databases: "Назад к базам",search_in_column: "Поиск по столбцу",sort_by: "Сортировать по",rows_per_page: "Строк на страницу",insert_record: "Вставить запись",edit_record: "Редактировать запись",delete: "Удалить",edit: "Изменить",save: "Сохранить",cancel: "Отмена",search: "Поиск",apply: "Применить"},tr:{app_name: "Dabiro",app_tagline: "Profesyonel veritabanı yönetim arayüzü",database_type_label: "Veritabanı Türü",host_label: "Sunucu/Dosya Yolu",username_label: "Kullanıcı Adı",password_label: "Parola",database_name_label: "Veritabanı Adı(isteğe bağlı)",connect_button: "Veritabanına Bağlan",select_all: "Tümünü Seç",search_databases_placeholder: "Veritabanı ara...",search_tables_placeholder: "Tablo ara...",grid_view: "Izgara",list_view: "Liste",databases_subtitle: "Tabloları ve verileri görmek için bir veritabanı seçin",create_database: "Veritabanı Oluştur",actions: "İşlemler",drop_selected: "Seçileni Sil",truncate_selected: "Seçileni Temizle",operations: "Operasyonlar",rename_table: "Tabloyu Yeniden Adlandır",copy_table: "Tabloyu Kopyala",move_table: "Tabloyu Taşı",total_databases: "Toplam Veritabanı",database_type_stat: "Veritabanı Türü",server_host: "Sunucu",host_placeholder: "localhost",username_placeholder: "root",password_placeholder: "Parola girin",database_placeholder: "Tüm veritabanlarını görmek için boş bırakın",databases: "Veritabanları",tables: "Tablolar",browse: "Gözat",structure: "Yapı",sql_console: "SQL Konsolu",import_data: "Veri İçe Aktar",export_data: "Veri Dışa Aktar",global_search: "Genel Arama",logout: "Çıkış Yap",theme: "Tema",language: "Dil",select_database: "Veritabanı Seç",select_table: "Tablo Seç",choose_database: "--Veritabanı seç--",choose_table: "--Tablo seç--",export_format: "Dışa Aktarım Formatı",download_database: "Veritabanını İndir",download_table: "Tabloyu İndir",export_entire_database: "Tüm Veritabanını Dışa Aktar",export_single_table: "Tek Tabloyu Dışa Aktar",export_description: "Veritabanlarını veya tabloları çeşitli formatlara aktarın",back_to_databases: "Veritabanlarına Dön",search_in_column: "Sütunda Ara",sort_by: "Sırala",rows_per_page: "Sayfa Başına Satır",insert_record: "Kayıt Ekle",edit_record: "Kaydı Düzenle",delete: "Sil",edit: "Düzenle",save: "Kaydet",cancel: "İptal",search: "Ara",apply: "Uygula"},hi:{app_name: "Dabiro",app_tagline: "व्यावसायिक डेटाबेस प्रबंधन इंटरफ़ेस",database_type_label: "डेटाबेस प्रकार",host_label: "होस्ट/फ़ाइल पाथ",username_label: "उपयोगकर्ता नाम",password_label: "पासवर्ड",database_name_label: "डेटाबेस नाम(वैकल्पिक)",connect_button: "डेटाबेस से कनेक्ट करें",select_all: "सभी चुनें",search_databases_placeholder: "डेटाबेस खोजें...",search_tables_placeholder: "टेबल खोजें...",grid_view: "ग्रिड",list_view: "सूची",databases_subtitle: "टेबल और डेटा देखने के लिए एक डेटाबेस चुनें",create_database: "डेटाबेस बनाएँ",actions: "कार्रवाई",drop_selected: "चयनित हटाएँ",truncate_selected: "चयनित खाली करें",operations: "ऑपरेशन",rename_table: "टेबल का नाम बदलें",copy_table: "टेबल कॉपी करें",move_table: "टेबल स्थानांतरित करें",total_databases: "कुल डेटाबेस",database_type_stat: "डेटाबेस प्रकार",server_host: "सर्वर",host_placeholder: "localhost",username_placeholder: "root",password_placeholder: "पासवर्ड दर्ज करें",database_placeholder: "सभी डेटाबेस देखने के लिए खाली छोड़ें",databases: "डेटाबेस",tables: "टेबल",browse: "ब्राउज़",structure: "स्ट्रक्चर",sql_console: "SQL कंसोल",import_data: "डेटा आयात करें",export_data: "डेटा निर्यात करें",global_search: "वैश्विक खोज",logout: "लॉगआउट",theme: "थीम",language: "भाषा",select_database: "डेटाबेस चुनें",select_table: "टेबल चुनें",choose_database: "--डेटाबेस चुनें--",choose_table: "--टेबल चुनें--",export_format: "निर्यात प्रारूप",download_database: "डेटाबेस डाउनलोड करें",download_table: "टेबल डाउनलोड करें",export_entire_database: "पूरा डेटाबेस निर्यात करें",export_single_table: "एकल टेबल निर्यात करें",export_description: "डेटाबेस या टेबल को विभिन्न प्रारूपों में निर्यात करें",back_to_databases: "डेटाबेस पर वापस जाएँ",search_in_column: "कॉलम में खोजें",sort_by: "क्रमबद्ध करें",rows_per_page: "प्रति पेज पंक्तियाँ",insert_record: "रिकॉर्ड जोड़ें",edit_record: "रिकॉर्ड संपादित करें",delete: "हटाएँ",edit: "संपादित",save: "सहेजें",cancel: "रद्द करें",search: "खोजें",apply: "लागू करें"},ko:{app_name: "Dabiro",app_tagline: "전문적인 데이터베이스 관리 인터페이스",database_type_label: "데이터베이스 유형",host_label: "호스트/파일 경로",username_label: "사용자명",password_label: "비밀번호",database_name_label: "데이터베이스 이름(선택 사항)",connect_button: "데이터베이스 연결",select_all: "전체 선택",search_databases_placeholder: "데이터베이스 검색...",search_tables_placeholder: "테이블 검색...",grid_view: "그리드",list_view: "목록",databases_subtitle: "테이블과 데이터를 보려면 데이터베이스를 선택하세요",create_database: "데이터베이스 생성",actions: "작업",drop_selected: "선택 삭제",truncate_selected: "선택 비우기",operations: "추가 작업",rename_table: "테이블 이름 변경",copy_table: "테이블 복사",move_table: "테이블 이동",total_databases: "총 데이터베이스",database_type_stat: "데이터베이스 유형",server_host: "서버",host_placeholder: "localhost",username_placeholder: "root",password_placeholder: "비밀번호 입력",database_placeholder: "모든 데이터베이스를 보려면 비워 두세요",databases: "데이터베이스",tables: "테이블",browse: "탐색",structure: "구조",sql_console: "SQL 콘솔",import_data: "데이터 가져오기",export_data: "데이터 내보내기",global_search: "전체 검색",logout: "로그아웃",theme: "테마",language: "언어",select_database: "데이터베이스 선택",select_table: "테이블 선택",choose_database: "--데이터베이스 선택--",choose_table: "--테이블 선택--",export_format: "내보내기 형식",download_database: "데이터베이스 다운로드",download_table: "테이블 다운로드",export_entire_database: "전체 데이터베이스 내보내기",export_single_table: "단일 테이블 내보내기",export_description: "데이터베이스나 테이블을 다양한 형식으로 내보내세요",back_to_databases: "데이터베이스로 돌아가기",search_in_column: "열에서 검색",sort_by: "정렬 기준",rows_per_page: "페이지당 행",insert_record: "레코드 추가",edit_record: "레코드 편집",delete: "삭제",edit: "편집",save: "저장",cancel: "취소",search: "검색",apply: "적용"}};function getCurrentLang(){const match=document.cookie.match(/dbadmin_lang=([^;]+)/);return match ? match[1] : 'en';}function translate(lang,key){const langPack=translations[lang] || translations.en;return(langPack && langPack[key])|| translations.en[key] || '';}function applyTranslations(langOverride){const lang=langOverride || getCurrentLang();document.querySelectorAll('[data-i18n]').forEach(el=>{const key=el.getAttribute('data-i18n');const value=translate(lang,key);if(value){el.textContent=value;}});document.querySelectorAll('[data-i18n-placeholder]').forEach(el=>{const key=el.getAttribute('data-i18n-placeholder');const value=translate(lang,key);if(value){el.setAttribute('placeholder',value);}});if(lang==='ar'){document.body.style.direction='rtl';}else{document.body.style.direction='ltr';}}document.addEventListener('DOMContentLoaded',applyTranslations);window.addEventListener('dbadmin:language-change',function(event){const lang=event && event.detail ? event.detail.lang : null;applyTranslations(lang);});</script><?php endif; ?><script>
        (function() {
            const themes = <?php echo json_encode(array_keys($theme_options)); ?>;
            const languages = <?php echo json_encode(array_keys($language_options)); ?>;
            const defaults = {
                theme: "<?php echo $current_theme; ?>",
                lang: "<?php echo $current_lang; ?>"
            };
            const ONE_YEAR = 60 * 60 * 24 * 365;

            function setCookie(name, value) {
                document.cookie = `${name}=${value};path=/;max-age=${ONE_YEAR}`;
            }

            function getCookie(name) {
                const match = document.cookie.match(new RegExp(name + '=([^;]+)'));
                return match ? decodeURIComponent(match[1]) : null;
            }

            function applyTheme(theme, options = {}) {
                if (!themes.includes(theme)) {
                    theme = defaults.theme;
                }

                document.documentElement.setAttribute('data-theme', theme);
                if (options.persist !== false) {
                    setCookie('dbadmin_theme', theme);
                }
                syncThemeControls(theme);

                if (!options.silent) {
                    window.dispatchEvent(new CustomEvent('dbadmin:theme-change', {
                        detail: {
                            theme
                        }
                    }));
                }
            }

            function applyLanguage(lang, options = {}) {
                if (!languages.includes(lang)) {
                    lang = defaults.lang;
                }

                document.documentElement.setAttribute('lang', lang);
                if (options.persist !== false) {
                    setCookie('dbadmin_lang', lang);
                }
                syncLanguageControls(lang);

                if (document.body) {
                    document.body.style.direction = lang === 'ar' ? 'rtl' : 'ltr';
                }

                if (!options.silent) {
                    window.dispatchEvent(new CustomEvent('dbadmin:language-change', {
                        detail: {
                            lang
                        }
                    }));
                }
            }

            function syncThemeControls(theme) {
                document.querySelectorAll('[data-theme-select]').forEach(select => {
                    if (select.value !== theme) {
                        select.value = theme;
                    }
                });
                document.querySelectorAll('[data-theme-option]').forEach(option => {
                    option.classList.toggle('active', option.dataset.themeOption === theme);
                });
            }

            function syncLanguageControls(lang) {
                document.querySelectorAll('[data-language-select]').forEach(select => {
                    if (select.value !== lang) {
                        select.value = lang;
                    }
                });
                document.querySelectorAll('[data-language-option]').forEach(option => {
                    option.classList.toggle('active', option.dataset.languageOption === lang);
                });
            }

            function closeDropdowns(except) {
                document.querySelectorAll('[data-dropdown].open').forEach(dropdown => {
                    if (!except || dropdown !== except) {
                        dropdown.classList.remove('open');
                    }
                });
            }

            function bindDropdowns() {
                document.querySelectorAll('[data-dropdown]').forEach(dropdown => {
                    const toggle = dropdown.querySelector('.dropdown-toggle');
                    if (!toggle) return;

                    toggle.addEventListener('click', event => {
                        event.preventDefault();
                        const isOpen = dropdown.classList.contains('open');
                        closeDropdowns(isOpen ? null : dropdown);
                        if (!isOpen) {
                            dropdown.classList.add('open');
                        }
                    });
                });

                document.addEventListener('click', event => {
                    if (!event.target.closest('[data-dropdown]')) {
                        closeDropdowns();
                    }
                });

                document.addEventListener('keydown', event => {
                    if (event.key === 'Escape') {
                        closeDropdowns();
                    }
                });
            }

            function bindPreferenceControls() {
                document.querySelectorAll('[data-theme-select]').forEach(select => {
                    select.addEventListener('change', event => {
                        applyTheme(event.target.value);
                    });
                });

                document.querySelectorAll('[data-language-select]').forEach(select => {
                    select.addEventListener('change', event => {
                        applyLanguage(event.target.value);
                    });
                });

                document.querySelectorAll('[data-theme-option]').forEach(option => {
                    option.addEventListener('click', event => {
                        event.preventDefault();
                        applyTheme(option.dataset.themeOption);
                    });
                });

                document.querySelectorAll('[data-language-option]').forEach(option => {
                    option.addEventListener('click', event => {
                        event.preventDefault();
                        applyLanguage(option.dataset.languageOption);
                    });
                });
            }

            document.addEventListener('DOMContentLoaded', () => {
                const initialTheme = getCookie('dbadmin_theme') || defaults.theme;
                const initialLang = getCookie('dbadmin_lang') || defaults.lang;

                applyTheme(initialTheme, {
                    persist: false,
                    silent: true
                });
                applyLanguage(initialLang, {
                    persist: false,
                    silent: true
                });

                bindPreferenceControls();
                bindDropdowns();

                // Show toast notifications for PHP messages
                <?php if (isset($success_message)): ?>
                showToast('<?php echo addslashes($success_message); ?>', 'success');
                <?php endif; ?><?php if (isset($error_message)): ?>
                showToast('<?php echo addslashes($error_message); ?>', 'error');
                <?php endif; ?>
            });
        })();
    </script></body></html><script>(function(){const themes=<?php echo json_encode(array_keys($theme_options));?>;const languages=<?php echo json_encode(array_keys($language_options));?>;const defaults={theme: "<?php echo $current_theme;?>",lang: "<?php echo $current_lang;?>"};const ONE_YEAR=60*60*24*365;function setCookie(name,value){document.cookie=`${name}=${value};path=/;max-age=${ONE_YEAR}`;}function getCookie(name){const match=document.cookie.match(new RegExp(name+'=([^;]+)'));return match ? decodeURIComponent(match[1]): null;}function applyTheme(theme,options={}){if(!themes.includes(theme)){theme=defaults.theme;}document.documentElement.setAttribute('data-theme',theme);if(options.persist !==false){setCookie('dbadmin_theme',theme);}syncThemeControls(theme);if(!options.silent){window.dispatchEvent(new CustomEvent('dbadmin:theme-change',{detail:{theme}}));}}function applyLanguage(lang,options={}){if(!languages.includes(lang)){lang=defaults.lang;}document.documentElement.setAttribute('lang',lang);if(options.persist !==false){setCookie('dbadmin_lang',lang);}syncLanguageControls(lang);if(document.body){document.body.style.direction=lang==='ar' ? 'rtl' : 'ltr';}if(!options.silent){window.dispatchEvent(new CustomEvent('dbadmin:language-change',{detail:{lang}}));}}function syncThemeControls(theme){document.querySelectorAll('[data-theme-select]').forEach(select=>{if(select.value !==theme){select.value=theme;}});document.querySelectorAll('[data-theme-option]').forEach(option=>{option.classList.toggle('active',option.dataset.themeOption===theme);});}function syncLanguageControls(lang){document.querySelectorAll('[data-language-select]').forEach(select=>{if(select.value !==lang){select.value=lang;}});document.querySelectorAll('[data-language-option]').forEach(option=>{option.classList.toggle('active',option.dataset.languageOption===lang);});}function closeDropdowns(except){document.querySelectorAll('[data-dropdown].open').forEach(dropdown=>{if(!except || dropdown !==except){dropdown.classList.remove('open');}});}function bindDropdowns(){document.querySelectorAll('[data-dropdown]').forEach(dropdown=>{const toggle=dropdown.querySelector('.dropdown-toggle');if(!toggle)return;toggle.addEventListener('click',event=>{event.preventDefault();const isOpen=dropdown.classList.contains('open');closeDropdowns(isOpen ? null : dropdown);if(!isOpen){dropdown.classList.add('open');}});});document.addEventListener('click',event=>{if(!event.target.closest('[data-dropdown]')){closeDropdowns();}});document.addEventListener('keydown',event=>{if(event.key==='Escape'){closeDropdowns();}});}function bindPreferenceControls(){document.querySelectorAll('[data-theme-select]').forEach(select=>{select.addEventListener('change',event=>{applyTheme(event.target.value);});});document.querySelectorAll('[data-language-select]').forEach(select=>{select.addEventListener('change',event=>{applyLanguage(event.target.value);});});document.querySelectorAll('[data-theme-option]').forEach(option=>{option.addEventListener('click',event=>{event.preventDefault();applyTheme(option.dataset.themeOption);});});document.querySelectorAll('[data-language-option]').forEach(option=>{option.addEventListener('click',event=>{event.preventDefault();applyLanguage(option.dataset.languageOption);});});}document.addEventListener('DOMContentLoaded',()=>{const initialTheme=getCookie('dbadmin_theme')|| defaults.theme;const initialLang=getCookie('dbadmin_lang')|| defaults.lang;applyTheme(initialTheme,{persist: false,silent: true});applyLanguage(initialLang,{persist: false,silent: true});bindPreferenceControls();bindDropdowns();<?php if(isset($success_message)): ?>showToast('<?php echo addslashes($success_message);?>','success');<?php endif;?><?php if(isset($error_message)): ?>showToast('<?php echo addslashes($error_message);?>','error');<?php endif;?>});})();</script>