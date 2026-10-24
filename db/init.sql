USE student_management;
CREATE TABLE classes (
 id INT AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL UNIQUE
);
CREATE TABLE students (
 id INT AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(30) NOT NULL UNIQUE,
 name VARCHAR(100) NOT NULL,
 email VARCHAR(150) NOT NULL,
 class_id INT NOT NULL,
 FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE RESTRICT
);
CREATE TABLE grades (
 id INT AUTO_INCREMENT PRIMARY KEY,
 student_id INT NOT NULL,
 subject VARCHAR(100) NOT NULL,
 score DECIMAL(4,2) NOT NULL CHECK (score BETWEEN 0 AND 10),
 UNIQUE(student_id, subject),
 FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);
INSERT INTO classes(name) VALUES ('CNTT01'), ('CNTT02');
INSERT INTO students(code,name,email,class_id) VALUES
 ('SV001','Nguyễn Văn An','an@example.com',1),
 ('SV002','Trần Thị Bình','binh@example.com',2);
INSERT INTO grades(student_id,subject,score) VALUES (1,'Triển khai hệ thống',8.5);
-- Official MySQL entrypoint creates MYSQL_USER with privileges on this database only.
-- The default example username is deliberately restricted further to CRUD.
REVOKE ALL PRIVILEGES ON student_management.* FROM 'student_app'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON student_management.* TO 'student_app'@'%';
