SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0
);

CREATE TABLE IF NOT EXISTS jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    department_id INT,
    name VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS personnel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    main_title ENUM('ผู้อำนวยการ', 'รองผู้อำนวยการ', 'ข้าราชการ', 'พนักงานราชการ', 'ครูพิเศษสอน', 'เจ้าหน้าที่') NOT NULL
);

CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    personnel_id INT,
    job_id INT,
    role ENUM('หัวหน้างาน', 'ผู้ช่วยหัวหน้างาน', 'เจ้าหน้าที่งาน') NOT NULL,
    FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_assignment (personnel_id, job_id, role)
);

SET FOREIGN_KEY_CHECKS=0;
DELETE FROM assignments;
DELETE FROM jobs;
DELETE FROM departments;
DELETE FROM personnel;
SET FOREIGN_KEY_CHECKS=1;

-- Insert Departments
INSERT INTO departments (id, name, sort_order) VALUES
(1, 'ฝ่ายบริหารทรัพยากร', 1),
(2, 'ฝ่ายยุทธศาสตร์และแผนงาน', 2),
(3, 'ฝ่ายกิจการนักเรียน นักศึกษา', 3),
(4, 'ฝ่ายวิชาการ', 4);

-- Insert Jobs
INSERT INTO jobs (id, department_id, name, sort_order) VALUES
(900, NULL, 'ผู้อำนวยการวิทยาลัย', 0),
(901, 1, 'รองผู้อำนวยการฝ่ายบริหารทรัพยากร', 0),
(902, 2, 'รองผู้อำนวยการฝ่ายยุทธศาสตร์และแผนงาน', 0),
(903, 3, 'รองผู้อำนวยการฝ่ายพัฒนากิจการนักเรียน นักศึกษา', 0),
(904, 4, 'รองผู้อำนวยการฝ่ายวิชาการ', 0);

INSERT INTO jobs (department_id, name) VALUES
(1, 'งานบริหารงานทั่วไป'), (1, 'งานบริหารและพัฒนาทรัพยากรบุคคล'), (1, 'งานการเงิน'), (1, 'งานการบัญชี'), (1, 'งานพัสดุ'), (1, 'งานอาคารสถานที่'), (1, 'งานทะเบียน'),
(2, 'งานพัฒนายุทธศาสตร์ แผนงาน และงบประมาณ'), (2, 'งานมาตรฐานและประกันคุณภาพ'), (2, 'งานศูนย์ดิจิทัลและสื่อสารองค์กร'), (2, 'งานส่งเสริมการวิจัย นวัตกรรม และสิ่งประดิษฐ์'), (2, 'งานส่งเสริมธุรกิจและการเป็นผู้ประกอบการ'), (2, 'งานติดตามและประเมินผลการอาชีวศึกษา'),
(3, 'งานกิจกรรมนักเรียนนักศึกษา'), (3, 'งานครูที่ปรึกษาและการแนะแนว'), (3, 'งานปกครองและความปลอดภัยนักเรียนนักศึกษา'), (3, 'งานโครงการพิเศษและการบริการ'), (3, 'งานสวัสดิการนักเรียน นักศึกษา'),
(4, 'แผนกวิชาช่างกลโรงงานและเทคนิคพื้นฐาน'), (4, 'แผนกวิชาช่างยนต์'), (4, 'แผนกวิชาช่างไฟฟ้ากำลัง'), (4, 'แผนกวิชาช่างอิเล็กทรอนิกส์'), (4, 'แผนกวิชาการบัญชี'), (4, 'แผนกวิชาเทคโนโลยีธุรกิจดิจิทัล'), (4, 'แผนกวิชาสามัญสัมพันธ์'), (4, 'แผนกวิชาชีพระยะสั้น'), (4, 'งานพัฒนาหลักสูตรและการจัดการเรียนรู้'), (4, 'งานวัดผลและประเมินผล'), (4, 'งานอาชีวศึกษาระบบทวิภาคีและความร่วมมือ'), (4, 'งานวิทยบริการและเทคโนโลยีการศึกษา'), (4, 'งานการศึกษาพิเศษและความเสมอภาคทางการศึกษา');


-- Insert Dummy Personnel
INSERT INTO personnel (name, main_title) VALUES
('ดร.สมชาย ใจดี', 'ผู้อำนวยการ'),
('นายรักชาติ ยิ่งชีพ', 'รองผู้อำนวยการ'),
('นางสมศรี ศรีสุข', 'รองผู้อำนวยการ'),
('นายปัญญา ดีเยี่ยม', 'รองผู้อำนวยการ'),
('นางสาวนารี งามดี', 'รองผู้อำนวยการ'),
('นายวิทยา รักเรียน', 'ข้าราชการ'),
('นางสุภาพร สอนดี', 'ข้าราชการ'),
('นายเก่งกาจ ชาญชัย', 'พนักงานราชการ'),
('นางสาวมาลี สีสวย', 'พนักงานราชการ'),
('นายช่างทอง มองไกล', 'ครูพิเศษสอน'),
('นางสาวสมใจ หวังดี', 'ครูพิเศษสอน'),
('นายขยัน หมั่นเพียร', 'เจ้าหน้าที่'),
('นางสาวรอบคอบ งานไว', 'เจ้าหน้าที่');
