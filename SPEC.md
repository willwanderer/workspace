# Workspace Dashboard Application - Specification Document

## 1. Project Overview

**Project Name:** WorkSpace Pro - Workplace & Task Management Dashboard

**Project Type:** Web Application (PHP Native + MySQL)

**Core Functionality:** A comprehensive workspace management system that combines task management, project tracking, client management, and productivity tools in a modern, responsive interface.

**Target Users:** Professionals, small teams, freelancers, and businesses needing an all-in-one workspace management solution.

---

## 2. Database Architecture

### 2.1 Database Name: `workspace_db`

### 2.2 Core Tables

#### A. Users & Authentication
```sql
-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    avatar VARCHAR(255) DEFAULT NULL,
    role ENUM('admin', 'manager', 'member') DEFAULT 'member',
    timezone VARCHAR(50) DEFAULT 'Asia/Jakarta',
    theme ENUM('light', 'dark', 'auto') DEFAULT 'light',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login DATETIME DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE
);

-- User sessions for remember me
CREATE TABLE user_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

#### B. To Do List Management
```sql
-- Tasks table
CREATE TABLE tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    user_id INT NOT NULL,
    project_id INT DEFAULT NULL,
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    category VARCHAR(50) DEFAULT 'general',
    label_color VARCHAR(7) DEFAULT '#6b7280',
    deadline DATETIME DEFAULT NULL,
    start_date DATE DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL
);

-- Task labels/tags
CREATE TABLE task_labels (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    label_name VARCHAR(50) NOT NULL,
    label_color VARCHAR(7) NOT NULL,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
);

-- Task comments
CREATE TABLE task_comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Task attachments
CREATE TABLE task_attachments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    mime_type VARCHAR(100),
    uploaded_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Task reminders
CREATE TABLE task_reminders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    task_id INT NOT NULL,
    remind_at DATETIME NOT NULL,
    is_sent BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
);
```

#### C. Project Management
```sql
-- Projects table
CREATE TABLE projects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    owner_id INT NOT NULL,
    status ENUM('planning', 'active', 'on_hold', 'completed', 'cancelled') DEFAULT 'planning',
    progress_percentage INT DEFAULT 0,
    start_date DATE,
    deadline DATE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Project team members
CREATE TABLE project_members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL,
    user_id INT NOT NULL,
    role VARCHAR(50) DEFAULT 'member',
    joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_project_user (project_id, user_id)
);

-- Project milestones
CREATE TABLE project_milestones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    due_date DATE,
    is_completed BOOLEAN DEFAULT FALSE,
    completed_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

-- Project attachments
CREATE TABLE project_attachments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    mime_type VARCHAR(100),
    uploaded_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);
```

#### D. Contacts & Clients
```sql
-- Contacts table
CREATE TABLE contacts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    company VARCHAR(100),
    position VARCHAR(100),
    address TEXT,
    website VARCHAR(255),
    notes TEXT,
    avatar VARCHAR(255) DEFAULT NULL,
    tags VARCHAR(255),
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Contact categories
CREATE TABLE contact_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    color VARCHAR(7) DEFAULT '#3b82f6',
    user_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

#### E. Quick Links / Bookmarks
```sql
-- Quick links table
CREATE TABLE quick_links (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    url VARCHAR(500) NOT NULL,
    favicon VARCHAR(255) DEFAULT NULL,
    icon_upload VARCHAR(255) DEFAULT NULL,
    category VARCHAR(50) DEFAULT 'general',
    is_pinned BOOLEAN DEFAULT FALSE,
    is_favorite BOOLEAN DEFAULT FALSE,
    click_count INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Link categories
CREATE TABLE link_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    icon VARCHAR(50) DEFAULT 'link',
    color VARCHAR(7) DEFAULT '#3b82f6',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

#### F. Notes
```sql
-- Notes table
CREATE TABLE notes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255),
    content TEXT,
    color VARCHAR(7) DEFAULT '#fef3c7',
    is_pinned BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Note attachments
CREATE TABLE note_attachments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    note_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    mime_type VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE
);
```

#### G. Activity Log
```sql
-- Activity log table
CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NOT NULL,
    old_value TEXT,
    new_value TEXT,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

#### H. Notifications
```sql
-- Notifications table
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    link VARCHAR(255),
    is_read BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

#### I. Settings
```sql
-- User settings table
CREATE TABLE user_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    theme VARCHAR(20) DEFAULT 'light',
    timezone VARCHAR(50) DEFAULT 'Asia/Jakarta',
    email_notifications BOOLEAN DEFAULT TRUE,
    browser_notifications BOOLEAN DEFAULT FALSE,
    task_reminder_days INT DEFAULT 1,
    language VARCHAR(10) DEFAULT 'en',
    date_format VARCHAR(20) DEFAULT 'Y-m-d',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

