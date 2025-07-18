-- 工場見学予約システム - データベース移行スクリプト
-- 実行前に必ずバックアップを取得してください

-- 1. timeslot_modeカラムは廃止されました
-- データ内容で自動的にパターンを判定するため、このカラムは不要です
-- 既に追加済みの場合は、remove_timeslot_mode.sqlで削除してください

-- 2. wp_factorysテーブルにcreated_atとupdated_atカラムを追加
-- （SPECIFICATION.mdに記載されているが、現在のテーブルには存在しない可能性があります）
ALTER TABLE wp_factorys 
ADD COLUMN created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP 
COMMENT '作成日時';

ALTER TABLE wp_factorys 
ADD COLUMN updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP 
COMMENT '更新日時';

-- 3. wp_unavailable_daysテーブルの拡張（60分・90分パターン対応）
-- 現在のテーブルはAM/PMのみ対応しているため、より詳細な時間帯管理が必要になる場合は
-- 以下のような追加カラムを検討してください（現時点では実装不要）

-- 参考：詳細な時間帯管理が必要な場合の拡張案
/*
ALTER TABLE wp_unavailable_days 
ADD COLUMN timeslot_60min_am TEXT DEFAULT NULL 
COMMENT '60分コースAM時間帯の利用不可スロット（JSON形式）';

ALTER TABLE wp_unavailable_days 
ADD COLUMN timeslot_60min_pm TEXT DEFAULT NULL 
COMMENT '60分コースPM時間帯の利用不可スロット（JSON形式）';

ALTER TABLE wp_unavailable_days 
ADD COLUMN timeslot_90min_am TEXT DEFAULT NULL 
COMMENT '90分コースAM時間帯の利用不可スロット（JSON形式）';

ALTER TABLE wp_unavailable_days 
ADD COLUMN timeslot_90min_pm TEXT DEFAULT NULL 
COMMENT '90分コースPM時間帯の利用不可スロット（JSON形式）';
*/

-- 4. wp_reservationsテーブルの確認・作成（まだ存在しない場合）
-- SPECIFICATION.mdに記載されているテーブル定義
CREATE TABLE IF NOT EXISTS wp_reservations (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    factory_id bigint(20) UNSIGNED NOT NULL,
    visit_date date NOT NULL,
    visit_duration int NOT NULL DEFAULT 60 COMMENT '見学時間（分）',
    time_slot varchar(20) NOT NULL COMMENT '時間帯',
    status varchar(20) NOT NULL DEFAULT 'new' COMMENT '予約ステータス',
    visitor_type varchar(50) NOT NULL COMMENT '見学者分類',
    is_travel_agency tinyint(1) NOT NULL DEFAULT 0 COMMENT '旅行会社フラグ',
    applicant_name varchar(255) NOT NULL COMMENT '申込者氏名',
    applicant_kana varchar(255) NOT NULL COMMENT '申込者氏名（ふりがな）',
    applicant_email varchar(255) NOT NULL COMMENT '申込者メールアドレス',
    applicant_phone varchar(20) NOT NULL COMMENT '申込者電話番号',
    applicant_mobile varchar(20) NOT NULL COMMENT '当日連絡先',
    applicant_postal_code varchar(10) DEFAULT NULL COMMENT '申込者郵便番号',
    applicant_prefecture varchar(50) DEFAULT NULL COMMENT '申込者都道府県',
    applicant_city varchar(255) DEFAULT NULL COMMENT '申込者市町村',
    applicant_address varchar(255) DEFAULT NULL COMMENT '申込者番地',
    organization_name varchar(255) DEFAULT NULL COMMENT '組織名',
    organization_kana varchar(255) DEFAULT NULL COMMENT '組織名（ふりがな）',
    visitor_count_adult int NOT NULL DEFAULT 0 COMMENT '見学者人数（大人）',
    visitor_count_child int NOT NULL DEFAULT 0 COMMENT '見学者人数（子ども）',
    transportation varchar(50) NOT NULL COMMENT '交通機関',
    vehicle_count int DEFAULT NULL COMMENT '台数',
    visit_purpose text DEFAULT NULL COMMENT '見学目的',
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    PRIMARY KEY (id),
    KEY idx_factory_date (factory_id, visit_date),
    KEY idx_status (status),
    FOREIGN KEY (factory_id) REFERENCES wp_factorys(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. wp_reservation_detailsテーブルの作成（追加項目の動的格納用）
CREATE TABLE IF NOT EXISTS wp_reservation_details (
    id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    reservation_id bigint(20) UNSIGNED NOT NULL,
    field_name varchar(100) NOT NULL COMMENT 'フィールド名',
    field_value text DEFAULT NULL COMMENT 'フィールド値',
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    PRIMARY KEY (id),
    KEY idx_reservation (reservation_id),
    FOREIGN KEY (reservation_id) REFERENCES wp_reservations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. 工場マスタデータの初期化（必要に応じて）
-- 現在のwp_factorysテーブルにデータが存在しない場合の初期データ投入
INSERT IGNORE INTO wp_factorys (id, name, capacity, timeslot_mode) VALUES
(1, '関東リサイクル工場', 50, 'am_pm_only'),
(2, '中部リサイクル工場', 50, 'am_pm_only'),
(3, '福山リサイクル工場', 50, 'am_pm_only'),
(4, '山形選別センター', 50, 'am_pm_only'),
(5, '松本選別センター', 50, 'am_pm_only'),
(6, '西宮選別センター', 50, 'am_pm_only'),
(7, '東海選別センター', 50, 'am_pm_only'),
(8, '金沢選別センター', 50, 'am_pm_only'),
(9, '九州選別センター', 50, 'am_pm_only');

-- 実行後の確認
-- SELECT * FROM wp_factorys;
-- DESCRIBE wp_factorys;
-- DESCRIBE wp_unavailable_days;
-- DESCRIBE wp_reservations;
-- DESCRIBE wp_reservation_details;