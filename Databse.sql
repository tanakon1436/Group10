-- สร้าง Database
CREATE DATABASE IF NOT EXISTS group10;
USE group10;

-- ตาราง User
CREATE TABLE User (
    User_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    Username VARCHAR(50) UNIQUE,
    Password VARCHAR(255),
    email VARCHAR(100),
    tel VARCHAR(20),
    role VARCHAR(50),
    Department VARCHAR(100),
    status VARCHAR(50)
);

-- ตาราง Publication
CREATE TABLE Publication (
    Pub_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    publish_year YEAR,
    journal VARCHAR(255),
    type VARCHAR(100),
    file_path VARCHAR(255),
    visibility VARCHAR(50),
    status VARCHAR(50),
    Manual TEXT,
    Author_id INT,
    FOREIGN KEY (Author_id) REFERENCES User(User_id)
);

-- ตาราง PublicationHistory
CREATE TABLE PublicationHistory (
    History_id INT AUTO_INCREMENT PRIMARY KEY,
    Pub_id INT,
    Edited_by INT,
    change_detail TEXT,
    edit_date DATETIME,
    FOREIGN KEY (Pub_id) REFERENCES Publication(Pub_id),
    FOREIGN KEY (Edited_by) REFERENCES User(User_id)
);

-- ตาราง Notification
CREATE TABLE Notification (
    Noti_id INT AUTO_INCREMENT PRIMARY KEY,
    User_id INT,
    Pub_id INT,
    message TEXT,
    date_time DATETIME,
    status VARCHAR(50),
    FOREIGN KEY (User_id) REFERENCES User(User_id),
    FOREIGN KEY (Pub_id) REFERENCES Publication(Pub_id)
);

-- ตาราง LoginHistory
CREATE TABLE LoginHistory (
    Login_id INT AUTO_INCREMENT PRIMARY KEY,
    User_id INT,
    time DATETIME,
    success BOOLEAN,
    FOREIGN KEY (User_id) REFERENCES User(User_id)
);