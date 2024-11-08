CREATE DATABASE IF NOT EXISTS fire_heureka_credentials;

GRANT ALL PRIVILEGES ON fire_heureka_credentials.* TO 'debian'@'localhost';

USE fire_heureka_credentials;

CREATE TABLE IF NOT EXISTS user_credentials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL         
);

