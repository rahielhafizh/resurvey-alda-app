CREATE DATABASE IF NOT EXISTS `resurvey_alda` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `resurvey_alda`;

-- Dummy Table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(255) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `initials` VARCHAR(10) NOT NULL,
    `role` ENUM(
        'admin',
        'surveyor',
        'manager'
    ) NOT NULL DEFAULT 'surveyor',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `email_unique` (`email`),
    KEY `role_index` (`role`),
    KEY `is_active_index` (`is_active`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Dummy Table
CREATE TABLE IF NOT EXISTS `tasks` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `status` ENUM(
        'new',
        'in_progress',
        'running',
        'completed',
        'uploaded'
    ) NOT NULL DEFAULT 'new',
    `priority` ENUM(
        'low',
        'medium',
        'high',
        'urgent'
    ) NOT NULL DEFAULT 'medium',
    `due_date` DATE,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `user_id_foreign` (`user_id`),
    KEY `status_index` (`status`),
    KEY `priority_index` (`priority`),
    KEY `due_date_index` (`due_date`),
    CONSTRAINT `tasks_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Dummy Table
CREATE TABLE IF NOT EXISTS `uploads` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `task_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_type` VARCHAR(100),
    `file_size` INT UNSIGNED NOT NULL,
    `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `task_id_foreign` (`task_id`),
    KEY `user_id_foreign` (`user_id`),
    KEY `uploaded_at_index` (`uploaded_at`),
    CONSTRAINT `uploads_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `uploads_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Dummy Table
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `ip_address` VARCHAR(45),
    `user_agent` VARCHAR(500),
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id_foreign` (`user_id`),
    KEY `action_index` (`action`),
    KEY `created_at_index` (`created_at`),
    CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

INSERT INTO
    `users` (
        `email`,
        `password`,
        `name`,
        `initials`,
        `role`
    )
VALUES (
        'admin@suzuki.co.id',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- admin123
        'Administrator',
        'AD',
        'admin'
    );

INSERT INTO
    `users` (
        `email`,
        `password`,
        `name`,
        `initials`,
        `role`
    )
VALUES (
        'rahiel.hafizh@suzuki.co.id',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'Rahiel',
        'RH',
        'surveyor'
    );

INSERT INTO
    `tasks` (
        `user_id`,
        `title`,
        `description`,
        `status`,
        `priority`,
        `due_date`
    )
VALUES (
        2,
        'Survey Lokasi A',
        'Melakukan resurvey di lokasi A untuk verifikasi data',
        'new',
        'high',
        '2026-05-01'
    ),
    (
        2,
        'Survey Lokasi B',
        'Survey rutin lokasi B',
        'in_progress',
        'medium',
        '2026-05-05'
    ),
    (
        2,
        'Survey Lokasi C',
        'Pengecekan ulang data lokasi C',
        'running',
        'medium',
        '2026-05-10'
    );

CREATE OR REPLACE VIEW `user_task_summary` AS
SELECT
    u.id as user_id,
    u.name as user_name,
    u.email,
    COUNT(
        CASE
            WHEN t.status = 'new' THEN 1
        END
    ) as new_tasks,
    COUNT(
        CASE
            WHEN t.status = 'in_progress' THEN 1
        END
    ) as in_progress_tasks,
    COUNT(
        CASE
            WHEN t.status = 'running' THEN 1
        END
    ) as running_tasks,
    COUNT(
        CASE
            WHEN t.status = 'completed' THEN 1
        END
    ) as completed_tasks,
    COUNT(
        CASE
            WHEN t.status = 'uploaded' THEN 1
        END
    ) as uploaded_tasks,
    COUNT(t.id) as total_tasks
FROM users u
    LEFT JOIN tasks t ON u.id = t.user_id
GROUP BY
    u.id,
    u.name,
    u.email;

CREATE INDEX idx_tasks_user_status ON tasks (user_id, status);

CREATE INDEX idx_uploads_task_user ON uploads (task_id, user_id);

ALTER TABLE `users` COMMENT = 'User accounts for the Resurvey Alda application';

ALTER TABLE `tasks` COMMENT = 'Survey tasks assigned to users';

ALTER TABLE `uploads` COMMENT = 'File uploads related to tasks';

ALTER TABLE `activity_logs` COMMENT = 'User activity tracking logs';