---

## 3. Application Architecture

### 3.1 Directory Structure
```
workspace/
├── config/
│   ├── database.php
│   ├── constants.php
│   └── functions.php
├── assets/
│   ├── css/
│   │   ├── main.css
│   │   ├── components.css
│   │   └── themes.css
│   ├── js/
│   │   ├── main.js
│   │   ├── charts.js
│   │   └── components.js
│   ├── images/
│   └── fonts/
├── includes/
│   ├── header.php
│   ├── sidebar.php
│   ├── footer.php
│   └── modals/
├── classes/
│   ├── Database.php
│   ├── User.php
│   ├── Task.php
│   ├── Project.php
│   ├── Contact.php
│   ├── QuickLink.php
│   ├── Note.php
│   └── Notification.php
├── pages/
│   ├── dashboard/
│   ├── tasks/
│   ├── projects/
│   ├── contacts/
│   ├── links/
│   ├── notes/
│   ├── settings/
│   └── auth/
├── uploads/
│   ├── avatars/
│   ├── attachments/
│   └── favicons/
├── index.php
├── api/
│   └── (REST API endpoints)
└── .htaccess
```

### 3.2 Core PHP Classes

#### Database Class (Singleton Pattern)
```php
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        $config = require 'config/database.php';
        $this->connection = new mysqli(
            $config['host'],
            $config['username'],
            $config['password'],
            $config['database']
        );
        if ($this->connection->connect_error) {
            die("Connection failed: " . $this->connection->connect_error);
        }
        $this->connection->set_charset("utf8mb4");
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    public function query($sql) {
        return $this->connection->query($sql);
    }
    
    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }
    
    public function escape($value) {
        return $this->connection->real_escape_string($value);
    }
}
```

#### Task Management Class
```php
class TaskManager {
    private $db;
    
    public function create($data) {
        // Validate and create new task
        // Set reminder if deadline exists
    }
    
    public function update($id, $data) {
        // Update task with activity logging
    }
    
    public function delete($id) {
        // Soft delete with activity log
    }
    
    public function getAll($filters = []) {
        // Get tasks with optional filters
    }
    
    public function getByStatus($status) {
        // Filter by status
    }
    
    public function getByPriority($priority) {
        // Filter by priority
    }
    
    public function getUpcomingDeadlines($days = 7) {
        // Get tasks due within specified days
    }
    
    public function updateProgress($id, $progress) {
        // Update task completion status
    }
}
```

---

## 4. UI/UX Design Specification

### 4.1 Color Palette

#### Light Theme
- **Primary:** #2563eb (Blue 600)
- **Primary Hover:** #1d4ed8 (Blue 700)
- **Secondary:** #64748b (Slate 500)
- **Accent:** #10b981 (Emerald 500)
- **Background:** #f8fafc (Slate 50)
- **Surface:** #ffffff (White)
- **Text Primary:** #1e293b (Slate 800)
- **Text Secondary:** #64748b (Slate 500)
- **Border:** #e2e8f0 (Slate 200)
- **Error:** #ef4444 (Red 500)
- **Warning:** #f59e0b (Amber 500)
- **Success:** #10b981 (Emerald 500)

#### Dark Theme
- **Primary:** #3b82f6 (Blue 500)
- **Primary Hover:** #60a5fa (Blue 400)
- **Secondary:** #94a3b8 (Slate 400)
- **Accent:** #34d399 (Emerald 400)
- **Background:** #0f172a (Slate 900)
- **Surface:** #1e293b (Slate 800)
- **Text Primary:** #f1f5f9 (Slate 100)
- **Text Secondary:** #94a3b8 (Slate 400)
- **Border:** #334155 (Slate 700)

### 4.2 Priority Colors
- **Low:** #6b7280 (Gray)
- **Medium:** #3b82f6 (Blue)
- **High:** #f59e0b (Amber)
- **Urgent:** #ef4444 (Red)

### 4.3 Status Colors
- **Pending:** #6b7280 (Gray)
- **In Progress:** #3b82f6 (Blue)
- **Completed:** #10b981 (Emerald)
- **Cancelled:** #ef4444 (Red)

### 4.4 Typography
- **Font Family:** 'Inter', -apple-system, BlinkMacSystemFont, sans-serif
- **Heading 1:** 32px, font-weight: 700
- **Heading 2:** 24px, font-weight: 600
- **Heading 3:** 20px, font-weight: 600
- **Body:** 14px, font-weight: 400
- **Small:** 12px, font-weight: 400

### 4.5 Layout Structure

#### Main Layout
- **Sidebar:** 260px fixed width (collapsible to 70px)
- **Header:** 64px height with user menu
- **Content Area:** Fluid with max-width 1400px
- **Responsive Breakpoints:**
  - Mobile: < 768px
  - Tablet: 768px - 1024px
  - Desktop: > 1024px

