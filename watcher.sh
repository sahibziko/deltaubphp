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
    php keepalive.php > /dev/null 2>&1
    sleep 5
done
