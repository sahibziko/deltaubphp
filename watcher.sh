#!/bin/bash
cd "$(dirname "$0")"

PIDFILE="sessions/watcher.pid"
if [ -f "$PIDFILE" ]; then
    OLDPID=$(cat "$PIDFILE" 2>/dev/null)
    if [ -n "$OLDPID" ] && ps -p "$OLDPID" > /dev/null 2>&1; then
        exit 0
    fi
fi

mkdir -p sessions
echo $$ > "$PIDFILE"

# 5 saniyəlik nəzarət dövrü (loqu şişirtmir, səssiz və etibarlı işləyir)
while true; do
    # Saytdan yeni istifadəçi daxil olduqda reload tələbi yaranır
    if [ -f "sessions/reload.flag" ]; then
        rm -f sessions/reload.flag
        pkill -9 -f 'php start.php' 2>/dev/null
        pkill -9 -f 'MadelineProto worker' 2>/dev/null
        rm -f sessions/bot.lock sessions/*/lock sessions/*/*.lock
        sleep 1
    fi

    # Əgər start.php işləmirsə, tək bir nüsxə başlat
    if ! pgrep -f 'php start.php' > /dev/null 2>&1; then
        rm -f sessions/bot.lock
        php start.php >> logs/bot.log 2>&1 &
    fi

    sleep 5
done