### 4.6 Component Specifications

#### Cards
- Border radius: 12px
- Box shadow: 0 1px 3px rgba(0,0,0,0.1)
- Padding: 24px
- Hover: translateY(-2px), shadow increase

#### Buttons
- Primary: Blue background, white text
- Secondary: White background, gray border
- Danger: Red background, white text
- Border radius: 8px
- Padding: 10px 20px
- Transition: all 0.2s ease

#### Form Inputs
- Border radius: 8px
- Border: 1px solid #e2e8f0
- Focus: ring-2 blue-500
- Padding: 12px 16px

#### Modals
- Backdrop: rgba(0,0,0,0.5)
- Border radius: 16px
- Max width: 600px
- Animation: fadeIn + scaleUp

---

## 5. Feature Specifications

### 5.1 Dashboard
- **Statistics Cards:** Total tasks, pending, in progress, completed
- **Charts:** 
  - Task completion pie chart
  - Weekly activity bar chart
  - Project progress line chart
- **Recent Activity:** Last 10 activities
- **Upcoming Deadlines:** Tasks due within 7 days
- **Quick Actions:** Add task, add project, add note

### 5.2 Task Management
- **Task List View:** Table with sortable columns
- **Task Card View:** Kanban-style board
- **Filters:** Status, priority, category, date range
- **Search:** Full-text search
- **Bulk Actions:** Delete, change status
- **Reminders:** Email/browser notification before deadline

### 5.3 Project Management
- **Project Cards:** Visual cards with progress
- **Gantt Chart:** Simple timeline visualization
- **Team Members:** Add/remove members
- **Milestones:** Track key dates
- **Progress Tracking:** Auto-calculate from tasks

### 5.4 Contacts
- **Contact List:** Searchable table
- **Contact Details:** Full profile view
- **Categories:** Tag-based organization
- **Quick Actions:** Email, call, visit website

### 5.5 Quick Links
- **Link Cards:** Visual bookmark cards
- **Auto Favicon:** Fetch from Google Favicon API
- **Custom Icon:** Upload option
- **Categories:** Organize by type
- **Favorites:** Pin important links

### 5.6 Notes
- **Note Cards:** Colorful sticky notes
- **Rich Text:** Basic formatting
- **Pinned Notes:** Always on top
- **Search:** Find notes quickly

### 5.7 Activity Log
- **Timeline View:** Chronological activities
- **Filters:** By type, date, user
- **Details:** Before/after values

### 5.8 Notifications
- **Notification Panel:** Dropdown from header
- **Types:** Task due, project update, mentions
- **Mark as Read:** Individual or all
- **Link to Entity:** Navigate to related item

### 5.9 Settings
- **Profile:** Edit user info
- **Appearance:** Theme selection
- **Notifications:** Toggle preferences
- **Timezone:** Set local timezone
- **Data:** Export/backup options

---

## 6. API Endpoints

### Tasks
- `GET /api/tasks` - List all tasks
- `POST /api/tasks` - Create task
- `GET /api/tasks/{id}` - Get task details
- `PUT /api/tasks/{id}` - Update task
- `DELETE /api/tasks/{id}` - Delete task

### Projects
- `GET /api/projects` - List all projects
- `POST /api/projects` - Create project
- `GET /api/projects/{id}` - Get project details
- `PUT /api/projects/{id}` - Update project
- `DELETE /api/projects/{id}` - Delete project

### Quick Links
- `GET /api/links` - List all links
- `POST /api/links` - Create link
- `GET /api/links/favicon?url={url}` - Fetch favicon

---

## 7. Security Considerations

- **Password Hashing:** Use password_hash() with BCRYPT
- **SQL Injection:** Prepared statements everywhere
- **XSS Prevention:** htmlspecialchars() on output
- **CSRF Protection:** Token-based validation
- **Session Security:** Secure, HttpOnly cookies
- **File Upload:** Validate mime types, store outside webroot

---

## 8. Acceptance Criteria

1. ✅ User can register, login, and logout
2. ✅ Dashboard displays accurate statistics and charts
3. ✅ Tasks can be created, edited, deleted with all fields
4. ✅ Task filtering and search work correctly
5. ✅ Projects display with progress and timeline
6. ✅ Contacts can be managed with search/filter
7. ✅ Quick links fetch favicon automatically
8. ✅ Notes can be created and organized
9. ✅ Activity log tracks all changes
10. ✅ Notifications appear for due tasks
11. ✅ Settings can be modified
12. ✅ Responsive design works on all devices
13. ✅ Dark/light theme toggle works
14. ✅ Data can be exported/backup

---

*Document Version: 1.0*
*Created: 2026-03-24*
