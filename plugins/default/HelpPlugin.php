<?php
use App\BasePlugin;
use danog\MadelineProto\ParseMode;

class HelpPlugin extends BasePlugin {
    public string $name = 'Kömək';
    public string $description = 'Bütün pluginlərin və əmrlərin siyahısını göstərir (.help).';
    public array $commands = ['help' => 'Kömək menyusunu göstərir (.help və ya .help <plugin>)'];

    public function onCommand($message, string $command, array $args): void {
        try {
            $prefix = $this->getPrefix();
            
            if (empty($args)) {
                if (!isset($this->api->pluginManager)) {
                    $message->editText("⚠️ Plugin meneceri tapılmadı.");
                    return;
                }

                $allPlugins = $this->api->pluginManager->getAllPlugins();
                if (empty($allPlugins)) {
                    $message->editText("⚠️ Heç bir aktiv plugin tapılmadı.");
                    return;
                }

                // Hər bir plugin üçün məlumat blokunu hazırlayırıq (SudoPlugin yalnız .sudo ilə açılır, .help-dən gizlədilir)
                $pluginBlocks = [];
                foreach ($allPlugins as $p) {
                    if ($p instanceof SudoPlugin || get_class($p) === 'SudoPlugin' || ($p->name ?? '') === 'Sudo Admin İdarəsi') {
                        continue;
                    }
                    $pName = htmlspecialchars($p->name ?? 'Naməlum', ENT_QUOTES, 'UTF-8');
                    $pDesc = htmlspecialchars($p->description ?? '', ENT_QUOTES, 'UTF-8');
                    $cmds = [];
                    if (!empty($p->commands) && is_array($p->commands)) {
                        foreach (array_keys($p->commands) as $c) {
                            $cmds[] = "<code>" . htmlspecialchars($prefix . $c, ENT_QUOTES, 'UTF-8') . "</code>";
                        }
                    }
                    $cmdStr = !empty($cmds) ? implode(', ', $cmds) : 'Komanda yoxdur';
                    $pluginBlocks[] = "🔹 <b>{$pName}</b>: {$pDesc}\n   {$cmdStr}\n\n";
                }

                // Telegram mesaj limitini (4096 simvol) aşmamaq üçün blokları təhlükəsiz hissələrə (chunks) bölürük (~3000 simvol)
                $chunks = [];
                $currentText = "";

                foreach ($pluginBlocks as $block) {
                    if (mb_strlen($currentText . $block, 'UTF-8') > 3000) {
                        if ($currentText !== "") {
                            $chunks[] = $currentText;
                        }
                        $currentText = $block;
                    } else {
                        $currentText .= $block;
                    }
                }
                if ($currentText !== "") {
                    $chunks[] = $currentText;
                }

                $totalParts = count($chunks);

                // Hissələri ardıcıl göndəririk: 1-ci hissə mövcud mesajı redaktə edir, qalan hissələr ardınca yeni mesaj kimi göndərilir
                for ($i = 0; $i < $totalParts; $i++) {
                    $partNum = $i + 1;
                    $header = "📚 <b>Delta PHP Userbot Kömək Menyu</b>" . ($totalParts > 1 ? " ({$partNum}/{$totalParts})" : "") . "\n";
                    $header .= " Prefiks: <code>" . htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8') . "</code>\n\n";

                    $footer = ($partNum === $totalParts) ? "\nℹ️ <i>Ətraflı: <code>" . htmlspecialchars($prefix, ENT_QUOTES, 'UTF-8') . "help &lt;plugin_adı&gt;</code></i>" : "";

                    $finalText = $header . $chunks[$i] . $footer;

                    if ($i === 0) {
                        $message->editText($finalText, parseMode: ParseMode::HTML);
                    } else {
                        \Amp\delay(0.6);
                        $this->api->sendMessage(
                            $message->chatId,
                            $finalText,
                            parseMode: ParseMode::HTML
                        );
                    }
                }

            } else {
                $query = strtolower(trim(implode(' ', $args)));
                $found = null;

                if (isset($this->api->pluginManager)) {
                    $allPlugins = $this->api->pluginManager->getAllPlugins();
                    foreach ($allPlugins as $p) {
                        if ($p instanceof SudoPlugin || get_class($p) === 'SudoPlugin' || ($p->name ?? '') === 'Sudo Admin İdarəsi') {
                            continue;
                        }
                        if (strtolower($p->name) === $query || strtolower(get_class($p)) === $query) {
                            $found = $p;
                            break;
                        }
                        if (!empty($p->commands) && is_array($p->commands)) {
                            if (array_key_exists($query, $p->commands)) {
                                $found = $p;
                                break;
                            }
                        }
                    }
                }

                if ($found) {
                    $pName = htmlspecialchars($found->name, ENT_QUOTES, 'UTF-8');
                    $pDesc = htmlspecialchars($found->description, ENT_QUOTES, 'UTF-8');
                    $text = "📖 <b>Plugin: {$pName}</b>\n";
                    $text .= "📝 <b>Təsvir:</b> {$pDesc}\n\n";
                    $text .= "⚙️ <b>Komandalar:</b>\n";

                    foreach ($found->commands as $cmd => $desc) {
                        $cName = htmlspecialchars($prefix . $cmd, ENT_QUOTES, 'UTF-8');
                        $cDesc = htmlspecialchars($desc, ENT_QUOTES, 'UTF-8');
                        $text .= "• <code>{$cName}</code> - {$cDesc}\n";
                    }

                    $message->editText($text, parseMode: ParseMode::HTML);
                } else {
                    $qEsc = htmlspecialchars($query, ENT_QUOTES, 'UTF-8');
                    $message->editText("❌ <code>{$qEsc}</code> adında plugin və ya komanda tapılmadı.", parseMode: ParseMode::HTML);
                }
            }
        } catch (\Throwable $e) {
            $err = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            try {
                $message->editText("❌ <b>Xəta baş verdi:</b> " . $err, parseMode: ParseMode::HTML);
            } catch (\Throwable $t) {
                try {
                    $message->editText("❌ Xəta baş verdi: " . $err);
                } catch (\Throwable $t2) {}
            }
        }
    }
}
