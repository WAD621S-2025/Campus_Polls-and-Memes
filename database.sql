CREATE DATABASE IF NOT EXISTS nust_campus_platform;
USE nust_campus_platform;


CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE IF NOT EXISTS polls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question VARCHAR(255) NOT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);


CREATE TABLE IF NOT EXISTS poll_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    poll_id INT NOT NULL,
    option_text VARCHAR(255) NOT NULL,
    votes INT DEFAULT 0,
    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE
);


CREATE TABLE IF NOT EXISTS memes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    file_path VARCHAR(255) NOT NULL,
    caption VARCHAR(255),
    likes INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);



-- Add a sample user
INSERT INTO users (username, password_hash)
VALUES ('demo_user', 'demo_password_hash');

-- Add a sample poll
INSERT INTO polls (question, created_by)
VALUES ('Who is the best lecturer?', 1);

-- Get the last inserted poll ID for adding options
SET @poll_id = LAST_INSERT_ID();

-- Add options for the sample poll
INSERT INTO poll_options (poll_id, option_text)
VALUES
(@poll_id, 'Mr. Adams'),
(@poll_id, 'Dr. Liza'),
(@poll_id, 'Prof. Moyo');

-- Add sample memes
INSERT INTO memes (user_id, file_path, caption)
VALUES
(1, 'uploads/unamed.png', 'When exam time hits 😭'),
(1, 'uploads/2017-04-07-meme-1.jpg', 'Tired students 😭'),
(1, 'uploads/Yoda_meme.jpeg', 'That group project guy 😤');



-- Vote for option with id = 2
UPDATE poll_options
SET votes = votes + 1
WHERE id = 2;

-- Like a meme with id = 1
UPDATE memes
SET likes = likes + 1
WHERE id = 1;


SELECT 
    p.id AS poll_id, 
    p.question, 
    o.id AS option_id, 
    o.option_text, 
    o.votes
FROM polls p
JOIN poll_options o ON p.id = o.poll_id
WHERE p.id = 1;


SELECT 
    id, 
    file_path, 
    caption, 
    likes, 
    created_at
FROM memes
ORDER BY created_at DESC
LIMIT 10;


SELECT 
    id, 
    file_path, 
    caption, 
    likes
FROM memes
WHERE caption LIKE '%exam%';
