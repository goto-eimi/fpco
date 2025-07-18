-- timeslot_modeカラムの削除スクリプト
-- 実行前に必ずバックアップを取得してください

-- 1. カラムが存在する場合のみ削除
ALTER TABLE wp_factorys 
DROP COLUMN IF EXISTS timeslot_mode;

-- 2. 削除後の確認
-- DESCRIBE wp_factorys;