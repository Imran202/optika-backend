<!DOCTYPE html>
<html lang="bs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bonus Sistem - Admin Panel</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #FF6B6B, #FF8E53);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .header p {
            font-size: 1.1em;
            opacity: 0.9;
        }
        
        .content {
            padding: 40px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 1.1em;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 1em;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #FF6B6B;
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .toggle-group {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .toggle {
            position: relative;
            width: 60px;
            height: 30px;
            background: #ccc;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .toggle.active {
            background: #4CAF50;
        }
        
        .toggle::after {
            content: '';
            position: absolute;
            top: 3px;
            left: 3px;
            width: 24px;
            height: 24px;
            background: white;
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        
        .toggle.active::after {
            transform: translateX(30px);
        }
        
        .btn {
            background: linear-gradient(135deg, #FF6B6B, #FF8E53);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 10px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(255, 107, 107, 0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #495057);
        }
        
        .btn-secondary:hover {
            box-shadow: 0 10px 20px rgba(108, 117, 125, 0.3);
        }
        
        .status {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .info-box {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .info-box h3 {
            color: #495057;
            margin-bottom: 10px;
        }
        
        .info-box p {
            color: #6c757d;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎁 Bonus Sistem</h1>
            <p>Upravljanje bonus KM za nove korisnike</p>
        </div>
        
        <div class="content">
            <div id="status"></div>
            
            <div class="info-box">
                <h3>ℹ️ Kako funkcioniše bonus sistem?</h3>
                <p>Kada se korisnik prvi put registruje u aplikaciju, automatski dobija bonus KM. Možete podesiti iznos bonus KM, naslov i poruku notifikacije.</p>
            </div>
            
            <form id="bonusForm">
                <div class="form-group">
                    <div class="toggle-group">
                        <label>Bonus sistem:</label>
                        <div class="toggle" id="enabledToggle">
                            <input type="checkbox" id="enabled" name="enabled" style="display: none;">
                        </div>
                        <span id="enabledStatus">Uključen</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="bonus_points">Bonus KM:</label>
                    <input type="number" id="bonus_points" name="bonus_points" min="0" placeholder="200">
                </div>
                
                <div class="form-group">
                    <label for="bonus_title">Naslov notifikacije:</label>
                    <input type="text" id="bonus_title" name="bonus_title" placeholder="Dobrodošli!">
                </div>
                
                <div class="form-group">
                    <label for="bonus_message">Poruka notifikacije:</label>
                    <textarea id="bonus_message" name="bonus_message" placeholder="Dobili ste 20 KM kao dobrodošlicu!"></textarea>
                </div>
                
                <button type="submit" class="btn">💾 Sačuvaj konfiguraciju</button>
                <button type="button" class="btn btn-secondary" onclick="toggleBonus()">🔄 Uključi/Isključi</button>
            </form>
        </div>
    </div>

    <script>
        let currentConfig = {};
        
        // Load current configuration
        async function loadConfig() {
            try {
                const response = await fetch('/api/bonus/config');
                const config = await response.json();
                currentConfig = config;
                
                document.getElementById('enabled').checked = config.enabled;
                document.getElementById('bonus_points').value = config.bonus_points;
                document.getElementById('bonus_title').value = config.bonus_title;
                document.getElementById('bonus_message').value = config.bonus_message;
                
                updateToggle();
                updateStatus();
            } catch (error) {
                showStatus('Greška pri učitavanju konfiguracije', 'error');
            }
        }
        
        // Update toggle visual state
        function updateToggle() {
            const toggle = document.getElementById('enabledToggle');
            const status = document.getElementById('enabledStatus');
            const checkbox = document.getElementById('enabled');
            
            if (checkbox.checked) {
                toggle.classList.add('active');
                status.textContent = 'Uključen';
            } else {
                toggle.classList.remove('active');
                status.textContent = 'Isključen';
            }
        }
        
        // Update status text
        function updateStatus() {
            const status = document.getElementById('enabledStatus');
            status.textContent = currentConfig.enabled ? 'Uključen' : 'Isključen';
        }
        
        // Toggle bonus system
        async function toggleBonus() {
            try {
                const response = await fetch('/api/admin/bonus/toggle', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                });
                
                const result = await response.json();
                
                if (result.success) {
                    currentConfig.enabled = result.enabled;
                    document.getElementById('enabled').checked = result.enabled;
                    updateToggle();
                    showStatus(result.message, 'success');
                } else {
                    showStatus('Greška pri prebacivanju', 'error');
                }
            } catch (error) {
                showStatus('Greška pri prebacivanju', 'error');
            }
        }
        
        // Save configuration
        document.getElementById('bonusForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const data = {
                enabled: formData.get('enabled') === 'on',
                bonus_points: parseInt(formData.get('bonus_points')),
                bonus_title: formData.get('bonus_title'),
                bonus_message: formData.get('bonus_message')
            };
            
            try {
                const response = await fetch('/api/admin/bonus/config', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showStatus('Konfiguracija je uspješno sačuvana!', 'success');
                    currentConfig = result.config;
                } else {
                    showStatus('Greška pri čuvanju konfiguracije', 'error');
                }
            } catch (error) {
                showStatus('Greška pri čuvanju konfiguracije', 'error');
            }
        });
        
        // Toggle click handler
        document.getElementById('enabledToggle').addEventListener('click', () => {
            const checkbox = document.getElementById('enabled');
            checkbox.checked = !checkbox.checked;
            updateToggle();
        });
        
        // Show status message
        function showStatus(message, type) {
            const statusDiv = document.getElementById('status');
            statusDiv.innerHTML = `<div class="status ${type}">${message}</div>`;
            
            setTimeout(() => {
                statusDiv.innerHTML = '';
            }, 5000);
        }
        
        // Load configuration on page load
        loadConfig();
    </script>
</body>
</html>
