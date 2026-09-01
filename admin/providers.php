<?php
require_once __DIR__.'/auth.php'; require_once __DIR__.'/../db_connect.php'; require_once __DIR__.'/../provider_client.php';
$msg=''; $err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 $act=$_POST['act']??'';
 try{
  if($act==='save'){
   $id=(int)($_POST['id']??0); $name=trim($_POST['name']??''); $url=trim($_POST['api_url']??''); $cur=trim($_POST['currency']??'USD'); $key=trim($_POST['api_key']??'');
   if(!$name||!filter_var($url,FILTER_VALIDATE_URL)) throw new RuntimeException('اسم المزود ورابط API الصحيح مطلوبان.');
   if($id){
    if($key!==''){ $enc=encrypt_provider_key($key); $st=$conn->prepare('UPDATE service_providers SET name=?,api_url=?,currency=?,api_key_encrypted=? WHERE id=?'); $st->bind_param('ssssi',$name,$url,$cur,$enc,$id); }
    else { $st=$conn->prepare('UPDATE service_providers SET name=?,api_url=?,currency=? WHERE id=?'); $st->bind_param('sssi',$name,$url,$cur,$id); }
   } else { if($key==='') throw new RuntimeException('مفتاح API مطلوب عند إضافة المزود لأول مرة.'); $enc=encrypt_provider_key($key); $st=$conn->prepare('INSERT INTO service_providers(name,api_url,currency,api_key_encrypted) VALUES(?,?,?,?)'); $st->bind_param('ssss',$name,$url,$cur,$enc); }
   $st->execute(); $msg='تم حفظ المزود بأمان.';
  } elseif($act==='balance') { $p=provider_get((int)$_POST['id']); if(!$p) throw new RuntimeException('المزود غير موجود'); $b=provider_balance($p); $msg='الرصيد: '.e($b['balance']??'—').' '.e($b['currency']??$p['currency']); }
 }catch(Throwable $e){$err=$e->getMessage();}
}
$providers=fetch_all($conn,'SELECT id,name,api_url,currency,is_active,last_balance,last_sync_at FROM service_providers ORDER BY id DESC');
$page_title_admin='مزودو API / السيرفرات'; include __DIR__.'/layout.php'; ?>
<?php if($msg):?><div class="alert alert-success"><?= e($msg) ?></div><?php endif;?><?php if($err):?><div class="alert alert-error"><?= e($err) ?></div><?php endif;?>
<div class="panel"><div class="panel-header"><div class="panel-title">🔌 إضافة / تعديل سيرفر SMM</div></div><form method="post" class="form-grid" style="padding:16px"><input type="hidden" name="act" value="save"><input class="form-input" name="name" placeholder="اسم السيرفر" required><input class="form-input" name="api_url" dir="ltr" placeholder="https://server.example/api/v2" required><input class="form-input" name="api_key" dir="ltr" type="password" placeholder="API Key — يتم تشفيره قبل التخزين"><input class="form-input" name="currency" value="USD" placeholder="USD"><button class="btn btn-primary">حفظ المزود</button></form></div>
<div class="panel" style="margin-top:16px"><table class="admin-table"><thead><tr><th>المزود</th><th>API</th><th>العملة</th><th>فحص</th></tr></thead><tbody><?php foreach($providers as $p):?><tr><td><?= e($p['name']) ?></td><td dir="ltr"><?= e($p['api_url']) ?></td><td><?= e($p['currency']) ?></td><td><form method="post"><input type="hidden" name="act" value="balance"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-secondary btn-sm">فحص الرصيد</button></form></td></tr><?php endforeach;?></tbody></table></div>
</div></div></div></body></html>