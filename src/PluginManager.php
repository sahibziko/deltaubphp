<?php
namespace App;

class PluginManager
{
    public array $plugins = [];
    public array $commandMap = [];
    public array $messageHandlers = [];
    
    private $api;
    private Database $db;
    private int $ownerId;
    private int $internalUserId;
    
    /** Static config - start.php tərəfindən set edilir */
    public static ?array $appConfig = null;

    public function __construct($api, Database $db, int $ownerId)
    {
        $this->api = $api;
        $this->db = $db;
        $this->ownerId = $ownerId;
        
        $userManager = new UserManager($db);
        $user = $userManager->getUserByTelegramId($ownerId);
        $this->internalUserId = $user ? $user['id'] : 0;
    }

    private function getConfig(): array
    {
        if (self::$appConfig !== null) {
            return self::$appConfig;
        }
        return [
            'plugins_dir' => dirname(__DIR__) . '/plugins/default',
            'custom_plugins_dir' => dirname(__DIR__) . '/plugins/custom',
        ];
    }

    public function loadDefaultPlugins(): void
    {
        $config = $this->getConfig();
        $defaultDir = $config['plugins_dir'];
        
        if (!is_dir($defaultDir)) return;
        
        foreach (glob($defaultDir . '/*.php') as $file) {
            $this->loadPluginFile($file, false);
        }
    }

    public function loadCustomPlugins(): void
    {
        $config = $this->getConfig();
        $customDir = $config['custom_plugins_dir'] . '/' . $this->ownerId;
        
        if (!is_dir($customDir)) return;
        
        foreach (glob($customDir . '/*.php') as $file) {
            $this->loadPluginFile($file, true);
        }
    }
    
    private ?array $userPluginsCache = null;

