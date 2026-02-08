-- pgconnect.sql
CREATE DATABASE IF NOT EXISTS pgconnect;
USE pgconnect;

-- users table
CREATE TABLE IF NOT EXISTS users (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100) NOT NULL,
  email      VARCHAR(150) NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,
  role       ENUM('user','owner','admin') NOT NULL DEFAULT 'user',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- pgs table
CREATE TABLE IF NOT EXISTS pgs (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  owner_id    INT NOT NULL,
  pg_name     VARCHAR(150) NOT NULL,
  location    VARCHAR(150) NOT NULL,
  city        VARCHAR(80)  NOT NULL,
  rent        INT NOT NULL,
  gender      VARCHAR(20) NOT NULL,
  vacancy     INT NOT NULL DEFAULT 0,
  amenities   TEXT,
  description TEXT,
  latitude    DECIMAL(10,8),
  longitude   DECIMAL(11,8),
  status      ENUM('pending','approved') DEFAULT 'pending',
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pgs_owner FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
);

-- bookings table
CREATE TABLE IF NOT EXISTS bookings (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  pg_id      INT NOT NULL,
  status     ENUM('requested','approved','rejected') DEFAULT 'requested',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_book_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_book_pg   FOREIGN KEY (pg_id)   REFERENCES pgs(id)   ON DELETE CASCADE
);

-- saved_pgs table
CREATE TABLE IF NOT EXISTS saved_pgs (
  id      INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  pg_id   INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_saved_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_saved_pg   FOREIGN KEY (pg_id)   REFERENCES pgs(id)   ON DELETE CASCADE
);

-- optional: seed one admin
INSERT INTO users (name, email, password, role)
VALUES (
  'Admin',
  'admin@pgconnect.in',
  -- password: admin123
  '$2y$10$5x2tv0okU/l7xv4nP0Qd9uWJz0lqH6pCPL5p5zJTr.EfS9gXOyq4u',
  'admin'
);
