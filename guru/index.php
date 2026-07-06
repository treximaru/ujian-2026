<?php
session_start();
header('Cache-Control:no-store,no-cache,must-revalidate');
header('Pragma:no-cache');
header('Expires:0');
$dataDir = __DIR__ . '/../data';
$uploadDir = __DIR__ . '/uploads';

function jr($f){global $dataDir;$p="$dataDir/$f.json";return file_exists($p)?json_decode(file_get_contents($p),true)??[]:[];}
function jw($f,$d){global $dataDir;file_put_contents("$dataDir/$f.json",json_encode($d,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));}
function isL(){return isset($_SESSION['uid']);}
function isA(){return isset($_SESSION['role'])&&$_SESSION['role']==='admin';}

function up($f,$s){global $uploadDir;$e=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));$x=['jpg','jpeg','png','gif','webp'];$y=['mp3','ogg','wav','m4a','webm'];$v=['mp4','webm','mkv','mov'];if(!in_array($e,array_merge($x,$y,$v)))return false;$n=uniqid().'.'.$e;$t="$uploadDir/$s/$n";if(move_uploaded_file($f['tmp_name'],$t)){chmod($t,0644);return"uploads/$s/$n";}return false;}
function csrf_token(){if(empty($_SESSION["csrf"]))$_SESSION["csrf"]=bin2hex(random_bytes(32));return$_SESSION["csrf"];}
function verifyPw($input,&$stored){if(password_verify($input,$stored))return true;$a=password_get_info($stored)["algo"];if($a!==null&&$a!==0)return false;if($stored===$input){$stored=password_hash($input,PASSWORD_BCRYPT);return true;}return false;}

if(isset($_GET['logout'])){session_destroy();header('Location: ?');exit;}
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['login'])){$now=time();$last=&$_SESSION['_last_login'];if($last&&$now-$last<2){sleep(2);}$last=$now;$g=jr('guru');foreach($g as &$x){if($x['nis']===$_POST['nis']&&verifyPw($_POST['password'],$x['password'])){$_SESSION['uid']=$x['id'];$_SESSION['nama']=$x['nama'];$_SESSION['mapel']=$x['mapel'];$_SESSION['role']=$x['role']??'guru';unset($_SESSION['_login_try']);jw('guru',$g);header('Location: ?');exit;}}unset($x);$_SESSION['_login_try']=($_SESSION['_login_try']??0)+1;if(($_SESSION['_login_try']??0)>5){sleep(5);}$e='NIS atau password salah!';}

// Guru CRUD
if(isL()&&isA()&&$_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['sp_guru'])){$g=jr('guru');$id=$_POST['gid']?:'g'.uniqid();$e=['id'=>$id,'nis'=>$_POST['gnis'],'nama'=>$_POST['gnama'],'mapel'=>$_POST['gmapel'],'password'=>password_hash($_POST['gpass'],PASSWORD_BCRYPT),'role'=>$_POST['grole']];$i=array_search($_POST['gid'],array_column($g,'id'));if($_POST['gid']&&$i!==false)$g[$i]=$e;else $g[]=$e;jw('guru',$g);header('Location: ?menu=guru&ok=1');exit;}
if(isL()&&isA()&&isset($_GET['hapus_guru'])){$g=jr('guru');$g=array_values(array_filter($g,fn($x)=>$x['id']!==$_GET['hapus_guru']));jw('guru',$g);header('Location: ?menu=guru');exit;}

// Ujian CRUD
if(isL()&&$_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['sp_ujian'])){
  $u=jr('ujian');$uid=$_POST['ujian_id']?:'u'.uniqid();
  $e=['id'=>$uid,'guru_id'=>$_SESSION['uid'],'judul'=>$_POST['judul'],'aktif'=>isset($_POST['aktif'])?1:0];
  $i=array_search($_POST['ujian_id'],array_column($u,'id'));
  if($_POST['ujian_id']&&$i!==false)$u[$i]=$e;else $u[]=$e;
  jw('ujian',$u);header('Location: ?menu=soal');exit;
}
if(isL()&&isset($_GET['hapus_ujian'])){$u=jr('ujian');$u=array_values(array_filter($u,fn($x)=>$x['id']!==$_GET['hapus_ujian']));jw('ujian',$u);$s=jr('soal');$s=array_values(array_filter($s,fn($x)=>$x['ujian_id']!==$_GET['hapus_ujian']));jw('soal',$s);header('Location: ?menu=soal');exit;}

// Siswa CRUD (JSON based)
if(isL()&&$_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['sp_siswa'])){$s=jr('siswa');$f=false;foreach($s as &$x){if($x['nis']===$_POST['snis']){$x=['nis'=>$_POST['snis'],'nama'=>$_POST['snama'],'kelas'=>$_POST['skelas'],'password'=>password_hash($_POST['spass'],PASSWORD_BCRYPT)];$f=true;break;}}unset($x);if(!$f)$s[]=['nis'=>$_POST['snis'],'nama'=>$_POST['snama'],'kelas'=>$_POST['skelas'],'password'=>password_hash($_POST['spass'],PASSWORD_BCRYPT)];jw('siswa',$s);header('Location: ?menu=siswa&ok=1');exit;}
if(isL()&&isset($_GET['hapus_siswa'])){$s=jr('siswa');$s=array_values(array_filter($s,fn($x)=>$x['nis']!==$_GET['hapus_siswa']));jw('siswa',$s);header('Location: ?menu=siswa');exit;}
if(isL()&&isset($_GET['hapus_semua_siswa'])){jw('siswa',[]);header('Location: ?menu=siswa');exit;}


// Import Siswa CSV
if(isL()&&isA()&&$_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['siswa_import'])){
  $text='';
  if(!empty($_POST['siswa_csv'])) $text=$_POST['siswa_csv'];
  elseif(isset($_FILES['siswa_file'])&&$_FILES['siswa_file']['error']===0) $text=file_get_contents($_FILES['siswa_file']['tmp_name']);
  $text=trim($text);
  if(!empty($text)){
    $siswa=jr('siswa');$ok=0;
    foreach(preg_split('/\
\
|\
|\
/',$text) as $line){
      $line=trim($line);if(empty($line))continue;
      $parts=str_getcsv($line);
      if(count($parts)>=4){
        $n=trim($parts[0]);$na=trim($parts[1]);$k=trim($parts[3]);$pw=trim($parts[4]??'12345');
        if(!empty($n)&&!empty($na)){
          $found=false;
          foreach($siswa as &$x){if($x['nis']===$n){$x=['nis'=>$n,'nama'=>$na,'kelas'=>$k,'password'=>password_hash($pw,PASSWORD_BCRYPT)];$found=true;break;}}unset($x);
          if(!$found)$siswa[]=['nis'=>$n,'nama'=>$na,'kelas'=>$k,'password'=>password_hash($pw,PASSWORD_BCRYPT)];
          $ok++;
        }
      }
    }
    jw('siswa',$siswa);header("Location: ?menu=siswa&ok=1&import=$ok");exit;
  }
  header('Location: ?menu=import&err=1');exit;
}

// Import Guru CSV
if(isL()&&isA()&&$_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['guru_import'])){
  $text='';
  if(!empty($_POST['guru_csv'])) $text=$_POST['guru_csv'];
  elseif(isset($_FILES['guru_file'])&&$_FILES['guru_file']['error']===0) $text=file_get_contents($_FILES['guru_file']['tmp_name']);
  $text=trim($text);
  if(!empty($text)){
    $guru=jr('guru');$ok=0;$uid=1;
    foreach($guru as $g){if($g['role']!='admin')$uid++;}
    foreach(preg_split('/\
\
|\
|\
/',$text) as $line){
      $line=trim($line);if(empty($line))continue;
      $parts=str_getcsv($line);
      if(count($parts)>=3){
        $n=trim($parts[0]);$na=trim($parts[1]);$m=trim($parts[2]);$pw=trim($parts[3]??'guru123');$role=trim($parts[4]??'guru');
        if(!empty($n)&&!empty($na)){
          $found=false;
          foreach($guru as &$x){if($x['nis']===$n){$x=['id'=>$x['id']??'g'.uniqid(),'nis'=>$n,'nama'=>$na,'mapel'=>$m,'password'=>password_hash($pw,PASSWORD_BCRYPT),'role'=>$role];$found=true;break;}}unset($x);
          if(!$found)$guru[]=['id'=>'g'.uniqid(),'nis'=>$n,'nama'=>$na,'mapel'=>$m,'password'=>password_hash($pw,PASSWORD_BCRYPT),'role'=>$role];
          $ok++;
        }
      }
    }
    jw('guru',$guru);header("Location: ?menu=guru&ok=1&import=$ok");exit;
  }
  header('Location: ?menu=import&err=1');exit;
}
// Toggle ujian â auto-toggle semua soal di dalamnya
if(isL()&&isset($_GET['toggle_ujian'])){$u=jr('ujian');$i=array_search($_GET['toggle_ujian'],array_column($u,'id'));if($i!==false){$newStatus=$u[$i]['aktif']?0:1;$u[$i]['aktif']=$newStatus;jw('ujian',$u);$s=jr('soal');foreach($s as &$soal){if($soal['ujian_id']===$_GET['toggle_ujian']){$soal['aktif']=$newStatus;}}unset($soal);jw('soal',$s);}header('Location: ?menu=soal');exit;}

// Soal CRUD
if(isL()&&$_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['sp_soal'])){
  $s=jr('soal');$id=$_POST['id']?:uniqid('s');$pil=[trim($_POST['pil0']??''),trim($_POST['pil1']??''),trim($_POST['pil2']??''),trim($_POST['pil3']??''),trim($_POST['pil4']??'')];
  $e=['id'=>$id,'guru_id'=>$_SESSION['uid'],'ujian_id'=>$_POST['ujian_id'],'pertanyaan'=>$_POST['pertanyaan'],'pilihan'=>$pil,'jawaban'=>(int)$_POST['jawaban'],'aktif'=>isset($_POST['aktif'])?1:0];
  $i=array_search($_POST['id'],array_column($s,'id'));
  if($_POST['id']&&$i!==false)$s[$i]=$e;else $s[]=$e;
  jw('soal',$s);header('Location: ?menu=soal&ujian='.$_POST['ujian_id'].'&ok=1');exit;
}
if(isL()&&isset($_GET['hapus_soal'])){
  $s=jr('soal');
  $hapusId=$_GET['hapus_soal'];
  $hapusUjian=$_GET['ujian']??'';
  $found=false;
  $s=array_values(array_filter($s,function($x) use($hapusId,&$found){if($x['id']===$hapusId&&!$found){$found=true;return false;}return true;}));
  jw('soal',$s);
  if($hapusUjian){header('Location: ?menu=soal&ujian='.urlencode($hapusUjian));}
  else{header('Location: ?menu=soal');}
  exit;
}

