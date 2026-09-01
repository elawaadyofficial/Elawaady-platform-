<?php
require_once __DIR__ . '/db_connect.php';

function provider_secret_key(): string {
    $k = getenv('APP_ENCRYPTION_KEY') ?: '';
    if ($k === '') return '';
    return hash('sha256', $k, true);
}
function encrypt_provider_key(string $plain): string {
    $key = provider_secret_key();
    if ($key === '') throw new RuntimeException('APP_ENCRYPTION_KEY غير مضبوط على الاستضافة');
    $iv = random_bytes(12); $tag = '';
    $ct = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv.$tag.$ct);
}
function decrypt_provider_key(?string $enc): string {
    if (!$enc) return '';
    $key = provider_secret_key(); if ($key === '') return '';
    $raw = base64_decode($enc, true); if ($raw === false || strlen($raw)<28) return '';
    $iv=substr($raw,0,12); $tag=substr($raw,12,16); $ct=substr($raw,28);
    $pt=openssl_decrypt($ct,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);
    return $pt === false ? '' : $pt;
}
function provider_call(array $provider, array $payload): array {
    $key = decrypt_provider_key($provider['api_key_encrypted'] ?? '');
    if ($key === '') throw new RuntimeException('مفتاح API للمورد غير متاح');
    $payload['key']=$key;
    $ch=curl_init($provider['api_url']);
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($payload),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_SSL_VERIFYPEER=>true]);
    $body=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); $err=curl_error($ch); curl_close($ch);
    if ($body===false || $err) throw new RuntimeException('فشل الاتصال بمزود الخدمة');
    $data=json_decode($body,true);
    if ($code<200 || $code>=300 || !is_array($data)) throw new RuntimeException('استجابة غير صالحة من مزود الخدمة');
    return $data;
}
function provider_get(int $id): ?array { global $conn; return fetch_one($conn,'SELECT * FROM service_providers WHERE id=? AND is_active=1','i',$id); }
function provider_services(array $p): array { return provider_call($p,['action'=>'services']); }
function provider_balance(array $p): array { return provider_call($p,['action'=>'balance']); }
function provider_add_order(array $p,string $service,string $link,int $qty): array { return provider_call($p,['action'=>'add','service'=>$service,'link'=>$link,'quantity'=>$qty]); }
function provider_order_status(array $p,string $orderId): array { return provider_call($p,['action'=>'status','order'=>$orderId]); }
?>