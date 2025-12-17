<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>AI Intelligent Master Dashboard</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        :root { --bg: #0d1117; --card: #161b22; --primary: #58a6ff; --text: #c9d1d9; --green: #238636; }
        body { background: var(--bg); color: var(--text); font-family: 'Segoe UI', sans-serif; margin: 0; display: flex; flex-direction: column; height: 100vh; }
        header { padding: 15px 25px; background: #161b22; border-bottom: 1px solid #30363d; display: flex; justify-content: space-between; align-items: center; }
        #chat-container { flex: 1; overflow-y: auto; padding: 25px; display: flex; flex-direction: column; gap: 20px; }
        .msg { max-width: 80%; padding: 15px 20px; border-radius: 12px; line-height: 1.6; position: relative; white-space: pre-wrap; }
        .user { align-self: flex-end; background: #1f6feb; color: white; border-bottom-right-radius: 2px; }
        .ai { align-self: flex-start; background: #30363d; border: 1px solid #484f58; border-bottom-left-radius: 2px; }
        .btn-group { margin-top: 10px; display: flex; gap: 10px; }
        .action-btn { font-size: 11px; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; color: white; }
        .input-area { background: #161b22; padding: 25px; border-top: 1px solid #30363d; display: flex; gap: 15px; }
        input { flex: 1; padding: 15px; background: #0d1117; border: 1px solid #30363d; color: white; border-radius: 8px; font-size: 16px; }
        button#sendBtn { padding: 0 30px; background: var(--green); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; }
        #typing { font-size: 12px; color: #8b949e; padding: 10px 25px; display: none; }
    </style>
</head>
<body>

<header>
    <div style="font-size: 20px; font-weight: bold;">🚀 AI COMMANDER <span style="font-weight: normal; color: #8b949e;">v3.0</span></div>
    <div>
        <button onclick="downloadFullPDF()" style="background:#58a6ff; color:black; border:none; padding:8px 15px; border-radius:5px; font-weight:bold; cursor:pointer; margin-right:10px;">PDF REPORT</button>
        <a href="/logout" style="color:#f85149; text-decoration:none; font-size:14px;">Logout</a>
    </div>
</header>

<div id="chat-container"></div>
<div id="typing">AI sedang menyusun respon 1000 kata...</div>

<div class="input-area">
    <input type="text" id="userInput" placeholder="Tulis instruksi atau tanya data JSON..." autocomplete="off">
    <button id="sendBtn" onclick="sendMessage()">KIRIM</button>
</div>

<script>
    // Memori Jangka Panjang (Sliding Window 200)
    let history = JSON.parse(localStorage.getItem('persistent_history')) || [
        { "role": "system", "content": "Good Person. Berikan respon mendalam, teknis, dan lengkap hingga 1000 kata jika diperlukan." }
    ];

    window.onload = () => {
        history.forEach(m => { if(m.role !== 'system') renderMsg(m.content, m.role); });
    };

    async function sendMessage() {
        const input = document.getElementById('userInput');
        const text = input.value.trim();
        if(!text) return;

        renderMsg(text, 'user');
        history.push({ "role": "user", "content": text });
        input.value = '';

        // Jaga limit 200 pesan
        if(history.length > 201) history.splice(1, 1);

        document.getElementById('typing').style.display = 'block';

        try {
            const res = await fetch('/chat', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ messages: history })
            });
            const data = await res.json();
            
            renderMsg(data.reply, 'assistant');
            history.push({ "role": "assistant", "content": data.reply });
            localStorage.setItem('persistent_history', JSON.stringify(history));
        } catch (e) {
            renderMsg("Error: Gagal memproses data besar.", 'assistant');
        } finally {
            document.getElementById('typing').style.display = 'none';
        }
    }

    function renderMsg(text, role) {
        const container = document.getElementById('chat-container');
        const div = document.createElement('div');
        div.className = `msg ${role === 'user' ? 'user' : 'ai'}`;
        div.innerText = text;

        if(role === 'assistant') {
            const btnGroup = document.createElement('div');
            btnGroup.className = "btn-group";
            
            const btnJSON = document.createElement('button');
            btnJSON.innerText = "📥 Download JSON";
            btnJSON.className = "action-btn";
            btnJSON.style.background = "#238636";
            btnJSON.onclick = () => {
                const blob = new Blob([text], { type: 'application/json' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `ai_data_${Date.now()}.json`;
                a.click();
            };
            
            btnGroup.appendChild(btnJSON);
            div.appendChild(btnGroup);
        }

        container.appendChild(div);
        container.scrollTop = container.scrollHeight;
    }

    function downloadFullPDF() {
        const element = document.getElementById('chat-container');
        html2pdf().from(element).set({
            margin: 10,
            filename: 'AI-Full-Report.pdf',
            html2canvas: { scale: 2, backgroundColor: '#0d1117' },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
        }).save();
    }

    document.getElementById('userInput').addEventListener('keypress', (e) => { if(e.key === 'Enter') sendMessage(); });
</script>
</body>
</html>
