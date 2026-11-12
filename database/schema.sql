-- database/schema.sql — модели физикии пойгоҳи kanban (MySQL / MariaDB, InnoDB, utf8mb4)
-- Скрипт такроран иҷро мешавад: пойгоҳи кӯҳна нест карда, аз нав сохта мешавад.
-- Диққат: DROP DATABASE тамоми додаҳоро нест мекунад.

DROP DATABASE IF EXISTS kanban;
CREATE DATABASE kanban
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kanban;
SET NAMES utf8mb4;

-- Намудҳо: калидҳо — INT UNSIGNED (манфӣ нест); вақт — DATETIME (маҳдудияти соли 2038-и TIMESTAMP нест);
-- сатрҳо — VARCHAR(n) бо дарозии асоснок; матни дароз — TEXT; ҳолатҳои пӯшида — ENUM.

CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,            -- ҳеши bcrypt, на паролӣ кушода
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_users_email UNIQUE (email)
) ENGINE=InnoDB;

CREATE TABLE projects (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(150) NOT NULL,
  description TEXT NULL,
  owner_id    INT UNSIGNED NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_projects_owner FOREIGN KEY (owner_id)
    REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- Ҷадвали пайвасткунанда: алоқаи M:N «корбарон — лоиҳаҳо»
CREATE TABLE project_members (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  role       ENUM('manager','member') NOT NULL DEFAULT 'member',
  CONSTRAINT uq_project_members UNIQUE (project_id, user_id),
  CONSTRAINT fk_members_project FOREIGN KEY (project_id)
    REFERENCES projects(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_members_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE columns (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id INT UNSIGNED NOT NULL,
  title      VARCHAR(100) NOT NULL,
  position   INT NOT NULL DEFAULT 0,
  CONSTRAINT chk_columns_position CHECK (position >= 0),
  CONSTRAINT fk_columns_project FOREIGN KEY (project_id)
    REFERENCES projects(id) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_columns_project_position (project_id, position)   -- тартиби сутунҳои тахта
) ENGINE=InnoDB;

CREATE TABLE tasks (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id  INT UNSIGNED NOT NULL,   -- истисно аз 3ШН: барои санҷиши ҳуқуқ бе JOIN (ниг. docs/06-logical-model.md)
  column_id   INT UNSIGNED NOT NULL,
  title       VARCHAR(200) NOT NULL,
  description TEXT NULL,
  assignee_id INT UNSIGNED NULL,
  due_date    DATE NULL,
  priority    ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  position    INT NOT NULL DEFAULT 0,
  created_by  INT UNSIGNED NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_tasks_position CHECK (position >= 0),
  CONSTRAINT fk_tasks_project FOREIGN KEY (project_id)
    REFERENCES projects(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_tasks_column FOREIGN KEY (column_id)
    REFERENCES columns(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_tasks_assignee FOREIGN KEY (assignee_id)
    REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_tasks_created_by FOREIGN KEY (created_by)
    REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_tasks_column_position (column_id, position),   -- тартиби кортҳо дар сутун
  INDEX idx_tasks_assignee (assignee_id),                  -- филтр аз рӯи иҷрокунанда (ФТ-10, ФТ-05)
  INDEX idx_tasks_due_date (due_date)                      -- филтр аз рӯи мӯҳлат ва рӯйхати гузашта (ФТ-10, ФТ-11)
) ENGINE=InnoDB;

CREATE TABLE task_comments (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  task_id    INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  body       TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_comments_task FOREIGN KEY (task_id)
    REFERENCES tasks(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_comments_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE task_history (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  task_id    INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  action     VARCHAR(30) NOT NULL,        -- created, update, move, comment, file
  details    TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_history_task FOREIGN KEY (task_id)
    REFERENCES tasks(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_history_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_history_task_created (task_id, created_at)     -- таърихи як вазифа бо тартиби вақт (ФТ-07)
) ENGINE=InnoDB;

CREATE TABLE task_attachments (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  task_id       INT UNSIGNED NOT NULL,
  user_id       INT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name   VARCHAR(255) NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_attachments_stored UNIQUE (stored_name),
  CONSTRAINT fk_attachments_task FOREIGN KEY (task_id)
    REFERENCES tasks(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_attachments_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;
