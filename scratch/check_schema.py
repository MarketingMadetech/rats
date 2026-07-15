import sqlite3
conn = sqlite3.connect('data/rats.db')
cursor = conn.cursor()
cursor.execute("SELECT sql FROM sqlite_master WHERE type='table' AND name='rats'")
sql = cursor.fetchone()[0]
print("--- SQL ---")
print(sql)
print("--- PRAGMA table_info ---")
cursor.execute("PRAGMA table_info(rats)")
for col in cursor.fetchall():
    print(col)
print("--- PRAGMA index_list ---")
cursor.execute("PRAGMA index_list(rats)")
for idx in cursor.fetchall():
    print(idx)
conn.close()
