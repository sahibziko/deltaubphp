# 🚀 Delta PHP Userbot - Tam VDS / VPS və Server Qurulum Bələdçisi

Bu sənəd Delta Userbot sisteminin istənilən **Ubuntu / Debian VDS (Virtual Dedicated Server) və ya VPS** üzərində 0-dan addım-addım tam sazlanması və köhnə hostinqdən köçürülməsi üçün hazırlanmışdır.

---

## 📌 Mündəricat
1. [Sistem Tələbləri](#1-sistem-tələbləri)
2. [Sistemin Yenilənməsi](#2-sistemin-yenilənməsi)
3. [PHP 8.3 və Bütün Lazımi Ekləntilərin (Extensions) Quraşdırılması](#3-php-83-və-ekləntilərin-quraşdırılması)
4. [MySQL / MariaDB Quraşdırılması və Baza İdxalı](#4-mysql--mariadb-quraşdırılması)
5. [Composer Quraşdırılması və Kitabxanaların Yüklənməsi](#5-composer-quraşdırılması)
6. [Layihənin Yerləşdirilməsi və `.env` Sazlanması](#6-layihənin-yerləşdirilməsi-və-env)
7. [Nginx Veb Serverinin Quraşdırılması (Veb Panel üçün)](#7-nginx-veb-serveri)
8. [Arxa Planda 7/24 Fasiləsiz İşlətmə](#8-arxa-planda-724-fasiləsiz-işlətmə)
   - **Metod 1 (Tövsiyə olunan):** Systemd Service (Server sönüb yansa da avtomatik qalxır)
   - **Metod 2:** Cron ilə `keepalive.php`
9. [Köhnə Hostinqdən Yeni VDS-ə Miqrasiya (Köçürülmə)](#9-köhnə-hostinqdən-vds-ə-miqrasiya)

---

## 1. Sistem Tələbləri
* **Əməliyyat Sistemi:** Ubuntu 22.04 LTS və ya Ubuntu 24.04 LTS (Debian 11/12 də uyğundur)
* **RAM:** Minimum 1 GB (Tövsiyə olunan: 2 GB və ya swap aktiv edilmiş 1 GB)
* **CPU:** 1 Core kifayətdir
* **Disk:** Minimum 10 GB SSD

---

## 2. Sistemin Yenilənməsi
VDS-ə SSH ilə bağlandıqdan sonra (`ssh root@server_ip`):

```bash
apt update && apt upgrade -y
apt install -y curl wget git unzip zip software-properties-common nano htop ufw
```

---

## 3. PHP 8.3 və Ekləntilərin Quraşdırılması
MadelineProto və Delta Userbot ən yaxşı şəkildə **PHP 8.3** üzərində işləyir.

### Ondřej Surý PHP Repositoriyasını əlavə edin:
```bash
add-apt-repository ppa:ondrej/php -y
apt update
```

### Bütün Zəruri PHP Ekləntilərini (Extensions) Yükləyin:
> [!IMPORTANT]
> MadelineProto kriptoqrafik hesablamalar üçün `gmp` və `bcmath`, proses idarəetməsi üçün `pcntl` və `posix`, şəkil generasiyası (.autopp, fontlar) üçün `gd` modullarını mütləq tələb edir!

```bash
apt install -y php8.3 php8.3-cli php8.3-fpm php8.3-mysql php8.3-mbstring \
               php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-gmp \
               php8.3-bcmath php8.3-intl php8.3-readline php8.3-ffi
```

### PHP Parametrlərinin Düzəldilməsi (`php.ini`):
CLI və FPM üçün yaddaş limitini artırın:

```bash
sed -i 's/memory_limit = .*/memory_limit = 512M/' /etc/php/8.3/cli/php.ini
sed -i 's/memory_limit = .*/memory_limit = 512M/' /etc/php/8.3/fpm/php.ini
sed -i 's/upload_max_filesize = .*/upload_max_filesize = 64M/' /etc/php/8.3/fpm/php.ini
sed -i 's/post_max_size = .*/post_max_size = 64M/' /etc/php/8.3/fpm/php.ini
sed -i 's/max_execution_time = .*/max_execution_time = 300/' /etc/php/8.3/fpm/php.ini

systemctl restart php8.3-fpm
```

---

## 4. MySQL / MariaDB Quraşdırılması

### 1. MariaDB Serverini Quraşdırın:
```bash
apt install -y mariadb-server
systemctl enable --now mariadb
```

### 2. Baza və İstifadəçi Yaradın:
MySQL konsoluna daxil olun:
```bash
mysql -u root
```

Konsolda ardıcıl olaraq bu əmrləri icra edin (parolu özünüzə uyğun dəyişin):
```sql
CREATE DATABASE delta_userbot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'delta_user'@'localhost' IDENTIFIED BY 'GucluParol123!';
GRANT ALL PRIVILEGES ON delta_userbot.* TO 'delta_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## 5. Composer Quraşdırılması
```bash
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer
```

Versiyanı yoxlayın:
```bash
composer --version
```

---

## 6. Layihənin Yerləşdirilməsi və `.env`

### 1. Qovluğu Yaradın və Layihəni Yerləşdirin:
Layihəni `/var/www/delta-php` qovluğuna qoymaq ən standart yoldur:
```bash
mkdir -p /var/www/delta-php
cd /var/www/delta-php
```
*(Fayllarınızı `git clone` və ya `scp` / WinSCP / FileZilla ilə bu qovluğa kopyalayın).*

### 2. Composer Kitabxanalarını Quraşdırın:
```bash
cd /var/www/delta-php
composer install --no-dev --optimize-autoloader
```

### 3. `.env` Faylını Yaradın:
```bash
cp .env.example .env
nano .env
```
Faylın daxilini VDS konfiqurasiyasına uyğun doldurun:
```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=delta_userbot
DB_USERNAME=delta_user
DB_PASSWORD=GucluParol123!

APP_ENV=production
APP_DEBUG=false
APP_URL=http://server_ip_veya_domen
```
*(Yadda saxlamaq üçün: `CTRL+O`, `Enter`, çıxmaq üçün: `CTRL+X`)*

### 4. Baza Sxemini İdxal (Import) Edin:
```bash
mysql -u delta_user -pGucluParol123! delta_userbot < database/schema.sql
```

### 5. Qovluq İcazələrini Təyin Edin:
```bash
mkdir -p sessions logs public/uploads plugins/custom
chown -R www-data:www-data /var/www/delta-php
chmod -R 775 /var/www/delta-php/sessions
chmod -R 775 /var/www/delta-php/logs
chmod -R 775 /var/www/delta-php/public/uploads
chmod -R 775 /var/www/delta-php/plugins/custom
```

---

## 7. Nginx Veb Serveri (Veb Panel üçün)

Veb paneli brauzerdən idarə etmək üçün Nginx quraşdırın:
```bash
apt install -y nginx
```

Nginx konfiqurasiya faylı yaradın:
```bash
nano /etc/nginx/sites-available/delta
```

Aşağıdakı mətni yapışdırın:
```nginx
server {
    listen 80;
    server_name server_ip_veya_domeniniz.com;
    root /var/www/delta-php/public;

    index index.php index.html;

    client_max_body_size 64M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(env|git|ht) {
        deny all;
    }
}
```

Konfiqurasiyanı aktivləşdirin və Nginx-i yenidən başladın:
```bash
ln -s /etc/nginx/sites-available/delta /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl restart nginx
```

---

## 8. Arxa Planda 7/24 Fasiləsiz İşlətmə

VDS-də paylaşılan hostinglərdəki (cPanel) kimi proses limitləri (NPROC) olmur. Buna görə ən etibarlı 2 seçiminiz var:

### 🌟 Metod 1 (Mütləq Tövsiyə Olunan): Systemd Service
Systemd server səviyyəsində işləyir. Əgər server qəfil sönüb yenidən açılarsa və ya bot gözlənilmədən xəta verib dayansa, Systemd 5 saniyə sonra botu avtomatik yenidən işə salır!

1. Servis faylını yaradın:
```bash
nano /etc/systemd/system/delta-userbot.service
```

2. Bu məzmunu əlavə edin:
```ini
[Unit]
Description=Delta Telegram Multi-Userbot Service
After=network.target mariadb.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/delta-php
ExecStart=/usr/bin/php /var/www/delta-php/start.php
Restart=always
RestartSec=5s
StandardOutput=append:/var/www/delta-php/logs/bot.log
StandardError=append:/var/www/delta-php/logs/bot.log

[Install]
WantedBy=multi-user.target
```

3. Servisi aktivləşdirin və başladın:
```bash
systemctl daemon-reload
systemctl enable delta-userbot
systemctl start delta-userbot
```

#### Statusu və Loqları Yoxlamaq üçün:
* Statusu görmək: `systemctl status delta-userbot`
* Canlı loqları izləmək: `tail -f /var/www/delta-php/logs/bot.log`
* Botu yenidən başlatmaq: `systemctl restart delta-userbot`
* Botu dayandırmaq: `systemctl stop delta-userbot`

---

### Metod 2: Crontab ilə `keepalive.php` (Alternativ)
Əgər Systemd əvəzinə vərdiş etdiyiniz Cron üsulundan istifadə etmək istəyirsinizsə:

```bash
crontab -e
```
Faylın ən sonuna bu sətri əlavə edin (hər 1 dəqiqədən bir botu yoxlayır və lazım gələrsə qaldırır):
```bash
* * * * * /usr/bin/php /var/www/delta-php/keepalive.php > /dev/null 2>&1
```

> **QEYD:** Crontab üçün **əsas qovluqdakı `keepalive.php`** istifadə edilməlidir! `public/keepalive.php` isə xarici veb URL (cron-job.org və s.) vasitəsilə sorğu göndərmək üçündür.

---

## 9. Köhnə Hostinqdən VDS-ə Miqrasiya

Köhnə hostingdən yeni VDS-ə keçərkən heç bir istifadəçinin çıxmaması (təkrar nömrə/kod tələb olunmaması) üçün bu 3 addımı edin:

1. **Köhnə Hostinqi Dayandırın:**
   * Köhnə cPanel-də Cron əmrini silin və bütün işləyən prosesləri (`pkill -9 -f php`) dayandırın (Telegram ikili qoşulma xətası verməsin deyə).
2. **`sessions/` Qovluğunu Köçürün:**
   * Köhnə serverdəki `sessions/` qovluğunu zip edib yeni VDS-dəki `/var/www/delta-php/sessions/` qovluğuna açın. İstifadəçilərin bütün Telegram sessiyaları (`*.madeline`) buradadır.
3. **Verilənlər Bazasını İxrac/İdxal Edin:**
   * Köhnə cPanel phpMyAdmin-dən bazanı `Export` (.sql) edin.
   * Yeni VDS-də idxal edin:
     ```bash
     mysql -u delta_user -pGucluParol123! delta_userbot < kohne_baza.sql
     ```
4. **VDS-də Botu Başladın:**
   ```bash
   systemctl start delta-userbot
   ```
Bütün istifadəçilərin botları heç bir fasilə olmadan, qaldığı yerdən işləməyə davam edəcək!
