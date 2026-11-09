"""Isolated CI-only TLS/SMTP fixtures; no connection to real mailboxes or payment systems."""
import http.client,http.server,ssl,socketserver,threading,sys
cert,key=sys.argv[1:3]
ctx=ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER);ctx.load_cert_chain(cert,key)
class Proxy(http.server.BaseHTTPRequestHandler):
 def handle_request(self):
  if self.path.startswith('/unsafe-redirect'):
   self.send_response(302);self.send_header('Location','http://pina.install.test/');self.send_header('Content-Length','0');self.end_headers();return
  size=int(self.headers.get('Content-Length','0'));body=self.rfile.read(size) if size else None
  c=http.client.HTTPConnection('127.0.0.1',8082,timeout=20);c.request(self.command,self.path,body,dict(self.headers));r=c.getresponse();data=r.read()
  self.send_response(r.status)
  for k,v in r.getheaders():
   if k.lower() not in ['connection','transfer-encoding','content-length']:self.send_header(k,v)
  self.send_header('Content-Length',str(len(data)));self.end_headers();self.wfile.write(data);c.close()
 do_GET=handle_request;do_POST=handle_request
 def log_message(self,*args):pass
class Mail(socketserver.StreamRequestHandler):
 def handle(self):
  def reply(t):self.wfile.write((t+'\r\n').encode());self.wfile.flush()
  reply('220 smtp.install.test ESMTP');auth=0;data=False
  while True:
   line=self.rfile.readline()
   if not line:return
   line=line.rstrip(b'\r\n')
   if data:
    if line==b'.':data=False;reply('250 Queued for CI test')
    continue
   if auth==1:auth=2;reply('334 UGFzc3dvcmQ6');continue
   if auth==2:auth=0;reply('235 Authenticated');continue
   cmd=line.upper()
   if cmd.startswith(b'EHLO'):reply('250-smtp.install.test');reply('250 AUTH LOGIN')
   elif cmd==b'AUTH LOGIN':auth=1;reply('334 VXNlcm5hbWU6')
   elif cmd.startswith((b'MAIL FROM:',b'RCPT TO:')):reply('250 OK')
   elif cmd==b'DATA':data=True;reply('354 End with dot')
   elif cmd==b'QUIT':reply('221 Bye');return
   else:reply('500 Unexpected command')
class TLSMail(socketserver.ThreadingTCPServer):
 allow_reuse_address=True;daemon_threads=True
 def get_request(self):
  s,a=super().get_request();return ctx.wrap_socket(s,server_side=True),a
mail=TLSMail(('127.0.0.1',465),Mail);threading.Thread(target=mail.serve_forever,daemon=True).start()
web=http.server.ThreadingHTTPServer(('127.0.0.1',443),Proxy);web.socket=ctx.wrap_socket(web.socket,server_side=True);web.serve_forever()
