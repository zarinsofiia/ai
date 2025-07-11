import requests
import mysql.connector

# === CONFIGURATION ===

DEESEEK_API_URL = "http://192.168.2.70:11434/api/generate"

DB_CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "",
    "database": "ysh_ims"
}

# === STEP 1: Ask the user for a question ===

question = input("🧠 What would you like to ask?: ")

# === STEP 2: Send prompt to DeepSeek AI ===

prompt = f"Convert this into a MySQL query only. No explanation, no markdown, no code block, just SQL: \"{question}\""

response = requests.post(DEESEEK_API_URL, json={
    "model": "deepseek-r1:7b",
    "prompt": prompt,
    "stream": False
})

if response.status_code != 200:
    print("❌ Error from DeepSeek:", response.text)
    exit()

# === STEP 3: Extract the SQL query only ===

raw = response.json().get("response", "").strip()

# Extract first valid SELECT line
lines = raw.splitlines()
sql = ""
for line in lines:
    line = line.strip()
    if line.lower().startswith("select"):
        sql = line
        if not sql.endswith(";"):
            sql += ";"
        break

if not sql:
    print("❌ Could not extract valid SQL.")
    print("AI response was:", raw)
    exit()

print("\n🧾 SQL generated by AI:")
print(sql)

# === STEP 4: Run SQL on your local MySQL database ===

try:
    conn = mysql.connector.connect(**DB_CONFIG)
    cursor = conn.cursor()
    cursor.execute(sql)
    result = cursor.fetchone()

    print("\n✅ Result from database:")
    print(result[0] if result else "No result returned.")

except mysql.connector.Error as err:
    print("❌ SQL error:", err)

finally:
    if 'cursor' in locals(): cursor.close()
    if 'conn' in locals(): conn.close()
