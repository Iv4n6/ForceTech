CREATE DATABASE IF NOT EXISTS url_shortener
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE url_shortener;

CREATE TABLE IF NOT EXISTS urls (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  long_url TEXT NOT NULL,
  short_code VARCHAR(8) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_short_code (short_code),
  INDEX idx_long_url (long_url(255)),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
