# Ba truy vấn LogQL cho báo cáo

Vào Grafana → Explore → chọn datasource **Loki** → chế độ Code.
Chọn Last 15 minutes rồi Run query.

## 1. Toàn bộ log web và reverse proxy

```logql
{project="student-management", service=~"nginx|web"}
```

Nginx ghi access log JSON; PHP/Apache ghi stdout/stderr. Chụp log có service và timestamp.

## 2. Request HTTP lỗi (4xx/5xx) qua Nginx

```logql
{project="student-management", service="nginx"} | json | __error__="" | status >= 400
```

Tạo lỗi 404 có chủ đích bằng cách truy cập:
http://localhost:8080/?page=khong-ton-tai
Trang này chỉ tạo log kiểm thử, không sửa dữ liệu. Chạy lại query để thấy status 404.
`__error__=""` loại dòng stderr không phải JSON của Nginx.

## 3. Số request mỗi giây theo mã HTTP

```logql
sum by (status) (rate({project="student-management", service="nginx"} | json | __error__="" [5m]))
```

Mở web, chuyển giữa các mục và chờ khoảng 30 giây. Query rate cần dữ liệu trong cửa sổ 5 phút.
Đổi sang biểu đồ Time series để chụp. Truy vấn này đo request qua Nginx, bao gồm probe /health.

## Luồng log và lưu trữ

Docker stdout/stderr → Docker API → Promtail (lọc project/service) → Loki → Grafana.
Lưu positions và dữ liệu Loki trên named volumes. Loki giữ log 24h để phù hợp máy RAM 8 GB.
Không dùng Docker Desktop GUI logs làm thay thế minh chứng Loki: phải chụp kết quả truy vấn.

Promtail đã EOL ngày 02/03/2026: giữ lại đúng rubric môn học, triển khai thật nên dùng Grafana Alloy.
Tài liệu: https://grafana.com/docs/loki/latest/send-data/promtail/