// Hapus nilai
if(isL()&&isset($_GET['hapus_nilai'])){$n=jr('nilai');$n=array_values(array_filter($n,fn($x)=>$x['id']!==$_GET['hapus_nilai']));jw('nilai',$n);header('Location: ?menu=nilai');exit;}
if(isL()&&isset($_GET['hapus_semua_nilai'])){jw('nilai',[]);header('Location: ?menu=nilai');exit;}
// Hapus log
if(isL()&&isset($_GET['hapus_log'])){$l=jr('log_curang');$l=array_values(array_filter($l,fn($x)=>$x['id']!==$_GET['hapus_log']));jw('log_curang',$l);header('Location: ?menu=monitor');exit;}
if(isL()&&isset($_GET['hapus_semua_log'])){jw('log_curang',[]);header('Location: ?menu=monitor');exit;}
// Hapus media
if(isL()&&isset($_GET['hapus_media'])){$p=realpath(__DIR__.'/'.$_GET['hapus_media']);if($p&&strpos($p,realpath(__DIR__.'/uploads'))===0&&file_exists($p)){unlink($p);}header('Location: ?menu=media');exit;}
if(isL()&&isset($_GET['hapus_semua_media'])){array_map('unlink',glob(__DIR__.'/uploads/images/*'));array_map('unlink',glob(__DIR__.'/uploads/audio/*'));array_map('unlink',glob(__DIR__.'/uploads/video/*'));header('Location: ?menu=media');exit;}
// Settings save
if(isL()&&$_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['sp_set'])){jw('settings',$_POST);header('Location: ?menu=pengaturan&ok=1');exit;}

// Media upload handler
if(isL()&&$_SERVER['REQUEST_METHOD']==='POST'&&isset($_FILES['media_upload'])){$e=strtolower(pathinfo($_FILES['media_upload']['name'],PATHINFO_EXTENSION));$img=['jpg','jpeg','png','gif','webp'];$vid=['mp4','webm','mkv','mov'];$sd=in_array($e,$img)?'images':(in_array($e,$vid)?'video':'audio');if(!is_dir(__DIR__."/uploads/$sd"))mkdir(__DIR__."/uploads/$sd",0755,true);$r=up($_FILES['media_upload'],$sd);if($r){header('Content-Type:application/json');echo json_encode(['ok'=>true,'ref'=>$r,'ext'=>$e,'subdir'=>$sd]);exit;}echo json_encode(['ok'=>false]);exit;}
if(isL()&&$_SERVER['REQUEST_METHOD']==='POST'&&isset($_FILES['media_modal'])){$e=strtolower(pathinfo($_FILES['media_modal']['name'],PATHINFO_EXTENSION));$img=['jpg','jpeg','png','gif','webp'];$vid=['mp4','webm','mkv','mov'];$sd=in_array($e,$img)?'images':(in_array($e,$vid)?'video':'audio');if(!is_dir(__DIR__."/uploads/$sd"))mkdir(__DIR__."/uploads/$sd",0755,true);$r=up($_FILES['media_modal'],$sd);if($r){header('Content-Type:application/json');echo json_encode(['ok'=>true,'ref'=>$r,'ext'=>$e,'subdir'=>$sd]);exit;}echo json_encode(['ok'=>false]);exit;}

// Aiken format import
if(isL()&&$_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['aiken_import'])){
  $text = '';
  if(!empty($_POST['aiken_text'])){
    $text = $_POST['aiken_text'];
  } elseif(isset($_FILES['aiken_file'])&&$_FILES['aiken_file']['error']===0){
    $text = file_get_contents($_FILES['aiken_file']['tmp_name']);
  }
  $text = trim($text);
  if(empty($text)){header('Location: ?menu=import&err=1');exit;}
  
  $lines = preg_split('/\r\n|\r|\n/', $text);
  $soalList = jr('soal');
  $ujianId = 'u'.uniqid();
  $judul = trim($_POST['judul_aiken']?:'Ulangan Harian');
  $aktif = isset($_POST['aktifkan_aiken'])?1:0;
  $ok = 0;
  
  $current = null;
  $answers = ['a'=>0,'b'=>1,'c'=>2,'d'=>3,'e'=>4];
  
  foreach($lines as $line){
    $line = trim($line);
    if(empty($line)) continue;
    
    if(preg_match('/^\d+[.)]\s+(.+)$/', $line, $m)){
      if($current !== null && !empty($current['pertanyaan']) && count($current['pilihan'])>=4){
        $soalList[] = [
          'id' => uniqid('s'),
          'guru_id' => $_SESSION['uid'],
          'ujian_id' => $ujianId,
          'pertanyaan' => $current['pertanyaan'],
          'pilihan' => array_pad(array_values($current['pilihan']), 5, ''),
          'jawaban' => $current['jawaban'],
          'aktif' => $aktif,
        ];
        $ok++;
      }
      $current = ['pertanyaan'=>$m[1], 'pilihan'=>[], 'jawaban'=>0];
    }
    elseif(preg_match('/^([a-eA-E])[.)]\s+(.+)$/', $line, $m)){
      if($current !== null){
        $idx = array_search(strtolower($m[1]), ['a','b','c','d','e']);
        if($idx !== false) $current['pilihan'][$idx] = $m[2];
      }
    }
    elseif(preg_match('/^(?:ANSWER|KUNCI|JAWABAN)\s*:\s*([a-eA-E])/i', $line, $m)){
      if($current !== null){
        $current['jawaban'] = $answers[strtolower($m[1])]??0;
      }
    }
    else {
      // Continuation text - append to current question
      if($current !== null && empty($current['pilihan'])){
        $current['pertanyaan'] .= "\n" . $line;
      }
    }
  }
  
  if($current !== null && !empty($current['pertanyaan']) && count($current['pilihan'])>=4){
    $soalList[] = [
      'id' => uniqid('s'),
      'guru_id' => $_SESSION['uid'],
      'ujian_id' => $ujianId,
      'pertanyaan' => $current['pertanyaan'],
      'pilihan' => array_pad(array_values($current['pilihan']), 5, ''),
      'jawaban' => $current['jawaban'],
      'aktif' => $aktif,
    ];
    $ok++;
  }
  
  if($ok > 0){
    jw('soal', $soalList);
    $u = jr('ujian');
    $u[] = ['id'=>$ujianId, 'guru_id'=>$_SESSION['uid'], 'judul'=>$judul, 'aktif'=>$aktif];
    jw('ujian', $u);
    header("Location: ?menu=soal&ujian=$ujianId&ok=1&import=$ok");
    exit;
  }
  header('Location: ?menu=import&err=1');
  exit;
}
// Export
if(isL()&&isset($_GET['export'])){$n=jr('nilai');header('Content-Type:text/csv;charset=utf-8');header('Content-Disposition:attachment;filename=nilai.csv');$o=fopen('php://output','w');fputcsv($o,['NIS','Nama','Kelas','Ujian','Nilai','Benar','Total','Waktu']);foreach($n as $v)fputcsv($o,[$v['nis'],$v['nama'],$v['kelas'],$v['judul'],$v['nilai'],$v['benar'],$v['total'],$v['waktu']]);fclose($o);exit;}

// API
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['api_submit_nilai'])&&strpos($_SERVER['HTTP_REFERER']??'',$_SERVER['HTTP_HOST'])!==false){$n=jr('nilai');$n[]=['id'=>uniqid(),'nis'=>$_POST['nis'],'nama'=>$_POST['nama'],'kelas'=>$_POST['kelas'],'judul'=>$_POST['judul'],'nilai'=>(int)$_POST['nilai'],'benar'=>(int)$_POST['benar'],'total'=>(int)$_POST['total'],'waktu'=>date('Y-m-d H:i:s')];jw('nilai',$n);echo'OK';exit;}
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['api_log_curang'])&&strpos($_SERVER['HTTP_REFERER']??'',$_SERVER['HTTP_HOST'])!==false){$l=jr('log_curang');$l[]=['waktu'=>date('Y-m-d H:i:s'),'nis'=>$_POST['nis'],'nama'=>$_POST['nama'],'tipe'=>$_POST['tipe'],'detail'=>$_POST['detail']];jw('log_curang',$l);echo'OK';exit;}

// API Soal - only return soal from active ujian + active soal
if(isset($_GET['api_soal'])){header('Cache-Control:no-store,no-cache,must-revalidate');$s=jr('soal');$u=jr('ujian');$filterUjian=$_GET['ujian_id']??null;$aktifUjian=array_filter($u,fn($x)=>$x['aktif']&&(!$filterUjian||$x['id']===$filterUjian));$aktifId=array_column($aktifUjian,'id');$s=array_values(array_filter($s,fn($x)=>$x['aktif']&&in_array($x['ujian_id'],$aktifId)));header('Content-Type:application/json');echo json_encode($s);exit;}
if(isset($_GET['api_settings'])){header('Cache-Control:no-store,no-cache,must-revalidate');header('Content-Type:application/json');echo json_encode(jr('settings'));exit;}
// API Ujian list for students (active ujian with soal count)
if(isset($_GET['api_ujian_list'])){header('Cache-Control:no-store,no-cache,must-revalidate');$u=jr('ujian');$s=jr('soal');$list=[];foreach($u as $x){if(!$x['aktif'])continue;$c=count(array_filter($s,fn($so)=>$so['ujian_id']===$x['id']&&$so['aktif']));if($c>0)$list[]=['id'=>$x['id'],'judul'=>$x['judul'],'soal'=>$c];}header('Content-Type:application/json');echo json_encode($list);exit;}
// Student login API
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['api_login_siswa'])&&strpos($_SERVER['HTTP_REFERER']??'',$_SERVER['HTTP_HOST'])!==false){$s=jr('siswa');foreach($s as &$x){if($x['nis']===$_POST['nis']&&verifyPw($_POST['password'],$x['password'])){jw('siswa',$s);echo json_encode(['status'=>'ok','nis'=>$x['nis'],'nama'=>$x['nama'],'kelas'=>$x['kelas']]);exit;}}unset($x);echo json_encode(['status'=>'error']);exit;}
// API media list
if(isset($_GET['api_media'])){header('Cache-Control:no-store,no-cache,must-revalidate');$img=glob(__DIR__.'/uploads/images/*.{jpg,jpeg,png,gif,webp}',GLOB_BRACE);$aud=glob(__DIR__.'/uploads/audio/*.{mp3,ogg,wav,m4a,webm}',GLOB_BRACE);$vid=glob(__DIR__.'/uploads/video/*.{mp4,webm,mkv,mov}',GLOB_BRACE);$f=array_map(fn($x)=>str_replace(__DIR__.'/','',$x),array_merge($img,$aud,$vid));header('Content-Type:application/json');echo json_encode($f);exit;}

