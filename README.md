# Quản lý sinh viên — Docker Compose

Website CRUD sinh viên, lớp và điểm số cho môn Triển khai và Quản trị Hệ thống Phần mềm.

## Chạy nhanh

Mở Docker Desktop (Linux containers), mở PowerShell tại thư mục dự án.

Nếu máy hiện tại đã được thiết lập, chỉ cần:

```powershell
docker compose up -d --build
docker compose ps
```

Nếu clone mới, tạo mật khẩu và tài khoản DB giám sát trước:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\setup.ps1
powershell -ExecutionPolicy Bypass -File .\scripts\setup-monitoring.ps1
docker compose up -d --build
```

Compose 2.20+ hỗ trợ include; đã kiểm tra trên Compose 2.27.1 / Docker Engine 26.1.4.
Giữ MYSQL_DATABASE=student_management và MYSQL_USER=student_app theo SQL khởi tạo.
Không commit .env hoặc .secrets. Không thay mật khẩu .env khi sử dụng lại volume DB cũ.

## Truy cập

| Thành phần | URL | Thông tin |
|---|---|---|
| Web qua Nginx | http://localhost:8080 | CRUD lớp, sinh viên, điểm |
| phpMyAdmin | http://localhost:8081 | student_app / MYSQL_PASSWORD trong .env |
| Grafana | http://localhost:3000/d/student-management | admin / GRAFANA_ADMIN_PASSWORD trong .env |
| Prometheus | http://localhost:9090/targets | 4 job UP |
| Loki | http://localhost:3100/ready | Ready khi khởi động xong |

Mật khẩu là giá trị phía sau dấu =, không bao gồm tên biến. Không đưa mật khẩu vào ảnh báo cáo.

## Thành phần và file cấu hình

| File/thư mục | Vai trò |
|---|---|
| src/index.php | CRUD, PDO prepared statements, CSRF, escape HTML, /health |
| Dockerfile | PHP 8.3 + Apache, PDO MySQL, USER www-data |
| db/init.sql | Ba bảng, khóa ngoại, dữ liệu mẫu và quyền CRUD |
| compose.yaml | Web/MySQL/phpMyAdmin/Nginx, mạng và persistent volume |
| nginx/nginx.conf | Reverse proxy, security headers, JSON access log |
| compose.monitoring.yaml | Prometheus, Grafana, cAdvisor, Blackbox, mysqld_exporter |
| monitoring/ | Scrape config, HTTP probe, datasource/dashboard tự provision |
| compose.logging.yaml | Loki và Promtail |
| logging/ | Loki TSDB filesystem + Promtail Docker discovery |
| scripts/setup.ps1 | Tạo hai mật khẩu ngẫu nhiên app/root |
| scripts/setup-monitoring.ps1 | Tạo mật khẩu Grafana/metrics, cấp quyền DB, tạo exporter.cnf |
| scripts/smoke.ps1 | Kiểm tra CRUD và ràng buộc dữ liệu |
| scripts/verify-stack.ps1 | Kiểm tra headers, CRUD, metrics, dashboard, datasource, 3 LogQL |
| docs/deployment-guide.md | Từng tiêu chí, lệnh kiểm tra, ảnh cần chụp, kịch bản demo |
| docs/logql.md | Ba truy vấn LogQL và cách tạo log minh chứng |
| docs/report-outline.md | Dàn ý báo cáo tối thiểu 10 trang |

Toàn bộ code nằm trong các file trên. Không cần cài PHP/MySQL trực tiếp trên Windows.
Sơ đồ và giải thích chi tiết: [hướng dẫn tiêu chí 3–7](docs/deployment-guide.md).

## Kiểm tra

Sau khi khởi động, đợi khoảng một phút cho metrics/log có dữ liệu:

```powershell
docker compose config --quiet
powershell -ExecutionPolicy Bypass -File .\scripts\verify-stack.ps1
docker stats --no-stream
git log --oneline -8
```

Không dùng output docker compose config đầy đủ để chụp ảnh: có thể hiện mật khẩu.
Không khẳng định hoàn tất chỉ dựa vào container Up: phải có mysql_up=1,
probe_success=1, số liệu container thật, Grafana dashboard có dữ liệu và LogQL trả log.

## Các mốc commit theo đề

- Commit 1: Nginx reverse proxy + security headers (chọn security headers, chưa dùng HTTPS).
- Commit 2: Prometheus/Grafana giám sát container/web/database.
- Commit 3: Loki/Promtail, ba query LogQL, xác minh hardening và hướng dẫn demo.

Các commit khởi tạo và sửa giao diện trước đó được giữ nguyên.

## Bảo mật và tài nguyên

Web chạy www-data, Nginx non-root + filesystem chỉ đọc; các mạng backend/frontend/monitoring
là internal. Chỉ các giao diện cần dùng được bind 127.0.0.1. MySQL và exporter không publish cổng.
Mật khẩu ngẫu nhiên riêng cho root/app/metrics/Grafana; app chỉ CRUD trong một DB,
metrics không được đọc bảng nghiệp vụ. Có security headers, cap_drop và no-new-privileges.
Docker logs xoay vòng 10 MB x 3/service; Prometheus/Loki giữ 24h.

Ngoại lệ: cAdvisor privileged và Promtail có Docker socket, quyền cao cần cho demo này;
mount socket :ro không làm Docker API thành chỉ đọc. Không mở stack lên Internet.
Promtail đã EOL ngày 02/03/2026, giữ theo đề; triển khai thật nên dùng Grafana Alloy.
Web demo chưa có đăng nhập/phân quyền. Pin image giúp tái lập, không thay thế việc cập nhật bảo mật.

## Dừng và chạy lại

```powershell
docker compose down
docker compose up -d --build
docker compose logs --tail=40 nginx web db
docker compose logs --tail=40 prometheus mysql-exporter cadvisor
docker compose logs --tail=40 loki promtail grafana
```

Không dùng down -v nếu muốn giữ dữ liệu. init.sql chỉ chạy khi volume DB trống.
Nếu di chuyển thư mục, giữ .env/.secrets và chạy lại up để cập nhật bind mount.
Máy kiểm tra: Windows 10 Pro 64-bit, RAM khoảng 8 GB, Docker Linux VM khoảng 3.77 GiB.

## Tài liệu tham khảo

- https://docs.docker.com/compose/how-tos/startup-order/
- https://docs.docker.com/reference/compose-file/include/
- https://hub.docker.com/_/mysql
- https://hub.docker.com/_/phpmyadmin
- https://prometheus.io/docs/guides/multi-target-exporter/
- https://github.com/prometheus/mysqld_exporter
- https://grafana.com/docs/loki/latest/configure/examples/configuration-examples/
- https://grafana.com/docs/loki/latest/send-data/promtail/

Báo cáo hoàn chỉnh cần thêm thông tin sinh viên và ảnh chụp thực tế theo hướng dẫn.
