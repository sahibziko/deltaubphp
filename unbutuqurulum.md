# 🚀 Delta PHP Userbot — Ubuntu (VPS/VDS) Sıfırdan Quraşdırma Bələdçisi

Bu təlimat **Ubuntu 22.04 / 24.04** əməliyyat sistemində **Delta PHP Multi-User Userbot** layihəsini sıfırdan qurmaq, 24/7 fasiləsiz işlətmək və GitHub-dan tək əmrlə yeniləmək üçün hazırlanmışdır.

---

## 📌 Məzmun
1. [Addım 1: Serverin Hazırlanması və PHP 8.3 Quraşdırılması](#addım-1-serverin-hazırlanması-və-php-83-quraşdırılması)
2. [Addım 2: Layihənin GitHub-dan Çəkilməsi](#addım-2-layihənin-github-dan-çəkilməsi)
3. [Addım 3: Veb Serverin (Nginx) Quraşdırılması](#addım-3-veb-serverin-nginx-quraşdırılması)
4. [Addım 4: Verilənlər Bazasının (MariaDB/MySQL) Yaradılması](#addım-4-verilənlər-bazasının-mariadbmysql-yaradılması)
5. [Addım 5: 24/7 Watcher (Nəzarətçi Xidməti) Aktivləşdirilməsi](#addım-5-247-watcher-nəzarətçi-xidməti-aktivləşdirilməsi)
6. [Addım 6: GitHub-da Dəyişiklik Edəndə Serveri Yeniləmək (Git Update)](#addım-6-github-da-dəyişiklik-edəndə-serveri-yeniləmək-git-update)
7. [Faydalı Əmrlər və İdarəetmə](#faydalı-əmrlər-və-idarəetmə)

---

## Addım 1: Serverin Hazırlanması və PHP 8.3 Quraşdırılması

Təmiz Ubuntu serverə SSH (terminal) ilə daxil olduqdan sonra bu əmrləri icra edin:

```bash
# 1. Paketləri yenilə və əsas alətləri yüklə
apt update && apt upgrade -y
apt install -y software-properties-common curl git unzip nano

# 2. PHP 8.3 repozitoriyasını əlavə et
add-apt-repository ppa:ondrej/php -y
apt update

# 3. MadelineProto və Layihə üçün tələb olunan PHP 8.3 modullarını qur
apt install -y php8.3-cli php8.3-fpm php8.3-curl php8.3-mbstring php8.3-xml \
php8.3-zip php8.3-gmp php8.3-bcmath php8.3-ffi php8.3-mysql php8.3-sqlite3

# 4. Composer-i rəsmi yolla yüklə
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer
```

---

## Addım 2: Layihənin GitHub-dan Çəkilməsi

Layihəni birbaşa `/var/www/UB` qovluğuna klonlayırıq və kitabxanaları qururuq:

```bash
# Layihəni GitHub-dan çək
git clone https://github.com/sahibziko/deltaubphp.git /var/www/UB
cd /var/www/UB

# Composer kitabxanalarını yüklə
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader

# Sessiya və loq qovluqlarını yarat
mkdir -p /var/www/UB/sessions /var/www/UB/logs

# İcazələri veb serverə (www-data) təyin et
chown -R www-data:www-data /var/www/UB
chmod -R 775 /var/www/UB
chmod -R 777 /var/www/UB/sessions /var/www/UB/logs /var/www/UB/public/api
```

---

## Addım 3: Veb Serverin (Nginx) Quraşdırılması

Saytın brauzerdə IP vasitəsilə açılması üçün Nginx quraşdırırıq:

```bash
apt install -y nginx

cat << 'EOF' > /etc/nginx/sites-available/default
server {
    listen 80 default_server;
    listen [::]:80 default_server;

    root /var/www/UB/public;
    index index.php index.html;

    server_name _;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
EOF

systemctl restart nginx php8.3-fpm
```

---

## Addım 4: Verilənlər Bazasının (MariaDB/MySQL) Yaradılması

İstifadəçilərin məlumatlarının saxlanması üçün MariaDB qurub bazanı hazırlayırıq:

```bash
apt install -y mariadb-server
systemctl start mariadb && systemctl enable mariadb

# Bazanı, istifadəçini və hüquqları yarat
mariadb << 'EOF'
CREATE DATABASE IF NOT EXISTS delta_userbot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'delta_user'@'localhost' IDENTIFIED BY 'DeltaPass2026!';
GRANT ALL PRIVILEGES ON delta_userbot.* TO 'delta_user'@'localhost';
FLUSH PRIVILEGES;
EOF

# Cədvəlləri yüklə
mariadb delta_userbot < /var/www/UB/database/schema.sql
```

### Botu Bazaya Qoş (`config/database.php`):
```bash
cat << 'EOF' > /var/www/UB/config/database.php
<?php

return [
    'host' => '127.0.0.1',
    'port' => '3306',
    'database' => 'delta_userbot',
    'username' => 'delta_user',
    'password' => 'DeltaPass2026!',
    'charset' => 'utf8mb4',
];
EOF
```

---

## Addım 5: 24/7 Watcher (Nəzarətçi Xidməti) Aktivləşdirilməsi

Botun heç vaxt sönməməsi, hər 5 saniyədən bir yoxlanması və `.reload` edildikdə dərhal oyanması üçün:

```bash
chmod +x /var/www/UB/watcher.sh

cat << 'EOF' > /etc/systemd/system/watcher.service
[Unit]
Description=UB Watcher Service
After=network.target mariadb.service

[Service]
Type=simple
WorkingDirectory=/var/www/UB
ExecStart=/bin/bash /var/www/UB/watcher.sh
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable --now watcher
```

> **Artıq saytınız hazırdır!**  
> Brauzerdə `http://SERVER_IP` yazaraq daxil ola və bot quraşdıra bilərsiniz.

---

## Addım 6: GitHub-da Dəyişiklik Edəndə Serveri Yeniləmək (Git Update)

GitHub-da kodlarda hər hansı dəyişiklik etdikdə (commit/push), serverdəki kodları yeniləyib botu təzələmək üçün aşağıdakı addımları edin:

### Tək Sətirlik Yeniləmə Əmri:
```bash
cd /var/www/UB && git pull && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader && pkill -9 -f 'php start.php'
```

Bu əmr nə edir?
1. `git pull` ilə son dəyişiklikləri GitHub-dan serverə çəkir;
2. Əgər yeni paket əlavə etmisinizsə, `composer install` ilə qurur;
3. `pkill -9 -f 'php start.php'` ilə köhnə bot prosesini bağlayır — arxada işləyən `watcher.sh` isə maksimum **5 saniyə ərzində** yeni kodlarla botu dərhal təkrar qaldırır!

### 💡 Rahatlıq üçün: Avtomatik Yeniləmə Skripti (`update.sh`)
Serverdə bir dəfə bu skripti yaradın:
```bash
cat << 'EOF' > /var/www/UB/update.sh
#!/bin/bash
cd /var/www/UB
echo "🔄 GitHub-dan son kodlar çəkilir..."
git pull
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader
echo "♻️ Bot yenidən başladılır..."
pkill -9 -f 'php start.php'
echo "✅ Uğurla yeniləndi! Watcher 5 saniyəyə botu yeni kodlarla qaldıracaq."
EOF
chmod +x /var/www/UB/update.sh
```

Bundan sonra GitHub-a nəsə atanda serverdə sadəcə bu qısa əmri vurmağınız kifayətdir:
```bash
/var/www/UB/update.sh
```

---

## Faydalı Əmrlər və İdarəetmə

* **Botun Canlı Loqlarını İzləmək:**
  ```bash
  tail -f /var/www/UB/logs/bot.log
  ```
  *(Çıxmaq üçün: `Ctrl + C`)*

* **Aktiv Prosesləri (Bot və Nəzarətçini) Yoxlamaq:**
  ```bash
  ps aux | grep -E 'php|watcher' | grep -v grep
  ```

* **Watcher Xidmətinin Statusu:**
  ```bash
  systemctl status watcher
  ```

* **Botu Əllə Söndürüb Təmiz Başlatmaq:**
  ```bash
  pkill -9 -f 'php start.php'; pkill -9 -f 'MadelineProto'; rm -f /var/www/UB/sessions/bot.lock
  ```