// ─── DATA ───
$menu=$_GET['menu']??'dashboard';
$uid=$_SESSION['uid']??'';
$role=$_SESSION['role']??'';
$settings=jr('settings')?:['acak_soal'=>false,'blokir_translate'=>true,'deteksi_tab'=>true,'deteksi_split'=>true,'blokir_copy'=>true,'blokir_devtools'=>true,'timer_per_soal'=>true,'waktu_per_soal'=>60,'max_pindah_tab'=>3,'max_split_screen'=>2];
$guru=jr('guru');
$soal=jr('soal');
$ujian=jr('ujian');
$filterUid=isA()?($_GET['guru']??''):$uid;
if(isA()&&$filterUid===''){$filterUjian=$ujian;$filterSoal=$soal;}else{$filterUjian=array_filter($ujian,fn($u)=>$u['guru_id']===$filterUid);$filterSoal=array_filter($soal,fn($s)=>$s['guru_id']===$filterUid);}
$mySoal=array_filter($soal,fn($s)=>$s['guru_id']===$uid);
$myUjian=array_filter($ujian,fn($u)=>$u['guru_id']===$uid);
$currentUjian=$_GET['ujian']??null;
$currentUjianData=null;
$ujianSoal=[];
if($currentUjian){
  foreach($ujian as $ux){if($ux['id']===$currentUjian){$currentUjianData=$ux;break;}}
  $ujianSoal=array_values(array_filter($soal,fn($s)=>$s['ujian_id']===$currentUjian));
}
$totalSiswa=count(jr('siswa')?:[]);
$nilai=jr('nilai');
$totalNilai=count($nilai);
$rataNilai=$totalNilai>0?round(array_sum(array_column($nilai,'nilai'))/$totalNilai):0;
$siswa=jr('siswa')?:[];
if(!isL()):?>
<!DOCTYPE html><html lang="id" translate="no">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="google" content="notranslate"><title>MAZ — Panel Guru</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',-apple-system,sans-serif;background:#0f172a;color:#e2e8f0;min-height:100vh}
.login-wrap{display:flex;align-items:center;justify-content:center;min-height:100vh;padding:16px}
.login-card{background:#1e293b;border-radius:16px;padding:40px 48px;max-width:420px;width:100%;box-shadow:0 25px 50px 0 rgba(0,0,0,.4)}
.login-card .logo{width:56px;height:56px;border-radius:14px;background:linear-gradient(135deg,#f59e0b,#f97316);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:24px;margin-bottom:16px}
input{width:100%;padding:12px 16px;border-radius:8px;border:1px solid #334155;background:#0f172a;color:#e2e8f0;font-size:.95rem;outline:none;transition:.2s}
input:focus{border-color:#3b82f6}
.btn{padding:12px 20px;border-radius:8px;border:none;background:#3b82f6;color:#fff;font-size:.9rem;font-weight:600;cursor:pointer;transition:.15s;display:inline-flex;align-items:center;gap:6px}
.btn:hover{background:#2563eb}.btn-block{width:100%;justify-content:center}
.error{background:#450a0a;color:#fca5a5;padding:10px;border-radius:8px;margin-top:12px;font-size:.85rem;text-align:center}
</style></head><body>
<div class="login-wrap"><div class="login-card">
  <div class="logo">MAZ</div>
  <h1>Panel Guru</h1><p style="color:#94a3b8;font-size:.9rem;margin-bottom:24px">Masuk untuk mengelola ujian</p>
  <form method="post">
      <input type="hidden" name="_csrf" value="<?=csrf_token()?>">
    <label>NIS / Username</label><input type="text" name="nis" placeholder="maz" required>
    <label>Password</label><input type="password" name="password" required>
    <button class="btn btn-block" type="submit" name="login">🚪 Masuk</button>
    <?php if(isset($e)):?><div class="error"><?=htmlspecialchars($e)?></div><?php endif;?>
  </form>
</div>
</div>
</body></html>
<?php exit;endif;

// Export Aiken
if(isL()&&isset($_GET['export_aiken'])){
  $exportUjianId = $_GET['export_aiken'];
  $allSoal = jr('soal');
  $exportSoal = array_filter($allSoal, fn($s)=>$s['ujian_id']===$exportUjianId);
  $letters = ['A','B','C','D','E'];
  $aiken = '';
  $no = 1;
  foreach($exportSoal as $es){
    $aiken .= $no . '. ' . str_replace(["\\r\\n","\\r","\\n"],"\n", $es['pertanyaan']) . "\n";
    foreach($es['pilihan'] as $pi=>$pt){
      if(trim($pt)!=='') $aiken .= $letters[$pi] . '. ' . $pt . "\n";
    }
    $aiken .= 'ANSWER: ' . $letters[$es['jawaban']] . "\n\n";
    $no++;
  }
  $judulFile = preg_replace('/[^a-zA-Z0-9\x{80}-\x{ff}]/u', '_', $exportUjianId) . '_soal.txt';
  header('Content-Type: text/plain; charset=utf-8');
  header('Content-Disposition: attachment; filename="' . $judulFile . '"');
  echo trim($aiken);
  exit;
}

// ─── NAVIGATION REBUILT ───
// Data structures
$navIcon = [
  'dashboard'=>'📊','soal'=>'📝','nilai'=>'📈','monitor'=>'🛡','media'=>'🖼',
  'siswa'=>'👥','import'=>'📥','pengaturan'=>'⚙️','guru'=>'👥'
];
$navLabel = [
  'dashboard'=>'Dashboard','soal'=>'Soal','nilai'=>'Nilai','monitor'=>'Monitor','media'=>'Media',
  'siswa'=>'Siswa','import'=>'Import','pengaturan'=>'Atur','guru'=>'Guru'
];
$navItems = ['dashboard','soal','nilai','monitor','media','siswa'];
$extraItems = ['import','pengaturan'];
if(isA())$extraItems[]='guru';
$isSoalSub = ($menu==='soal' && $currentUjian);
$pageTitle = $navLabel[$menu]??'Dashboard';
if($isSoalSub && $currentUjianData ){$pageTitle = htmlspecialchars($currentUjianData['judul']);}
?><!DOCTYPE html><html lang="id" translate="no">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="google" content="notranslate"><title>MAZ — <?=$pageTitle?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',-apple-system,sans-serif;background:#0f172a;color:#e2e8f0;min-height:100vh;display:flex;flex-direction:column}
input,textarea,select{width:100%;padding:10px 14px;border-radius:8px;border:1px solid #334155;background:#0f172a;color:#e2e8f0;font-size:.9rem;outline:none;font-family:inherit;transition:.15s}
input:focus,textarea:focus,select:focus{border-color:#3b82f6}

/* ── BUTTONS ── */
.btn{padding:12px 20px;border-radius:8px;border:none;background:#3b82f6;color:#fff;font-size:.9rem;font-weight:600;cursor:pointer;transition:.15s;display:inline-flex;align-items:center;gap:6px;text-decoration:none}
.btn:hover{background:#2563eb}.btn-block{width:100%;justify-content:center}
.btn-sm{padding:6px 12px;font-size:.78rem}.btn-xs{padding:4px 8px;font-size:.72rem}
.btn-danger{background:#ef4444}.btn-danger:hover{background:#dc2626}
.btn-success{background:#22c55e}.btn-success:hover{background:#16a34a}
.btn-warning{background:#f59e0b}.btn-warning:hover{background:#d97706}
.btn-outline{background:transparent;border:1px solid #334155;color:#94a3b8}
.btn-outline:hover{border-color:#3b82f6;color:#60a5fa}

/* ── TOP BAR ── */
.topbar{background:#1e293b;padding:10px 16px;display:flex;align-items:center;gap:10px;border-bottom:1px solid #334155;position:sticky;top:0;z-index:100}
.topbar .logo{width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,#f59e0b,#f97316);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:14px;flex-shrink:0}
.topbar .title{flex:1;font-size:.95rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.hamburger{background:none;border:none;color:#94a3b8;font-size:1.4rem;cursor:pointer;padding:4px;display:flex;align-items:center}
.hamburger:hover{color:#fff}
.topbar-right{display:flex;align-items:center;gap:8px;font-size:.78rem;color:#94a3b8;flex-shrink:0}
.topbar-right .uname{display:none}@media(min-width:480px){.topbar-right .uname{display:inline}}
.topbar a{color:#94a3b8;text-decoration:none;font-size:.78rem}.topbar a:hover{color:#fff}

/* ── SIDEBAR ── */
/* ── APP LAYOUT ── */
.app-layout{display:flex;flex:1;min-height:0}

/* ── SIDEBAR DESKTOP ── */
.sidebar-dt{width:200px;background:#1e293b;border-right:1px solid #334155;flex-shrink:0;display:none;flex-direction:column;overflow-y:auto}
@media(min-width:768px){.sidebar-dt{display:flex}}
.sidebar-dt .sd-h{padding:12px 14px;border-bottom:1px solid #334155}
.sidebar-dt .sd-h .sd-n{font-size:.82rem;font-weight:600}
.sidebar-dt .sd-h .sd-s{font-size:.65rem;color:#64748b}
.sidebar-dt .sd-g{padding:4px 0}
.sidebar-dt .sd-l{padding:6px 14px 2px;font-size:.6rem;color:#475569;text-transform:uppercase;letter-spacing:.05em;font-weight:600}
.sidebar-dt a.sd-i{display:flex;align-items:center;gap:8px;padding:8px 14px;color:#94a3b8;text-decoration:none;font-size:.78rem;transition:.1s;border-left:3px solid transparent}
.sidebar-dt a.sd-i:hover{background:#0f172a;color:#e2e8f0}
.sidebar-dt a.sd-i.aktif{background:#0f172a;color:#60a5fa;border-left-color:#60a5fa;font-weight:600}
.sidebar-dt .sd-x{height:1px;background:#334155;margin:6px 14px}
.sidebar-dt .sd-o{margin-top:auto;padding:8px 14px;border-top:1px solid #334155;font-size:.7rem;color:#64748b}
.sidebar-dt .sd-o a.sd-i{display:inline;padding:0;color:#ef4444;font-size:.72rem;border:none}
.sidebar-dt .sd-o a:hover{background:transparent}

/* ── SIDEBAR MOBILE ── */
.sidebar-mo{position:fixed;top:0;left:-280px;width:260px;height:100%;background:#1e293b;z-index:201;transition:left .25s;overflow-y:auto;padding:12px 0;border-right:1px solid #334155}
.sidebar-mo.open{left:0}
.sidebar-mo .sm-h{display:flex;align-items:center;gap:10px;padding:0 14px 14px;border-bottom:1px solid #334155;margin-bottom:6px}
.sidebar-mo .sm-h .logo{width:34px;height:34px;border-radius:10px;background:linear-gradient(135deg,#f59e0b,#f97316);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:15px}
.sidebar-mo .sm-h div{font-size:.82rem;font-weight:600;color:#e2e8f0}
.sidebar-mo .sm-h small{display:block;font-size:.65rem;color:#64748b;font-weight:400}
.sidebar-mo .sg{padding:4px 0}
.sidebar-mo .sg .sl{padding:5px 14px 2px;font-size:.6rem;color:#475569;text-transform:uppercase;letter-spacing:.05em;font-weight:600}
.sidebar-mo a.sm-i{display:flex;align-items:center;gap:10px;padding:9px 14px;color:#94a3b8;text-decoration:none;font-size:.8rem;transition:.1s;border-left:3px solid transparent}
.sidebar-mo a.sm-i:hover{background:#0f172a;color:#e2e8f0}
.sidebar-mo a.sm-i.aktif{background:#0f172a;color:#60a5fa;border-left-color:#60a5fa;font-weight:600}
.sidebar-mo .sx{height:1px;background:#334155;margin:6px 14px}
.sidebar-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:200;display:none}
.sidebar-overlay.open{display:block}

.content{padding:16px;width:100%;flex:1;overflow-y:auto;padding-bottom:72px}
@media(min-width:768px){.content{padding:20px 28px;padding-bottom:24px}}

/* ── BOTTOM NAV (mobile) ── */
.bottom-nav{display:flex;background:#1e293b;border-top:1px solid #334155;position:fixed;bottom:0;left:0;width:100%;z-index:99}
.bottom-nav a{flex:1;display:flex;flex-direction:column;align-items:center;padding:6px 4px 4px;text-decoration:none;color:#64748b;font-size:.62rem;gap:1px;transition:.1s;border-top:2px solid transparent}
.bottom-nav a.aktif{color:#60a5fa;border-top-color:#60a5fa}
.bottom-nav a .bni{font-size:1.2rem;line-height:1}
.bottom-nav a:hover{color:#e2e8f0}
@media(min-width:640px){.bottom-nav{display:none}}

/* ── CONTENT ── */
.content{padding:16px;max-width:1200px;margin:0 auto;width:100%;flex:1;padding-bottom:72px}
@media(min-width:640px){.content{padding:24px 32px;padding-bottom:24px}}
.card{background:#1e293b;border-radius:12px;padding:20px;margin-bottom:14px}
.card h3{font-size:1.05rem;margin-bottom:12px;color:#f1f5f9}
.hidden{display:none!important}

/* ── STATS ── */
.stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px}
.stat-item{background:#0f172a;border-radius:10px;padding:16px;text-align:center}
.stat-item .num{font-size:1.8rem;font-weight:800;background:linear-gradient(135deg,#f59e0b,#f97316);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
.stat-item .lbl{font-size:.78rem;color:#94a3b8;margin-top:4px}

/* ── TABLE ── */
.table-wrap{overflow-x:auto;border:1px solid #1e293b;border-radius:8px}
table{width:100%;border-collapse:collapse;font-size:.82rem}
th{text-align:left;padding:8px 10px;color:#94a3b8;font-weight:500;border-bottom:1px solid #334155;white-space:nowrap;background:#0f172a;font-size:.75rem;text-transform:uppercase;letter-spacing:.03em}
td{padding:8px 10px;border-bottom:1px solid #1e293b}
tr:hover td{background:#0f172a80}tr:last-child td{border-bottom:none}

/* ── LABEL ── */
.label{display:inline-block;padding:2px 10px;border-radius:999px;font-size:.68rem;font-weight:600}
.label-green{background:#052e16;color:#22c55e}.label-red{background:#450a0a;color:#ef4444}
.label-yellow{background:#451a03;color:#f59e0b}.label-blue{background:#0c1929;color:#60a5fa}

/* ── FORM ── */
.form-group{margin-bottom:10px}
.form-group label{display:block;font-size:.75rem;color:#94a3b8;margin-bottom:3px;font-weight:500}

/* ── EXISTING STYLES ── */
.opt-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:6px}
.opt-item{display:flex;align-items:center;gap:6px;background:#0f172a;padding:8px 12px;border-radius:8px;font-size:.82rem;white-space:nowrap}
.opt-item input[type="radio"]{accent-color:#22c55e;width:14px;height:14px;margin:0;flex-shrink:0;background:#1e293b;border:1px solid #334155}
.opt-item input[type="text"]{flex:1;min-width:0;background:#1e293b;border:1px solid #334155;border-radius:4px;padding:4px 8px;color:#e2e8f0;font-size:.8rem;width:auto}
.opt-item .ol{font-weight:700;width:20px;flex-shrink:0;text-align:center;font-size:.82rem}
.opt-item .mbtn{background:transparent;border:1px solid #334155;border-radius:3px;color:#60a5fa;cursor:pointer;font-size:.6rem;padding:2px 5px;flex-shrink:0}
.opt-item .mbtn:hover{background:#1e293b}
.toast{position:fixed;top:20px;right:20px;background:#052e16;color:#86efac;padding:12px 24px;border-radius:10px;font-size:.82rem;z-index:999;border:1px solid #22c55e50;animation:fi .3s;box-shadow:0 8px 24px rgba(0,0,0,.4)}
.toast.err{background:#450a0a;color:#fca5a5;border-color:#ef4444}
@keyframes fi{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.empty{text-align:center;color:#64748b;padding:32px;font-size:.85rem}
.file-zone{border:2px dashed #334155;border-radius:10px;padding:24px;text-align:center;cursor:pointer;transition:.2s}
.file-zone:hover{border-color:#3b82f6;background:#0f172a}
.toggle-row{display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:#0f172a;border-radius:8px;margin-bottom:6px}
.toggle-row .lbl{font-size:.85rem}.toggle-row .desc{font-size:.72rem;color:#64748b;margin-top:2px}
.switch{position:relative;width:40px;height:22px;flex-shrink:0}
.switch input{opacity:0;width:0;height:0}
.sl{position:absolute;top:0;left:0;right:0;bottom:0;background:#334155;transition:.3s;border-radius:22px}
.sl:before{content:"";height:16px;width:16px;left:3px;bottom:3px;background:#94a3b8;transition:.3s;border-radius:50%;position:absolute}
input:checked+.sl{background:#22c55e}input:checked+.sl:before{background:#fff;transform:translateX(18px)}
.media-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px}
.media-item{background:#0f172a;border-radius:8px;overflow:hidden;position:relative}
.media-item img{width:100%;height:100px;object-fit:cover;display:block}
.media-item .info{padding:4px 6px;font-size:.68rem;color:#94a3b8;word-break:break-all}
.media-item audio{width:100%;height:30px}
.media-item .cpy{position:absolute;top:3px;right:3px;background:#1e293b;border:none;border-radius:4px;color:#60a5fa;cursor:pointer;padding:2px 6px;font-size:.62rem;opacity:.85}
.ujian-list{display:flex;flex-direction:column;gap:6px}
.ujian-row{display:flex;align-items:center;gap:10px;padding:12px 16px;background:#0f172a;border-radius:10px;border:1px solid #1e293b;transition:.15s}
.ujian-row:hover{border-color:#334155;background:#1e293b}
.ujian-link{display:flex;align-items:center;gap:12px;flex:1;min-width:0;text-decoration:none;color:inherit}
.ujian-folder{font-size:1.3rem;flex-shrink:0}
.ujian-info{flex:1;min-width:0}
.ujian-title{font-size:.9rem;font-weight:600;color:#e2e8f0}
.ujian-meta{display:flex;gap:10px;font-size:.75rem;color:#64748b;flex-wrap:wrap;margin-top:3px}
.ujian-actions{display:flex;gap:6px;flex-shrink:0}
.breadcrumb{font-size:.8rem;color:#64748b;margin-bottom:12px;display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.breadcrumb a{color:#60a5fa;text-decoration:none;display:inline-flex;align-items:center;gap:4px}
.breadcrumb a:hover{text-decoration:underline}
.guru-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:8px}
.guru-card{padding:12px;background:#0f172a;border-radius:8px}.guru-card .gn{font-weight:600;font-size:.85rem}
.settings-grid{max-width:500px}
.act-bar{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:12px}
.qlist{margin:0}
.qitem{display:flex;align-items:center;gap:10px;padding:10px 12px;background:#0f172a;border-radius:8px;cursor:pointer;transition:.12s;border:1px solid transparent;margin-bottom:4px}
.qitem:hover{border-color:#3b82f6;background:#1e293b}
.qitem .qno{width:30px;height:30px;border-radius:8px;background:#1e293b;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.82rem;color:#60a5fa;flex-shrink:0}
.qitem .qtext{flex:1;font-size:.82rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#94a3b8}
.qitem .qstat{margin-left:auto;display:flex;gap:4px;align-items:center}
.modal-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.6);z-index:999;display:flex;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(2px)}
.modal{background:#1e293b;border-radius:16px;padding:20px;max-width:650px;width:100%;max-height:80vh;display:flex;flex-direction:column}
.modal h3{margin-bottom:10px}.modal-body{overflow-y:auto;flex:1;padding:4px 0}
.media-pick-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:8px}
.media-pick-item{border:2px solid transparent;border-radius:8px;overflow:hidden;cursor:pointer;transition:.12s;background:#0f172a}
.media-pick-item:hover,.media-pick-item:focus{border-color:#3b82f6}
.media-pick-item img{width:100%;height:70px;object-fit:cover;display:block}
.media-pick-item .pinfo{padding:3px 4px;font-size:.62rem;color:#94a3b8;word-break:break-all}
.media-pick-item audio{width:100%;height:28px}
.media-pick-item .pname{font-size:.62rem;padding:0 4px 3px;color:#64748b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.modal-upload{display:flex;gap:8px;align-items:center;padding:6px 0;border-bottom:1px solid #334155;margin-bottom:8px}
.modal-upload input[type="file"]{display:none}
</style></head><body>

<?php // ─── TOAST ───
if(isset($_GET['ok'])):?><div class="toast">✅ Berhasil<?php if(isset($_GET['import'])):?>(<?=(int)$_GET['import']?> soal)<?php endif;?></div><?php endif;
if(isset($_GET['err'])):?><div class="toast err">❌ Gagal!</div><?php endif;?>

<?php // ─── TOPBAR ─── ?>
<div class="topbar">
  <button class="hamburger" onclick="toggleSidebar()" aria-label="Menu">☰</button>
  <div class="logo">M</div>
  <div class="title"><?=$pageTitle?></div>
  <div class="topbar-right">
    <span class="uname"><?=htmlspecialchars($_SESSION['nama']??'')?></span>
    <?php if($isSoalSub):?>
    <a href="?menu=soal<?=isA()&&$filterUid!==''?'&guru='.urlencode($filterUid):''?>" class="btn btn-xs btn-outline">←</a>
    <?php endif;?>
    <a href="?logout=1" style="color:#ef4444">✕</a>
  </div>
</div>

<?php // ─── SIDEBAR ─── ?>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<?php // ─── SIDEBAR DESKTOP (always visible on wider screens) ─── ?>
<div class="app-layout">
<div class="sidebar-dt">
  <div class="sd-h">
    <div class="sd-n">MAZ Panel</div>
    <div class="sd-s"><?=htmlspecialchars($_SESSION['role']??'')?> · <?=htmlspecialchars($_SESSION['nama']??'')?></div>
  </div>
  <div class="sd-g">
    <div class="sd-l">Menu</div>
    <?php foreach($navItems as $n):?>
    <a href="?menu=<?=$n?><?=($n==='soal'&&isA()&&$filterUid!=='')?'&guru='.urlencode($filterUid):''?>" class="sd-i <?=$menu===$n?'aktif':''?>">
      <span style="width:20px;text-align:center"><?=$navIcon[$n]?></span> <?=$navLabel[$n]?>
    </a>
    <?php endforeach;?>
  </div>
  <div class="sd-x"></div>
  <div class="sd-g">
    <div class="sd-l">Lainnya</div>
    <?php foreach($extraItems as $n):?>
    <a href="?menu=<?=$n?>" class="sd-i <?=$menu===$n?'aktif':''?>">
      <span style="width:20px;text-align:center"><?=$navIcon[$n]?></span> <?=$navLabel[$n]?>
    </a>
    <?php endforeach;?>
  </div>
  <div class="sd-o">
    <div><?=htmlspecialchars($_SESSION['nama']??'')?></div>
    <div style="margin-top:2px"><a href="?logout=1" class="sd-i" style="color:#ef4444">🚪 Keluar</a></div>
  </div>
</div>

<?php // ─── SIDEBAR MOBILE (slide from hamburger) ─── ?>
<div class="sidebar-mo" id="sidebarMobile">
  <div class="sm-h">
    <div class="logo">M</div>
    <div><small><?=htmlspecialchars($_SESSION['role']??'')?></small><?=htmlspecialchars($_SESSION['nama']??'')?></div>
  </div>
  <div class="sg">
    <div class="sl">Menu</div>
    <?php foreach($navItems as $n):?>
    <a href="?menu=<?=$n?><?=($n==='soal'&&isA()&&$filterUid!=='')?'&guru='.urlencode($filterUid):''?>" class="sm-i <?=$menu===$n?'aktif':''?>">
      <span style="font-size:1.1rem;width:22px;text-align:center"><?=$navIcon[$n]?></span> <?=$navLabel[$n]?>
    </a>
    <?php endforeach;?>
  </div>
  <div class="sx"></div>
  <div class="sg">
    <div class="sl">Lainnya</div>
    <?php foreach($extraItems as $n):?>
    <a href="?menu=<?=$n?>" class="sm-i <?=$menu===$n?'aktif':''?>">
      <span style="font-size:1rem;width:22px;text-align:center"><?=$navIcon[$n]?></span> <?=$navLabel[$n]?>
    </a>
    <?php endforeach;?>
  </div>
  <div class="sx"></div>
  <div style="padding:12px 14px;font-size:.75rem;color:#64748b">
    <?=htmlspecialchars($_SESSION['nama']??'')?> · <a href="?logout=1" style="color:#ef4444;text-decoration:none">Keluar</a>
  </div>
</div>

<?php // ─── BOTTOM NAV (mobile) ─── ?>
<div class="bottom-nav">
  <?php foreach($navItems as $n):?>
  <a href="?menu=<?=$n?><?=($n==='soal'&&isA()&&$filterUid!=='')?'&guru='.urlencode($filterUid):''?>" class="<?=$menu===$n?'aktif':''?>">
    <span class="bni"><?=$navIcon[$n]?></span>
    <span><?=$navLabel[$n]?></span>
  </a>
  <?php endforeach;?>
</div>

<script>
  
  
function toggleSidebar(){document.getElementById('sidebarMobile').classList.toggle('open');document.getElementById('sidebarOverlay').classList.toggle('open');}
</script>

<div class="content">

<?php // ─── FILTER GURU (admin, dropdown) ─── ?>
<?php if(isA() && $menu==='soal' && !$currentUjian):?>
<div class="card" style="padding:8px 14px;margin-bottom:10px">
  <div style="display:flex;flex-wrap:wrap;align-items:center;gap:4px">
    <span style="font-size:.72rem;color:#64748b">👤 Guru:</span>
    <select onchange="location.href=this.value" style="width:auto;padding:4px 8px;font-size:.78rem;background:#0f172a;border:1px solid #334155;border-radius:6px;color:#e2e8f0;outline:none">
      <option value="?menu=soal" <?=$filterUid===''?'selected':''?>>Semua</option>
      <option value="?menu=soal&guru=<?=urlencode($uid)?>" <?=$filterUid===$uid?'selected':''?>>Saya</option>
      <?php foreach($guru as $g):if($g['id']===$uid)continue;?>
        <option value="?menu=soal&guru=<?=urlencode($g['id'])?>" <?=$filterUid===$g['id']?'selected':''?>><?=htmlspecialchars($g['nama'])?></option>
      <?php endforeach;?>
    </select>
  </div>
</div>
<?php endif;?>

<?php // ─── CONTENT ─── ?>
<?php if($menu==='dashboard'):?>
  <div class="stat-grid">
    <div class="stat-item"><div class="num"><?=$totalSiswa?></div><div class="lbl">👥 Siswa</div></div>
    <div class="stat-item"><div class="num"><?=count($mySoal)?></div><div class="lbl">📝 Soal Saya</div></div>
    <div class="stat-item"><div class="num"><?=count($myUjian)?></div><div class="lbl">📋 Ujian Saya</div></div>
    <div class="stat-item"><div class="num"><?=$totalNilai?></div><div class="lbl">📈 Nilai</div></div>
    <div class="stat-item"><div class="num"><?=$rataNilai?></div><div class="lbl">⭐ Rata-rata</div></div>
  </div>
  <?php if(isA()):?>
  <div class="card">
    <h3>👥 Guru</h3>
    <div class="guru-grid"><?php foreach($guru as $g):$jml=count(array_filter($soal,fn($s)=>$s['guru_id']===$g['id']));?>
      <div class="guru-card"><div class="gn"><?=htmlspecialchars($g['nama'])?></div>
        <div style="font-size:.75rem;color:#64748b"><?=htmlspecialchars($g['mapel'])?> · <?=$jml?> soal</div>
        <span class="label <?=$g['role']==='admin'?'label-yellow':'label-blue'?>" style="margin-top:3px"><?=$g['role']?></span>
      </div>
    <?php endforeach;?></div>
  </div>
  <?php endif;?>

<?php // ─── SOAL ─── ?>
<?php elseif($menu==='soal'):?>
  <?php if(!$currentUjian):?>
  <!-- Ujian list -->
  <div class="act-bar">
    <h3 style="margin:0">📁 Folder Soal</h3>
    <button class="btn btn-sm btn-success" onclick="document.getElementById('nuf').classList.toggle('hidden')">➕ Buat Folder</button>
  </div>
  <div id="nuf" class="card hidden">
    <form method="post">
      <input type="hidden" name="_csrf" value="<?=csrf_token()?>"><input type="hidden" name="ujian_id" value="">
      <div class="form-group"><label>Judul Ujian</label><input type="text" name="judul" placeholder="UTS Bhs Inggris X-TKR" required></div>
      <button class="btn btn-sm btn-success" type="submit" name="sp_ujian">💾 Buat Ujian</button>
      <button class="btn btn-sm btn-outline" type="button" onclick="this.closest('#nuf').classList.add('hidden')">Batal</button>
    </form>
  </div>
  <?php if(empty($filterUjian)):?><div class="card"><div class="empty">Belum ada folder ujian.</div></div>
  <?php else:?>
  <!-- Tambah Ujian (inline) -->
  <div class="card" style="padding:12px 16px;margin-bottom:10px">
    <div style="display:flex;justify-content:space-between;align-items:center">
      <span style="font-size:.85rem;color:#94a3b8"><b><?=count($filterUjian)?></b> folder</span>
      <button class="btn btn-sm btn-success" onclick="document.getElementById('tuj').classList.toggle('hidden')">➕ Buat Folder</button>
    </div>
    <div id="tuj" class="hidden" style="margin-top:8px">
      <form method="post">
      <input type="hidden" name="_csrf" value="<?=csrf_token()?>">
        <input type="hidden" name="sp_ujian" value="1">
        <div class="form-group"><label>Judul Ujian</label>
          <div style="display:flex;gap:6px">
            <input type="text" name="judul" placeholder="Mis: Ulangan Harian 1" required style="flex:1">
            <label style="display:flex;align-items:center;gap:3px;color:#94a3b8;font-size:.75rem;white-space:nowrap"><input type="checkbox" name="aktif" value="1" checked> Aktif</label>
            <button class="btn btn-sm btn-success" type="submit">💾</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="ujian-list">
  <?php foreach($filterUjian as $uj):
    $jml=count(array_filter($filterSoal,fn($s)=>$s['ujian_id']===$uj['id']));
    $jmlAktif=count(array_filter($filterSoal,fn($s)=>$s['ujian_id']===$uj['id']&&$s['aktif']));
    $canToggle=isA()||$uj['guru_id']===$uid;?>
    <div class="ujian-row">
      <a href="?menu=soal<?=isA()&&$filterUid!==''?'&guru='.urlencode($filterUid):''?>&ujian=<?=urlencode($uj['id'])?>" class="ujian-link">
        <span class="ujian-folder">📁</span>
        <div class="ujian-info">
          <div class="ujian-title"><?=htmlspecialchars($uj['judul'])?></div>
          <div class="ujian-meta">
            <span>📝 <?=$jml?> soal</span>
            <span>🟢 <?=$jmlAktif?> aktif</span>
            <span class="label <?=$uj['aktif']?'label-green':'label-red'?>"><?=$uj['aktif']?'Aktif':'Nonaktif'?></span>
          </div>
        </div>
      </a>
      <?php if($canToggle):?>
      <div class="ujian-actions">
        <a href="?toggle_ujian=<?=urlencode($uj['id'])?>" class="btn btn-xs <?=$uj['aktif']?'btn-warning':'btn-success'?>" onclick="event.stopPropagation()"><?=$uj['aktif']?'Nonaktifkan':'Aktifkan'?></a>
        <a href="?hapus_ujian=<?=urlencode($uj['id'])?>" class="btn btn-xs btn-danger" onclick="event.stopPropagation();return confirm('Hapus folder ini beserta semua soalnya?')">Hapus</a>
      </div>
      <?php endif;?>
    </div>
  <?php endforeach;?>
  </div>
  <?php endif;?>

  <?php else:?>
  <!-- Soal list in selected ujian -->
  <?php if($currentUjianData):?>
  <div class="breadcrumb">
    <a href="?menu=soal<?=isA()&&$filterUid!==''?'&guru='.urlencode($filterUid):''?>"><span style="font-size:1.1rem">←</span> Folder</a>
    <span style="color:#475569">/</span>
    <span style="font-weight:600">📁 <?=htmlspecialchars($currentUjianData['judul'])?></span>
    <span style="color:#475569;font-size:.75rem">(<?=count($ujianSoal)?> soal)</span>
    <?php if(isA()||$currentUjianData['guru_id']===$uid):?>
    <a href="?toggle_ujian=<?=urlencode($currentUjian)?>" class="btn btn-xs <?=$currentUjianData['aktif']?'btn-danger':'btn-success'?>" onclick="return confirm('<?=$currentUjianData['aktif']?'Nonaktifkan':'Aktifkan'?> ujian ini?')" style="margin-left:auto;text-decoration:none"><?=$currentUjianData['aktif']?'🔴 Nonaktifkan':'🟢 Aktifkan'?></a>
    <?php endif;?>
  </div>

  <div class="act-bar">
    <h3 style="margin:0">📝 Daftar Soal</h3>
    <div style="display:flex;gap:6px">
      <button class="btn btn-sm btn-success" onclick="openEdit(null)">➕ Tambah Soal</button>
      <a href="?export_aiken=<?=urlencode($currentUjian)?>" class="btn btn-sm btn-outline">📤 Export Aiken</a>
    </div>
  </div>

  <!-- Inline Aiken Import within ujian -->
  <div class="card" style="padding:12px 16px;margin-bottom:10px">
    <div style="display:flex;justify-content:space-between;align-items:center">
      <span style="font-size:.85rem;color:#94a3b8">📥 Import Aiken</span>
      <button class="btn btn-sm btn-success" onclick="document.getElementById('aiForm').classList.toggle('hidden')">➕ / ➖</button>
    </div>
    <div id="aiForm" class="hidden" style="margin-top:8px">
      <p style="color:#94a3b8;font-size:.75rem;margin-bottom:6px">Import soal ke <b><?=htmlspecialchars($currentUjianData['judul'])?></b> via Aiken:</p>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="aiken_import" value="1">
        <input type="hidden" name="judul_aiken" value="<?=htmlspecialchars($currentUjianData['judul']??'')?>">
        <input type="hidden" name="aktifkan_aiken" value="1">
        <div class="form-group"><label>Paste teks Aiken</label>
          <textarea name="aiken_text" style="min-height:80px;font-family:monospace;font-size:.75rem;background:#0f172a;color:#e2e8f0;border:1px solid #334155;border-radius:8px;padding:8px;width:100%" placeholder="1. Soal?\na. A\nb. B\nc. C\nd. D\ne. E\nANSWER: C"></textarea>
        </div>
        <button class="btn btn-sm btn-success" type="submit">📥 Import</button>
      </form>
    </div>
  </div>

  <?php if(empty($ujianSoal)):?><div class="card"><div class="empty">Belum ada soal.</div></div>
  <?php else:?>
  <div class="qlist">
  <?php foreach($ujianSoal as $i=>$s):?>
    <div class="qitem" style="width:calc(10% - 8px);min-width:56px;display:inline-flex;flex-direction:column;align-items:center;gap:4px;padding:8px 6px;text-align:center" data-id="<?=$s['id']?>" data-pertanyaan='<?=json_encode($s['pertanyaan'],JSON_HEX_APOS)?>' data-p0="<?=htmlspecialchars($s['pilihan'][0]??'',ENT_QUOTES)?>" data-p1="<?=htmlspecialchars($s['pilihan'][1]??'',ENT_QUOTES)?>" data-p2="<?=htmlspecialchars($s['pilihan'][2]??'',ENT_QUOTES)?>" data-p3="<?=htmlspecialchars($s['pilihan'][3]??'',ENT_QUOTES)?>" data-p4="<?=htmlspecialchars($s['pilihan'][4]??'',ENT_QUOTES)?>" data-jawaban="<?=$s['jawaban']?>" data-aktif="<?=$s['aktif']?>" onclick="openEdit(this)">
      <div class="qno"><?=$i+1?></div>
    </div>
  <?php endforeach;?>
  </div>
  <?php endif;?>

  <!-- Edit Soal Form -->
  <div id="editArea" class="card" style="margin-bottom:14px">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
      <h4 id="eTitle" style="margin:0">Tambah Soal</h4>
      <button class="btn btn-sm btn-outline" type="button" onclick="clearEdit()">X</button>
    </div>
    <div class="toolbar" style="display:flex;gap:6px;margin-bottom:8px;flex-wrap:wrap">
      <button type="button" onclick="execCmd('bold')" title="Bold"><b>B</b></button>
      <button type="button" onclick="execCmd('italic')" title="Italic"><i>I</i></button>
      <button type="button" onclick="execCmd('formatBlock','P')" title="Paragraf">Paragraf</button>
      <button type="button" onclick="execCmd('insertOrderedList')" title="Daftar Nomor">1️⃣</button>
      <button type="button" onclick="execCmd('insertUnorderedList')" title="Daftar Poin">🔘</button>
      <button type="button" onclick="document.getElementById('mf').click()">Media</button>
      <input type="file" id="mf" accept="image/*,audio/*,video/*" style="display:none" onchange="insertMedia(this)">
    </div>
    <form method="post" id="editForm" onsubmit="var ed=document.getElementById('questionEditor');if(ed)document.getElementById('q').value=ed.textContent.replace(/\n/g,'\\n');">
      <input type="hidden" name="ujian_id" value="<?=htmlspecialchars($currentUjian)?>">
      <input type="hidden" name="id" id="sid" value="">
      <div class="form-group"><label>Pertanyaan</label>
        <div id="questionEditor" contenteditable="true" style="min-height:60px;border:1px solid #334155;border-radius:8px;padding:8px;background:#0f172a;color:#e2e8f0"></div>
        <textarea name="pertanyaan" id="q" style="display:none"></textarea>
      </div>
      <div class="form-group"><label>Pilihan Jawaban (A-E) <span style="font-size:.7rem;color:#94a3b8">[img:path] [audio:path] [video:path]</span></label>
        <div class="opt-grid" style="grid-template-columns:1fr 1fr 1fr">
          <?php $ls=[["A","#f87171"],["B","#60a5fa"],["C","#34d399"],["D","#fbbf24"],["E","#a78bfa"]];foreach($ls as $li):?>
          <div class="opt-item" style="position:relative">
            <input type="radio" name="jawaban" value="<?=array_search($li,$ls)?>" required>
            <span class="ol" style="color:<?=$li[1]?>"><?=$li[0]?></span>
            <div style="display:flex;align-items:center;gap:4px;flex:1;min-width:0">
              <input type="text" name="pil<?=array_search($li,$ls)?>" id="p<?=$li[0]?>" placeholder="Opsi <?=$li[0]?>" required style="flex:1;min-width:0">
              <button type="button" onclick="openMediaForOption('p<?=$li[0]?>')" title="Sisipkan Media" style="background:#334155;border:1px solid #475569;color:#94a3b8;border-radius:6px;padding:4px 6px;cursor:pointer;font-size:11px;white-space:nowrap">🖼</button>
            </div>
          </div>
          <?php endforeach;?>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:10px;margin-top:6px;flex-wrap:wrap">
        <label style="display:flex;align-items:center;gap:4px;color:#94a3b8;font-size:.8rem;cursor:pointer"><input type="checkbox" name="aktif" id="acb" value="1" checked> Aktif</label>
        <button class="btn btn-sm btn-success" type="submit" name="sp_soal" style="margin:0">Simpan</button>
        <button class="btn btn-sm btn-outline" type="button" onclick="clearEdit()">Batal</button>
        <a href="#" id="delBtn" class="btn btn-sm btn-danger" style="display:none" onclick="event.preventDefault();return confirm('Hapus soal ini?')">Hapus</a>
      </div>
    </form>
  </div>
  <!-- Media Modal -->  <!-- Media Modal -->
  <div class="modal-overlay hidden" id="mediaModal" onclick="if(event.target===this)closeMedia()">
    <div class="modal">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
        <h3>🖼 Media</h3>
        <button class="btn btn-sm btn-outline" onclick="closeMedia()">✕</button>
      </div>
      <div class="modal-upload">
        <button class="btn btn-sm btn-success" onclick="document.getElementById('mf').click()">📤 Upload</button>
        <input type="file" id="mf" accept="image/*,audio/*,video/*" onchange="uploadMedia(this)">
        <span id="muStat" style="font-size:.75rem;color:#94a3b8"></span>
      </div>
      <div class="modal-body"><div class="media-pick-grid" id="mediaGrid"></div></div>
    </div>
  </div>

  <script>
  let editTarget=null, mediaTarget=null, optionMediaTarget=null;
  function execCmd(cmd,val){document.execCommand(cmd,false,val||null);document.getElementById('questionEditor').focus();}
  function clearEdit(){
    document.getElementById('editForm').reset();
    document.getElementById('sid').value='';
    const ed=document.getElementById('questionEditor'); if(ed) ed.innerHTML='';
    document.getElementById('eTitle').textContent='Tambah Soal';
    document.getElementById('delBtn').style.display='none';
    editTarget=null;
  }
  function insertMedia(input){
    const file=input.files[0]; if(!file) return;
    const fd=new FormData(); fd.append('media_upload',file);
    fetch('',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
      if(d && d.ok){ const ed=document.getElementById('questionEditor'); ed.focus(); document.execCommand('insertHTML',false,'<img src="/guru/'+d.ref+'" style="max-width:100%">'); input.value=''; }
    });
  }
  function openEdit(el){
    editTarget=el;
    if(!el){ clearEdit(); document.getElementById('editArea').scrollIntoView({behavior:'smooth',block:'start'}); return; }
    document.getElementById('editArea').scrollIntoView({behavior:'smooth',block:'start'});
    document.getElementById('eTitle').textContent='Edit Soal';
    document.getElementById('sid').value=el.dataset.id;
    document.getElementById('q').value=el.dataset.pertanyaan;
    const ed=document.getElementById('questionEditor'); if(ed) ed.innerHTML = (el.dataset.pertanyaan || '').split('\\n').join('<br>');    const ps=['A','B','C','D','E'];
    for(let i=0;i<5;i++)document.getElementById('p'+ps[i]).value=el['dataset']['p'+i]||'';
    document.querySelectorAll('input[name="jawaban"]')[parseInt(el.dataset.jawaban)].checked=true;
    document.getElementById('acb').checked=el.dataset.aktif==='1';
    document.getElementById('delBtn').style.display='';
    document.getElementById('delBtn').href='?hapus_soal='+encodeURIComponent(el.dataset.id)+'&ujian=<?=urlencode($currentUjian)?>';
  }
  function openMediaForOption(inputId){optionMediaTarget=inputId;mediaTarget=null;document.getElementById('mediaModal').classList.remove('hidden');loadMedia();}
  function openMedia(t){mediaTarget=t;optionMediaTarget=null;document.getElementById('mediaModal').classList.remove('hidden');loadMedia();}
  function closeMedia(){document.getElementById('mediaModal').classList.add('hidden');mediaTarget=null;optionMediaTarget=null;}
  async function loadMedia(){
    const r=await fetch('?api_media=1'),list=await r.json();
    document.getElementById('mediaGrid').innerHTML=list.map(f=>{
      const isImg=f.match(/\.(jpg|jpeg|png|gif|webp)$/i);
      const isVid=f.match(/\.(mp4|webm|mkv|mov)$/i);
      const ref=f.replace(/^uploads\//,'');
      const typ=isImg?'img':(isVid?'vid':'aud');
      const preview=isImg?'<img src="'+f+'" loading="lazy">':(isVid?'<video src="'+f+'" preload="none" style="width:100%;border-radius:6px"></video>':'<audio src="'+f+'" preload="none"></audio>');
      return '<div class="media-pick-item" onclick="pickMedia(\''+ref+'\',\''+typ+'\')">'+preview+'<div class="pname">'+f.split('/').pop()+'</div></div>';
    }).join('');
  }
  function pickMedia(ref,type){
    const tags={img:'[img:',aud:'[audio:',vid:'[video:'};
    const tag=(tags[type]||'[img:')+ref+']';
    if(optionMediaTarget){
      const inp=document.getElementById(optionMediaTarget);
      if(inp){inp.value+=(inp.value?' ':'')+tag;}
    }else{
      const ta=document.getElementById(mediaTarget);
      if(ta)ta.value+=(ta.value?'\n':'')+tag;
    }
    closeMedia();
  }
  async function uploadMedia(i){
    if(!i.files[0])return;
    document.getElementById('muStat').textContent='Uploading…';
    const f=new FormData();f.append('media_modal',i.files[0]);
    const r=await fetch('',{method:'POST',body:f});
    const d=await r.json();
    if(d.ok){
      document.getElementById('muStat').textContent='✅'+d.ref;
      loadMedia();
    }else document.getElementById('muStat').textContent='❌ Gagal';
    i.value='';
  }
  </script>
  <?php endif;?>
  <?php endif;?>
  </div>

<?php // ─── NILAI ─── ?>
<?php elseif($menu==='nilai'):?>
  <div class="act-bar"><h3 style="margin:0">📈 Nilai</h3>
    <a href="?export=1" class="btn btn-sm btn-success">📥 Export CSV</a>
    <a href="?hapus_semua_nilai=1" class="btn btn-sm btn-danger" onclick="return confirm('Hapus SEMUA nilai? Data tidak bisa dikembalikan!')" style="margin-left:auto">🗑 Hapus Semua</a>
  </div>
  <?php if(empty($nilai)):?><div class="card"><div class="empty">Belum ada nilai.</div></div>
  <?php else:?><div class="table-wrap"><table>
    <tr><th>NIS</th><th>Nama</th><th>Kelas</th><th>Ujian</th><th>Nilai</th><th>Benar</th><th>Total</th><th>Waktu</th><th></th></tr>
    <?php foreach($nilai as $n):?>
    <tr><td><?=htmlspecialchars($n['nis'])?></td><td><?=htmlspecialchars($n['nama'])?></td><td><?=htmlspecialchars($n['kelas'])?></td>
      <td><?=htmlspecialchars($n['judul'])?></td><td style="font-weight:700"><?=$n['nilai']?></td>
      <td><?=$n['benar']?></td><td><?=$n['total']?></td><td style="font-size:.75rem"><?=$n['waktu']?></td>
      <td><a href="?hapus_nilai=<?=urlencode($n['id'])?>" class="btn btn-xs btn-danger" onclick="return confirm('Hapus nilai ini?')">🗑</a></td>
    </tr>
    <?php endforeach;?>
  </table></div><?php endif;?>

<?php // ─── MONITOR ─── ?>
<?php elseif($menu==='monitor'):?>
  <div class="act-bar"><h3>🛡 Monitor Kecurangan</h3>
    <a href="?hapus_semua_log=1" class="btn btn-sm btn-danger" onclick="return confirm('Hapus SEMUA log kecurangan?')" style="margin-left:auto">🗑 Hapus Semua</a>
  </div>
  <?php $log=jr('log_curang');?>
  <?php if(empty($log)):?><div class="card"><div class="empty">Belum ada log.</div></div>
  <?php else:?><div class="table-wrap"><table>
    <tr><th>Waktu</th><th>NIS</th><th>Nama</th><th>Tipe</th><th>Detail</th><th></th></tr>
    <?php foreach(array_reverse($log) as $l):?>
    <tr><td style="font-size:.75rem"><?=$l['waktu']?></td><td><?=htmlspecialchars($l['nis'])?></td><td><?=htmlspecialchars($l['nama'])?></td>
      <td><span class="label label-red"><?=htmlspecialchars($l['tipe'])?></span></td><td style="font-size:.78rem"><?=htmlspecialchars($l['detail'])?></td>
      <td><a href="?hapus_log=<?=urlencode($l['id'])?>" class="btn btn-xs btn-danger" onclick="return confirm('Hapus?')">🗑</a></td>
    </tr>
    <?php endforeach;?>
  </table></div><?php endif;?>

<?php // ─── MEDIA ─── ?>
<?php elseif($menu==='media'):?>
  <div class="act-bar"><h3>🖼 Media</h3>
    <button class="btn btn-sm btn-success" onclick="document.getElementById('muf').click()">📤 Upload</button>
    <a href="?hapus_semua_media=1" class="btn btn-sm btn-danger" onclick="return confirm('Hapus SEMUA media?')" style="margin-left:auto">🗑 Hapus Semua</a>
    <form method="post" enctype="multipart/form-data" id="muf" style="display:none">
      <input type="file" name="media_upload" accept="image/*,audio/*,video/*" onchange="this.form.submit()">
    </form>
  </div>
  <?php $mediaFiles=array_merge(
    glob(__DIR__.'/uploads/images/*.{jpg,jpeg,png,gif,webp}',GLOB_BRACE),
    glob(__DIR__.'/uploads/audio/*.{mp3,ogg,wav,m4a,webm}',GLOB_BRACE),
    glob(__DIR__.'/uploads/video/*.{mp4,webm,mkv,mov}',GLOB_BRACE)
  );?>
  <?php if(empty($mediaFiles)):?><div class="card"><div class="empty">Belum ada media.</div></div>
  <?php else:?><div class="media-grid">
    <?php foreach($mediaFiles as $f):$ref=str_replace(__DIR__.'/','',$f);$isImg=preg_match('/\.(jpg|jpeg|png|gif|webp)$/i',$f);$isVid=preg_match('/\.(mp4|webm|mkv|mov)$/i',$f);?>
    <div class="media-item">
      <?php if($isImg):?><img src="<?=$ref?>" loading="lazy"><?php elseif($isVid):?><video src="<?=$ref?>" controls preload="none" style="width:100%;border-radius:6px"></video><?php else:?><audio src="<?=$ref?>" controls preload="none"></audio><?php endif;?>
      <div class="info">
        <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?=basename($f)?></div>
        <div style="display:flex;gap:4px;margin-top:3px">
          <button class="cpy" onclick="navigator.clipboard.writeText('<?=$isImg?'[img:':($isVid?'[video:':'[audio:')?><?=$ref?>]')">📋</button>
          <a href="?hapus_media=<?=$ref?>" class="cpy" onclick="return confirm('Hapus?')">🗑</a>
        </div>
      </div>
    </div>
    <?php endforeach;?>
  </div><?php endif;?>

<?php // ─── SISWA ─── ?>
<?php elseif($menu==='siswa'):?>
  <div class="act-bar"><h3>👥 Siswa</h3>
    <button class="btn btn-sm btn-success" onclick="document.getElementById('sf').classList.toggle('hidden')">➕ Tambah</button>
    <a href="?hapus_semua_siswa=1" class="btn btn-sm btn-danger" onclick="return confirm('Hapus SEMUA siswa? Data tidak bisa dikembalikan!')" style="margin-left:auto">🗑 Hapus Semua</a>
  </div>
  <div id="sf" class="card hidden" style="max-width:400px">
    <form method="post">
      <input type="hidden" name="_csrf" value="<?=csrf_token()?>"><input type="hidden" name="sp_siswa" value="1">
      <div class="form-group"><label>NIS</label><input type="text" name="snis" required></div>
      <div class="form-group"><label>Nama</label><input type="text" name="snama" required></div>
      <div class="form-group"><label>Kelas</label>
        <select name="skelas" required>
          <?php foreach(['X-TKR','X-TSM','XI-TKR','XI-TSM','XII-TKR','XII-TSM'] as $k):?>
          <option value="<?=$k?>"><?=$k?></option>
          <?php endforeach;?>
        </select>
      </div>
      <div class="form-group"><label>Password</label><input type="text" name="spass" value="12345"></div>
      <button class="btn btn-sm btn-success" type="submit">💾 Simpan</button>
    </form>
  </div>
  <?php if(empty($siswa)):?><div class="card"><div class="empty">Belum ada siswa.</div></div>
  <?php else:?><div class="table-wrap"><table>
    <tr><th>NIS</th><th>Nama</th><th>Kelas</th><th>Password</th><th></th></tr>
    <?php foreach($siswa as $s):?>
    <tr style="cursor:pointer" onclick="editSiswa('<?=htmlspecialchars($s['nis'],ENT_QUOTES)?>','<?=htmlspecialchars($s['nama'],ENT_QUOTES)?>','<?=htmlspecialchars($s['kelas'],ENT_QUOTES)?>','<?=htmlspecialchars($s['password']??'12345',ENT_QUOTES)?>')">
      <td><?=htmlspecialchars($s['nis'])?></td><td><?=htmlspecialchars($s['nama'])?></td><td><?=htmlspecialchars($s['kelas'])?></td><td><?=htmlspecialchars($s['password']??'12345',ENT_QUOTES)?></td>
      <td><a href="?hapus_siswa=<?=urlencode($s['nis'])?>" class="btn btn-xs btn-danger" onclick="event.stopPropagation();return confirm('Hapus <?=htmlspecialchars($s['nama'])?>?')">🗑</a></td>
    </tr>
    <?php endforeach;?>
  </table></div><?php endif;?>
<script>
function editSiswa(nis,nama,kelas,pass){
  document.getElementById('sf').classList.remove('hidden');
  document.getElementById('sf').scrollIntoView({behavior:'smooth',block:'start'});
  document.querySelector('#sf input[name="snis"]').value=nis;
  document.querySelector('#sf input[name="snama"]').value=nama;
  var sel=document.querySelector('#sf select[name="skelas"]');
  for(var i=0;i<sel.options.length;i++){if(sel.options[i].value===kelas){sel.selectedIndex=i;break;}}
  document.querySelector('#sf input[name="spass"]').value=pass;
}
</script>

<?php // ─── IMPORT ─── ?>
<?php elseif($menu==='import'):?>
<div class="card" style="max-width:550px;margin-top:8px">
  <h3>📝 Import Soal Format Aiken</h3>
  <p style="color:#94a3b8;font-size:.78rem;margin-bottom:6px">Format Aiken:</p>
  <pre style="background:#0f172a;padding:8px;border-radius:6px;font-size:.72rem;color:#94a3b8;line-height:1.5;margin-bottom:10px;white-space:pre-wrap">1. What is the capital of France?
a. London
b. Berlin
c. Paris
d. Madrid
e. Rome
ANSWER: C</pre>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="aiken_import" value="1">
    <div class="form-group"><label>Nama Ujian</label><input type="text" name="judul_aiken" value="Ulangan Harian"></div>
    <div class="form-group"><label>Paste teks Aiken</label>
      <textarea name="aiken_text" style="min-height:120px;font-family:monospace;font-size:.78rem;background:#0f172a;color:#e2e8f0;border:1px solid #334155;border-radius:8px;padding:8px;width:100%" placeholder="Tempel teks Aiken di sini..."></textarea>
    </div>
    <div style="text-align:center;color:#64748b;font-size:.75rem;margin:6px 0">— atau upload file .txt —</div>
    <div class="file-zone" onclick="document.getElementById('af').click()" style="padding:14px;cursor:pointer">
      <div id="al" style="color:#94a3b8;font-size:.78rem">📄 Klik pilih file .txt</div>
      <input type="file" id="af" name="aiken_file" accept=".txt,.aiken" style="display:none" onchange="document.getElementById('al').textContent=this.files[0].name">
    </div>
    <label style="display:flex;align-items:center;gap:4px;color:#94a3b8;font-size:.78rem;cursor:pointer;margin-bottom:8px"><input type="checkbox" name="aktifkan_aiken" value="1" checked> Aktifkan</label>
    <button class="btn btn-sm btn-success" type="submit">📥 Import</button>
  </form>
</div>


<?php if(isA()):?>
<div class="card" style="max-width:550px;margin-top:8px">
  <h3>👥 Import Siswa CSV</h3>
  <p style="color:#94a3b8;font-size:.78rem;margin-bottom:6px">Format CSV: <code>NIS,Nama,Kelas,Jurusan,Password</code> (5 kolom)</p>
  <pre style="background:#0f172a;padding:8px;border-radius:6px;font-size:.72rem;color:#94a3b8;line-height:1.5;margin-bottom:10px;white-space:pre-wrap">25317567,"ACHMAD DZAKKI AL FIKRI",X,X-TKR-1,12345
23456789,"BUDI SANTOSO",XI,TSM-2,12345</pre>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="siswa_import" value="1">
    <div class="form-group"><label>Paste CSV siswa</label>
      <textarea name="siswa_csv" style="min-height:80px;font-family:monospace;font-size:.78rem;background:#0f172a;color:#e2e8f0;border:1px solid #334155;border-radius:8px;padding:8px;width:100%" placeholder="NIS,Nama,Kelas,Jurusan,Password"></textarea>
    </div>
    <div style="text-align:center;color:#64748b;font-size:.75rem;margin:6px 0">— atau upload file .csv —</div>
    <div class="file-zone" onclick="document.getElementById('sf').click()" style="padding:14px;cursor:pointer">
      <div id="sl" style="color:#94a3b8;font-size:.78rem">📄 Klik pilih file .csv</div>
      <input type="file" id="sf" name="siswa_file" accept=".csv,.txt" style="display:none" onchange="document.getElementById('sl').textContent=this.files[0].name">
    </div>
    <button class="btn btn-sm btn-success" type="submit">📥 Import Siswa</button>
  </form>
</div>
<?php endif;?>

<?php if(isA()):?>
<div class="card" style="max-width:550px;margin-top:8px">
  <h3>👤 Import Guru CSV</h3>
  <p style="color:#94a3b8;font-size:.78rem;margin-bottom:6px">Format: <code>KodeGuru,Nama,MataPelajaran,Password,Role (opsional)</code></p>
  <pre style="background:#0f172a;padding:8px;border-radius:6px;font-size:.72rem;color:#94a3b8;line-height:1.5;margin-bottom:10px;white-space:pre-wrap">guru01,Sri Wahyuni,Matematika,guru123,guru
guru02,Ahmad Roni,Bahasa Indonesia,guru123,guru</pre>
  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="guru_import" value="1">
    <div class="form-group"><label>Paste CSV guru</label>
      <textarea name="guru_csv" style="min-height:80px;font-family:monospace;font-size:.78rem;background:#0f172a;color:#e2e8f0;border:1px solid #334155;border-radius:8px;padding:8px;width:100%" placeholder="KodeGuru,Nama,Mapel,Password"></textarea>
    </div>
    <div style="text-align:center;color:#64748b;font-size:.75rem;margin:6px 0">— atau upload file .csv —</div>
    <div class="file-zone" onclick="document.getElementById('gf').click()" style="padding:14px;cursor:pointer">
      <div id="gl" style="color:#94a3b8;font-size:.78rem">📄 Klik pilih file .csv</div>
      <input type="file" id="gf" name="guru_file" accept=".csv,.txt" style="display:none" onchange="document.getElementById('gl').textContent=this.files[0].name">
    </div>
    <button class="btn btn-sm btn-success" type="submit">📥 Import Guru</button>
  </form>
</div>
<?php endif;?>
<?php // ─── SETTINGS ─── ?>
<?php elseif($menu==='pengaturan'):$settoggles=[
  ['🔀 Acak Soal','acak_soal'],
  ['🚫 Blokir Translate','blokir_translate'],
  ['🚫 Deteksi Tab Pindah','deteksi_tab'],
  ['🚫 Deteksi Split Screen','deteksi_split'],
  ['🚫 Blokir Copy','blokir_copy'],
  ['🚫 Blokir DevTools','blokir_devtools'],
  ['⏱ Timer Per Soal','timer_per_soal'],
];?>
  <div class="card settings-grid">
    <h3>⚙️ Pengaturan</h3>
    <form method="post">
      <input type="hidden" name="_csrf" value="<?=csrf_token()?>">
      <?php foreach($settoggles as $t):?>
      <div class="toggle-row"><div><div class="lbl"><?=$t[0]?></div></div><label class="switch"><input type="checkbox" name="<?=$t[1]?>" <?=$settings[$t[1]]?'checked':''?>><span class="sl"></span></label></div>
      <?php endforeach;?>
      <div class="form-group" style="margin-top:10px"><label>Waktu per Soal (detik)</label>
        <input type="number" name="waktu_per_soal" value="<?=$settings['waktu_per_soal']??60?>" min="10" max="600" style="max-width:120px">
      </div>
      <div class="form-group"><label>Waktu Minimal per Soal (detik)</label>
        <input type="number" name="waktu_min_soal" value="<?=$settings['waktu_min_soal']??15?>" min="0" max="120" style="max-width:120px">
        <div style="font-size:0.75rem;color:#94a3b8;margin-top:3px">Siswa tidak bisa lanjut sebelum waktu minimal terpenuhi</div>
      </div>
      <div class="form-group"><label>Max Pindah Tab</label>
        <input type="number" name="max_pindah_tab" value="<?=$settings['max_pindah_tab']??3?>" min="1" max="20" style="max-width:80px">
      </div>
      <div class="form-group"><label>Max Split Screen</label>
        <input type="number" name="max_split_screen" value="<?=$settings['max_split_screen']??2?>" min="1" max="10" style="max-width:80px">
      </div>
      <button class="btn btn-sm btn-success" type="submit" name="sp_set">💾 Simpan</button>
    </form>
  </div>

<?php // ─── GURU (admin) ─── ?>
<?php elseif($menu==='guru'&&isA()):?>
  <div class="act-bar"><h3>👥 Guru</h3>
    <button class="btn btn-sm btn-success" onclick="document.getElementById('gf').classList.toggle('hidden')">➕ Tambah</button>
  </div>
  <div id="gf" class="card hidden" style="max-width:400px">
    <form method="post">
      <input type="hidden" name="_csrf" value="<?=csrf_token()?>"><input type="hidden" name="sp_guru" value="1">
      <input type="hidden" name="gid" id="gid" value="">
      <div class="form-group"><label>Kode Guru</label><input type="text" name="gnis" required></div>
      <div class="form-group"><label>Nama</label><input type="text" name="gnama" required></div>
      <div class="form-group"><label>Mapel</label><input type="text" name="gmapel" required></div>
      <div class="form-group"><label>Password</label><input type="text" name="gpass" value="guru123"></div>
      <div class="form-group"><label>Role</label>
        <select name="grole"><option value="guru">Guru</option><option value="admin">Admin</option></select>
      </div>
      <button class="btn btn-sm btn-success" type="submit">💾 Simpan</button>
    </form>
  </div>
  <?php if(empty($guru)):?><div class="card"><div class="empty">Belum ada guru.</div></div>
  <?php else:?><div class="guru-grid">
    <?php foreach($guru as $g):$jml=count(array_filter($soal,fn($s)=>$s['guru_id']===$g['id']));?>
    <div class="guru-card" style="cursor:pointer" onclick="editGuru('<?=htmlspecialchars($g['id'])?>','<?=htmlspecialchars($g['nis'])?>','<?=htmlspecialchars($g['nama'])?>','<?=htmlspecialchars($g['mapel'])?>','<?=htmlspecialchars($g['password']??'guru123')?>','<?=$g['role']?>')">
      <div class="gn"><?=htmlspecialchars($g['nama'])?></div>
      <div style="font-size:.75rem;color:#64748b"><?=htmlspecialchars($g['mapel'])?> · <?=$jml?> soal</div>
      <span class="label <?=$g['role']==='admin'?'label-yellow':'label-blue'?>"><?=$g['role']?></span>
      <div style="margin-top:6px"><a href="?hapus_guru=<?=urlencode($g['id'])?>" class="btn btn-xs btn-danger" onclick="event.stopPropagation();return confirm('Hapus?')">🗑</a></div>
    </div>
    <?php endforeach;?>
  </div><?php endif;?>
<script>
function editGuru(id,nis,nama,mapel,pass,role){
  document.getElementById('gf').classList.remove('hidden');
  document.getElementById('gf').scrollIntoView({behavior:'smooth',block:'start'});
  document.getElementById('gid').value=id;
  document.querySelector('#gf input[name="gnis"]').value=nis;
  document.querySelector('#gf input[name="gnama"]').value=nama;
  document.querySelector('#gf input[name="gmapel"]').value=mapel;
  document.querySelector('#gf input[name="gpass"]').value=pass;
  var sel=document.querySelector('#gf select[name="grole"]');
  for(var i=0;i<sel.options.length;i++){if(sel.options[i].value===role){sel.selectedIndex=i;break;}}
}
</script>

<?php endif;?>

</div>
</body></html>
