-- 工場見学予約システム - バックアップスクリプト
-- 実行日時: 実行前に日時を記録してください

-- 1. wp_factorysテーブルのバックアップ
CREATE TABLE wp_factorys_backup_20250718 AS SELECT * FROM wp_factorys;

-- 2. wp_unavailable_daysテーブルのバックアップ
CREATE TABLE wp_unavailable_days_backup_20250718 AS SELECT * FROM wp_unavailable_days;

-- 3. wp_reservationsテーブルのバックアップ（存在する場合）
CREATE TABLE wp_reservations_backup_20250718 AS SELECT * FROM wp_reservations;

-- 4. 工場関連のユーザーメタデータのバックアップ
CREATE TABLE wp_usermeta_factory_backup_20250718 AS 
SELECT * FROM wp_usermeta 
WHERE meta_key IN ('assigned_factory', 'max_participants');

-- バックアップの確認
SELECT 'wp_factorys' as table_name, COUNT(*) as record_count FROM wp_factorys
UNION ALL
SELECT 'wp_factorys_backup_20250718', COUNT(*) FROM wp_factorys_backup_20250718
UNION ALL
SELECT 'wp_unavailable_days', COUNT(*) FROM wp_unavailable_days  
UNION ALL
SELECT 'wp_unavailable_days_backup_20250718', COUNT(*) FROM wp_unavailable_days_backup_20250718
UNION ALL
SELECT 'wp_usermeta_factory_backup_20250718', COUNT(*) FROM wp_usermeta_factory_backup_20250718;

-- 万が一の復旧用コマンド（コメントアウト）
/*
-- 復旧手順（緊急時のみ使用）
-- 1. 現在のテーブルをリネーム
-- RENAME TABLE wp_factorys TO wp_factorys_modified;
-- 2. バックアップからリストア  
-- RENAME TABLE wp_factorys_backup_20250718 TO wp_factorys;
*/