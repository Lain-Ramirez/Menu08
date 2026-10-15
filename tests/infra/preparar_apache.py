"""Apache aislado para probar las dos reglas .htaccess, incluido TLS local."""
from pathlib import Path
import os,shutil,subprocess
REPO=Path(__file__).resolve().parents[2]
RUNTIME=REPO/'tests/.runtime/apache'
tls=RUNTIME/'tls';tls.mkdir(parents=True,exist_ok=True)
subprocess.run(['openssl','req','-x509','-newkey','rsa:2048','-nodes','-days','2','-subj','/CN=localhost','-addext','subjectAltName=DNS:localhost,IP:127.0.0.1','-keyout',str(tls/'key.pem'),'-out',str(tls/'cert.pem')],check=True,capture_output=True)
for name,source in [('publica',REPO/'ADSO.menu08.com/.htaccess'),('aplicacion',REPO/'menu08_app/publico/.htaccess')]:
    root=RUNTIME/'sitios'/name;(root/'recursos/css').mkdir(parents=True,exist_ok=True)
    shutil.copy2(source,root/'.htaccess')
    shutil.copy2(REPO/'ADSO.menu08.com/recursos/css/md3.css',root/'recursos/css/md3.css')
    (root/'index.html').write_text('Servidor aislado de prueba HTTPS de Menu08')
args=['docker','run','--name','menu08-ga9-apache','-d','-p','127.0.0.1:8090:80','-p','127.0.0.1:8443:443']
for source,target in [(RUNTIME/'sitios','/usr/local/apache2/htdocs'),(tls,'/testssl'),(REPO/'tests/infra/apache.conf','/usr/local/apache2/conf/httpd.conf')]:
    args+=['--mount','type=bind,src='+str(source)+',dst='+target+',readonly']
args+=['httpd:2.4']
subprocess.run(args,check=True)
print('Apache HTTP 8090 y HTTPS 8443. Certificado de prueba: '+str(tls/'cert.pem'))
