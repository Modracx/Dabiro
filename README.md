# Dabiro v1.0.0

[![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/Modracx/Dabiro)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Status](https://img.shields.io/badge/status-stable-success.svg)](https://github.com/Modracx/Dabiro)
[![Themes](https://img.shields.io/badge/theme-7%20Themes-orange.svg)](https://github.com/Modracx/Dabiro)

Dabiro is a modern, single-file database administration tool with a fully responsive web interface.  
It provides professional-grade features for managing **MySQL/MariaDB**, **PostgreSQL**, and **SQLite** databases—without installation, setup complexity, or external dependencies.

---

## Features

### Multi-Database Support
- MySQL / MariaDB  
- PostgreSQL  
- SQLite  

### Database Management
- Create, drop, and manage multiple databases  
- Add/remove database connections  
- Real-time performance metrics  

### Table Operations
- Browse, create, rename, copy, and move tables  
- Pagination optimized for large tables  
- Smart query optimization  

### Data Management
- Insert, edit, delete records  
- Advanced filtering, sorting, and limiting  
- Grid and list viewing modes  

### SQL Console
- Run custom queries  
- Real-time results  
- Query history tracking  

### Import & Export
- Export to SQL, JSON, CSV, XML  
- Import SQL files  
- Supports full database export  

### Global Search
- Search across all tables and databases  
- Instant, multi-field search  

### User Interface
- Fully responsive and mobile-friendly  
- 7 built-in themes: Light, Dark, Blue, Green, Purple, Sunset, Slate  

### Language Support
- 13 languages supported: English, Spanish, French, German, Portuguese, Chinese, Japanese, Arabic, Italian, Russian, Turkish, Hindi, Korean  

### Security & Session Management
- Secure session handling  
- Timeout protection  
- Strong credential checks  
- Access log monitoring  

---

## Installation

1. Upload the single Dabiro file to your server  
2. Ensure your database server is running  
3. Open the file in a browser (e.g., `https://yourserver/dabiro.php`)  
4. Add database credentials  
5. Start managing databases instantly  

> No external libraries, no additional configuration, no command-line required.

---

## Supported Databases

| Database    | Minimum Version |
|------------|----------------|
| MySQL      | 5.7+           |
| MariaDB    | 10.0+          |
| PostgreSQL | 9.0+           |
| SQLite     | 3.0+           |

---

## Troubleshooting

**Connection Issues**
- Check host/port  
- Verify username/password  
- Ensure DB server is running  
- Confirm user permissions  

**SQLite Notes**
- Verify file path  
- Check file read/write permissions  

**Performance**
- Add or optimize table indexes  
- Use advanced filtering on large tables  
- Export massive datasets instead of browsing directly  

---

## Performance Features
- Efficient pagination for large datasets  
- Optimized query execution  
- Cached metadata for faster browsing  
- Non-blocking UI with loading indicators  

---

## Security Notes
- Always use strong database credentials  
- Protect the file behind HTTPS  
- Restrict access via `.htaccess` or server firewall  
- Perform regular backups  

---

## License
Dabiro is provided for personal and organizational use.  
Full license details can be found in the repository’s **LICENSE** file.
