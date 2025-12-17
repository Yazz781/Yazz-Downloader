from flask import Flask, render_template, jsonify, request
import requests
from openai import OpenAI

app = Flask(__name__)

# API Key Anda yang sudah terpasang
client = OpenAI(api_key="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")

@app.route('/')
def home():
    return render_template('index.html')

@app.route('/process-ai')
def process_ai():
    target_lang = request.args.get('lang', 'Auto-Detect')
    
    try:
        # 1. Ambil data dari API
        res = requests.get("https://host.optikl.ink/data/data", timeout=10)
        res.raise_for_status()
        data_json = res.json()

        # 2. Instruksi Prompt GPT-4o
        lang_instruction = f"dalam Bahasa {target_lang}" if target_lang != 'Auto-Detect' else "dalam bahasa yang paling sesuai dengan konteks data atau Bahasa Indonesia"
        
        prompt = f"""Anda adalah analis data senior. Analisis data JSON berikut {lang_instruction}. 
        Berikan poin-poin penting: 1. Ringkasan Eksekutif, 2. Analisis Tren, 3. Rekomendasi.
        Gunakan format yang rapi."""

        response = client.chat.completions.create(
            model="gpt-4o",
            messages=[
                {"role": "system", "content": "Anda adalah asisten AI profesional yang ahli dalam analisis data multilingual."},
                {"role": "user", "content": f"{prompt}\n\nData: {str(data_json)}"}
            ]
        )
        
        ai_insight = response.choices[0].message.content

        return jsonify({
            "status": "success",
            "ai_insight": ai_insight,
            "raw_data": data_json
        })
    except Exception as e:
        return jsonify({"status": "error", "message": str(e)}), 500

if __name__ == '__main__':
    app.run(debug=True)
      
