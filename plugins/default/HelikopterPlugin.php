<?php
use App\BasePlugin;

class HelikopterPlugin extends BasePlugin {
    public string $name = 'Helikopter (Kardeşim Helikopter)';
    public string $description = 'Kardeşim helikopter mahnı animasiyası (.krdsm).';
    public array $commands = [
        'krdsm' => 'Helikopter mahnı animasiyası (.krdsm)'
    ];

    public function onCommand($message, string $command, array $args): void {
        try {
            $frames = [
                "**▬▬▬.◙.▬▬▬\n═▂▄▄▓▄▄▂\n◢◤ █▀▀████▄▄▄◢◤\n█▄ █ █▄ ███▀▀▀▀▀▀╬\n◥█████◤\n══╩══╩═\nHER AN DÖNEBİLİR DEVRAN**",
                "**ㅤ▬▬.◙.▬▬\n═▂▄▄▓▄▄▂\n◢◤ █▀▀████▄▄▄◢◤\n█▄ █ █▄ ███▀▀▀▀▀▀╬\n◥█████◤\n══╩══╩═\nKERVAN, BU GECE DE KERVAN**",
                "**ㅤㅤ▬.◙.▬\n═▂▄▄▓▄▄▂\n◢◤ █▀▀████▄▄▄◢◤\n█▄ █ █▄ ███▀▀▀▀▀▀╬\n◥█████◤\n══╩══╩═\nDERMAN, DOLUSU VER LAN**",
                "**ㅤㅤㅤ.◙.\n═▂▄▄▓▄▄▂\n◢◤ █▀▀████▄▄▄◢◤\n█▄ █ █▄ ███▀▀▀▀▀▀╬\n◥█████◤\n══╩══╩═\nŞİRKETİM GİYDİYİRO DİZOLARI NİKEDAN**",
                "**ㅤㅤ▬.◙.▬\n═▂▄▄▓▄▄▂\n◢◤ █▀▀████▄▄▄◢◤\n█▄ █ █▄ ███▀▀▀▀▀▀╬\n◥█████◤\n══╩══╩═\nBUNU AL YENİ GÖNDER**",
                "**ㅤ▬▬.◙.▬▬\n═▂▄▄▓▄▄▂\n◢◤ █▀▀████▄▄▄◢◤\n█▄ █ █▄ ███▀▀▀▀▀▀╬\n◥█████◤\n══╩══╩═\nPRRRR**",
                "**▬▬▬.◙.▬▬▬\n═▂▄▄▓▄▄▂\n◢◤ █▀▀████▄▄▄◢◤ㅤ\n█▄ █ █▄ ███▀▀▀▀▀▀╬\n◥█████◤\n══╩══╩═\nKARDEŞİM HELİKOPTER PAT PAT**",
                "**▬▬▬.◙.▬▬▬\n═▂▄▄▓▄▄▂\n◢◤ █▀▀████▄▄▄◢◤\n█▄ █ █▄ ███▀▀▀▀▀▀╬\n◥█████◤\n══╩══╩═\nYAT YERE DEDİ AMCA**",
                "**▬▬▬.◙.▬▬▬\n═▂▄▄▓▄▄▂\n◢◤ █▀▀████▄▄▄◢◤\n█▄ █ █▄ ███▀▀▀▀▀▀╬\n◥█████◤\n══╩══╩═\nMEKANDA KİŞİ BAŞI DÖRD KARI ANCA RÜYANDA**",
                "**▬▬▬.◙.▬▬▬\n═▂▄▄▓▄▄▂\n◢◤ █▀▀████▄▄▄◢◤\n█▄ █ █▄ ███▀▀▀▀▀▀╬\n◥█████◤\n══╩══╩═\nALKOL Bİ YANDA, KURUSU ELİMDE, GÜMÜŞ DİŞİMDE, ALTIN CEBİMDE**",
                "**▬▬▬.◙.▬▬▬\n═▂▄▄▓▄▄▂\n◢◤ █▀▀████▄▄▄◢◤\n█▄ █ █▄ ███▀▀▀▀▀▀╬\n◥█████◤\n══╩══╩═\nÜZERİM BEN SENİ, HEMEN SEVİNME**",
                "▬▬▬.◙.▬▬▬\n═▂▄▄▓▄▄▂\n◢◤ █▀▀████▄▄▄◢◤\n█▄ █ █▄ ███▀▀▀▀▀▀╬\n◥█████◤\n══╩══╩═\nSOĞUYORUM MÜCADELE OLMADAN İŞTE",
                "▬▬▬.◙.▬▬▬\n═▂▄▄▓▄▄▂\n◢◤ █▀▀████▄▄▄◢◤\n█▄ █ █▄ ███▀▀▀▀▀▀╬\n◥█████◤\n══╩══╩═\nVARSA HER İŞTE Bİ HAYIR",
                "▬▬▬.◙.▬▬▬\n═▂▄▄▓▄▄▂\n◢◤ █▀▀████▄▄▄◢◤\n█▄ █ █▄ ███▀▀▀▀▀▀╬\n◥█████◤\n══╩══╩═\nGÜZELİM BU GECEYİ BENİM İÇİN AYIR",
                "▬▬▬.◙.▬▬▬\n═▂▄▄▓▄▄▂\n◢◤ █▀▀████▄▄▄◢◤\n█▄ █ █▄ ███▀▀▀▀▀▀╬\n◥█████◤\n══╩══╩═\nKARDEŞİM HELİKOPTER PAT PAT"
            ];

            foreach ($frames as $frame) {
                try {
                    $message->editText($frame);
                } catch (\danog\MadelineProto\FloodWaitException $fw) {
                    break;
                } catch (\Throwable $t) {}

                \Amp\delay(0.7);
            }

        } catch (\Throwable $e) {
            $err = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $message->editText("❌ Xəta: " . $err);
        }
    }
}
