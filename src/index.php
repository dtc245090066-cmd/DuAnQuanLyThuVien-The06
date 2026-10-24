<?php
declare(strict_types=1);
session_start();
function h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
try {
    $db = new PDO('mysql:host='.getenv('DB_HOST').';dbname='.getenv('DB_NAME').';charset=utf8mb4', getenv('DB_USER'), getenv('DB_PASSWORD'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
} catch (Throwable $e) {
    error_log('Database connection failed'); http_response_code(503); exit('Cơ sở dữ liệu chưa sẵn sàng. Kiểm tra docker compose logs db.');
}
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)==='/health') {
    $db->query('SELECT 1'); header('Content-Type: application/json'); echo '{"status":"ok","database":"ok"}'; exit;
}
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$entities = [
 'classes'=>['title'=>'Lớp học','nav'=>'Thêm lớp học','fields'=>['name'=>'Tên lớp']],
 'students'=>['title'=>'Sinh viên','nav'=>'Thêm sinh viên','fields'=>['code'=>'Mã sinh viên','name'=>'Họ tên','email'=>'Email','class_id'=>'Lớp']],
 'grades'=>['title'=>'Điểm số','nav'=>'Cập nhật điểm số','fields'=>['student_id'=>'Sinh viên','subject'=>'Môn học','score'=>'Điểm (0–10)']]
];
$page = $_GET['page'] ?? 'students';
if (!isset($entities[$page])) { http_response_code(404); exit('Không tìm thấy trang'); }
$meta=$entities[$page]; $error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 try {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) throw new RuntimeException('Phiên không hợp lệ. Tải lại trang.');
    $id=filter_var($_POST['id'] ?? 0,FILTER_VALIDATE_INT);
    if ($id===false || $id<0) throw new RuntimeException('ID không hợp lệ.');
    if (($_POST['action'] ?? '')==='delete') {
        if (!$id) throw new RuntimeException('Thiếu ID.');
        $stmt=$db->prepare("DELETE FROM `$page` WHERE id=?"); $stmt->execute([$id]);
    } else {
        $values=[];
        foreach ($meta['fields'] as $key=>$label) {
            $v=trim((string)($_POST[$key] ?? ''));
            if ($v==='') throw new RuntimeException('Vui lòng nhập '.$label.'.');
            if (strlen($v)>($key==='email'?150:100)) throw new RuntimeException($label.' quá dài.');
            if ($key==='code' && strlen($v)>30) throw new RuntimeException('Mã sinh viên tối đa 30 ký tự.');
            if ($key==='email' && !filter_var($v,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Email không hợp lệ.');
            if ($key==='score' && (!is_numeric($v) || (float)$v<0 || (float)$v>10)) throw new RuntimeException('Điểm phải từ 0 đến 10.');
            if (in_array($key,['class_id','student_id']) && (!ctype_digit($v) || (int)$v<1)) throw new RuntimeException('Lựa chọn không hợp lệ.');
            $values[]=$v;
        }
        $columns=array_keys($meta['fields']);
        if ($id) { $sql="UPDATE `$page` SET ".implode(',',array_map(fn($c)=>"`$c`=?",$columns)).' WHERE id=?'; $values[]=$id; }
        else { $sql="INSERT INTO `$page` (`".implode('`,`',$columns).'`) VALUES ('.implode(',',array_fill(0,count($columns),'?')).')'; }
        $db->prepare($sql)->execute($values);
    }
    header('Location: ?page='.$page.'&saved=1'); exit;
 } catch (PDOException $e) { $error='Không lưu được: mã/tên bị trùng hoặc dữ liệu đang được tham chiếu. Hãy kiểm tra lớp, sinh viên và môn học.'; }
 catch (RuntimeException $e) { $error=$e->getMessage(); }
}
$classes=$db->query('SELECT * FROM classes ORDER BY name')->fetchAll();
$students=$db->query('SELECT * FROM students ORDER BY code')->fetchAll();
$classNames=array_column($classes,'name','id');
$studentNames=[]; foreach($students as $s) $studentNames[$s['id']]=$s['code'].' — '.$s['name'];
$edit=null;
if (isset($_GET['edit'])) { $stmt=$db->prepare("SELECT * FROM `$page` WHERE id=?"); $stmt->execute([(int)$_GET['edit']]); $edit=$stmt->fetch() ?: null; }
$rows=$db->query("SELECT * FROM `$page` ORDER BY id DESC")->fetchAll();
?>
<!doctype html><html lang="vi"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Quản lý sinh viên</title>
<style>body{font:16px system-ui;margin:0;background:#f1f5f9;color:#172554}header{background:#172554;color:white;padding:24px}main{max-width:1100px;margin:24px auto;padding:0 18px}nav{display:flex;gap:12px;margin-bottom:24px}a{color:#1d4ed8}nav a,button{padding:10px 16px;border:0;border-radius:6px;background:#2563eb;color:white;text-decoration:none;cursor:pointer}section{background:white;padding:22px;border-radius:12px;margin-bottom:24px;overflow:auto}form.editor{display:flex;flex-wrap:wrap;gap:16px;align-items:end}label{display:grid;gap:6px}input,select{padding:10px;border:1px solid #94a3b8;border-radius:5px}table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:12px;border-bottom:1px solid #ddd}.inline{display:inline}button.delete{background:#b91c1c;padding:6px 12px}.error{color:#b91c1c}.success{color:#15803d}</style>
<header><h1>Quản lý sinh viên</h1><div>Bài thực hành triển khai hệ thống • PHP + MySQL + Docker Compose</div></header><main><nav><?php foreach($entities as $key=>$entity): ?><a href="?page=<?=h($key)?>"><?=h($entity['nav'])?></a><?php endforeach ?></nav>
<?php if($error): ?><p class="error"><?=h($error)?></p><?php endif ?>
<?php if(isset($_GET['saved'])): ?><p class="success">Thao tác thành công.</p><?php endif ?>
<section><h2><?= $edit?'Sửa':'Thêm' ?> <?=h($meta['title'])?></h2><form method="post" class="editor"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="id" value="<?=h($edit['id'] ?? 0)?>">
<?php foreach($meta['fields'] as $key=>$label): ?><label><?=h($label)?>
<?php if(in_array($key,['class_id','student_id'])): $options=$key==='class_id'?$classNames:$studentNames; ?><select name="<?=h($key)?>" required><option value="">Chọn...</option><?php foreach($options as $id=>$name): ?><option value="<?=h($id)?>" <?= (string)($edit[$key] ?? '')===(string)$id?'selected':'' ?>><?=h($name)?></option><?php endforeach ?></select>
<?php else: ?><input name="<?=h($key)?>" value="<?=h($edit[$key] ?? '')?>" type="<?=$key==='score'?'number':($key==='email'?'email':'text')?>" <?=$key==='score'?'min="0" max="10" step="0.01"':('maxlength="'.($key==='email'?150:($key==='code'?30:100)).'"')?> required><?php endif ?></label><?php endforeach ?>
<button>Lưu</button><?php if($edit): ?><a href="?page=<?=h($page)?>">Hủy sửa</a><?php endif ?></form></section>
<section><h2>Danh sách <?=h($meta['title'])?> (<?=count($rows)?>)</h2><table><thead><tr><th>ID</th><?php foreach($meta['fields'] as $label): ?><th><?=h($label)?></th><?php endforeach ?><th>Thao tác</th></tr></thead><tbody>
<?php foreach($rows as $row): ?><tr><td><?=h($row['id'])?></td><?php foreach($meta['fields'] as $key=>$label): ?><td><?=h($key==='class_id'?($classNames[$row[$key]] ?? ''):($key==='student_id'?($studentNames[$row[$key]] ?? ''):$row[$key]))?></td><?php endforeach ?><td><a href="?page=<?=h($page)?>&edit=<?=h($row['id'])?>">Sửa</a> <form method="post" class="inline" onsubmit="return confirm('Xóa dữ liệu này? Xóa sinh viên sẽ xóa cả điểm của sinh viên.');"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="id" value="<?=h($row['id'])?>"><input type="hidden" name="action" value="delete"><button class="delete">Xóa</button></form></td></tr><?php endforeach ?>
</tbody></table><p>Lớp đang có sinh viên không thể xóa. Xóa sinh viên sẽ xóa các điểm liên quan.</p></section></main></html>
