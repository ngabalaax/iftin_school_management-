-- Database creation
CREATE DATABASE IF NOT EXISTS iftin_school_management;
USE iftin_school_management;

-- Users table (base table for all roles)
CREATE TABLE users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  first_name VARCHAR(50) NOT NULL,
  last_name VARCHAR(50) NOT NULL,
  role ENUM('manager', 'teacher', 'student', 'parent') NOT NULL,
  phone VARCHAR(20),
  address TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_login TIMESTAMP NULL,
  is_active BOOLEAN DEFAULT TRUE,
  INDEX idx_role (role),
  INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Students table (extends users)
CREATE TABLE students (
  student_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNIQUE NOT NULL,
  admission_number VARCHAR(20) UNIQUE NOT NULL,
  date_of_birth DATE NOT NULL,
  gender ENUM('male', 'female', 'other') NOT NULL,
  current_class_id INT,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_admission (admission_number),
  INDEX idx_class (current_class_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Parents table (extends users)
CREATE TABLE parents (
  parent_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNIQUE NOT NULL,
  occupation VARCHAR(100),
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Parent-Student relationship table
CREATE TABLE parent_student (
  id INT AUTO_INCREMENT PRIMARY KEY,
  parent_id INT NOT NULL,
  student_id INT NOT NULL,
  relationship ENUM('mother', 'father', 'guardian') NOT NULL,
  FOREIGN KEY (parent_id) REFERENCES parents(parent_id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
  UNIQUE KEY (parent_id, student_id),
  INDEX idx_parent (parent_id),
  INDEX idx_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teachers table (extends users)
CREATE TABLE teachers (
  teacher_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNIQUE NOT NULL,
  employee_id VARCHAR(20) UNIQUE NOT NULL,
  qualification VARCHAR(100),
  specialization VARCHAR(100),
  hire_date DATE NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Academic years table
CREATE TABLE academic_years (
  year_id INT AUTO_INCREMENT PRIMARY KEY,
  year_name VARCHAR(50) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  is_current BOOLEAN DEFAULT FALSE,
  UNIQUE KEY (year_name),
  INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Classes table
CREATE TABLE classes (
  class_id INT AUTO_INCREMENT PRIMARY KEY,
  class_name VARCHAR(50) NOT NULL,
  academic_year_id INT NOT NULL,
  class_teacher_id INT,
  capacity INT,
  room_number VARCHAR(20),
  FOREIGN KEY (academic_year_id) REFERENCES academic_years(year_id),
  FOREIGN KEY (class_teacher_id) REFERENCES teachers(teacher_id),
  UNIQUE KEY (class_name, academic_year_id),
  INDEX idx_teacher (class_teacher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Subjects table
CREATE TABLE subjects (
  subject_id INT AUTO_INCREMENT PRIMARY KEY,
  subject_name VARCHAR(50) NOT NULL,
  subject_code VARCHAR(20) UNIQUE NOT NULL,
  description TEXT,
  is_core BOOLEAN DEFAULT FALSE,
  INDEX idx_code (subject_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Class-Subjects junction table
CREATE TABLE class_subjects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  class_id INT NOT NULL,
  subject_id INT NOT NULL,
  teacher_id INT NOT NULL,
  schedule_day ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'),
  start_time TIME,
  end_time TIME,
  FOREIGN KEY (class_id) REFERENCES classes(class_id) ON DELETE CASCADE,
  FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
  FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id),
  UNIQUE KEY (class_id, subject_id),
  INDEX idx_teacher (teacher_id),
  INDEX idx_schedule (schedule_day, start_time, end_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enrollments table
CREATE TABLE enrollments (
  enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  class_id INT NOT NULL,
  academic_year_id INT NOT NULL,
  enrollment_date DATE NOT NULL,
  status ENUM('active', 'transferred', 'graduated', 'withdrawn') DEFAULT 'active',
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
  FOREIGN KEY (class_id) REFERENCES classes(class_id),
  FOREIGN KEY (academic_year_id) REFERENCES academic_years(year_id),
  UNIQUE KEY (student_id, class_id, academic_year_id),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Attendance table
CREATE TABLE attendance (
  attendance_id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  class_id INT NOT NULL,
  class_subject_id INT,
  date DATE NOT NULL,
  status ENUM('present', 'absent', 'late', 'excused') NOT NULL,
  recorded_by INT NOT NULL,
  notes TEXT,
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
  FOREIGN KEY (class_id) REFERENCES classes(class_id),
  FOREIGN KEY (class_subject_id) REFERENCES class_subjects(id),
  FOREIGN KEY (recorded_by) REFERENCES users(user_id),
  UNIQUE KEY (student_id, class_id, date),
  INDEX idx_date (date),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Assignments table
CREATE TABLE assignments (
  assignment_id INT AUTO_INCREMENT PRIMARY KEY,
  class_subject_id INT NOT NULL,
  title VARCHAR(100) NOT NULL,
  description TEXT,
  assignment_type ENUM('homework', 'quiz', 'test', 'project', 'exam') NOT NULL,
  due_date DATE NOT NULL,
  max_score DECIMAL(5,2) NOT NULL,
  weight DECIMAL(5,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (class_subject_id) REFERENCES class_subjects(id) ON DELETE CASCADE,
  INDEX idx_due_date (due_date),
  INDEX idx_type (assignment_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Grades table
CREATE TABLE grades (
  grade_id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  assignment_id INT NOT NULL,
  score DECIMAL(5,2) NOT NULL,
  comments TEXT,
  recorded_by INT NOT NULL,
  recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
  FOREIGN KEY (assignment_id) REFERENCES assignments(assignment_id) ON DELETE CASCADE,
  FOREIGN KEY (recorded_by) REFERENCES users(user_id),
  UNIQUE KEY (student_id, assignment_id),
  INDEX idx_score (score)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System configuration table
CREATE TABLE system_config (
  config_id INT AUTO_INCREMENT PRIMARY KEY,
  config_key VARCHAR(50) UNIQUE NOT NULL,
  config_value TEXT NOT NULL,
  description TEXT,
  is_public BOOLEAN DEFAULT FALSE,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by INT,
  FOREIGN KEY (updated_by) REFERENCES users(user_id),
  INDEX idx_key (config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Announcements table
CREATE TABLE announcements (
  announcement_id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(100) NOT NULL,
  content TEXT NOT NULL,
  target_roles JSON,
  target_classes JSON,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(user_id),
  INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit log table
CREATE TABLE audit_log (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  action VARCHAR(50) NOT NULL,
  table_name VARCHAR(50) NOT NULL,
  record_id INT,
  old_values JSON,
  new_values JSON,
  ip_address VARCHAR(45),
  user_agent TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_action (action),
  INDEX idx_table (table_name),
  INDEX idx_user (user_id),
  INDEX idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert initial system configuration
INSERT INTO system_config (config_key, config_value, description, is_public) VALUES
('school_name', 'Iftin Academy', 'The name of the school', TRUE),
('school_address', '123 Education Street, Mogadishu', 'Physical address of the school', TRUE),
('school_phone', '+252612345678', 'Main contact phone number', TRUE),
('school_email', 'info@iftin.edu.so', 'Main contact email', TRUE),
('attendance_threshold', '75', 'Minimum attendance percentage required', FALSE),
('grade_pass_percentage', '50', 'Minimum percentage to pass assignments', FALSE),
('system_timezone', 'Africa/Mogadishu', 'Default timezone for the system', FALSE),
('default_academic_year', '2023-2024', 'Current academic year', TRUE);

-- Insert sample academic year
INSERT INTO academic_years (year_name, start_date, end_date, is_current) VALUES
('2023-2024', '2023-09-01', '2024-06-30', TRUE);

-- Insert sample subjects
INSERT INTO subjects (subject_name, subject_code, description, is_core) VALUES
('Mathematics', 'MATH101', 'Core mathematics curriculum', TRUE),
('English Language', 'ENG101', 'English reading and writing', TRUE),
('Somali Language', 'SOM101', 'Somali reading and writing', TRUE),
('Science', 'SCI101', 'General science', TRUE),
('History', 'HIS101', 'World and Somali history', FALSE),
('Geography', 'GEO101', 'Physical and human geography', FALSE),
('Islamic Studies', 'ISL101', 'Islamic education', TRUE),
('Computer Science', 'CMP101', 'Basic computing skills', FALSE),
('Physical Education', 'PE101', 'Sports and physical activities', FALSE),
('Art', 'ART101', 'Creative arts and crafts', FALSE);

-- Insert sample manager user
INSERT INTO users (username, password_hash, email, first_name, last_name, role, phone, is_active) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@iftin.edu.so', 'System', 'Administrator', 'manager', '+252611234567', TRUE);

-- Insert sample teacher users
INSERT INTO users (username, password_hash, email, first_name, last_name, role, phone, is_active) VALUES
('teacher1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher1@iftin.edu.so', 'Ahmed', 'Mohamed', 'teacher', '+252612345678', TRUE),
('teacher2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher2@iftin.edu.so', 'Aisha', 'Hassan', 'teacher', '+252613456789', TRUE);

INSERT INTO teachers (user_id, employee_id, qualification, specialization, hire_date) VALUES
(2, 'TEA001', 'M.Ed Mathematics', 'Mathematics', '2020-09-01'),
(3, 'TEA002', 'B.A English', 'English Literature', '2021-02-15');

-- Insert sample classes
INSERT INTO classes (class_name, academic_year_id, class_teacher_id, capacity, room_number) VALUES
('Grade 1A', 1, 1, 30, 'Room 101'),
('Grade 1B', 1, 2, 30, 'Room 102'),
('Grade 2A', 1, NULL, 30, 'Room 201');

-- Insert class subjects
INSERT INTO class_subjects (class_id, subject_id, teacher_id, schedule_day, start_time, end_time) VALUES
(1, 1, 1, 'monday', '08:00:00', '09:00:00'), -- Math for Grade 1A
(1, 2, 2, 'tuesday', '08:00:00', '09:00:00'), -- English for Grade 1A
(2, 1, 1, 'wednesday', '08:00:00', '09:00:00'), -- Math for Grade 1B
(2, 2, 2, 'thursday', '08:00:00', '09:00:00'); -- English for Grade 1B

-- Insert sample student users
INSERT INTO users (username, password_hash, email, first_name, last_name, role, phone, is_active) VALUES
('student1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student1@iftin.edu.so', 'Ali', 'Abdi', 'student', '+252614567890', TRUE),
('student2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student2@iftin.edu.so', 'Fatima', 'Omar', 'student', '+252615678901', TRUE),
('student3', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student3@iftin.edu.so', 'Mohamed', 'Yusuf', 'student', '+252616789012', TRUE);

INSERT INTO students (user_id, admission_number, date_of_birth, gender, current_class_id) VALUES
(4, 'STU2023001', '2016-05-15', 'male', 1),
(5, 'STU2023002', '2016-07-22', 'female', 1),
(6, 'STU2023003', '2016-03-10', 'male', 2);

-- Insert sample parent users
INSERT INTO users (username, password_hash, email, first_name, last_name, role, phone, is_active) VALUES
('parent1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'parent1@example.com', 'Abdi', 'Hussein', 'parent', '+252617890123', TRUE),
('parent2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'parent2@example.com', 'Hawa', 'Mohamed', 'parent', '+252618901234', TRUE);

INSERT INTO parents (user_id, occupation) VALUES
(7, 'Business Owner'),
(8, 'Teacher');

-- Link parents to students
INSERT INTO parent_student (parent_id, student_id, relationship) VALUES
(1, 1, 'father'),
(1, 2, 'father'),
(2, 3, 'mother');

-- Insert enrollments
INSERT INTO enrollments (student_id, class_id, academic_year_id, enrollment_date) VALUES
(1, 1, 1, '2023-09-01'),
(2, 1, 1, '2023-09-01'),
(3, 2, 1, '2023-09-01');

-- Insert sample assignments
INSERT INTO assignments (class_subject_id, title, description, assignment_type, due_date, max_score, weight) VALUES
(1, 'Addition Basics', 'Complete the addition problems', 'homework', '2023-10-15', 20, 10),
(1, 'Midterm Exam', 'Covering chapters 1-5', 'exam', '2023-11-10', 100, 30),
(2, 'Reading Comprehension', 'Read the passage and answer questions', 'quiz', '2023-10-20', 30, 15);

-- Insert sample grades
INSERT INTO grades (student_id, assignment_id, score, comments, recorded_by) VALUES
(1, 1, 18, 'Excellent work!', 2),
(2, 1, 15, 'Good effort', 2),
(1, 3, 25, 'Well done', 3),
(2, 3, 28, 'Excellent reading skills', 3);

-- Insert sample attendance records
INSERT INTO attendance (student_id, class_id, class_subject_id, date, status, recorded_by) VALUES
(1, 1, 1, '2023-10-02', 'present', 2),
(2, 1, 1, '2023-10-02', 'present', 2),
(3, 2, 3, '2023-10-03', 'late', 3),
(1, 1, 2, '2023-10-03', 'present', 2),
(2, 1, 2, '2023-10-03', 'absent', 2);

-- Insert sample announcement
INSERT INTO announcements (title, content, target_roles, target_classes, start_date, end_date, created_by) VALUES
('Parent-Teacher Meeting', 'There will be a parent-teacher meeting next week. Please check the schedule.', '["parent"]', NULL, '2023-10-01', '2023-10-15', 1),
('Math Competition', 'All grade 1 students are invited to participate in the math competition.', '["student"]', '[1, 2]', '2023-10-05', '2023-10-20', 2);