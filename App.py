import os
import requests
import sqlite3
from flask import Flask, render_template, jsonify, request, session, redirect, url_for
from functools import wraps

app = Flask(__name__)
app.secret_key = "KUNCI_RAHASIA_KOMPLEKS_2025" # Ganti string ini untuk keamanan sesi

# 1. Inisialisasi Database SQLite (Arsip Jangka Panjang)
def init_db():
    conn = sqlite3.connect('chat_history.db')
    c = conn.cursor()
    c.execute('''CREATE TABLE IF NOT EXISTS messages 
                 (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id TEXT, role TEXT, content TEXT)''')
    conn.commit()
    conn.close()

init_db()

# 2. Sistem Keamanan Login (Username: admin, Password: password123)
USERS = {"admin": "password123"}

def login_required(f):
    @wraps(f)
    def decorated_function(*args, **kwargs):
        if "user" not in session: return redirect(url_for("login"))
        return f(*args, **kwargs)
    return decorated_function

@app.route('/login', methods=['GET', 'POST'])
def login():
    if request.method == 'POST':
        u, p = request.form.get('username'), request.form.get('password')
        if USERS.get(u) == p:
            session['user'] = u
            return redirect(url_for('home'))
        return "Login Gagal! Username atau Password salah."
    return '''<body style="background:#0b0e14;color:white;display:flex;justify-content:center;align-items:center;height:100vh;font-family:sans-serif;">
              <form method="post" style="background:#161b22;padding:30px;border-radius:10px;border:1px solid #30363d;width:300px;">
              <h2 style="text-align:center;">🔐 AI Admin Dashboard</h2>
              <input type="text" name="username" placeholder="Username" required style="width:100%;padding:10px;margin:10px 0;background:#0d1117;color:white;border:1px solid #30363d;">
              <input type="password" name="password" placeholder="Password" required style="width:100%;padding:10px;margin:10px 0;background:#0d1117;color:white;border:1px solid #30363d;">
              <button type="submit" style="width:100%;padding:12px;background:#238636;color:white;border:none;border-radius:5px;cursor:pointer;font-weight:bold;">LOGIN</button>
              </form></body>'''

@app.route('/')
@login_required
def home():
    return render_template('index.html')

# 3. Endpoint Chat Utama (Proxy ke host.optikl.ink)
@app.route('/chat', methods=['POST'])
@login_required
def chat():
    user_msg = request.json.get('message')
    full_context = request.json.get('messages') # Menerima 200 pesan dari frontend
    user_id = "1234"

    api_url = "https://host.optikl.ink/ai/gpt4"
    payload = {
        "messages": full_context,
        "user_id": user_id
    }
    
    try:
        # Panggil API AI Eksternal
        response = requests.post(api_url, json=payload, timeout=30)
        data = response.json()
        ai_reply = data.get('answer') or data.get('message') or "No response"
        
        # Simpan History ke Database lokal
        conn = sqlite3.connect('chat_history.db')
        c = conn.cursor()
        c.execute("INSERT INTO messages (user_id, role, content) VALUES (?, 'user', ?)", (user_id, user_msg))
        c.execute("INSERT INTO messages (user_id, role, content) VALUES (?, 'assistant', ?)", (user_id, ai_reply))
        conn.commit()
        conn.close()
        
        return jsonify({"reply": ai_reply})
    except Exception as e:
        return jsonify({"reply": f"Terjadi kesalahan koneksi: {str(e)}"})

@app.route('/logout')
def logout():
    session.pop('user', None)
    return redirect(url_for('login'))

if __name__ == '__main__':
    app.run(debug=True, port=5000)
