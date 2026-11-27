#!/usr/bin/env python3
"""A tiny SMTP server for tests only: accepts every message and saves it as a file.
   python3 tools/fake-smtp.py PORT OUTDIR [--refuse-auth]"""
import socket, sys, os, threading, time

port, outdir = int(sys.argv[1]), sys.argv[2]
refuse = '--refuse-auth' in sys.argv
os.makedirs(outdir, exist_ok=True)

def handle(conn):
    f = conn.makefile('rwb')
    def say(s): f.write((s + '\r\n').encode()); f.flush()
    say('220 fake ESMTP')
    rcpt, data, auth_step = [], None, 0
    while True:
        line = f.readline()
        if not line: break
        l = line.decode(errors='replace').rstrip('\r\n')
        if data is not None:
            if l == '.':
                name = os.path.join(outdir, '%d-%d.eml' % (int(time.time() * 1000), os.getpid()))
                with open(name, 'w') as out: out.write('X-Rcpt: ' + ','.join(rcpt) + '\n' + '\n'.join(data))
                data = None; say('250 queued')
            else:
                data.append(l[1:] if l.startswith('..') else l)
            continue
        cmd = l.split(' ')[0].upper()
        if auth_step == 1: auth_step = 2; say('334 UGFzc3dvcmQ6'); continue
        if auth_step == 2:
            auth_step = 0; say('535 bad credentials' if refuse else '235 ok'); continue
        if cmd == 'EHLO': say('250-fake'); say('250 AUTH LOGIN')
        elif cmd == 'AUTH': auth_step = 1; say('334 VXNlcm5hbWU6')
        elif cmd == 'MAIL': rcpt = []; say('250 ok')
        elif cmd == 'RCPT': rcpt.append(l.split(':', 1)[1].strip('<> ')); say('250 ok')
        elif cmd == 'DATA': data = []; say('354 go')
        elif cmd == 'QUIT': say('221 bye'); break
        else: say('250 ok')
    conn.close()

s = socket.socket(); s.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
s.bind(('127.0.0.1', port)); s.listen(5)
while True:
    c, _ = s.accept(); threading.Thread(target=handle, args=(c,), daemon=True).start()
