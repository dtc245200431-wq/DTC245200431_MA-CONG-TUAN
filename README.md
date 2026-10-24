# Attendance System

Ung dung diem danh sinh vien bang PHP, MySQL va Docker Compose. He thong co dang nhap quan tri, ghi nhan diem danh va dashboard monitoring.

## Chay lan dau
1. Cai Docker Desktop va mo Docker Desktop cho den khi hien `Engine running`.
2. Mo thu muc co `docker-compose.yml` bang VS Code.
3. Tao file cau hinh:

```powershell
Copy-Item .env.example .env
```

4. Tao mat khau quan tri manh (it nhat 12 ky tu), sinh hash bang PHP trong Docker:

```powershell
docker run --rm php:8.3-cli php -r "echo password_hash('MatKhauManhCuaBan', PASSWORD_DEFAULT), PHP_EOL;"
```

Thay `MatKhauManhCuaBan` bang mat khau rieng cua ban. Sao chep hash in ra vao `ADMIN_PASSWORD_HASH` trong `.env`; dat `ADMIN_USERNAME` va thay cac mat khau mau MySQL/Grafana bang gia tri rieng. Khong chia se hoac commit `.env`.

Neu `.env` da ton tai, sua no thay vi ghi de; Compose se bao loi ro rang neu cac mat khau database/Grafana chua duoc dat. Neu doi `DB_PASSWORD` khi da co volume MySQL, can doi mat khau tai khoan `attendance_user` trong MySQL cho khop truoc khi khoi dong lai app.

5. Chay ung dung:

```powershell
docker compose up -d --build
docker compose ps
```

6. Mo `http://localhost:8080` va dang nhap bang `ADMIN_USERNAME` va mat khau da dung de tao hash.

## Cac dia chi
- Ung dung: http://localhost:8080 (chi mo tren may dang chay Docker)
- phpMyAdmin: http://localhost:8080/phpmyadmin/
- Grafana: http://localhost:8080/grafana/ (admin / gia tri GRAFANA_PASSWORD trong .env)
- Prometheus: http://localhost:8080/prometheus/

## Neu da chay ban cu
Docker co the dang giu database cu trong volume. De chay sach tu dau va XOA TOAN BO du lieu database cu, dung:
```powershell
docker compose down -v
docker compose up -d --build
```
Canh bao: `docker compose down -v` xoa du lieu MySQL va du lieu monitoring. Chi chay neu ban chap nhan mat du lieu cu.

Neu chi muon dung va dung lai ma giu du lieu:
```powershell
docker compose down
docker compose up -d --build --force-recreate
```

## Chuc nang
- Them sinh vien
- Tao buoi diem danh
- Ghi nhan Co mat / Vang mat / Di muon
- Xem lich su va loc theo buoi hoc
- Luu du lieu vao MySQL
- Dang nhap quan tri, gioi han thu mat khau va token CSRF cho cac thao tac ghi
- Validate du lieu dau vao, bao ve cookie session va khong hien loi SQL truc tiep len giao dien

## Luu y bao mat va sao luu
- Cac mat khau mau trong `.env.example` chi de nhan dien, phai doi truoc khi dung. Khong dua `.env` len Git.
- Cong 8080 duoc bind vao `127.0.0.1` de tranh mo giao dien quan tri ra mang LAN/Internet. Neu can truy cap tu thiet bi khac, can cau hinh HTTPS, xac thuc va firewall truoc khi doi port mapping.
- phpMyAdmin va Prometheus phuc vu muc dich hoc tap, khong nen dua truc tiep ra Internet.
- Dung `docker compose down -v` se xoa database va du lieu monitoring. Hay sao luu du lieu truoc khi xoa volume.
