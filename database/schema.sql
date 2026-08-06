-- =====================================================================
-- Online Voting System - Database Schema
-- Target: MySQL 5.7+ / MariaDB (Hostinger shared hosting compatible)
-- Voter login/identity = matric number (voters.matric_no)
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. admins  -- people who can log into the admin panel
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  email         VARCHAR(100) NOT NULL UNIQUE,
  password      VARCHAR(255) NOT NULL,          -- store with PHP password_hash()
  full_name     VARCHAR(150) NOT NULL,
  role          ENUM('super_admin','admin') NOT NULL DEFAULT 'admin',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. election_settings -- one row holding the current election config
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS election_settings (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title              VARCHAR(150) NOT NULL DEFAULT 'Departmental Election',
  start_datetime     DATETIME NULL,
  end_datetime       DATETIME NULL,
  status             ENUM('upcoming','ongoing','ended') NOT NULL DEFAULT 'upcoming',
  show_live_results  TINYINT(1) NOT NULL DEFAULT 1,   -- toggle the public results page on/off
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. positions -- offices being contested (President, Course Rep, ...)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS positions (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title          VARCHAR(100) NOT NULL,
  description    TEXT NULL,
  display_order  INT NOT NULL DEFAULT 0,
  status         ENUM('open','closed') NOT NULL DEFAULT 'open',
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. candidates -- contestants registered by the admin, tied to a position
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS candidates (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  position_id   INT UNSIGNED NOT NULL,
  matric_no     VARCHAR(20)  NULL,               -- candidate's own matric no (optional)
  full_name     VARCHAR(150) NOT NULL,
  photo         VARCHAR(255) NULL,                -- uploaded file path, e.g. uploads/candidates/xxx.jpg
  department    VARCHAR(100) NULL,
  level         VARCHAR(10)  NULL,
  manifesto     TEXT NULL,
  status        ENUM('active','disqualified') NOT NULL DEFAULT 'active',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_candidates_position FOREIGN KEY (position_id)
    REFERENCES positions(id) ON DELETE CASCADE,
  INDEX idx_candidates_position (position_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. voters -- eligible students; matric_no IS the voter ID used to log in
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS voters (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  matric_no     VARCHAR(20)  NOT NULL UNIQUE,     -- e.g. 22/0313, 23/DE/0516
  full_name     VARCHAR(150) NOT NULL,
  department    VARCHAR(100) NULL,
  level         VARCHAR(10)  NULL,
  email         VARCHAR(100) NULL,
  phone         VARCHAR(20)  NULL,
  pin_hash      VARCHAR(255) NULL,                -- optional extra PIN/password, hashed
  status        ENUM('eligible','blocked') NOT NULL DEFAULT 'eligible',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. votes -- one real ballot per voter per position
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS votes (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  voter_id      INT UNSIGNED NOT NULL,
  position_id   INT UNSIGNED NOT NULL,
  candidate_id  INT UNSIGNED NOT NULL,
  ip_address    VARCHAR(45) NULL,
  voted_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_votes_voter FOREIGN KEY (voter_id) REFERENCES voters(id) ON DELETE CASCADE,
  CONSTRAINT fk_votes_position FOREIGN KEY (position_id) REFERENCES positions(id) ON DELETE CASCADE,
  CONSTRAINT fk_votes_candidate FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_voter_position (voter_id, position_id),  -- enforces "one vote per position"
  INDEX idx_votes_candidate (candidate_id),
  INDEX idx_votes_position (position_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. rigged_votes -- admin-injected vote adjustments (the "rigging" page)
--    Kept as a separate, fully-audited ledger instead of fake rows in
--    `votes`, so real turnout and rigged totals can always be told apart.
--    `quantity` can be negative to remove votes from a candidate.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rigged_votes (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  candidate_id  INT UNSIGNED NOT NULL,
  position_id   INT UNSIGNED NOT NULL,
  admin_id      INT UNSIGNED NOT NULL,
  quantity      INT NOT NULL,
  reason        VARCHAR(255) NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_rigged_candidate FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
  CONSTRAINT fk_rigged_position FOREIGN KEY (position_id) REFERENCES positions(id) ON DELETE CASCADE,
  CONSTRAINT fk_rigged_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
  INDEX idx_rigged_candidate (candidate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. admin_activity_log -- audit trail for admin actions (incl. rigging)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_activity_log (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id      INT UNSIGNED NULL,
  action        VARCHAR(100) NOT NULL,
  details       TEXT NULL,
  ip_address    VARCHAR(45) NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_log_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. election_results view -- what the public live-results page reads.
--    total_votes = genuine votes + rigged adjustment, per candidate.
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW election_results AS
SELECT
  c.id                              AS candidate_id,
  c.full_name                       AS candidate_name,
  c.photo                           AS candidate_photo,
  p.id                              AS position_id,
  p.title                           AS position_title,
  COALESCE(rv.real_votes, 0)        AS real_votes,
  COALESCE(rg.rigged_votes, 0)      AS rigged_votes,
  COALESCE(rv.real_votes, 0) + COALESCE(rg.rigged_votes, 0) AS total_votes
FROM candidates c
JOIN positions p ON p.id = c.position_id
LEFT JOIN (
  SELECT candidate_id, COUNT(*) AS real_votes
  FROM votes
  GROUP BY candidate_id
) rv ON rv.candidate_id = c.id
LEFT JOIN (
  SELECT candidate_id, SUM(quantity) AS rigged_votes
  FROM rigged_votes
  GROUP BY candidate_id
) rg ON rg.candidate_id = c.id
WHERE c.status = 'active';

-- =====================================================================
-- Seed data
-- =====================================================================

-- Default election config
INSERT INTO election_settings (title, status, show_live_results)
VALUES ('Departmental Election', 'upcoming', 1);

-- Default admin login: username "admin", password "ChangeMe@2026"
-- CHANGE THIS PASSWORD IMMEDIATELY after first login.
-- Hash below was generated with PHP: password_hash('ChangeMe@2026', PASSWORD_BCRYPT)
INSERT INTO admins (username, email, password, full_name, role)
VALUES (
  'admin',
  'admin@example.com',
  '$2y$12$NpP21H/Y1UufsQrPzp7vJOaKfwXvKCxAsZJgx9ovm60LoxyhMXdUW',
  'Super Admin',
  'super_admin'
);

-- Example positions (edit/remove as needed from the admin panel)
INSERT INTO positions (title, description, display_order) VALUES
  ('President', 'Overall head of the association', 1),
  ('Vice President', 'Assists and deputizes for the President', 2),
  ('Secretary General', 'Handles records and correspondence', 3),
  ('Financial Secretary', 'Handles financial records', 4),
  ('Course Representative', 'Class representative to the department', 5);

SET FOREIGN_KEY_CHECKS = 1;
