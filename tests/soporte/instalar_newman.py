"""Descarga herramientas; no aporta datos del proyecto."""
import pathlib,urllib.request,tarfile,subprocess,os
import playwright
root=pathlib.Path('/tmp/menu08-qa-runtime')
npm=root/'npm.tgz'
urllib.request.urlretrieve('https://registry.npmjs.org/npm/-/npm-10.9.3.tgz',npm)
dest=root/'npm';dest.mkdir(exist_ok=True)
with tarfile.open(npm) as t:t.extractall(dest,filter='data')
node=pathlib.Path(playwright.__file__).resolve().parent/'driver/node'
bin=root/'bin';bin.mkdir(exist_ok=True)
if not (bin/'node').exists():(bin/'node').symlink_to(node)
env=dict(os.environ,PATH=str(bin)+':'+os.environ['PATH'],npm_config_cache=str(root/'npm-cache'))
subprocess.run([str(node),str(dest/'package/bin/npm-cli.js'),'install','--prefix',str(root/'newman'),'newman@6.2.1','@zxing/library@0.21.3','pngjs@7.0.0'],env=env,check=True)
