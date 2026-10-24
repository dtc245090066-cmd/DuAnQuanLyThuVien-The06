# Quản lý sinh viên — bài thực hành Docker Compose

Đề 2: quản lý sinh viên, lớp và điểm số. PHP 8.3 + MySQL 8.4 + phpMyAdmin 5.2.
Phạm vi hiện tại: bước 1 (mã nguồn/Git) và bước 2 (web/DB/phpMyAdmin).
Chưa triển khai Nginx, Prometheus/Grafana, Loki/Promtail hoặc báo cáo hoàn chỉnh.

## Cấu trúc và mã nguồn

| File | Vai trò |
|---|---|
| `src/index.php` | Giao diện CRUD, kiểm tra dữ liệu, PDO prepared statements, CSRF, `/health` |
| `db/init.sql` | Tạo 3 bảng, khóa ngoại, dữ liệu mẫu, giới hạn quyền DB |
| `Dockerfile` | PHP Apache, PDO MySQL, web chạy bằng www-data ở cổng 8080 |
| `compose.yaml` | Khởi chạy web, MySQL, phpMyAdmin; mạng riêng và volume DB |
| `.env.example` | Mẫu cấu hình, không chứa mật khẩu thật |
| `scripts/setup.ps1` | Tạo `.env` với hai mật khẩu ngẫu nhiên khác nhau |
| `.gitignore`, `.dockerignore` | Loại mật khẩu và dữ liệu không cần thiết |

Toàn bộ code nằm trong các file trên, có thể mở bằng VS Code hoặc Notepad.

## Bước 1 — Chuẩn bị và quản lý mã nguồn

### 1.1 Kiểm tra máy

Mở PowerShell tại thư mục dự án:

```powershell
docker --version
docker compose version
git --version
```

Máy đã kiểm tra: Windows 10 Pro 64-bit, RAM 7.9 GiB; có Docker CLI 26.1.4,
Compose 2.27.1 và Git. Docker Engine chưa chạy tại thời điểm kiểm tra.
Chưa xác minh CPU/WSL2 hoặc khả năng chạy container Linux thành công.
Không cần cài PHP hoặc MySQL trực tiếp trên Windows.

Mở Docker Desktop từ Start, đợi Engine chạy, dùng Linux containers. Kiểm tra:

```powershell
docker version
docker info --format '{{.OSType}}'
wsl --status
```

Thành công: `docker version` có cả Client và Server; OSType là `linux`.
Nếu Docker báo lỗi WSL/ảo hóa, ghi lại nguyên văn lỗi trước khi chỉnh hệ thống.
RAM 8 GB: đóng ứng dụng nặng; chỉ chạy 3 dịch vụ này trước khi thêm giám sát.

