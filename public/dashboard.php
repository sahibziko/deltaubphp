<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: install.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="az">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delta PHP - İdarəetmə Paneli</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="dashboard-body">
    <nav class="sidebar glass-panel">
        <div class="sidebar-header">
            <i class="fa-solid fa-paper-plane"></i>
            <h2>Δ Delta</h2>
        </div>
        <ul class="nav-links">
            <li class="active" data-target="dashboard-section"><i class="fa-solid fa-chart-pie"></i> Ana Səhifə</li>
            <li data-target="plugins-section"><i class="fa-solid fa-puzzle-piece"></i> Pluginlər</li>
            <li data-target="settings-section"><i class="fa-solid fa-gear"></i> Parametrlər</li>
            <li data-target="logs-section"><i class="fa-solid fa-list-ul"></i> Loqlar</li>
        </ul>
        <div class="sidebar-footer">
            <a href="logout.php" class="logout-btn"><i class="fa-solid fa-right-from-bracket"></i> Çıxış</a>
        </div>
    </nav>

    <main class="main-content">
        <header class="top-header glass-panel">
            <div class="user-info">
                <img src="https://ui-avatars.com/api/?name=User&background=7c3aed&color=fff" alt="User" class="avatar">
                <div>
                    <h3 id="user-name">Yüklənir...</h3>
                    <p class="text-muted" id="user-phone">...</p>
                </div>
            </div>
            <div class="status-badge" id="bot-status-badge">
                <span class="dot active"></span> <span id="bot-status-text">Aktiv</span>
            </div>
        </header>

        <div class="content-sections">
            <!-- Dashboard Section -->
            <section id="dashboard-section" class="section active">
                <h2 class="section-title">Xülasə</h2>
                <div class="stats-grid">
                    <div class="stat-card glass-card">
                        <div class="stat-icon"><i class="fa-solid fa-robot"></i></div>
                        <div class="stat-info">
                            <h4>Status</h4>
                            <h2 id="stat-status">Aktiv</h2>
                        </div>
                    </div>
                    <div class="stat-card glass-card">
                        <div class="stat-icon"><i class="fa-solid fa-plug"></i></div>
                        <div class="stat-info">
                            <h4>Aktiv Pluginlər</h4>
                            <h2 id="stat-plugins">0</h2>
                        </div>
                    </div>
                    <div class="stat-card glass-card">
                        <div class="stat-icon"><i class="fa-solid fa-clock"></i></div>
                        <div class="stat-info">
                            <h4>Uptime</h4>
                            <h2 id="stat-uptime">0s</h2>
                        </div>
                    </div>
                    <div class="stat-card glass-card">
                        <div class="stat-icon"><i class="fa-solid fa-terminal"></i></div>
                        <div class="stat-info">
                            <h4>Komanda Sayı</h4>
                            <h2 id="stat-commands">0</h2>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Plugins Section -->
            <section id="plugins-section" class="section">
                <div class="section-header">
                    <h2 class="section-title">Pluginlər</h2>
                    <div class="search-box">
                        <i class="fa-solid fa-search"></i>
                        <input type="text" id="plugin-search" placeholder="Axtar...">
                    </div>
                </div>
                <div class="plugins-grid" id="plugins-container">
                    <!-- Loaded via JS -->
                </div>
            </section>

            <!-- Settings Section -->
            <section id="settings-section" class="section">
                <h2 class="section-title">Parametrlər</h2>
                <div class="settings-card glass-card">
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Bot Statusu</h4>
                            <p class="text-muted">Botun işlək vəziyyətini dəyişdir</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="toggle-bot-status" checked>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="divider"></div>
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Komanda Prefiksi</h4>
                            <p class="text-muted">Bot komandaları üçün işarə (məs. . və ya !)</p>
                        </div>
                        <div class="prefix-input-group">
                            <input type="text" id="prefix-input" value="." maxlength="1">
                            <button id="save-prefix" class="btn btn-primary btn-sm">Yadda saxla</button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Logs Section -->
            <section id="logs-section" class="section">
                <h2 class="section-title">Son Fəaliyyətlər</h2>
                <div class="logs-card glass-card">
                    <table class="logs-table">
                        <thead>
                            <tr>
                                <th>Tarix</th>
                                <th>Fəaliyyət</th>
                                <th>Detallar</th>
                            </tr>
                        </thead>
                        <tbody id="logs-body">
                            <!-- Loaded via JS -->
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>

    <!-- Toast Notifications -->
    <div id="toast-container" class="toast-container"></div>

    <script src="assets/js/dashboard.js"></script>
</body>
</html>
