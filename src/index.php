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
$totals = [
    'students' => count($students),
    'classes' => count($classes),
    'grades' => (int)$db->query('SELECT COUNT(*) FROM grades')->fetchColumn()
];
$average = $db->query('SELECT AVG(score) FROM grades')->fetchColumn();
$descriptions = [
    'students'=>'Theo dõi thông tin sinh viên và kết nối với từng lớp học.',
    'classes'=>'Sắp xếp lớp học để quản lý sinh viên thuận tiện hơn.',
    'grades'=>'Cập nhật kết quả học tập, theo dõi từng môn học.'
];
$placeholders = ['code'=>'VD: SV005','name'=>$page==='classes'?'VD: CNTT03':'Nhập họ và tên','email'=>'sinhvien@example.com','subject'=>'VD: Triển khai hệ thống','score'=>'0.00 – 10.00'];
$formValues = $error && ($_POST['action'] ?? '') !== 'delete' ? $_POST : ($edit ?: []);
function uiIcon(string $name): string {
    $paths = [
        'students'=>'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M22 21v-2a4 4 0 0 0-3-3.87"/><circle cx="9" cy="7" r="4"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'classes'=>'<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
        'grades'=>'<path d="M9 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-4M9 15l9-9 3 3-9 9-4 1 1-4Z"/><path d="m16 8 3 3"/>',
        'cap'=>'<path d="m2 9 10-5 10 5-10 5L2 9ZM6 11v6c4 3 8 3 12 0v-6M22 9v7"/>',
        'plus'=>'<path d="M12 5v14M5 12h14"/>',
        'search'=>'<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/>',
        'arrow'=>'<path d="M5 12h14m-6-6 6 6-6 6"/>',
        'edit'=>'<path d="m15 5 4 4M4 20l4-1L20 7a2.83 2.83 0 0 0-4-4L4 15l-1 6Z"/>',
        'trash'=>'<path d="M3 6h18M9 6V4h6v2M5 6l1 14h12l1-14M10 10v6M14 10v6"/>',
        'check'=>'<path d="m5 12 4 4L19 6"/>',
        'info'=>'<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7h.01"/>',
        'calendar'=>'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>'
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name] ?? $paths['info']).'</svg>';
}
$today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Bangkok')))->format('d/m/Y');
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#125847">
<title><?=h($meta['title'])?> · Campus</title>
<link rel="stylesheet" href="/assets/app.css">
<script src="/assets/app.js" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Đến nội dung chính</a>
<aside class="sidebar">
    <a class="brand" href="?page=students"><span class="brand-symbol"><?=uiIcon('cap')?></span><span>campus<span class="brand-dot">.</span><small>QUẢN LÝ SINH VIÊN</small></span></a>
    <div class="workspace"><span class="workspace-icon">C</span><div>Không gian đào tạo<small>Quản lý học tập</small></div><span class="workspace-mark">⌄</span></div>
    <p class="nav-caption">KHÔNG GIAN QUẢN LÝ</p>
    <nav aria-label="Điều hướng chính">
    <?php foreach($entities as $key=>$entity): ?>
        <a class="nav-item <?=$page===$key?'active':''?>" href="?page=<?=h($key)?>" <?=$page===$key?'aria-current="page"':''?>>
            <?=uiIcon($key)?><span><?=h($entity['nav'])?></span><span class="nav-count"><?=h($totals[$key])?></span>
        </a>
    <?php endforeach ?>
    </nav>
    <div class="sidebar-note"><span class="note-icon"><?=uiIcon('cap')?></span><h3>Mọi thông tin,<br>một không gian.</h3><p>Quản lý lớp học, sinh viên và kết quả học tập thật dễ dàng.</p><span class="note-line"></span></div>
    <div class="sidebar-bottom"><span class="avatar">QL</span><div>Quản lý đào tạo<small>Hệ thống sinh viên</small></div></div>