    private function isPluginEnabled(string $pluginName, string $file, bool $isCustom): bool
    {
        if (!$this->internalUserId) {
            return true;
        }

        if ($this->userPluginsCache === null) {
            $this->userPluginsCache = [];
            $stmt = $this->db->prepare("SELECT plugin_name, enabled FROM user_plugins WHERE user_id = ?");
            $stmt->execute([$this->internalUserId]);
            while ($row = $stmt->fetch()) {
                $this->userPluginsCache[$row['plugin_name']] = (int) $row['enabled'];
            }
        }

        if (!array_key_exists($pluginName, $this->userPluginsCache)) {
            $stmt = $this->db->prepare("INSERT INTO user_plugins (user_id, plugin_name, plugin_file, is_custom, enabled) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$this->internalUserId, $pluginName, $file, $isCustom ? 1 : 0]);
            $this->userPluginsCache[$pluginName] = 1;
            return true;
        }

        return $this->userPluginsCache[$pluginName] === 1;
    }

    private function loadPluginFile(string $file, bool $isCustom): void
    {
        $className = basename($file, '.php');
        $pluginName = $className;
        
        if (!$this->isPluginEnabled($pluginName, $file, $isCustom)) {
            return;
        }
        
        if (!class_exists($className)) {
            require_once $file;
        }
        
        $fullClassName = "\\App\\Plugins\\" . $className;
        if (!class_exists($fullClassName)) {
            $fullClassName = $className;
        }
        
        if (class_exists($fullClassName) && is_subclass_of($fullClassName, BasePlugin::class)) {
            $plugin = new $fullClassName();
            $this->registerPlugin($plugin, $isCustom);
        }
    }

    public function registerPlugin(BasePlugin $plugin, bool $isCustom = false): void
    {
        // Kənar (custom) pluginlər Sandbox rejimində işləyir: Database obyekti null olaraq ötürülür
        $plugin->init($this->api, $isCustom ? null : $this->db, $this->ownerId, $isCustom);
        $this->plugins[$plugin->name] = $plugin;
        
        foreach ($plugin->commands as $cmd => $desc) {
            $this->commandMap[$cmd] = $plugin->name;
        }
        
        if ($plugin->handlesMessages) {
            $this->messageHandlers[] = $plugin->name;
        }
    }

    /**
     * Kənar plugin üçün Dərin Təhlükəsizlik Auditi (Sandbox və Bütün Boşluqların Qarşısının Alınması)
     */
    public static function validatePluginSecurity(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return ['valid' => false, 'error' => 'Plugin faylı tapılmadı.'];
        }

        $code = @file_get_contents($filePath);
        if ($code === false || strlen(trim($code)) === 0) {
            return ['valid' => false, 'error' => 'Plugin faylı boşdur.'];
        }

        // 1. PHP Sintaksis yoxlanışı (PHP Lint)
        $lintCmd = 'php -l ' . escapeshellarg($filePath) . ' 2>&1';
        $lintOutput = @shell_exec($lintCmd);
        if ($lintOutput && stripos($lintOutput, 'No syntax errors detected') === false) {
            $errLine = trim(strtok($lintOutput, "\n"));
            return ['valid' => false, 'error' => 'PHP Sintaksis xətası aşkarlandı: ' . htmlspecialchars($errLine, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')];
        }

        // 2. Qadağan olunmuş təhlükəli funksiyalar, siniflər və açar sözlər
        $forbiddenKeywords = [
            // Shell və Sistem icrası
            'exec' => 'Sistem və Shell əmrlərinin icrası qadağandır',
            'shell_exec' => 'Shell əmrlərinin icrası qadağandır',
            'system' => 'Sistem əmrlərinin icrası qadağandır',
            'passthru' => 'Sistem əmrlərinin icrası qadağandır',
            'proc_open' => 'Proses idarəetməsi qadağandır',
            'proc_close' => 'Proses idarəetməsi qadağandır',
            'popen' => 'Proses icrası qadağandır',
            'pclose' => 'Proses icrası qadağandır',
            'pcntl_exec' => 'Proses icrası qadağandır',
            'pcntl_fork' => 'Proses fork edilməsi qadağandır',
            'pcntl_signal' => 'Proses siqnalları qadağandır',
            'dl' => 'Dinamik modul yüklənməsi qadağandır',
            'mail' => 'Serverdən mail göndərilməsi qadağandır',
            'mb_send_mail' => 'Serverdən mail göndərilməsi qadağandır',

            // Dinamik Kod İcrası və Callback Bypass-ları
            'eval' => 'Dinamik eval() icrası qadağandır',
            'assert' => 'assert() icrası qadağandır',
            'create_function' => 'create_function() qadağandır',
            'call_user_func' => 'Dinamik call_user_func qadağandır',
            'call_user_func_array' => 'Dinamik call_user_func_array qadağandır',
            'forward_static_call' => 'Dinamik forward_static_call qadağandır',
            'forward_static_call_array' => 'Dinamik forward_static_call_array qadağandır',
            'register_shutdown_function' => 'register_shutdown_function qadağandır',
            'register_tick_function' => 'register_tick_function qadağandır',
            'array_map' => 'Dinamik array_map callback qadağandır',
            'array_filter' => 'Dinamik array_filter callback qadağandır',
            'array_reduce' => 'Dinamik array_reduce callback qadağandır',
            'array_walk' => 'Dinamik array_walk callback qadağandır',
            'array_walk_recursive' => 'Dinamik array_walk_recursive callback qadağandır',
            'usort' => 'Dinamik usort callback qadağandır',
            'uasort' => 'Dinamik uasort callback qadağandır',
            'uksort' => 'Dinamik uksort callback qadağandır',

            // Reflection / Kod Təftişi
            'reflection' => 'Reflection API qadağandır',
            'reflectionclass' => 'ReflectionClass qadağandır',
            'reflectionfunction' => 'ReflectionFunction qadağandır',
            'reflectionmethod' => 'ReflectionMethod qadağandır',
            'reflectionproperty' => 'ReflectionProperty qadağandır',
            'reflectionobject' => 'ReflectionObject qadağandır',

            // Obfuskasiya və Şifrələmə Funksiyaları
            'base64_decode' => 'Gizlədilmiş / obfuskasiya olunmuş kodların icrası qadağandır',
            'hex2bin' => 'Hex obfuskasiya qadağandır',
            'bin2hex' => 'Bin obfuskasiya qadağandır',
            'str_rot13' => 'Rot13 obfuskasiya qadağandır',
            'gzinflate' => 'Sıxılmış kodların açılması qadağandır',
            'gzuncompress' => 'Sıxılmış kodların açılması qadağandır',
            'gzdecode' => 'Sıxılmış kodların açılması qadağandır',

            // Deserialization (POP Gadget Chain Qoruması)
            'unserialize' => 'unserialize() təhlükəsizlik səbəbilə qadağandır',
            'yaml_parse' => 'yaml_parse() qadağandır',

            // Server və Ətraf Mühit Məlumatlarının Oxunması
            'getenv' => 'Server mühit dəyişənlərinin oxunması qadağandır',
            'putenv' => 'Mühit dəyişənlərinin dəyişdirilməsi qadağandır',
            'apache_getenv' => 'Server mühitinin oxunması qadağandır',
            'apache_setenv' => 'Server mühitinin dəyişdirilməsi qadağandır',
            'get_defined_vars' => 'Daxili dəyişənlərin oxunması qadağandır',
            'get_defined_constants' => 'Daxili sabitlərə baxış qadağandır',
            'get_defined_functions' => 'Daxili funksiyalara baxış qadağandır',
            'get_declared_classes' => 'Daxili siniflərə baxış qadağandır',
            'phpinfo' => 'phpinfo() məlumat sızması qadağandır',
            'ini_set' => 'PHP konfiqurasiyasının dəyişdirilməsi qadağandır',
            'ini_get' => 'PHP konfiqurasiyasının oxunması qadağandır',

            // Kənar faylların daxil edilməsi
            'include' => 'Kənar faylların include edilməsi qadağandır',
            'require' => 'Kənar faylların require edilməsi qadağandır',
            'include_once' => 'Kənar faylların include_once edilməsi qadağandır',
            'require_once' => 'Kənar faylların require_once edilməsi qadağandır',

            // Verilənlər Bazası (DB) Təhlükəsizliyi
            '->db' => 'Kənar pluginlər Verilənlər Bazasına (DB) daxil ola bilməz',
            'database' => 'Verilənlər Bazası sinifləri qadağandır',
            'pdo' => 'Kənar verilənlər bazası bağlantısı (PDO) qadağandır',
            'pdostatement' => 'Verilənlər bazası əməliyyatları qadağandır',
            'mysqli' => 'Kənar verilənlər bazası bağlantısı (mysqli) qadağandır',
            'sqlite' => 'Kənar verilənlər bazası bağlantısı (sqlite) qadağandır',
            'sqlite3' => 'Kənar verilənlər bazası bağlantısı (sqlite3) qadağandır',
            'query' => 'Birbaşa verilənlər bazası sorğuları qadağandır',
            'prepare' => 'Birbaşa verilənlər bazası sorğuları qadağandır',

            // Fayl və Server İdarəetməsi / Fayl Sistemindən Oxuma-Yazma
            'unlink' => 'Server fayllarının silinməsi qadağandır',
            'rmdir' => 'Qovluqların silinməsi qadağandır',
            'mkdir' => 'Serverdə qovluq yaradılması qadağandır',
            'chmod' => 'Fayl hüquqlarının dəyişdirilməsi qadağandır',
            'chown' => 'Fayl sahibliyinin dəyişdirilməsi qadağandır',
            'rename' => 'Server fayllarının adının dəyişdirilməsi qadağandır',
            'copy' => 'Server fayllarının kopyalanması qadağandır',
            'file_put_contents' => 'Server fayllarının dəyişdirilməsi qadağandır',
            'fwrite' => 'Fayllara birbaşa yazılması qadağandır',
            'fputs' => 'Fayllara birbaşa yazılması qadağandır',
            'touch' => 'Fayl yaradılması qadağandır',
            'symlink' => 'Symlink yaradılması qadağandır',
            'fopen' => 'Server fayllarının açılması qadağandır',
            'file_get_contents' => 'Server fayllarının oxunması qadağandır',
            'readfile' => 'Server fayllarının oxunması qadağandır',
            'file' => 'Server fayllarının oxunması qadağandır',
            'scandir' => 'Server qovluqlarının skan edilməsi qadağandır',
            'glob' => 'Server qovluqlarının skan edilməsi qadağandır',
            'opendir' => 'Server qovluqlarının açılması qadağandır',

            // Prosesin dayandırılması / Botun söndürülməsi
            'posix_kill' => 'Proseslərin dayandırılması qadağandır',
            'exit' => 'exit() funksiyası ilə botu dayandırmaq qadağandır',
            'die' => 'die() funksiyası ilə botu dayandırmaq qadağandır',
        ];

        // 3. PHP Tokenizer ilə dərindən AST/Token analizi
        $tokens = token_get_all($code);
        $tokenCount = count($tokens);
        $insideClass = false;
        $classFound = false;
        $braceDepth = 0;

        for ($i = 0; $i < $tokenCount; $i++) {
            $token = $tokens[$i];

            if (is_array($token)) {
                $tokenType = $token[0];
                $tokenVal = strtolower($token[1]);

                // Class daxilində olub-olmamasını izləyirik
                if ($tokenType === T_CLASS) {
                    $classFound = true;
                }

                // Qlobal və Server dəyişənlərinin ($SERVER, $ENV, $GLOBALS və s.) oxunmasının qarşısının alınması
                if ($tokenType === T_VARIABLE) {
                    if (in_array($tokenVal, ['$_server', '$_env', '$globals', '$_session', '$_cookie', '$_post', '$_get', '$_files'])) {
                        return ['valid' => false, 'error' => "Server və qlobal dəyişənlərə (<code>{$tokenVal}</code>) müraciət qadağandır"];
                    }

                    // Dinamik dəyişən funksiyası çağırışının yoxlanması: $func(...)
                    for ($j = $i + 1; $j < $tokenCount; $j++) {
                        $nextToken = $tokens[$j];
                        if (is_array($nextToken) && ($nextToken[0] === T_WHITESPACE || $nextToken[0] === T_COMMENT || $nextToken[0] === T_DOC_COMMENT)) {
                            continue;
                        }
                        if ($nextToken === '(') {
                            return ['valid' => false, 'error' => "Dinamik dəyişən funksiyası (<code>{$tokenVal}()</code>) icrası qadağandır"];
                        }
                        break;
                    }
                }

                // Dinamik obyekt metodu çağırışının yoxlanması: $obj->$var(...)
                if ($tokenType === T_OBJECT_OPERATOR || (defined('T_NULLSAFE_OBJECT_OPERATOR') && $tokenType === T_NULLSAFE_OBJECT_OPERATOR)) {
                    for ($j = $i + 1; $j < $tokenCount; $j++) {
                        $nextToken = $tokens[$j];
                        if (is_array($nextToken) && ($nextToken[0] === T_WHITESPACE || $nextToken[0] === T_COMMENT || $nextToken[0] === T_DOC_COMMENT)) {
                            continue;
                        }
                        if (is_array($nextToken) && $nextToken[0] === T_VARIABLE) {
                            return ['valid' => false, 'error' => "Dinamik metod (<code>->{$nextToken[1]}</code>) icrası qadağandır"];
                        }
                        break;
                    }
                }

                // Qadağan olunmuş funksiya və identifikatorlar
                if ($tokenType === T_STRING || $tokenType === T_VARIABLE) {
                    if (isset($forbiddenKeywords[$tokenVal])) {
                        return ['valid' => false, 'error' => $forbiddenKeywords[$tokenVal] . " (<code>{$tokenVal}</code>)"];
                    }
                }

                if ($tokenType === T_REQUIRE || $tokenType === T_REQUIRE_ONCE || $tokenType === T_INCLUDE || $tokenType === T_INCLUDE_ONCE) {
                    return ['valid' => false, 'error' => 'Kənar faylların require/include edilməsi qadağandır'];
                }

                if ($tokenType === T_EXIT) {
                    return ['valid' => false, 'error' => 'exit/die ilə botu dayandırmaq qadağandır'];
                }

                if ($tokenType === T_EVAL) {
                    return ['valid' => false, 'error' => 'eval() ilə kod icrası qadağandır'];
                }

                // Sinifdən kənar sərbəst icra olunan kodların bloklanması
                if (!$classFound) {
                    $allowedPreClass = [
                        T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT,
                        T_USE, T_NAMESPACE, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED,
                        T_STRING, T_AS
                    ];
                    if (!in_array($tokenType, $allowedPreClass) && $tokenVal !== ';') {
                        return ['valid' => false, 'error' => "Plugin faylında sinifdən (class) kənar sərbəst kodların icrası qadağandır"];
                    }
                }

            } else {
                // String tipli tokenlər
                if ($token === '`') {
                    return ['valid' => false, 'error' => 'Backtick (`) ilə shell icrası qadağandır'];
                }

                // Dinamik dəyişən-dəyişən ($$var) sintaksisinin yoxlanması
                if ($token === '$') {
                    for ($j = $i + 1; $j < $tokenCount; $j++) {
                        $nextToken = $tokens[$j];
                        if (is_array($nextToken) && ($nextToken[0] === T_WHITESPACE || $nextToken[0] === T_COMMENT)) {
                            continue;
                        }
                        if (is_array($nextToken) && ($nextToken[0] === T_VARIABLE || $nextToken[0] === T_STRING)) {
                            return ['valid' => false, 'error' => "Dinamik dəyişən-dəyişən (\$\$) sintaksisi qadağandır"];
                        }
                        break;
                    }
                }

                if ($token === '{') {
                    $braceDepth++;
                    if ($classFound && !$insideClass) {
                        $insideClass = true;
                    }
                } elseif ($token === '}') {
                    $braceDepth--;
                    if ($insideClass && $braceDepth === 0) {
                        $insideClass = false; // Sinif bitdi
                    }
                }
            }
        }

        // 4. Mətn səviyyəsində Regex yoxlanışları (bütün növ gizli müraciətlər)
        foreach ($forbiddenKeywords as $pattern => $errMsg) {
            $regex = '/\b' . preg_quote($pattern, '/') . '\b/i';
            if ($pattern === '->db') {
                $regex = '/->\s*db\b/i';
            }
            if (preg_match($regex, $code)) {
                return ['valid' => false, 'error' => $errMsg . " (<code>{$pattern}</code>)"];
            }
        }

        // Təhlükəli SQL açar sözləri
        if (preg_match('/\b(SELECT|INSERT\s+INTO|UPDATE\s+|DELETE\s+FROM|DROP\s+TABLE|TRUNCATE\s+TABLE|ALTER\s+TABLE|UNION\s+SELECT|INFORMATION_SCHEMA)\b/i', $code, $sqlM)) {
            return ['valid' => false, 'error' => "Kənar pluginlərdə SQL sorğuları qadağandır (<code>" . htmlspecialchars($sqlM[0], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</code>)"];
        }

        // Qadağan olunmuş fayl adları və yolları
        if (preg_match('/(\.env|config\.php|config\.json|sessions|brain|\.\.\/|\/etc\/|id_rsa|passwd|shadow)/i', $code, $pathM)) {
            return ['valid' => false, 'error' => "Konfiqurasiya və ya daxili sistem yollarına müraciət qadağandır (<code>" . htmlspecialchars($pathM[0], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</code>)"];
        }

        // MadelineProto daxili söndürmə metodları
        if (preg_match('/->\s*(stop|restart|logout|resetState)\s*\(/i', $code, $botM)) {
            return ['valid' => false, 'error' => "Botun işini dayandıran daxili metodlara müraciət qadağandır (<code>->{$botM[1]}()</code>)"];
        }

        // 5. Plugin strukturunun yoxlanışı (class extends BasePlugin)
        if (!preg_match('/class\s+([a-zA-Z0-9_]+)\s+extends\s+(?:\\\\App\\\\)?BasePlugin/i', $code, $classMatches)) {
            return ['valid' => false, 'error' => 'Plugin "BasePlugin" sinifindən törəməlidir (class ... extends BasePlugin)'];
        }

        $className = $classMatches[1];

        // 6. Default sistem plugini ilə ad toqquşması yoxlanışı
        $defaultPluginsDir = dirname(__DIR__) . '/plugins/default';
        if (file_exists($defaultPluginsDir . '/' . $className . '.php') || file_exists($defaultPluginsDir . '/' . ucfirst($className) . '.php')) {
            return ['valid' => false, 'error' => "<b>{$className}</b> əsas sistem pluginidir! Əsas sistem pluginlərini kənar pluginlə əvəzləmək qadağandır."];
        }

        return ['valid' => true, 'error' => '', 'class_name' => $className];
    }

    public function installPlugin(string $filePath, int $ownerId): array
    {
        if (!file_exists($filePath)) {
            return ['success' => false, 'message' => 'Fayl tapılmadı.', 'plugin_name' => ''];
        }

        // Təhlükəsizlik yoxlanışı
        $securityCheck = self::validatePluginSecurity($filePath);
        if (!$securityCheck['valid']) {
            @unlink($filePath);
            return [
                'success' => false,
                'message' => "🛡 <b>Təhlükəsizlik İmtinası!</b>\n" . $securityCheck['error'] . "\n\n<i>Kənar pluginlər Verilənlər Bazasına (DB) daxil ola, sistem/shell əmrləri icra edə və ya faylları dəyişdirə bilməz.</i>",
                'plugin_name' => ''
            ];
        }

        $className = $securityCheck['class_name'];
        $config = $this->getConfig();
        $customDir = $config['custom_plugins_dir'] . '/' . $ownerId;
        
        if (!is_dir($customDir)) {
            @mkdir($customDir, 0755, true);
        }
        
        $destPath = $customDir . '/' . $className . '.php';
        
        if (!copy($filePath, $destPath)) {
            @unlink($filePath);
            return ['success' => false, 'message' => 'Plugini qovluğa kopyalamaq mümkün olmadı.', 'plugin_name' => $className];
        }
        @unlink($filePath);
        
        if ($this->internalUserId) {
            $stmt = $this->db->prepare("INSERT INTO user_plugins (user_id, plugin_name, plugin_file, is_custom, enabled) VALUES (?, ?, ?, 1, 1) ON DUPLICATE KEY UPDATE enabled=1, plugin_file=VALUES(plugin_file)");
            $stmt->execute([$this->internalUserId, $className, $destPath]);
        }
        
        $this->loadPluginFile($destPath, true);
        
        return [
            'success' => true,
            'message' => "✅ <b>{$className}</b> kənar plugini təhlükəsizlik auditindən uğurla keçdi və quraşdırıldı!",
            'plugin_name' => $className
        ];
    }

    public function uninstallPlugin(string $pluginName, int $ownerId): array
    {
        $config = $this->getConfig();
        $defaultDir = $config['plugins_dir'];

        // Default sistem pluginləri silinə bilməz!
        if (file_exists($defaultDir . '/' . $pluginName . '.php') || file_exists($defaultDir . '/' . ucfirst($pluginName) . '.php')) {
            return [
                'success' => false,
                'message' => "⚠️ <b>{$pluginName}</b> əsas (default) sistem pluginidir və silinə bilməz!\n<i>Yalnız öz quraşdırdığınız kənar pluginləri silə bilərsiniz.</i>"
            ];
        }

        $customDir = $config['custom_plugins_dir'] . '/' . $ownerId;
        $customFile = $customDir . '/' . $pluginName . '.php';
        $found = false;

        if (file_exists($customFile)) {
            @unlink($customFile);
            $found = true;
        }

        if ($this->internalUserId) {
            $stmt = $this->db->prepare("SELECT plugin_file FROM user_plugins WHERE user_id = ? AND plugin_name = ? AND is_custom = 1");
            $stmt->execute([$this->internalUserId, $pluginName]);
            $row = $stmt->fetch();
            
            if ($row) {
                if (file_exists($row['plugin_file'])) {
                    @unlink($row['plugin_file']);
                }
                $stmtDel = $this->db->prepare("DELETE FROM user_plugins WHERE user_id = ? AND plugin_name = ? AND is_custom = 1");
                $stmtDel->execute([$this->internalUserId, $pluginName]);
                $found = true;
            }
        }

        if ($found) {
            // Plugin obyektini və komandalarını yaddaşdan silirik
            if (isset($this->plugins[$pluginName])) {
                $pObj = $this->plugins[$pluginName];
                foreach ($pObj->commands as $cmd => $desc) {
                    if (isset($this->commandMap[$cmd]) && $this->commandMap[$cmd] === $pluginName) {
                        unset($this->commandMap[$cmd]);
                    }
                }
                unset($this->plugins[$pluginName]);
            }
            $this->messageHandlers = array_filter($this->messageHandlers, fn($m) => $m !== $pluginName);

            return ['success' => true, 'message' => "✅ <b>{$pluginName}</b> kənar plugini uğurla silindi."];
        }

        return [
            'success' => false,
            'message' => "⚠️ <b>{$pluginName}</b> adlı kənar plugin tapılmadı.\n<i>Silə biləcəyiniz kənar pluginlərin siyahısı üçün:</i> <code>.puninstall</code>"
        ];
    }

    public function getCustomPlugins(int $userId): array
    {
        $customs = [];
        $config = $this->getConfig();
        $customDir = $config['custom_plugins_dir'] . '/' . $this->ownerId;
        
        if (is_dir($customDir)) {
            foreach (glob($customDir . '/*.php') as $file) {
                $base = basename($file, '.php');
                $customs[$base] = [
                    'name' => $base,
                    'file' => $file
                ];
            }
        }
        
        if ($this->internalUserId) {
            $stmt = $this->db->prepare("SELECT plugin_name, plugin_file FROM user_plugins WHERE user_id = ? AND is_custom = 1");
            $stmt->execute([$this->internalUserId]);
            foreach ($stmt->fetchAll() as $row) {
                $name = $row['plugin_name'];
                if (!isset($customs[$name])) {
                    $customs[$name] = [
                        'name' => $name,
                        'file' => $row['plugin_file']
                    ];
                }
            }
        }
        
        return array_values($customs);
    }

    public function getPlugin(string $name): ?BasePlugin
    {
        return $this->plugins[$name] ?? null;
    }

    public function getAllPlugins(): array
    {
        return $this->plugins;
    }

    public function getCommandList(): array
    {
        return $this->commandMap;
    }

    public function handleCommand($message, string $command, array $args): bool
    {
        if (isset($this->commandMap[$command])) {
            $pluginName = $this->commandMap[$command];
            $plugin = $this->plugins[$pluginName];
            try {
                $plugin->onCommand($message, $command, $args);
            } catch (\Exception $e) {}
            return true;
        }
        return false;
    }

    public function handleMessage($message): void
    {
        foreach ($this->messageHandlers as $pluginName) {
            $plugin = $this->plugins[$pluginName];
            try {
                $plugin->onMessage($message);
            } catch (\Exception $e) {}
        }
    }

    public function enablePlugin(string $name, int $userId): bool
    {
        $stmt = $this->db->prepare("UPDATE user_plugins SET enabled = 1 WHERE user_id = ? AND plugin_name = ?");
        return $stmt->execute([$userId, $name]);
    }

    public function disablePlugin(string $name, int $userId): bool
    {
        $stmt = $this->db->prepare("UPDATE user_plugins SET enabled = 0 WHERE user_id = ? AND plugin_name = ?");
        return $stmt->execute([$userId, $name]);
    }
}
