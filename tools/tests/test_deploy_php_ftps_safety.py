import base64
import hashlib
import io
import json
from pathlib import Path
import tempfile
import unittest
from unittest.mock import patch
from tools import deploy_php_ftps as deploy


class DeploymentSafetyTest(unittest.TestCase):
    def test_migration_refuses_before_login_or_helper_creation(self):
        from tools import deploy_cpanel_session as session
        with patch.object(session.sys, 'argv', ['deploy', 'release', 'token', 'migrate']), patch.object(session.urllib.request, 'build_opener') as opener:
            with self.assertRaisesRegex(RuntimeError, 'Remote DDL disabled'):
                session.main()
            opener.assert_not_called()

    def test_extractor_requires_authenticated_post(self):
        source = deploy.extractor_source('.a777-package-fixture.zip', 'a'*64).decode()
        self.assertIn("!== 'POST'", source)
        self.assertIn('hash_equals', source)
        self.assertIn('HTTP_X_A777_DEPLOY', source)

    def test_real_php_extractor_refuses_get_and_wrong_token(self):
        import subprocess
        with tempfile.TemporaryDirectory() as temporary:
            script = Path(temporary) / 'extract.php'
            script.write_bytes(deploy.extractor_source('.a777-package-missing.zip', 'a'*64))
            for method, token in [('GET', 'a'*64), ('POST', 'b'*64)]:
                php = "$_SERVER['REQUEST_METHOD']=" + json.dumps(method) + ";$_SERVER['HTTP_X_A777_DEPLOY']=" + json.dumps(token) + ";register_shutdown_function(function(){fwrite(STDERR,(string)http_response_code());});include " + json.dumps(script.as_posix()) + ";"
                result = subprocess.run(['php', '-r', php], capture_output=True, text=True, check=True)
                self.assertEqual('404', result.stderr)
                self.assertEqual('', result.stdout)

    def test_verification_refuses_http(self):
        with self.assertRaisesRegex(RuntimeError, 'HTTPS'):
            deploy.deployment_opener('http://example.invalid/')

    def test_delete_verifies_absence_and_permission_failure_is_not_absence(self):
        client = deploy.Ftps.__new__(deploy.Ftps)
        client.ftp = unittest.mock.MagicMock()
        client.ftp.nlst.return_value = ['/parent/helper.php']
        with self.assertRaisesRegex(RuntimeError, 'cleanup unverified'):
            client.delete('/parent/helper.php')
        client.ftp.nlst.side_effect = deploy.ftplib.error_perm('550 denied')
        with self.assertRaises(deploy.ftplib.error_perm):
            client.exists('/parent/helper.php')

    def release(self, root):
        root=Path(root);(root/'public').mkdir();(root/'vendor').mkdir()
        for name,data in {'public/build-info.json':b'{"sourceCommit":"unit"}','public/index.php':b'<?php','public/.htaccess':b'RewriteEngine On','bootstrap.php':b'<?php','vendor/autoload.php':b'<?php','release.json':b'{}'}.items():
            (root/name).write_bytes(data)
        (root/'release.json').write_text(json.dumps({'format':'architex-release-v1','environment':'production','sourceDirty':False,'sourceCommit':'a'*40,'sourceSnapshotSha256':'b'*64}))
        self.manifest(root)
        return root

    def manifest(self,root):
        files={p.relative_to(root).as_posix():{'sha256':hashlib.sha256(p.read_bytes()).hexdigest(),'bytes':p.stat().st_size} for p in root.rglob('*') if p.is_file() and p.name!='release-manifest.json'}
        (root/'release-manifest.json').write_text(json.dumps({'format':'architex-release-manifest-v1','files':files}))

    def test_production_rejects_dirty_staging_or_missing_provenance_before_connect(self):
        for change in [{'sourceDirty':True},{'environment':'staging'},{'sourceCommit':None},{'sourceSnapshotSha256':None},{}]:
            with self.subTest(change=change),tempfile.TemporaryDirectory() as temp:
                release=self.release(temp);metadata=json.loads((release/'release.json').read_text()) if change else {}
                metadata.update(change);(release/'release.json').write_text(json.dumps(metadata));self.manifest(release)
                args=['deploy','--release',str(release),'--ftp-env','unused','--remote-parent','/parent','--target-url','https://example.invalid']
                with patch.object(deploy.sys,'argv',args),patch.object(deploy,'Ftps') as connection:
                    with self.assertRaisesRegex(RuntimeError,'provenance'):deploy.main()
                    connection.assert_not_called()

    def test_staging_requires_explicit_environment_and_keeps_runtime_staging(self):
        with tempfile.TemporaryDirectory() as temp:
            release=self.release(temp);metadata=json.loads((release/'release.json').read_text());metadata.update(environment='staging',sourceDirty=True,sourceCommit=None)
            (release/'release.json').write_text(json.dumps(metadata));self.manifest(release)
            deploy.validate_release(release,'staging')
            env={key:'unit-fixture-value' for key in deploy.REQUIRED_RUNTIME_KEYS};env['A777_APP_KEY']=base64.b64encode(b'x'*32).decode();env['A777_BACKUP_ARCHIVE_PASSWORD']='unit-rehearsal-password-only'
            self.assertIn(b"'APP_ENV' => 'staging'",deploy.build_runtime_config('https://example.invalid','/private',env,'staging'))

    def test_manifest_hash_and_unexpected_paths_checked(self):
        with tempfile.TemporaryDirectory() as temp:
            release=self.release(temp);deploy.validate_release(release)
            (release/'public/index.php').write_text('tampered')
            with self.assertRaisesRegex(RuntimeError,'manifest'):deploy.validate_release(release)
            self.manifest(release);(release/'config').mkdir();(release/'config/runtime.php').write_text('<?php return [];');self.manifest(release)
            with self.assertRaisesRegex(RuntimeError,'forbidden'):deploy.validate_release(release)

    def test_html_homepage_status_check_does_not_parse_json(self):
        response=unittest.mock.MagicMock();response.__enter__.return_value=response;response.status=200;response.read.return_value=b'<!doctype html><title>Architex</title>'
        with patch.object(deploy,'deployment_opener') as opener:
            opener.return_value.open.return_value=response
            self.assertEqual(deploy.http_status('https://example.invalid/'),200)

    def test_runtime_requires_existing_stable_application_key(self):
        env={key:'unit-fixture-value' for key in deploy.REQUIRED_RUNTIME_KEYS};env.pop('A777_APP_KEY',None)
        with self.assertRaisesRegex(RuntimeError,'A777_APP_KEY'):deploy.build_runtime_config('https://example.invalid','/private',env)

    def test_every_swap_failure_before_and_after_remote_effect_restores_old_paths(self):
        for fail_at in range(1,5):
            for after_effect in [False,True]:
                with self.subTest(rename=fail_at,after_effect=after_effect),tempfile.TemporaryDirectory() as temp:
                    self.run_fake_deployment(temp,fail_at,after_effect)

    def test_every_postswap_http_failure_restores_old_paths(self):
        for path in ['/', '/api/v1/health', '/api/v1/readiness', '/api/v1/legal-notices/signup']:
            with self.subTest(path=path), tempfile.TemporaryDirectory() as temp:
                self.run_fake_deployment(temp,0,False,path)

    def test_rollback_errors_are_explicit_and_other_path_is_still_restored(self):
        class Fake:
            paths={'old-public','public','old-runtime','runtime'}
            def exists(self,path):return path in self.paths
            def rename(self,a,b):
                if a=='public':raise RuntimeError('injected persistent transport failure')
                self.paths.remove(a);self.paths.add(b)
        fake=Fake()
        with self.assertRaisesRegex(RuntimeError,'Rollback incomplete for: public'):
            deploy.rollback_swap(fake,[('public','public','old-public','candidate-public',True),('runtime','runtime','old-runtime','candidate-runtime',True)])
        self.assertIn('runtime',fake.paths);self.assertNotIn('old-runtime',fake.paths)

    def test_tampered_artifact_fails_before_connect(self):
        with tempfile.TemporaryDirectory() as temp:
            release=self.release(temp);(release/'public/index.php').write_text('tampered')
            args=['deploy','--release',str(release),'--ftp-env','nonexistent','--remote-parent','/parent','--target-url','https://example.invalid']
            with patch.object(deploy.sys,'argv',args),patch.object(deploy,'Ftps') as connection:
                with self.assertRaisesRegex(RuntimeError,'manifest'):deploy.main()
                connection.assert_not_called()

    def test_missing_existing_runtime_configuration_fails_closed(self):
        with tempfile.TemporaryDirectory() as temp:
            release=self.release(temp)
            args=['deploy','--release',str(release),'--ftp-env','unused','--remote-parent','/parent','--target-url','https://example.invalid']
            with patch.object(deploy.sys,'argv',args),patch.dict(deploy.os.environ,{},clear=True),patch.object(deploy,'env_file_values',return_value={'FTP_HOST':'example.invalid','FTP_USER':'unit','FTP_PASS':'unit-only'}),patch.object(deploy,'Ftps') as connection:
                connection.return_value.exists.return_value=False
                connection.return_value.read_bytes.return_value=b''
                with self.assertRaisesRegex(RuntimeError,'Existing private runtime'):deploy.main()

    def run_fake_deployment(self,temp,fail_at,after_effect,failed_http_path=None):
        release=self.release(temp);ftp_env=Path(temp)/'ftp.env';ftp_env.write_text('FTP_HOST=example.invalid\nFTP_USER=unit\nFTP_PASS=unit-only\n')
        # Credential files cannot be part of the artifact.
        ftp_env.unlink();ftp_env=Path(temp).parent/(Path(temp).name+'-ftp.env');ftp_env.write_text('FTP_HOST=example.invalid\nFTP_USER=unit\nFTP_PASS=unit-only\n')
        class Fake:
            def __init__(self):self.paths={'/parent/.a777-runtime-active':'old-runtime','/parent/site':'old-public'};self.calls=0
            def exists(self,p):return p in self.paths
            def mkdirs(self,p):self.paths[p]='directory'
            def put_file(self,*args):pass
            def put_bytes(self,*args):pass
            def read_bytes(self,*args):return b'<?php return [];'
            def delete(self,*args):pass
            def close(self):pass
            def rename(self,a,b):
                self.calls+=1
                if self.calls==fail_at and not after_effect:raise KeyboardInterrupt('injected interruption')
                if b in self.paths:raise RuntimeError('destination exists')
                self.paths[b]=self.paths.pop(a)
                if self.calls==fail_at and after_effect:raise KeyboardInterrupt('injected interruption')
        fake=Fake()
        def archive(source,destination,runtime,public,active):
            fake.paths['/parent/'+runtime]='new-runtime';fake.paths['/parent/'+public]='new-public';destination.write_bytes(b'archive');return 1,7
        args=['deploy','--release',str(release),'--ftp-env',str(ftp_env),'--remote-parent','/parent','--target-name','site','--target-url','https://example.invalid']
        env={key:'unit-fixture-value' for key in deploy.REQUIRED_RUNTIME_KEYS};env['A777_APP_KEY']=base64.b64encode(b'x'*32).decode();env['A777_BACKUP_ARCHIVE_PASSWORD']='unit-rehearsal-password-only'
        try:
            status=lambda url:503 if failed_http_path and url=='https://example.invalid'+failed_http_path else 200
            json_check=lambda url,*args:(200, {'sourceCommit':'unit'} if url.endswith('/build-info.json') else {'ok':True,'data':{'status':'ready','components':{name:{'status':'ready'} for name in ('database','migrations','jobs','mail')}}})
            with patch.object(deploy.sys,'argv',args),patch.dict(deploy.os.environ,env,clear=True),patch.object(deploy,'Ftps',return_value=fake),patch.object(deploy,'build_transport_archive',side_effect=archive),patch.object(deploy,'http_json',side_effect=json_check),patch.object(deploy,'http_status',side_effect=status),patch.object(deploy.sys,'stdout',io.StringIO()):
                with self.assertRaises(RuntimeError if failed_http_path else KeyboardInterrupt):deploy.main()
            self.assertEqual(fake.paths.get('/parent/.a777-runtime-active'),'old-runtime')
            self.assertEqual(fake.paths.get('/parent/site'),'old-public')
        finally:ftp_env.unlink(missing_ok=True)

if __name__=='__main__':unittest.main()
