"""Execute PHP CGI without a listening socket; no successful writes or mail sends."""
from pathlib import Path
import json,os,subprocess,tempfile
root=Path(__file__).resolve().parent.parent
count=0
with tempfile.TemporaryDirectory(prefix='sip-cgi-test-') as folder:
    folder=Path(folder)
    fixture=folder/'request.php'
    fixture.write_text("<?php session_start(); $_SESSION['csrf_token']='synthetic-csrf'; require " + repr(str(root/'includes/security.php')) + "; sip_check_request(); echo 'accepted';")
    cases=[('GET','/shop/products.php','',None,200),('GET','/shop/admin/products.php','delete=1',None,405),('POST','/shop/login.php','',None,403),('POST','/shop/login.php','','wrong',403),('POST','/shop/login.php','','synthetic-csrf',200)]
    for method,path,query,token,status in cases:
        env=dict(os.environ,REDIRECT_STATUS='1',REQUEST_METHOD=method,SCRIPT_FILENAME=str(fixture),SCRIPT_NAME=path,QUERY_STRING=query,CONTENT_LENGTH='0')
        if token:env['HTTP_X_CSRF_TOKEN']=token
        else:env.pop('HTTP_X_CSRF_TOKEN',None)
        r=subprocess.run(['php-cgi','-d',f'session.save_path={folder}'],env=env,capture_output=True,text=True)
        if status==200: assert 'accepted' in r.stdout,(method,path,r.stdout)
        else:assert f'Status: {status}' in r.stdout and 'accepted' not in r.stdout,(method,path,r.stdout)
        assert 'Warning:' not in r.stdout and 'Fatal error' not in r.stdout
        count+=1
    for name in ['login.php','admin/admin_login.php']:
        env=dict(os.environ,REDIRECT_STATUS='1',REQUEST_METHOD='POST',SCRIPT_FILENAME=str(root/name),SCRIPT_NAME='/'+name,JWT_SECRET='',CONTENT_TYPE='application/json',CONTENT_LENGTH='2')
        r=subprocess.run(['php-cgi','-d',f'session.save_path={folder}'],input='{}',env=env,capture_output=True,text=True)
        assert 'Status: 503' in r.stdout and 'Authentication is temporarily unavailable.' in r.stdout
        assert 'JWT_SECRET' not in r.stdout and 'Fatal error' not in r.stdout
        count+=1
print(f'PASS: {count} PHP CGI request guard and login error checks')
