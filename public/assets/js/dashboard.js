document.addEventListener('DOMContentLoaded', () => {
    // Navigation
    const navLinks = document.querySelectorAll('.nav-links li');
    const sections = document.querySelectorAll('.section');

    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            navLinks.forEach(l => l.classList.remove('active'));
            sections.forEach(s => s.classList.remove('active'));
            
            link.classList.add('active');
            const targetId = link.getAttribute('data-target');
            document.getElementById(targetId).classList.add('active');
        });
    });

    // Toast notification
    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        if (!container) return;
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        
        const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
        toast.innerHTML = `<i class="fa-solid ${icon}"></i> <span>${message}</span>`;
        
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }

    // Load Status
    async function loadStatus() {
        try {
            const res = await fetch('api/status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'get_status' })
            });
            const data = await res.json();
            
            if (data.success) {
                if (data.user) {
                    document.getElementById('user-name').textContent = data.user.name || 'İstifadəçi';
                    document.getElementById('user-phone').textContent = data.user.phone || '';
                    document.getElementById('prefix-input').value = data.user.prefix || '.';
                }
                
                document.getElementById('stat-status').textContent = data.status === 'active' ? 'Aktiv' : 'Deaktiv';
                document.getElementById('stat-plugins').textContent = data.plugin_count || '0';
                document.getElementById('stat-uptime').textContent = data.uptime || 'Deaktiv';
                document.getElementById('stat-commands').textContent = data.command_count || '0';
                
                const toggle = document.getElementById('toggle-bot-status');
                if (toggle) {
                    toggle.checked = data.status === 'active';
                }
                updateStatusBadge(data.status === 'active');
            }
        } catch (e) {
            console.error(e);
        }
    }

    function updateStatusBadge(isActive) {
        const badge = document.getElementById('bot-status-badge');
        const text = document.getElementById('bot-status-text');
        if (!badge || !text) return;
        
        if (isActive) {
            badge.style.background = 'rgba(16, 185, 129, 0.1)';
            badge.style.color = 'var(--success)';
            badge.style.borderColor = 'rgba(16, 185, 129, 0.2)';
            const dot = badge.querySelector('.dot');
            if (dot) dot.style.background = 'var(--success)';
            text.textContent = 'Aktiv';
        } else {
            badge.style.background = 'rgba(239, 68, 68, 0.1)';
            badge.style.color = 'var(--danger)';
            badge.style.borderColor = 'rgba(239, 68, 68, 0.2)';
            const dot = badge.querySelector('.dot');
            if (dot) dot.style.background = 'var(--danger)';
            text.textContent = 'Deaktiv';
        }
    }

    // Load Plugins
    async function loadPlugins() {
        try {
            const res = await fetch('api/plugins.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'list' })
            });
            const data = await res.json();
            
            if (data.success) {
                const container = document.getElementById('plugins-container');
                if (!container) return;
                container.innerHTML = '';
                
                data.plugins.forEach(plugin => {
                    const customBadge = plugin.is_custom ? '<span class="badge">Özəl</span>' : '';
                    const checked = plugin.enabled ? 'checked' : '';
                    
                    container.innerHTML += `
                        <div class="plugin-card glass-card">
                            <div class="plugin-header">
                                <div class="plugin-title">
                                    <h4>${plugin.name} v${plugin.version || '1.0.0'}</h4>
                                    ${customBadge}
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" class="plugin-toggle" data-class="${plugin.class_name}" data-name="${plugin.name}" ${checked}>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <p class="text-muted">${plugin.description || ''}</p>
                        </div>
                    `;
                });
                
                // Attach toggle events
                document.querySelectorAll('.plugin-toggle').forEach(toggle => {
                    toggle.addEventListener('change', async (e) => {
                        const className = e.target.getAttribute('data-class');
                        const displayName = e.target.getAttribute('data-name');
                        const enabled = e.target.checked ? 1 : 0;
                        
                        const r = await fetch('api/plugins.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({ action: 'toggle', plugin_name: className, enabled: enabled })
                        });
                        const resData = await r.json();
                        
                        if (resData.success) {
                            showToast(`${displayName} plugini ${enabled ? 'aktivləşdirildi' : 'deaktiv edildi'}`);
                        } else {
                            e.target.checked = !enabled; // revert
                            showToast(resData.error || 'Xəta baş verdi', 'error');
                        }
                    });
                });
            }
        } catch (e) {
            console.error(e);
        }
    }

    // Load Logs
    async function loadLogs() {
        try {
            const res = await fetch('api/status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'get_logs' })
            });
            const data = await res.json();
            
            if (data.success && data.logs) {
                const tbody = document.getElementById('logs-body');
                if (!tbody) return;
                tbody.innerHTML = '';
                
                data.logs.forEach(log => {
                    tbody.innerHTML += `
                        <tr>
                            <td>${log.created_at}</td>
                            <td>${log.action}</td>
                            <td><span class="text-muted">${log.details}</span></td>
                        </tr>
                    `;
                });
            }
        } catch (e) {
            console.error(e);
        }
    }

    // Save Prefix
    const savePrefixBtn = document.getElementById('save-prefix');
    if (savePrefixBtn) {
        savePrefixBtn.addEventListener('click', async () => {
            const prefix = document.getElementById('prefix-input').value;
            if (!prefix) return;
            
            try {
                const res = await fetch('api/plugins.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'set_prefix', prefix: prefix })
                });
                const data = await res.json();
                
                if (data.success) {
                    showToast('Prefiks yeniləndi');
                } else {
                    showToast(data.error || 'Xəta baş verdi', 'error');
                }
            } catch (e) {
                showToast('Xəta baş verdi', 'error');
            }
        });
    }

    // Toggle Bot Status
    const toggleBotStatus = document.getElementById('toggle-bot-status');
    if (toggleBotStatus) {
        toggleBotStatus.addEventListener('change', async (e) => {
            const status = e.target.checked ? 'active' : 'inactive';
            
            try {
                const res = await fetch('api/status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'toggle_status', status: status })
                });
                const data = await res.json();
                
                if (data.success) {
                    showToast(`Bot ${status === 'active' ? 'aktivləşdirildi' : 'deaktiv edildi'}`);
                    updateStatusBadge(e.target.checked);
                    document.getElementById('stat-status').textContent = status === 'active' ? 'Aktiv' : 'Deaktiv';
                } else {
                    e.target.checked = !e.target.checked;
                    showToast(data.error || 'Xəta baş verdi', 'error');
                }
            } catch (err) {
                e.target.checked = !e.target.checked;
                showToast('Xəta baş verdi', 'error');
            }
        });
    }

    // Search plugin
    const pluginSearchInput = document.getElementById('plugin-search');
    if (pluginSearchInput) {
        pluginSearchInput.addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            document.querySelectorAll('.plugin-card').forEach(card => {
                const title = card.querySelector('h4').textContent.toLowerCase();
                const desc = card.querySelector('p').textContent.toLowerCase();
                if (title.includes(term) || desc.includes(term)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }

    // Initialize
    loadStatus();
    loadPlugins();
    loadLogs();
    
    // Auto refresh status every 30s
    setInterval(loadStatus, 30000);
});
