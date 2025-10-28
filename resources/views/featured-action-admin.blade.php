<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Featured Action Admin - Optika.ba</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
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
            background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 16px;
        }
        
        .content {
            padding: 40px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #E5E7EB;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none;
            border-color: #DC2626;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .toggle-section {
            background: #F9FAFB;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border: 2px solid #E5E7EB;
        }
        
        .toggle-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .toggle-switch {
            position: relative;
            width: 60px;
            height: 30px;
            background: #E5E7EB;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .toggle-switch.active {
            background: #DC2626;
        }
        
        .toggle-slider {
            position: absolute;
            top: 3px;
            left: 3px;
            width: 24px;
            height: 24px;
            background: white;
            border-radius: 50%;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .toggle-switch.active .toggle-slider {
            transform: translateX(30px);
        }
        
        .btn {
            background: linear-gradient(135deg, #DC2626 0%, #B91C1C 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(220, 38, 38, 0.3);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: #6B7280;
            margin-left: 10px;
        }
        
        .status {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .status.success {
            background: #D1FAE5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }
        
        .status.error {
            background: #FEE2E2;
            color: #991B1B;
            border: 1px solid #FECACA;
        }
        
        .preview {
            background: #F9FAFB;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            border: 2px solid #E5E7EB;
        }
        
        .preview h3 {
            color: #374151;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .preview-content {
            background: white;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #E5E7EB;
        }
        
        .color-preview {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .color-box {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: 2px solid #E5E7EB;
        }
        
        .color-presets {
            background: #F9FAFB;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid #E5E7EB;
        }
        
        .preset-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .preset-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .preset-item:hover {
            background: #E5E7EB;
            transform: translateY(-2px);
        }
        
        .preset-preview {
            width: 60px;
            height: 40px;
            border-radius: 6px;
            margin-bottom: 8px;
            border: 2px solid #E5E7EB;
        }
        
        .preset-item span {
            font-size: 12px;
            color: #374151;
            font-weight: 500;
        }
        
        .color-section {
            background: #F9FAFB;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid #E5E7EB;
        }
        
        .color-input-container {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .color-input-container input[type="color"] {
            width: 50px;
            height: 40px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        
        .color-input-container input[type="text"] {
            flex: 1;
            padding: 8px 12px;
            border: 2px solid #E5E7EB;
            border-radius: 6px;
            font-family: monospace;
            font-size: 14px;
        }
        
        .color-input-container input[type="text"]:focus {
            outline: none;
            border-color: #DC2626;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎯 Featured Action Admin</h1>
            <p>Upravljajte istaknutom akcijom iz backend-a</p>
        </div>
        
        <div class="content">
            <div id="status"></div>
            
            <!-- Toggle Section -->
            <div class="toggle-section">
                <div class="toggle-container">
                    <div>
                        <h3 style="margin: 0; color: #374151;">Uključi/Isključi Akciju</h3>
                        <p style="margin: 5px 0 0 0; color: #6B7280; font-size: 14px;">Kontrolirajte da li se akcija prikazuje korisnicima</p>
                    </div>
                    <div class="toggle-switch" id="toggleSwitch" onclick="toggleAction()">
                        <div class="toggle-slider"></div>
                    </div>
                </div>
            </div>
            
            <!-- Configuration Form -->
            <form id="configForm">
                <h3 style="color: #374151; margin-bottom: 20px; font-size: 18px;">Konfiguracija Sadržaja</h3>
                
                <div class="form-group">
                    <label for="title">Naslov Akcije</label>
                    <input type="text" id="title" name="content[title]" placeholder="npr. Istaknuta akcija">
                </div>
                
                <div class="form-group">
                    <label for="subtitle">Podnaslov</label>
                    <textarea id="subtitle" name="content[subtitle]" placeholder="Opis akcije koji će se prikazati korisnicima"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="badge_text">Tekst Badge-a</label>
                    <input type="text" id="badge_text" name="content[badge_text]" placeholder="npr. NOVO, AKCIJA, POPUST">
                </div>
                
                <h3 style="color: #374151; margin: 30px 0 20px 0; font-size: 18px;">Detalji Akcije</h3>
                
                <div class="form-group">
                    <label for="brand_name">Naziv Brenda</label>
                    <input type="text" id="brand_name" name="action[brand_name]" placeholder="npr. Polaroid brend">
                </div>
                
                <div class="form-group">
                    <label for="description">Opis Ponude</label>
                    <input type="text" id="description" name="action[description]" placeholder="npr. 15% popusta + 5% na karticu">
                </div>
                
                <div class="form-group">
                    <label for="meta_text">Meta Tekst</label>
                    <input type="text" id="meta_text" name="action[meta_text]" placeholder="npr. Ograničeno vrijeme">
                </div>
                
                <div class="form-group">
                    <label for="logo_path">Logo Fajl</label>
                    <input type="text" id="logo_path" name="action[logo_path]" placeholder="npr. polaroid-logo.png">
                </div>
                
                <h3 style="color: #374151; margin: 30px 0 20px 0; font-size: 18px;">🎨 Dizajn i Boje</h3>
                
                <!-- Color Presets -->
                <div class="color-presets">
                    <h4 style="color: #6B7280; margin-bottom: 15px; font-size: 14px;">Preddefinisane kombinacije:</h4>
                    <div class="preset-grid">
                        <div class="preset-item" onclick="applyPreset('classic')">
                            <div class="preset-preview" style="background: linear-gradient(135deg, #667eea, #764ba2);"></div>
                            <span>Klasična</span>
                        </div>
                        <div class="preset-item" onclick="applyPreset('sunset')">
                            <div class="preset-preview" style="background: linear-gradient(135deg, #ff6b6b, #ee5a24);"></div>
                            <span>Zalazak</span>
                        </div>
                        <div class="preset-item" onclick="applyPreset('ocean')">
                            <div class="preset-preview" style="background: linear-gradient(135deg, #4facfe, #00f2fe);"></div>
                            <span>Ocean</span>
                        </div>
                        <div class="preset-item" onclick="applyPreset('forest')">
                            <div class="preset-preview" style="background: linear-gradient(135deg, #56ab2f, #a8e6cf);"></div>
                            <span>Šuma</span>
                        </div>
                        <div class="preset-item" onclick="applyPreset('purple')">
                            <div class="preset-preview" style="background: linear-gradient(135deg, #667eea, #764ba2);"></div>
                            <span>Ljubičasta</span>
                        </div>
                        <div class="preset-item" onclick="applyPreset('fire')">
                            <div class="preset-preview" style="background: linear-gradient(135deg, #ff416c, #ff4b2b);"></div>
                            <span>Vatra</span>
                        </div>
                    </div>
                </div>
                
                <!-- Main Gradient -->
                <div class="color-section">
                    <h4 style="color: #374151; margin: 20px 0 15px 0; font-size: 16px;">Glavni Gradient Pozadine</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="gradient_start">Početna Boja</label>
                            <div class="color-input-container">
                                <input type="color" id="gradient_start" name="design[gradient_start]" value="#667eea">
                                <input type="text" id="gradient_start_text" placeholder="#667eea" maxlength="7">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="gradient_end">Završna Boja</label>
                            <div class="color-input-container">
                                <input type="color" id="gradient_end" name="design[gradient_end]" value="#764ba2">
                                <input type="text" id="gradient_end_text" placeholder="#764ba2" maxlength="7">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Badge Gradient -->
                <div class="color-section">
                    <h4 style="color: #374151; margin: 20px 0 15px 0; font-size: 16px;">Badge Gradient (Logo sekcija)</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="badge_gradient_start">Početna Boja</label>
                            <div class="color-input-container">
                                <input type="color" id="badge_gradient_start" name="design[badge_gradient_start]" value="#4facfe">
                                <input type="text" id="badge_gradient_start_text" placeholder="#4facfe" maxlength="7">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="badge_gradient_end">Završna Boja</label>
                            <div class="color-input-container">
                                <input type="color" id="badge_gradient_end" name="design[badge_gradient_end]" value="#00f2fe">
                                <input type="text" id="badge_gradient_end_text" placeholder="#00f2fe" maxlength="7">
                            </div>
                        </div>
                    </div>
                </div>
                
                <h3 style="color: #374151; margin: 30px 0 20px 0; font-size: 18px;">Vremenski Period</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="start_date">Datum Početka</label>
                        <input type="datetime-local" id="start_date" name="timing[start_date]">
                    </div>
                    
                    <div class="form-group">
                        <label for="end_date">Datum Završetka</label>
                        <input type="datetime-local" id="end_date" name="timing[end_date]">
                    </div>
                </div>
                
                <div style="margin-top: 30px;">
                    <button type="submit" class="btn" id="saveBtn">💾 Sačuvaj Konfiguraciju</button>
                    <button type="button" class="btn btn-secondary" onclick="loadConfig()">🔄 Učitaj Trenutnu</button>
                </div>
            </form>
            
            <!-- Preview Section -->
            <div class="preview">
                <h3>Pregled Boja</h3>
                <div class="color-preview">
                    <div>
                        <div class="color-box" id="gradientPreview" style="background: linear-gradient(135deg, #667eea, #764ba2);"></div>
                        <small>Glavni Gradient</small>
                    </div>
                    <div>
                        <div class="color-box" id="badgePreview" style="background: linear-gradient(135deg, #4facfe, #00f2fe);"></div>
                        <small>Badge Gradient</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const API_BASE = window.location.origin + '/api';
        let authToken = null;
        
        // Load configuration on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadConfig();
            setupEventListeners();
        });
        
        function setupEventListeners() {
            // Update preview when colors change
            document.getElementById('gradient_start').addEventListener('input', updateGradientPreview);
            document.getElementById('gradient_end').addEventListener('input', updateGradientPreview);
            document.getElementById('badge_gradient_start').addEventListener('input', updateBadgePreview);
            document.getElementById('badge_gradient_end').addEventListener('input', updateBadgePreview);
            
            // Sync color picker with text input
            document.getElementById('gradient_start').addEventListener('input', function() {
                document.getElementById('gradient_start_text').value = this.value;
            });
            document.getElementById('gradient_end').addEventListener('input', function() {
                document.getElementById('gradient_end_text').value = this.value;
            });
            document.getElementById('badge_gradient_start').addEventListener('input', function() {
                document.getElementById('badge_gradient_start_text').value = this.value;
            });
            document.getElementById('badge_gradient_end').addEventListener('input', function() {
                document.getElementById('badge_gradient_end_text').value = this.value;
            });
            
            // Sync text input with color picker
            document.getElementById('gradient_start_text').addEventListener('input', function() {
                if (isValidHex(this.value)) {
                    document.getElementById('gradient_start').value = this.value;
                    updateGradientPreview();
                }
            });
            document.getElementById('gradient_end_text').addEventListener('input', function() {
                if (isValidHex(this.value)) {
                    document.getElementById('gradient_end').value = this.value;
                    updateGradientPreview();
                }
            });
            document.getElementById('badge_gradient_start_text').addEventListener('input', function() {
                if (isValidHex(this.value)) {
                    document.getElementById('badge_gradient_start').value = this.value;
                    updateBadgePreview();
                }
            });
            document.getElementById('badge_gradient_end_text').addEventListener('input', function() {
                if (isValidHex(this.value)) {
                    document.getElementById('badge_gradient_end').value = this.value;
                    updateBadgePreview();
                }
            });
            
            // Form submission
            document.getElementById('configForm').addEventListener('submit', saveConfig);
        }
        
        function updateGradientPreview() {
            const start = document.getElementById('gradient_start').value;
            const end = document.getElementById('gradient_end').value;
            const preview = document.getElementById('gradientPreview');
            preview.style.background = `linear-gradient(135deg, ${start}, ${end})`;
        }
        
        function updateBadgePreview() {
            const start = document.getElementById('badge_gradient_start').value;
            const end = document.getElementById('badge_gradient_end').value;
            const preview = document.getElementById('badgePreview');
            preview.style.background = `linear-gradient(135deg, ${start}, ${end})`;
        }
        
        async function loadConfig() {
            try {
                const response = await fetch(`${API_BASE}/featured-action/config`);
                const data = await response.json();
                
                if (data.enabled !== undefined) {
                    updateToggle(data.enabled);
                }
                
                if (data.content) {
                    document.getElementById('title').value = data.content.title || '';
                    document.getElementById('subtitle').value = data.content.subtitle || '';
                    document.getElementById('badge_text').value = data.content.badge_text || '';
                }
                
                if (data.action) {
                    document.getElementById('brand_name').value = data.action.brand_name || '';
                    document.getElementById('description').value = data.action.description || '';
                    document.getElementById('meta_text').value = data.action.meta_text || '';
                    document.getElementById('logo_path').value = data.action.logo_path || '';
                }
                
                if (data.design) {
                    document.getElementById('gradient_start').value = data.design.gradient_start || '#667eea';
                    document.getElementById('gradient_end').value = data.design.gradient_end || '#764ba2';
                    document.getElementById('badge_gradient_start').value = data.design.badge_gradient_start || '#4facfe';
                    document.getElementById('badge_gradient_end').value = data.design.badge_gradient_end || '#00f2fe';
                    
                    // Update text inputs
                    document.getElementById('gradient_start_text').value = data.design.gradient_start || '#667eea';
                    document.getElementById('gradient_end_text').value = data.design.gradient_end || '#764ba2';
                    document.getElementById('badge_gradient_start_text').value = data.design.badge_gradient_start || '#4facfe';
                    document.getElementById('badge_gradient_end_text').value = data.design.badge_gradient_end || '#00f2fe';
                    
                    updateGradientPreview();
                    updateBadgePreview();
                }
                
                if (data.timing) {
                    document.getElementById('start_date').value = data.timing.start_date || '';
                    document.getElementById('end_date').value = data.timing.end_date || '';
                }
                
                showStatus('Konfiguracija učitana uspješno!', 'success');
                
            } catch (error) {
                console.error('Error loading config:', error);
                showStatus('Greška pri učitavanju konfiguracije: ' + error.message, 'error');
            }
        }
        
        async function saveConfig(e) {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const config = {};
            
            // Convert form data to nested object
            for (let [key, value] of formData.entries()) {
                const keys = key.split('[').map(k => k.replace(']', ''));
                if (keys.length === 2) {
                    if (!config[keys[0]]) config[keys[0]] = {};
                    config[keys[0]][keys[1]] = value;
                } else {
                    config[key] = value;
                }
            }
            
            try {
                const response = await fetch(`${API_BASE}/admin/featured-action/config`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${authToken}`
                    },
                    body: JSON.stringify(config)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showStatus('✅ Konfiguracija sačuvana uspješno! Aplikacija će se ažurirati za nekoliko sekundi.', 'success');
                } else {
                    showStatus('❌ Greška pri čuvanju: ' + (data.message || 'Nepoznata greška'), 'error');
                }
                
            } catch (error) {
                console.error('Error saving config:', error);
                showStatus('Greška pri čuvanju konfiguracije: ' + error.message, 'error');
            }
        }
        
        async function toggleAction() {
            const toggle = document.getElementById('toggleSwitch');
            const isActive = toggle.classList.contains('active');
            const newState = !isActive;
            
            try {
                const response = await fetch(`${API_BASE}/admin/featured-action/toggle`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${authToken}`
                    },
                    body: JSON.stringify({ enabled: newState })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    updateToggle(newState);
                    showStatus('✅ ' + data.message + ' Aplikacija će se ažurirati za nekoliko sekundi.', 'success');
                } else {
                    showStatus('❌ Greška pri prebacivanju: ' + (data.message || 'Nepoznata greška'), 'error');
                }
                
            } catch (error) {
                console.error('Error toggling action:', error);
                showStatus('Greška pri prebacivanju: ' + error.message, 'error');
            }
        }
        
        function updateToggle(isActive) {
            const toggle = document.getElementById('toggleSwitch');
            if (isActive) {
                toggle.classList.add('active');
            } else {
                toggle.classList.remove('active');
            }
        }
        
        function showStatus(message, type) {
            const statusDiv = document.getElementById('status');
            statusDiv.innerHTML = `<div class="status ${type}">${message}</div>`;
            
            setTimeout(() => {
                statusDiv.innerHTML = '';
            }, 5000);
        }
        
        // Color preset definitions
        const colorPresets = {
            classic: {
                gradient_start: '#667eea',
                gradient_end: '#764ba2',
                badge_gradient_start: '#4facfe',
                badge_gradient_end: '#00f2fe'
            },
            sunset: {
                gradient_start: '#ff6b6b',
                gradient_end: '#ee5a24',
                badge_gradient_start: '#ff9ff3',
                badge_gradient_end: '#f368e0'
            },
            ocean: {
                gradient_start: '#4facfe',
                gradient_end: '#00f2fe',
                badge_gradient_start: '#667eea',
                badge_gradient_end: '#764ba2'
            },
            forest: {
                gradient_start: '#56ab2f',
                gradient_end: '#a8e6cf',
                badge_gradient_start: '#4facfe',
                badge_gradient_end: '#00f2fe'
            },
            purple: {
                gradient_start: '#667eea',
                gradient_end: '#764ba2',
                badge_gradient_start: '#ff9ff3',
                badge_gradient_end: '#f368e0'
            },
            fire: {
                gradient_start: '#ff416c',
                gradient_end: '#ff4b2b',
                badge_gradient_start: '#ff6b6b',
                badge_gradient_end: '#ee5a24'
            }
        };
        
        function applyPreset(presetName) {
            const preset = colorPresets[presetName];
            if (!preset) return;
            
            // Update color pickers
            document.getElementById('gradient_start').value = preset.gradient_start;
            document.getElementById('gradient_end').value = preset.gradient_end;
            document.getElementById('badge_gradient_start').value = preset.badge_gradient_start;
            document.getElementById('badge_gradient_end').value = preset.badge_gradient_end;
            
            // Update text inputs
            document.getElementById('gradient_start_text').value = preset.gradient_start;
            document.getElementById('gradient_end_text').value = preset.gradient_end;
            document.getElementById('badge_gradient_start_text').value = preset.badge_gradient_start;
            document.getElementById('badge_gradient_end_text').value = preset.badge_gradient_end;
            
            // Update previews
            updateGradientPreview();
            updateBadgePreview();
            
            showStatus(`🎨 Primijenjena ${presetName} kombinacija boja!`, 'success');
        }
        
        function isValidHex(hex) {
            return /^#[0-9A-F]{6}$/i.test(hex);
        }
    </script>
</body>
</html>
