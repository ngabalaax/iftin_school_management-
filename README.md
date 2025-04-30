# iftin_school_management-
# Iftin School Management System

## 📝 Overview
A comprehensive school management system designed to handle student, teacher, parent, and administrator interactions in one unified platform. The system features role-based access control, attendance tracking, grade management, and scheduling.

## 🌟 Features

### 👨‍💼 Multi-Role System
- **Administrators**: Full system control
- **Teachers**: Manage classes, attendance, and grades
- **Students**: View schedules and grades
- **Parents**: Monitor child's progress

### 📊 Core Modules
- Attendance tracking
- Grade management
- Class scheduling
- User management
- Academic year configuration

## 🛠️ Technical Stack
- **Frontend**: HTML5, CSS3, Bootstrap 5, JavaScript
- **Backend**: PHP 8.0+
- **Database**: MySQL
- **Server**: Apache (XAMPP recommended for development)

## 📂 Project Structure

```
└── 📁iftin
    └── 📁assets
        └── 📁css
            └── sidebar.css
        └── 📁image
            └── default.jpeg
    └── 📁auth
        └── forgot-password.php
        └── login.php
        └── logout.php
    └── 📁config
        └── database.php
        └── schema.sql
        └── session.php
    └── 📁includes
        └── auth_check.php
        └── navbar.php
        └── profile.php
    └── 📁manager
        └── 📁attendance
            └── overview.php
            └── records.php
        └── 📁classes
            └── assign.php
            └── manage.php
        └── dashboard.php
        └── 📁others
            └── navbar.php
            └── sidebar.php
        └── 📁system
            └── academic_year.php
            └── config.php
        └── 📁users
            └── create.php
            └── delete.php
            └── edit.php
            └── list.php
    └── 📁parent
        └── dashboard.php
        └── navbar.php
        └── parent_assignments.php
        └── parent_attendance.php
        └── parent_grades.php
        └── parent_schedule.php
        └── sidebar.php
    └── 📁student
        └── assignments.php
        └── dashboard.php
        └── grades.php
        └── navbar.php
        └── schedule.php
        └── sidebar.php
    └── 📁teacher
        └── 📁attendance
            └── take.php
            └── view.php
        └── 📁classes
            └── view.php
        └── dashboard.php
        └── 📁grades
            └── assignments.php
            └── create.php
            └── manage.php
        └── navbar.php
        └── sidebar.php
    └── 📁uploads
        └── 📁profile_pictures
            └── profile_1_1746043393.png
    └── index.php
```

## 🚀 Installation Guide

### Prerequisites
- XAMPP/WAMP/LAMP stack
- PHP 8.0+
- MySQL 5.7+
- Composer (for optional dependencies)

### Setup Steps
1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/iftin-school-management.git
   ```

2. **Database Setup**
   - Import `schema.sql` from the config folder
   - Configure database credentials in `config/database.php`

3. **File Permissions**
   ```bash
   chmod -R 755 uploads/
   ```

4. **Default Admin Credentials**
   - Username: `admin`
   - Password: `password` (Change immediately after first login)
   - all passwords: `password` (this password is for all the database password)

## 🔒 Security Notes
- All passwords are hashed using PHP's `password_hash()`
- SQL queries use prepared statements
- Session management includes CSRF protection
- File uploads are strictly validated

## 🧑‍💻 Development
To contribute:
1. Fork the repository
2. Create a feature branch
3. Submit a pull request


---