</aside>
<div class="app">
<header class="topbar"><div class="breadcrumb">Không gian đào tạo <span>/</span> <strong><?=h($meta['title'])?></strong></div><div class="date"><?=uiIcon('calendar')?> <?=h($today)?></div></header>
<main id="main">
    <div class="page-heading"><div><p class="eyebrow">QUẢN LÝ HỌC TẬP</p><h1><?=h($meta['title'])?></h1><p class="description"><?=h($descriptions[$page])?></p></div><a class="button primary heading-action" href="#editor"><?=uiIcon('plus')?> <?=h($entities[$page]['nav'])?></a></div>
    <section class="overview" aria-label="Tổng quan">
        <?php foreach($entities as $key=>$entity): ?>
        <a href="?page=<?=h($key)?>" class="stat-card stat-<?=h($key)?>"><div class="stat-top"><span class="stat-label">Tổng <?=h($entity['title'])?></span><span class="stat-icon"><?=uiIcon($key)?></span></div><strong class="stat-value"><?=h($totals[$key])?></strong><div class="stat-bottom"><span><?=$key==='grades'?($average!==null?'Điểm trung bình: '.h(number_format((float)$average,2)):'Chưa có điểm được ghi nhận'):($key==='students'?'Sinh viên trong hệ thống':'Lớp học đang được quản lý')?></span><?=uiIcon('arrow')?></div></a>
        <?php endforeach ?>
    </section>
    <?php if($error): ?><div class="alert alert-error" role="alert"><?=uiIcon('info')?><p class="error"><?=h($error)?></p></div><?php endif ?>
    <?php if(isset($_GET['saved'])): ?><div class="alert alert-success" role="status"><?=uiIcon('check')?><p>Đã lưu thay đổi thành công.</p></div><?php endif ?>
    <div class="content-grid">
        <section class="panel list-panel" aria-labelledby="list-title">
            <div class="panel-heading"><div><h2 id="list-title">Danh sách <?=h($meta['title'])?> <span class="count-badge"><?=count($rows)?></span></h2><p>Thông tin được cập nhật trong hệ thống.</p></div><span class="list-decoration"><?=uiIcon($page)?></span></div>
            <div class="table-toolbar"><label class="search-box"><?=uiIcon('search')?><input type="search" id="table-search" placeholder="Tìm trong danh sách..." aria-label="Tìm trong danh sách <?=h($meta['title'])?>"></label><span class="table-total"><span class="status-dot"></span> <span id="result-count"><?=count($rows)?></span> bản ghi</span></div>
            <div class="table-wrap"><table><thead><tr><th>ID</th><?php foreach($meta['fields'] as $label): ?><th><?=h($label)?></th><?php endforeach ?><th class="actions-title">Thao tác</th></tr></thead><tbody id="data-rows">
            <?php foreach($rows as $row): ?><tr><td><?=h($row['id'])?></td><?php foreach($meta['fields'] as $key=>$label): ?><td><?=h($key==='class_id'?($classNames[$row[$key]] ?? ''):($key==='student_id'?($studentNames[$row[$key]] ?? ''):$row[$key]))?></td><?php endforeach ?><td><div class="row-actions"><a class="icon-button edit-button" href="?page=<?=h($page)?>&edit=<?=h($row['id'])?>#editor" aria-label="Sửa bản ghi <?=h($row['id'])?>" title="Sửa"><?=uiIcon('edit')?></a><form method="post" class="inline" onsubmit="return confirm('Xóa dữ liệu này? Xóa sinh viên sẽ xóa cả điểm của sinh viên.');"><input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>"><input type="hidden" name="id" value="<?=h($row['id'])?>"><input type="hidden" name="action" value="delete"><button class="icon-button delete-button" aria-label="Xóa bản ghi <?=h($row['id'])?>" title="Xóa"><?=uiIcon('trash')?></button></form></div></td></tr><?php endforeach ?>
            </tbody></table></div>
            <div class="empty-state" id="empty-state" <?=count($rows)?'hidden':''?>><?=uiIcon($page)?><h3 id="empty-title">Chưa có dữ liệu</h3><p id="empty-description">Bắt đầu bằng cách thêm thông tin ở biểu mẫu bên cạnh.</p></div>
            <div class="table-footer"><?=uiIcon('info')?><span><?=$page==='classes'?'Lớp đang có sinh viên sẽ được bảo vệ khỏi thao tác xóa.':($page==='students'?'Xóa sinh viên sẽ xóa các điểm số liên quan.':'Điểm hợp lệ nằm trong khoảng từ 0 đến 10.')?></span></div>
        </section>
        <section class="panel editor-panel" id="editor" aria-labelledby="editor-title">
            <div class="editor-heading"><span class="editor-icon"><?=uiIcon($edit?'edit':'plus')?></span><div><h2 id="editor-title"><?=$edit?'Chỉnh sửa thông tin':h($meta['nav'])?></h2><p><?=$edit?'Cập nhật và lưu thông tin mới.':'Điền thông tin để tạo bản ghi mới.'?></p></div></div>
            <form method="post" class="editor">
                <input type="hidden" name="csrf" value="<?=h($_SESSION['csrf'])?>">
                <input type="hidden" name="id" value="<?=h($formValues['id'] ?? 0)?>">
                <?php foreach($meta['fields'] as $key=>$label): ?><label class="field"><span><?=h($label)?> <span class="required">*</span></span>
                <?php if(in_array($key,['class_id','student_id'])): $options=$key==='class_id'?$classNames:$studentNames; ?>
                    <select name="<?=h($key)?>" required><option value="">Chọn <?=$key==='class_id'?'lớp học':'sinh viên'?>...</option><?php foreach($options as $id=>$name): ?><option value="<?=h($id)?>" <?=(string)($formValues[$key] ?? '')===(string)$id?'selected':''?>><?=h($name)?></option><?php endforeach ?></select>
                    <?php if(!$options): ?><small class="field-hint">Hãy thêm <?=$key==='class_id'?'lớp học':'sinh viên'?> trước khi tiếp tục.</small><?php endif ?>
                <?php else: ?>
                    <input name="<?=h($key)?>" value="<?=h($formValues[$key] ?? '')?>" placeholder="<?=h($placeholders[$key] ?? '')?>" type="<?=$key==='score'?'number':($key==='email'?'email':'text')?>" <?=$key==='score'?'min="0" max="10" step="0.01"':('maxlength="'.($key==='email'?150:($key==='code'?30:100)).'"')?> required>
                <?php endif ?></label><?php endforeach ?>
                <div class="form-actions"><button class="button primary" type="submit"><?=uiIcon('check')?> <?=$edit?'Lưu thay đổi':'Lưu thông tin'?></button><?php if($edit): ?><a class="button secondary" href="?page=<?=h($page)?>">Hủy chỉnh sửa</a><?php else: ?><button type="reset" class="reset-button">Nhập lại</button><?php endif ?></div>
            </form>
            <div class="form-note"><?=uiIcon('info')?><p>Các trường có dấu <span class="required">*</span> là bắt buộc. Hãy kiểm tra thông tin trước khi lưu.</p></div>
        </section>
    </div>
    <footer class="page-footer"><span>Campus <span class="footer-dot">•</span> Không gian quản lý sinh viên</span><span>Rõ thông tin. Gọn quản lý.</span></footer>
</main>
</div>
</body>
</html>