### 1.2 Tạo mật khẩu local

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\setup.ps1
```

Script chỉ tạo `.env` khi chưa có, sinh mật khẩu bằng bộ sinh số ngẫu nhiên mật mã.
Giữ `MYSQL_DATABASE=student_management` và `MYSQL_USER=student_app`, vì SQL khởi tạo
đang dùng hai tên này. Không đưa `.env` lên GitHub hoặc vào ảnh báo cáo.

### 1.3 Commit và GitHub

Thư mục hiện tại đã có `.git`, không cần `git init` lại. Thư mục khác chưa có Git thì chạy `git init`.

```powershell
git status
git add .gitignore .dockerignore .env.example Dockerfile compose.yaml db src scripts README.md docs
git diff --cached --stat
git commit -m "chore: initialize student management CRUD and Docker Compose"
```

Nếu Git yêu cầu danh tính, đặt tên/email thật của bạn cho repository này rồi commit lại:

```powershell
git config user.name "TEN_CUA_BAN"
git config user.email "EMAIL_CUA_BAN"
```

Vào GitHub, tạo repository `student-management-docker` trống (không tạo README sẵn).
Thay `TEN_GITHUB` dưới đây bằng tài khoản thật:

```powershell
git branch -M main
git remote -v
git remote add origin https://github.com/TEN_GITHUB/student-management-docker.git
git push -u origin main
```

Nếu đã có `origin`, kiểm tra URL; chỉ dùng `git remote set-url origin URL_THAT` nếu cần sửa.
Đăng nhập theo cửa sổ Git Credential Manager, không ghi token vào URL.

Kiểm tra thành công: GitHub hiển thị README và mã nguồn; không có `.env`.

```powershell
git ls-files .env
git log --oneline -5
```

Lệnh đầu không được hiện gì. Commit nền này tách riêng, không tính thay commit 1 của Nginx.
Các mốc sau: commit 1 Nginx + security headers/HTTPS; commit 2 monitoring;
commit 3 Loki + Promtail + LogQL. Chưa thực hiện các mốc đó ở bước này.

Ảnh cần chụp: phiên bản công cụ (Client và Server), GitHub có cây file/README,
lịch sử commit. Không chụp nội dung `.env`.

## Bước 2 — Chạy web, MySQL và phpMyAdmin

### 2.1 Khởi động

Chạy tại thư mục có `compose.yaml`, sau khi Docker Desktop sẵn sàng:

```powershell
docker compose config --quiet
docker compose up -d --build
docker compose ps
```

Lần đầu cần Internet để tải image và biên dịch PDO MySQL, có thể mất vài phút.
MySQL phải healthy rồi web/phpMyAdmin mới chạy. Không dùng `docker compose config`
đầy đủ để chụp ảnh vì kết quả có thể chứa mật khẩu.

Mở web: http://localhost:8080

Mở phpMyAdmin: http://localhost:8081

Đăng nhập phpMyAdmin với user `student_app`, mật khẩu `MYSQL_PASSWORD` trong `.env`.
Chỉ đọc mật khẩu trên máy; không dùng tài khoản root cho ứng dụng.
Tài khoản này được SELECT/INSERT/UPDATE/DELETE trong database `student_management`;
không được CREATE/DROP bảng. Schema được tạo tự động trong lần khởi tạo DB đầu.

### 2.2 Kiểm tra web và DB

```powershell
Invoke-RestMethod http://localhost:8080/health
docker compose ps
docker compose logs --tail=50 web db phpmyadmin
```

Thành công: health trả `status: ok`, `database: ok`; có 3 dịch vụ đang chạy, DB healthy.
Trên web thấy hai sinh viên mẫu. Trên phpMyAdmin thấy `classes`, `students`, `grades`.

Thực hành theo thứ tự:

1. Mục Lớp học: thêm lớp `CNTT03`, sửa thành `CNTT03A`.
2. Mục Sinh viên: thêm `SV003`, tên tùy chọn, email hợp lệ, chọn `CNTT03A`.
3. Mục Điểm số: chọn `SV003`, môn `Docker`, điểm `8.5`; sửa điểm thành `9`.
4. Thử nhập điểm `11`: bị chặn. Trùng mã sinh viên: có thông báo lỗi.
5. Thử xóa lớp đang có sinh viên: bị chặn bởi khóa ngoại.
6. Xóa điểm thử, xóa sinh viên thử, rồi xóa lớp thử: thành công.

Kiểm tra lưu dữ liệu: thêm một lớp thử, chạy hai lệnh dưới rồi tải lại trang.
Lớp vừa thêm phải còn vì DB lưu trên named volume.

```powershell
docker compose down
docker compose up -d
```

Trong tab SQL của phpMyAdmin có thể chạy:

```sql
SHOW TABLES;
SELECT s.code, s.name, c.name AS class_name, g.subject, g.score
FROM students s
JOIN classes c ON c.id = s.class_id
LEFT JOIN grades g ON g.student_id = s.id;
SHOW GRANTS FOR CURRENT_USER;
```

Ảnh cần chụp: `docker compose ps` với 3 dịch vụ; danh sách sinh viên;
màn hình lớp học; màn hình điểm sau sửa; phpMyAdmin có 3 bảng và kết quả JOIN;
health endpoint; thông báo chặn thao tác không hợp lệ; dữ liệu còn sau restart.

### 2.3 Dừng và xử lý lỗi

```powershell
docker compose stop
docker compose start
docker compose logs --tail=100 db
```

- Không kết nối được Docker pipe: mở Docker Desktop, đợi Server sẵn sàng.
- Cổng 8080/8081 bị chiếm: đổi cổng bên trái trong `compose.yaml` thành 8090/8091,
  chạy lại `docker compose up -d`, truy cập cổng mới.
- DB khởi tạo chậm: xem log DB, chờ healthy; không tải lại liên tục.
- Đổi mật khẩu trong `.env` sau khi DB đã có volume không tự đổi mật khẩu DB.
  Khôi phục mật khẩu đúng hoặc đổi bằng SQL quản trị trước khi cập nhật `.env`.
- `init.sql` chỉ chạy lần đầu khi volume trống. Không dùng `down -v` tùy tiện:
  tùy chọn `-v` xóa toàn bộ dữ liệu DB của dự án.
- Lỗi tải image/apt: kiểm tra mạng, Docker Desktop và chạy lại build.

## Biện pháp đã đặt nền

Web chạy www-data, không root; mạng backend internal; DB không publish cổng;
hai mật khẩu ngẫu nhiên khác nhau; tài khoản ứng dụng chỉ có quyền CRUD;
prepared statements, encode HTML và CSRF token. phpMyAdmin và MySQL chưa được
khẳng định chạy non-root. Đây là demo thực hành local, chưa có đăng nhập/phân quyền
người dùng và chưa có TLS; không mở lên Internet.

## Tài liệu chính thức

- https://docs.docker.com/compose/how-tos/startup-order/
- https://hub.docker.com/_/mysql
- https://hub.docker.com/_/phpmyadmin

## Báo cáo dự kiến (ít nhất 10 trang)

Xem `docs/report-outline.md`. Hiện chỉ thu thập minh chứng bước 1–2;
không ghi các chức năng giám sát/Nginx là đã hoàn thành.
