-- Schéma MySQL 5.7+ / MariaDB 10.3+ — utf8mb4
-- Importable tel quel via phpMyAdmin, ou exécuté par install.php.

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email         VARCHAR(190) NOT NULL,
    name          VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    last_household_id INT UNSIGNED NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS households (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name             VARCHAR(100) NOT NULL,
    default_servings TINYINT UNSIGNED NOT NULL DEFAULT 4,
    created_by       INT UNSIGNED NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_hh_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS household_members (
    household_id INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    role         ENUM('owner','member') NOT NULL DEFAULT 'member',
    joined_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (household_id, user_id),
    KEY idx_hm_user (user_id),
    CONSTRAINT fk_hm_hh   FOREIGN KEY (household_id) REFERENCES households (id) ON DELETE CASCADE,
    CONSTRAINT fk_hm_user FOREIGN KEY (user_id)      REFERENCES users (id)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Liens d'invitation : réutilisables jusqu'à expiration / révocation / max_uses.
CREATE TABLE IF NOT EXISTS invitations (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    household_id INT UNSIGNED NOT NULL,
    token        CHAR(40) NOT NULL,
    created_by   INT UNSIGNED NULL,
    expires_at   DATETIME NULL,
    max_uses     SMALLINT UNSIGNED NULL,
    uses         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    revoked_at   DATETIME NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_inv_token (token),
    KEY idx_inv_hh (household_id),
    CONSTRAINT fk_inv_hh      FOREIGN KEY (household_id) REFERENCES households (id) ON DELETE CASCADE,
    CONSTRAINT fk_inv_creator FOREIGN KEY (created_by)   REFERENCES users (id)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipes (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    household_id INT UNSIGNED NOT NULL,
    name         VARCHAR(200) NOT NULL,
    category     VARCHAR(30) NOT NULL DEFAULT 'autre',
    url          VARCHAR(500) NULL,
    notes        TEXT NULL,
    servings     TINYINT UNSIGNED NOT NULL DEFAULT 4,
    favorite     TINYINT(1) NOT NULL DEFAULT 0,
    auto_ok      TINYINT(1) NOT NULL DEFAULT 1,
    created_by   INT UNSIGNED NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rec_hh (household_id, name),
    CONSTRAINT fk_rec_hh      FOREIGN KEY (household_id) REFERENCES households (id) ON DELETE CASCADE,
    CONSTRAINT fk_rec_creator FOREIGN KEY (created_by)   REFERENCES users (id)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipe_ingredients (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipe_id INT UNSIGNED NOT NULL,
    name      VARCHAR(150) NOT NULL,
    quantity  DECIMAL(10,2) NULL,
    unit      VARCHAR(30) NULL,
    aisle     VARCHAR(40) NULL,
    position  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_ing_recipe (recipe_id, position),
    CONSTRAINT fk_ing_recipe FOREIGN KEY (recipe_id) REFERENCES recipes (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Repas planifiés. recipe_id peut être NULL (repas libre : label).
CREATE TABLE IF NOT EXISTS meals (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    household_id INT UNSIGNED NOT NULL,
    meal_date    DATE NOT NULL,
    slot         ENUM('lunch','dinner') NOT NULL DEFAULT 'dinner',
    recipe_id    INT UNSIGNED NULL,
    label        VARCHAR(200) NULL,
    servings     TINYINT UNSIGNED NULL,
    notes        VARCHAR(500) NULL,
    created_by   INT UNSIGNED NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_meals_date (household_id, meal_date),
    KEY idx_meals_recipe (recipe_id),
    CONSTRAINT fk_meals_hh      FOREIGN KEY (household_id) REFERENCES households (id) ON DELETE CASCADE,
    CONSTRAINT fk_meals_recipe  FOREIGN KEY (recipe_id)    REFERENCES recipes (id)    ON DELETE SET NULL,
    CONSTRAINT fk_meals_creator FOREIGN KEY (created_by)   REFERENCES users (id)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Liste de courses : une ligne = un article coché (clé = hash nom|unité), par semaine.
CREATE TABLE IF NOT EXISTS shopping_checks (
    household_id INT UNSIGNED NOT NULL,
    week_start   DATE NOT NULL,
    item_key     CHAR(40) NOT NULL,
    PRIMARY KEY (household_id, week_start, item_key),
    CONSTRAINT fk_chk_hh FOREIGN KEY (household_id) REFERENCES households (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shopping_extras (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    household_id INT UNSIGNED NOT NULL,
    week_start   DATE NOT NULL,
    name         VARCHAR(150) NOT NULL,
    quantity     VARCHAR(50) NULL,
    aisle        VARCHAR(40) NULL,
    checked      TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_extra_week (household_id, week_start),
    CONSTRAINT fk_extra_hh FOREIGN KEY (household_id) REFERENCES households (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Liens de partage public (lecture seule) d'une semaine : menu + courses.
CREATE TABLE IF NOT EXISTS share_links (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    household_id INT UNSIGNED NOT NULL,
    week_start   DATE NOT NULL,
    token        CHAR(40) NOT NULL,
    created_by   INT UNSIGNED NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_share_token (token),
    KEY idx_share_week (household_id, week_start),
    CONSTRAINT fk_share_hh      FOREIGN KEY (household_id) REFERENCES households (id) ON DELETE CASCADE,
    CONSTRAINT fk_share_creator FOREIGN KEY (created_by)   REFERENCES users (id)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Limitation des tentatives de connexion.
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip           VARCHAR(45) NOT NULL,
    email        VARCHAR(190) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_la (ip, email, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
