import os
import requests
import sqlite3
from flask import Flask, render_template, jsonify, request, session, redirect, url_for
from functools import wraps

app = Flask(__name__)
app.secret_key = "SECRET_KEY_2025"

# Inisialisasi Database SQLite
def init_db():
    conn = sqlite3.connect('chat_history.db')
    c = conn.cursor()
    c.execute('''CREATE TABLE IF NOT EXISTS chat_logs 
                 (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id TEXT, role TEXT, content TEXT)''')
    conn.commit()
    conn.close()

init_db()

# Login Guard
USERS = {"admin": "admin123"}

def login_required(f):
    @wraps(f)
    def decorated_function(*args, **kwargs):
        if "user" not in session: return redirect(url_for("login"))
        return f(*args, **kwargs)
    return decorated_function

@app.route('/login', methods=['GET', 'POST'])
def login():
    if request.method == 'POST':
        u, p = request.form.get('u'), request.form.get('p')
        if USERS.get(u) == p:
            session['user'] = u
            return redirect(url_for('index'))
    return '''<body style="background:#0d1117;color:white;display:flex;justify-content:center;align-items:center;height:100vh;font-family:sans-serif;">
              <form method="post" style="background:#161b22;padding:30px;border-radius:8px;border:1px solid #30363d;">
              <h2>🔒 AI Secure Login</h2>
              <input type="text" name="u" placeholder="Username" required style="width:100%;padding:10px;margin-bottom:10px;"><br>
              <input type="password" name="p" placeholder="Password" required style="width:100%;padding:10px;margin-bottom:10px;"><br>
              <button type="submit" style="width:100%;padding:10px;background:#238636;color:white;border:none;cursor:pointer;">Masuk</button>
              </form></body>'''

@app.route('/')
@login_required
def index():
    return render_template('index.html')

@app.route('/chat', methods=['POST'])
@login_required
def chat():
    user_id = "1234"
    data = request.json
    messages = data.get('messages', [])
    
    # 1. Simpan pesan User ke DB
    last_user_msg = messages[-1]['content']
    conn = sqlite3.connect('chat_history.db')
    c = conn.cursor()
    c.execute("INSERT INTO chat_logs (user_id, role, content) VALUES (?, ?, ?)", (user_id, 'user', last_user_msg))
    
    # 2. Kirim ke API Eksternal (host.optikl.ink)
    try:
        api_url = "https://host.optikl.ink/ai/gpt4"
        payload = {"messages": messages, "user_id": user_id}
        response = requests.post(api_url, json=payload, timeout=60)
        ai_res = response.json()
        
        reply = ai_res.get('answer') or ai_res.get('message') or "No response"
        
        # 3. Simpan jawaban AI ke DB
        c.execute("INSERT INTO chat_logs (user_id, role, content) VALUES (?, ?, ?)", (user_id, 'assistant', reply))
        conn.commit()
        return jsonify({"reply": reply})
    except Exception as e:
        return jsonify({"reply": f"Error: {str(e)}"}), 500
    finally:
        conn.close()

@app.route('/logout')
def logout():
    session.pop('user', None)
    return redirect(url_for('login'))

if __name__ == '__main__':
    app.run(debug=True)
