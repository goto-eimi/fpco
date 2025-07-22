-- 工場見学予約システム用データベーステーブル作成
-- 実行前に必ずバックアップを取得してください

-- 予約テーブル
CREATE TABLE IF NOT EXISTS `wp_reservations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reservation_id` varchar(20) NOT NULL COMMENT '予約受付番号',
  `factory_id` int(11) NOT NULL COMMENT '工場ID',
  `reservation_date` date NOT NULL COMMENT '見学日',
  `timeslot` varchar(20) NOT NULL COMMENT '時間帯',
  `applicant_name` varchar(100) NOT NULL COMMENT '申込者名',
  `applicant_name_kana` varchar(100) NOT NULL COMMENT '申込者名（ふりがな）',
  `email` varchar(255) NOT NULL COMMENT 'メールアドレス',
  `phone` varchar(20) NOT NULL COMMENT '電話番号',
  `mobile` varchar(20) NOT NULL COMMENT '携帯番号',
  `postal_code` varchar(7) NOT NULL COMMENT '郵便番号',
  `prefecture` varchar(10) NOT NULL COMMENT '都道府県',
  `city` varchar(50) NOT NULL COMMENT '市区町村',
  `address` varchar(255) NOT NULL COMMENT '番地',
  `building` varchar(255) DEFAULT NULL COMMENT '建物名',
  `transportation` varchar(20) NOT NULL COMMENT '交通機関',
  `transportation_other` varchar(255) DEFAULT NULL COMMENT 'その他交通機関',
  `vehicle_count` int(11) DEFAULT NULL COMMENT '台数',
  `purpose` text NOT NULL COMMENT '見学目的',
  `is_travel_agency` enum('yes','no') NOT NULL DEFAULT 'no' COMMENT '旅行会社フラグ',
  `visitor_category` varchar(20) NOT NULL COMMENT '見学者分類',
  `total_visitors` int(11) NOT NULL COMMENT '見学者総数',
  `form_data` longtext NOT NULL COMMENT '全フォームデータ（JSON）',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'ステータス',
  `created_at` datetime NOT NULL COMMENT '作成日時',
  `updated_at` datetime NOT NULL COMMENT '更新日時',
  PRIMARY KEY (`id`),
  UNIQUE KEY `reservation_id` (`reservation_id`),
  KEY `factory_date` (`factory_id`, `reservation_date`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='工場見学予約';

-- 旅行会社情報テーブル
CREATE TABLE IF NOT EXISTS `wp_reservation_agencies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reservation_id` varchar(20) NOT NULL COMMENT '予約受付番号',
  `agency_name` varchar(255) NOT NULL COMMENT '旅行会社名',
  `agency_phone` varchar(20) NOT NULL COMMENT '旅行会社電話番号',
  `agency_postal_code` varchar(7) NOT NULL COMMENT '郵便番号',
  `agency_prefecture` varchar(10) NOT NULL COMMENT '都道府県',
  `agency_city` varchar(50) NOT NULL COMMENT '市区町村',
  `agency_address` varchar(255) NOT NULL COMMENT '番地',
  `agency_building` varchar(255) DEFAULT NULL COMMENT '建物名',
  `agency_fax` varchar(20) DEFAULT NULL COMMENT 'FAX番号',
  `agency_contact_mobile` varchar(20) DEFAULT NULL COMMENT '担当者携帯',
  `agency_contact_email` varchar(255) NOT NULL COMMENT '担当者メール',
  `created_at` datetime NOT NULL COMMENT '作成日時',
  PRIMARY KEY (`id`),
  KEY `reservation_id` (`reservation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='旅行会社情報';