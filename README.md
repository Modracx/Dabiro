# Dabiro

[![Version](https://img.shields.io/badge/version-1.0.2-blue.svg)](https://github.com/Modracx/Dabiro)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Status](https://img.shields.io/badge/status-stable-success.svg)](https://github.com/Modracx/Dabiro)
[![Themes](https://img.shields.io/badge/themes-7%20included-orange.svg)](https://github.com/Modracx/Dabiro)
[![Languages](https://img.shields.io/badge/languages-13%20supported-brightgreen.svg)](https://github.com/Modracx/Dabiro)

**Professional database administration made simple.** Dabiro is a modern, single-file database management tool with a fully responsive web interface. Supports both **PHP** and **Node.js** servers. Manage **MySQL/MariaDB**, **PostgreSQL**, and **SQLite** databases with professional-grade features—no installation complexity, no external dependencies, no setup required.

## Table of Contents
- [Features](#features)
- [Quick Start](#quick-start)
- [Supported Databases](#supported-databases)
- [Installation](#installation)
- [Usage](#usage)
- [Security](#security)
- [Troubleshooting](#troubleshooting)
- [Performance](#performance)
- [License](#license)

---

## Features

### 🗄️ Multi-Database Support
- **MySQL** 5.7+
- **MariaDB** 10.0+
- **PostgreSQL** 9.0+
- **SQLite** 3.0+

### 📊 Database Management
- Create, drop, and manage multiple databases
- Add/remove database connections with credential security
- Real-time connection status and performance metrics
- Connection pooling for optimal resource utilization

### 📋 Table Operations
- Browse, create, rename, copy, and move tables
- Pagination optimized for large datasets
- Smart query optimization and caching
- Table structure viewing and modification

### ✏️ Data Management
- Insert, edit, delete records with validation
- Advanced filtering, sorting, and limiting
- Grid and list viewing modes
- Batch operations on multiple records

### 💻 SQL Console
- Execute custom SQL queries with syntax highlighting
- Real-time query results with formatting
- Query history tracking and management
- Error messages with helpful diagnostics

### 📤 Import & Export
- Export to SQL, JSON, CSV, and XML formats
- Import SQL files for data migration
- Full database export capabilities
- Scheduled backup options

### 🔍 Global Search
- Search across all tables and databases
- Instant multi-field search functionality
- Result highlighting and navigation

### 🎨 User Interface
- Fully responsive and mobile-friendly design
- 7 built-in professional themes:
  - Light, Dark, Blue, Green, Purple, Sunset, Slate
- Intuitive dashboard and navigation
- Non-blocking UI with loading indicators

### 🌐 Language Support
- 13 languages: English, Spanish, French, German, Portuguese, Chinese, Japanese, Arabic, Italian, Russian, Turkish, Hindi, Korean
- Easy language switching in preferences

### 🔐 Security & Session Management
- Secure session handling with timeout protection
- Strong credential validation
- CSRF token protection
- Access log monitoring
- Support for encrypted connections (HTTPS recommended)  

---

## Quick Start

1. **Download** the appropriate version:
   - PHP: `dabiro.php` for PHP-based servers
   - Node.js: `dabiro.js` with Express server

2. **Deploy**:
   - **PHP**: Upload `php/dabiro.php` to your web server
   - **Node.js**: `npm install` and `npm start` in the `node/` directory

3. **Access**: Open in your browser
   - PHP: `https://yourserver/dabiro.php`
   - Node.js: `http://localhost:3000` (default port)

4. **Connect**: Add your database credentials

5. **Manage**: Start administering your databases instantly

---

## Supported Databases

| Database    | Minimum Version | Support |
|------------|-----------------|---------|
| MySQL      | 5.7+            | ✅ Full |
| MariaDB    | 10.0+           | ✅ Full |
| PostgreSQL | 9.0+            | ✅ Full |
| SQLite     | 3.0+            | ✅ Full |

---

## Installation

### Option 1: PHP (Single File)

The PHP version is ideal for traditional web hosting with no external dependencies.

```bash
# 1. Upload the file
cp php/dabiro.php /var/www/html/

# 2. Set proper permissions
chmod 644 /var/www/html/dabiro.php

# 3. Access via browser
# https://yourserver.com/dabiro.php
```

**Requirements:**
- PHP 7.4+
- Database client libraries (mysqli for MySQL, pgsql for PostgreSQL, sqlite3 for SQLite)
- Web server (Apache, Nginx, etc.)

### Option 2: Node.js (Express Server)

The Node.js version provides a dedicated server with additional features and scalability.

```bash
# 1. Navigate to the directory
cd node/

# 2. Install dependencies
npm install

# 3. Start the server
npm start

# For development with auto-reload:
npm run dev

# 4. Access via browser
# http://localhost:3000
```

**Requirements:**
- Node.js 14+
- npm or yarn

---

## Usage

### Adding Connections

1. Click **"Add Connection"** on the dashboard
2. Select your database type (MySQL, PostgreSQL, SQLite)
3. Enter connection credentials:
   - Host
   - Port
   - Username
   - Password
   - Database name (or file path for SQLite)
4. Click **"Test Connection"** to verify
5. Click **"Save Connection"**

### Managing Databases

- **View**: Click on any connection to see its databases
- **Create**: Right-click or use the "New Database" button
- **Delete**: Select and confirm deletion
- **Backup**: Use the Export feature to create backups

### Working with Tables

- **Create**: New Table button with column configuration
- **Browse**: Click table name to view and edit data
- **Search**: Use the search bar to filter records
- **Export**: Download table data in SQL, CSV, JSON, or XML
- **Modify**: Edit table structure and properties

### Running Queries

1. Go to the **SQL Console** tab
2. Write or paste your SQL query
3. Click **Execute** or press Ctrl+Enter
4. View results in the results panel
5. Export results if needed

---

## Performance

Dabiro is optimized for handling large databases efficiently:

- **Pagination**: Smart pagination for tables with millions of rows
- **Query Caching**: Metadata caching for faster browsing
- **Lazy Loading**: UI loads data on-demand, not all at once
- **Optimized Queries**: Automatic query optimization for complex operations
- **Index Support**: Indexes are identified and leveraged
- **Batch Operations**: Efficient handling of bulk inserts/updates/deletes

**Tips for Best Performance:**
- Add indexes to frequently searched columns
- Use filtering to reduce result sets on large tables
- Export massive datasets instead of browsing directly
- Optimize database queries using the SQL console  

---

## License
Dabiro is provided for personal and organizational use.  
Full license details can be found in the repository’s **LICENSE** file.
