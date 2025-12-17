import os
from flask import Flask, render_template, jsonify, request, session, redirect, url_for
from functools import wraps
import requests
from openai import OpenAI

app = Flask(__name__)
app.secret_key = "KUNCI_RAHASIA_ANDA_2025" # Ganti untuk keamanan sesi

# Konfigurasi OpenAI API
# Untuk deploy, gunakan: client = OpenAI(api_key=os.environ.get("OPENAI_API_KEY"))
client = OpenAI(api_key="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")

# Database User Sederhana
USERS = {"admin": "password123"}
last_data_context = {}

def login_required(f):
    @wraps(f)
    def decorated_function(*args, **kwargs):
        if "user" not in session:
            return redirect(url_for("login"))
        return f(*args, **kwargs)
    return decorated_function

@app.route('/login', methods=['GET', 'POST'])
def login():
    if request.method == 'POST':
        u, p = request.form.get('username'), request.form.get('password')
        if USERS.get(u) == p:
            session['user'] = u
            return redirect(url_for('home'))
        return "Login Gagal!"
    return '''
        <body style="background:#0f172a; color:white; font-family:sans-serif; display:flex; justify-content:center; align-items:center; height:100vh;">
            <form method="post" style="background:#1e293b; padding:40px; border-radius:15px; text-align:center;">
                <h2>🛡️ AI Admin Login</h2>
                <input type="text" name="username" placeholder="Username" style="padding:10px; margin:10px;"><br>
                <input type="password" name="password" placeholder="Password" style="padding:10px; margin:10px;"><br>
                <button type="submit" style="padding:10px 20px; background:#8b5cf6; color:white; border:none; border-radius:5px; cursor:pointer;">Masuk</button>
            </form>
        </body>
    '''

@app.route('/')
@login_required
def home():
    return render_template('index.html')

@app.route('/process-ai')
@login_required
def process_ai():
    global last_data_context
    lang = request.args.get('lang', 'Indonesia')
    try:
        res = requests.get("https://host.optikl.ink/data/data", timeout=10)
        last_data_context = res.json()
        
        response = client.chat.completions.create(
            model="gpt-4o",
            messages=[
                {"role": "system", "content": f"Analis data profesional. Tulis laporan mendalam dalam Bahasa {lang}."},
                {"role": "user", "content": f"Data: {str(last_data_context)}"}
            ]
        )
        return jsonify({"status": "success", "ai_insight": response.choices[0].message.content, "raw_data": last_data_context})
    except Exception as e:
        return jsonify({"status": "error", "message": str(e)})

@app.route('/chat', methods=['POST'])
@login_required
def chat():
    user_msg = request.json.get('message')
    try:
        response = client.chat.completions.create(
            model="gpt-4o",
            messages=[
                {"role": "system", "content": f"Anda adalah AI Peneliti. Gunakan konteks ini: {str(last_data_context)}. Cari sumber jika perlu dan jawab dengan tepat."},
                {"role": "user", "content": user_msg}
            ]
        )
        return jsonify({"reply": response.choices[0].message.content})
    except:
        return jsonify({"reply": "AI gagal merespon."})

@app.route('/logout')
def logout():
    session.pop('user', None)
    return redirect(url_for('login'))

if __name__ == '__main__':
    app.run(debug=True)

