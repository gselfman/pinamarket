<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
query('DELETE FROM pm_rate_buckets WHERE created_at < ?', [gmdate('Y-m-d H:i:s',time()-86400)]);
query('DELETE FROM pm_password_resets WHERE expires_at < ?', [now()]);
echo "Expired security records removed. Financial records unchanged.\n";
