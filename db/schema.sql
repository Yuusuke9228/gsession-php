-- データベース作成
CREATE DATABASE IF NOT EXISTS g_session;
USE g_session;

-- 組織テーブル
CREATE TABLE IF NOT EXISTS organizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    parent_id INT NULL,
    level INT NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES organizations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ユーザーテーブル
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    organization_id INT,
    position VARCHAR(100),
    phone VARCHAR(20),
    mobile_phone VARCHAR(20),
    status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    role ENUM('admin', 'manager', 'user') NOT NULL DEFAULT 'user',
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ユーザー組織関連テーブル（一人のユーザーが複数の組織に所属可能）
CREATE TABLE IF NOT EXISTS user_organizations (
    user_id INT NOT NULL,
    organization_id INT NOT NULL,
    is_primary BOOLEAN NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, organization_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- user_tokens テーブルを作成
CREATE TABLE IF NOT EXISTS user_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- スケジュールテーブル
CREATE TABLE IF NOT EXISTS schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    all_day BOOLEAN NOT NULL DEFAULT 0,
    location VARCHAR(255),
    creator_id INT NOT NULL,
    visibility ENUM('public', 'private', 'specific') NOT NULL DEFAULT 'public',
    priority ENUM('high', 'normal', 'low') NOT NULL DEFAULT 'normal',
    status ENUM('scheduled', 'tentative', 'cancelled') NOT NULL DEFAULT 'scheduled',
    repeat_type ENUM('none', 'daily', 'weekly', 'monthly', 'yearly') NOT NULL DEFAULT 'none',
    repeat_end_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- スケジュール参加者テーブル
CREATE TABLE IF NOT EXISTS schedule_participants (
    schedule_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('pending', 'accepted', 'declined', 'tentative') NOT NULL DEFAULT 'pending',
    notification_sent BOOLEAN NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (schedule_id, user_id),
    FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- スケジュール組織共有テーブル
CREATE TABLE IF NOT EXISTS schedule_organizations (
    schedule_id INT NOT NULL,
    organization_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (schedule_id, organization_id),
    FOREIGN KEY (schedule_id) REFERENCES schedules(id) ON DELETE CASCADE,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- システム設定テーブル
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ワークフローテンプレートテーブル
CREATE TABLE IF NOT EXISTS workflow_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT 'テンプレート名',
    description TEXT COMMENT '説明',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active' COMMENT 'ステータス',
    creator_id INT NOT NULL COMMENT '作成者ID',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ワークフローテンプレート';

-- フォーム定義テーブル
CREATE TABLE IF NOT EXISTS workflow_form_definitions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL COMMENT 'テンプレートID',
    field_id VARCHAR(50) NOT NULL COMMENT 'フィールドID',
    field_type ENUM('text', 'textarea', 'select', 'radio', 'checkbox', 'date', 'number', 'file', 'heading', 'hidden') NOT NULL COMMENT 'フィールドタイプ',
    label VARCHAR(100) NOT NULL COMMENT 'ラベル',
    placeholder VARCHAR(100) COMMENT 'プレースホルダー',
    help_text TEXT COMMENT 'ヘルプテキスト',
    options TEXT COMMENT '選択肢（JSON形式）',
    validation TEXT COMMENT 'バリデーションルール（JSON形式）',
    is_required BOOLEAN NOT NULL DEFAULT FALSE COMMENT '必須項目か',
    sort_order INT NOT NULL DEFAULT 0 COMMENT '表示順',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    FOREIGN KEY (template_id) REFERENCES workflow_templates(id) ON DELETE CASCADE,
    UNIQUE KEY (template_id, field_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ワークフローフォーム定義';

-- 承認経路定義テーブル
CREATE TABLE IF NOT EXISTS workflow_route_definitions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_id INT NOT NULL COMMENT 'テンプレートID',
    step_number INT NOT NULL COMMENT 'ステップ番号',
    step_type ENUM('approval', 'notification') NOT NULL DEFAULT 'approval' COMMENT 'ステップタイプ（承認/通知）',
    step_name VARCHAR(100) NOT NULL COMMENT 'ステップ名',
    approver_type ENUM('user', 'role', 'organization', 'dynamic') NOT NULL COMMENT '承認者タイプ',
    approver_id INT COMMENT '承認者ID（user, role, organizationの場合）',
    dynamic_approver_field_id VARCHAR(50) COMMENT '動的承認者フィールドID（dynamicの場合）',
    allow_delegation BOOLEAN NOT NULL DEFAULT FALSE COMMENT '代理承認を許可するか',
    allow_self_approval BOOLEAN NOT NULL DEFAULT FALSE COMMENT '自己承認を許可するか',
    parallel_approval BOOLEAN NOT NULL DEFAULT FALSE COMMENT '平行承認か',
    approval_condition TEXT COMMENT '承認条件（JSON形式）',
    sort_order INT NOT NULL DEFAULT 0 COMMENT '表示順',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    FOREIGN KEY (template_id) REFERENCES workflow_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ワークフロー承認経路定義';

-- 申請テーブル
CREATE TABLE IF NOT EXISTS workflow_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_number VARCHAR(50) NOT NULL COMMENT '申請番号',
    template_id INT NOT NULL COMMENT 'テンプレートID',
    title VARCHAR(255) NOT NULL COMMENT '申請タイトル',
    status ENUM('draft', 'pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'draft' COMMENT 'ステータス',
    current_step INT DEFAULT NULL COMMENT '現在のステップ',
    requester_id INT NOT NULL COMMENT '申請者ID',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    FOREIGN KEY (template_id) REFERENCES workflow_templates(id) ON DELETE RESTRICT,
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE RESTRICT,
    UNIQUE KEY (request_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ワークフロー申請';

-- 申請データテーブル
CREATE TABLE IF NOT EXISTS workflow_request_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL COMMENT '申請ID',
    field_id VARCHAR(50) NOT NULL COMMENT 'フィールドID',
    value TEXT COMMENT '値',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    FOREIGN KEY (request_id) REFERENCES workflow_requests(id) ON DELETE CASCADE,
    UNIQUE KEY (request_id, field_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ワークフロー申請データ';

-- 添付ファイルテーブル
CREATE TABLE IF NOT EXISTS workflow_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL COMMENT '申請ID',
    field_id VARCHAR(50) NOT NULL COMMENT 'フィールドID',
    file_name VARCHAR(255) NOT NULL COMMENT 'ファイル名',
    file_path VARCHAR(255) NOT NULL COMMENT 'ファイルパス',
    file_size INT NOT NULL COMMENT 'ファイルサイズ',
    mime_type VARCHAR(100) NOT NULL COMMENT 'MIMEタイプ',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    FOREIGN KEY (request_id) REFERENCES workflow_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ワークフロー添付ファイル';

-- 承認履歴テーブル
CREATE TABLE IF NOT EXISTS workflow_approvals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL COMMENT '申請ID',
    step_number INT NOT NULL COMMENT 'ステップ番号',
    approver_id INT NOT NULL COMMENT '承認者ID',
    delegate_id INT COMMENT '代理承認者ID',
    status ENUM('pending', 'approved', 'rejected', 'skipped') NOT NULL DEFAULT 'pending' COMMENT 'ステータス',
    comment TEXT COMMENT 'コメント',
    acted_at TIMESTAMP NULL DEFAULT NULL COMMENT '承認/却下日時',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    FOREIGN KEY (request_id) REFERENCES workflow_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (delegate_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ワークフロー承認履歴';

-- コメントテーブル
CREATE TABLE IF NOT EXISTS workflow_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL COMMENT '申請ID',
    user_id INT NOT NULL COMMENT 'ユーザーID',
    comment TEXT NOT NULL COMMENT 'コメント',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    FOREIGN KEY (request_id) REFERENCES workflow_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ワークフローコメント';

-- 代理承認設定テーブル
CREATE TABLE IF NOT EXISTS workflow_delegates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT 'ユーザーID',
    delegate_id INT NOT NULL COMMENT '代理人ID',
    template_id INT COMMENT 'テンプレートID（NULLの場合はすべてのテンプレート）',
    start_date DATE NOT NULL COMMENT '開始日',
    end_date DATE NOT NULL COMMENT '終了日',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active' COMMENT 'ステータス',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (delegate_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES workflow_templates(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='ワークフロー代理承認設定';

-- 初期データ挿入
INSERT INTO organizations (name, code, level, description)
VALUES ('本社', 'HQ', 1, 'トップレベル組織');

INSERT INTO users (username, password, email, first_name, last_name, display_name, organization_id, role)
VALUES ('admin', '$2y$10$fIfMRDXytV.YStSWln4raOAWV9xEfOUui9JAj0.2z3ejVahDvjpwq', 'admin@example.com', '管理者', 'ユーザー', '管理者', 1, 'admin');

INSERT INTO user_organizations (user_id, organization_id, is_primary)
VALUES (1, 1, 1);
