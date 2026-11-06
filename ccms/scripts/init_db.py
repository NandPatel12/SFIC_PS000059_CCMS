#!/usr/bin/env python3
"""
Gandhidham CCMS Database Initialization & SQLite Migration Tool
"""
import os
import sqlite3
import re

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DB_PATH = os.path.join(BASE_DIR, 'storage', 'database.sqlite')

def clean_sql_for_sqlite(sql):
    lines = []
    for line in sql.split('\n'):
        if re.match(r'^\s*(CREATE DATABASE|USE)\b', line, re.IGNORECASE):
            continue
        line = re.sub(r'\)\s*ENGINE=InnoDB;', ');', line, flags=re.IGNORECASE)
        if re.search(r'^\s*(INDEX|KEY|UNIQUE KEY)\s+', line, re.IGNORECASE):
            continue
        lines.append(line)
    
    sql_text = '\n'.join(lines)
    sql_text = re.sub(r',\s*\n\s*\)', '\n)', sql_text)
    sql_text = re.sub(r'`id`\s+(INT|BIGINT)\s+AUTO_INCREMENT\s+PRIMARY\s+KEY', '`id` INTEGER PRIMARY KEY AUTOINCREMENT', sql_text, flags=re.IGNORECASE)
    sql_text = re.sub(r'ENUM\([^)]+\)', 'TEXT', sql_text, flags=re.IGNORECASE)
    # Replace JSON data type but avoid replacing substrings like evidence_json
    sql_text = re.sub(r'\bJSON\b', 'TEXT', sql_text, flags=re.IGNORECASE)
    sql_text = re.sub(r'ON UPDATE CURRENT_TIMESTAMP', '', sql_text, flags=re.IGNORECASE)
    sql_text = re.sub(r'ON DUPLICATE KEY UPDATE[^;]+;', ';', sql_text, flags=re.IGNORECASE)
    sql_text = re.sub(r'DATE_ADD\(NOW\(\),\s*INTERVAL\s*(\d+)\s*HOUR\)', "datetime('now', '+\\1 hours')", sql_text, flags=re.IGNORECASE)
    sql_text = re.sub(r'NOW\(\)', "datetime('now')", sql_text, flags=re.IGNORECASE)
    return sql_text

def main():
    os.makedirs(os.path.join(BASE_DIR, 'storage'), exist_ok=True)
    if os.path.exists(DB_PATH):
        os.remove(DB_PATH)

    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()

    schema_file = os.path.join(BASE_DIR, 'database', 'schema.sql')
    seeds_file = os.path.join(BASE_DIR, 'database', 'seeds', 'demo_data.sql')

    print(f"Applying schema from {schema_file}...")
    with open(schema_file, 'r', encoding='utf-8') as f:
        schema_sql = clean_sql_for_sqlite(f.read())
        cursor.executescript(schema_sql)

    print(f"Seeding demo data from {seeds_file}...")
    with open(seeds_file, 'r', encoding='utf-8') as f:
        seeds_sql = clean_sql_for_sqlite(f.read())
        cursor.executescript(seeds_sql)

    # Ensure unique index for device_latest_state
    cursor.execute("CREATE UNIQUE INDEX IF NOT EXISTS idx_device_latest_asset ON device_latest_state(asset_type, asset_id)")

    conn.commit()
    conn.close()
    print(f"Database successfully re-generated at {DB_PATH}")

if __name__ == '__main__':
    main()
