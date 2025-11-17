-- 1. Créer users SANS la foreign key vers repositories
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('student','formateur') NOT NULL DEFAULT 'student',
  github_token TEXT NULL,
  github_username VARCHAR(255) NULL,
  active_repo_id INT NULL,
  created_at DATETIME NOT NULL
);

-- 2. Créer repositories (maintenant users existe)
CREATE TABLE IF NOT EXISTS repositories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  github_url VARCHAR(255) NULL,
  description TEXT NULL,
  user_id INT NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_repos_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 3. Créer tasks
CREATE TABLE IF NOT EXISTS tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT NULL,
  status ENUM('todo','in_progress','review','done') NOT NULL DEFAULT 'todo',
  priority ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  user_id INT NULL,
  repo_id INT NULL,
  labels TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  CONSTRAINT fk_tasks_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_tasks_repo FOREIGN KEY (repo_id) REFERENCES repositories(id) ON DELETE SET NULL
);

-- 4. Créer commits
CREATE TABLE IF NOT EXISTS commits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  message VARCHAR(255) NOT NULL,
  sha VARCHAR(100) NULL,
  user_id INT NOT NULL,
  repo_id INT NULL,
  task_id INT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_commits_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_commits_repo FOREIGN KEY (repo_id) REFERENCES repositories(id) ON DELETE SET NULL,
  CONSTRAINT fk_commits_task FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL
);

-- 5. Créer notifications
CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  type VARCHAR(50) NOT NULL,
  message VARCHAR(255) NOT NULL,
  data JSON NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 6. Ajouter la foreign key vers repositories dans users
ALTER TABLE users 
ADD CONSTRAINT fk_users_active_repo 
FOREIGN KEY (active_repo_id) REFERENCES repositories(id) ON DELETE SET NULL;