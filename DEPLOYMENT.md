# Panduan Deployment ke Fly.io

Dokumentasi lengkap untuk deploy aplikasi Laravel backend-covre ke Fly.io.

## Prasyarat

1. Install Fly CLI:
   ```bash
   # Windows (PowerShell)
   iwr https://fly.io/install.ps1 -useb | iex

   # macOS/Linux
   curl -L https://fly.io/install.sh | sh
   ```

2. Login ke Fly.io:
   ```bash
   fly auth login
   ```

## Langkah 1: Setup Database PostgreSQL

Aplikasi ini memerlukan PostgreSQL database. Buat database di Fly.io:

```bash
# Buat Postgres database
fly postgres create --name backend-covre-db --region sin

# Attach database ke aplikasi
fly postgres attach backend-covre-db --app backend-covre
```

Perintah ini akan otomatis membuat environment variable `DATABASE_URL` di aplikasi Anda.

## Langkah 2: Setup Persistent Storage Volume

Aplikasi ini menggunakan local storage untuk menyimpan file upload (videos, images, dll). Buat volume untuk persistent storage:

```bash
# Buat volume untuk storage (minimal 10GB untuk video files)
fly volumes create storage_data --size 10 --region sin --app backend-covre
```

**PENTING:** Volume harus dibuat sebelum deploy pertama kali!

## Langkah 3: Setup Environment Variables

Set semua environment variable yang diperlukan:

### Required - APP Configuration
```bash
fly secrets set APP_NAME="CoVre" --app backend-covre
fly secrets set APP_KEY="base64:YOUR_APP_KEY_HERE" --app backend-covre
```

**Cara generate APP_KEY:**
```bash
# Di local development
php artisan key:generate --show
# Copy output dan set sebagai APP_KEY
```

### Optional - Production Settings
```bash
# Set to false di production untuk keamanan
fly secrets set VIDEO_AUTO_APPROVE="false" --app backend-covre

# Mail configuration (jika menggunakan email)
fly secrets set MAIL_MAILER="smtp" --app backend-covre
fly secrets set MAIL_HOST="your-smtp-host" --app backend-covre
fly secrets set MAIL_PORT="587" --app backend-covre
fly secrets set MAIL_USERNAME="your-username" --app backend-covre
fly secrets set MAIL_PASSWORD="your-password" --app backend-covre
fly secrets set MAIL_FROM_ADDRESS="noreply@covre.app" --app backend-covre
```

## Langkah 4: Deploy Aplikasi

1. **First time deployment:**
   ```bash
   # Launch aplikasi (akan membuat fly.toml jika belum ada)
   fly launch --no-deploy

   # Setelah konfigurasi selesai, deploy
   fly deploy
   ```

2. **Update deployment:**
   ```bash
   # Deploy perubahan terbaru
   fly deploy
   ```

3. **Deploy dengan build ulang:**
   ```bash
   # Force rebuild tanpa cache
   fly deploy --no-cache
   ```

## Langkah 5: Scale Resources (Opsional)

Jika aplikasi memerlukan lebih banyak resource:

```bash
# Scale memory dan CPU
fly scale vm shared-cpu-1x --memory 1024 --app backend-covre

# Scale jumlah instances (PERHATIAN: Hanya 1 instance jika menggunakan volume!)
# Volumes hanya bisa di-mount ke 1 instance
fly scale count 1 --app backend-covre

# Untuk scale horizontal, pertimbangkan migrasi ke object storage (S3/R2)
```

**PENTING:** Karena menggunakan volume untuk storage, aplikasi hanya bisa run di 1 instance. Untuk multiple instances, perlu migrasi ke cloud object storage.

## Langkah 6: Monitoring dan Logs

```bash
# Lihat logs real-time
fly logs --app backend-covre

# Lihat status aplikasi
fly status --app backend-covre

# Lihat resource usage
fly vm status --app backend-covre

# SSH ke container (untuk debugging)
fly ssh console --app backend-covre
```

## Troubleshooting

### Error: Database connection failed
```bash
# Check database status
fly postgres db list --app backend-covre-db

# Restart database
fly postgres restart --app backend-covre-db
```

### Error: Migration failed
```bash
# SSH ke container dan run migration manual
fly ssh console --app backend-covre
php artisan migrate --force
```

### Error: Storage upload failed
```bash
# Check volume mount
fly ssh console --app backend-covre
ls -la /var/www/html/storage/app
# Test storage
php artisan tinker
Storage::disk('public')->put('test.txt', 'test');
```

### Error: Volume not found
```bash
# List volumes
fly volumes list --app backend-covre

# Create volume if missing
fly volumes create storage_data --size 10 --region sin --app backend-covre
```

### Clear cache
```bash
fly ssh console --app backend-covre
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

## Environment Variables Summary

Berikut adalah daftar lengkap environment variables yang perlu diset:

| Variable | Required | Description |
|----------|----------|-------------|
| `APP_KEY` | ✅ | Laravel application key (generate dengan `php artisan key:generate --show`) |
| `DATABASE_URL` | ✅ | Auto-generated saat attach Postgres database |
| `VIDEO_AUTO_APPROVE` | ⚠️ | Set to `false` for production |
| `MAIL_*` | ❌ | Optional mail configuration |

## Post-Deployment Checklist

- [ ] Verify aplikasi dapat diakses: `https://backend-covre.fly.dev`
- [ ] Test health check endpoint: `https://backend-covre.fly.dev/up`
- [ ] Test API registration: `POST https://backend-covre.fly.dev/api/register`
- [ ] Test file upload (video/image)
- [ ] Verify volume mounted correctly: `fly ssh console -C "ls -la /var/www/html/storage/app"`
- [ ] Verify storage symlink: `fly ssh console -C "ls -la /var/www/html/public/storage"`
- [ ] Verify database migrations berhasil
- [ ] Check logs untuk errors: `fly logs`
- [ ] Monitor resource usage: `fly vm status`
- [ ] Monitor volume usage: `fly volumes list`
- [ ] Setup monitoring/alerts (optional)

## Custom Domain (Opsional)

Jika ingin menggunakan custom domain:

```bash
# Add custom domain
fly certs create yourdomain.com --app backend-covre

# Setup DNS records
# Add CNAME record pointing to: backend-covre.fly.dev
```

## Backup Strategy

1. **Database Backup:**
   ```bash
   # Postgres automatic snapshots
   fly postgres backup list --app backend-covre-db

   # Manual backup
   fly postgres db dump --app backend-covre-db > backup-$(date +%Y%m%d).sql
   ```

2. **Storage Backup (Volume):**
   ```bash
   # Volume snapshots (otomatis setiap hari)
   fly volumes list --app backend-covre

   # Untuk backup manual, bisa taruh di external storage
   fly ssh console --app backend-covre
   tar -czf storage-backup.tar.gz /var/www/html/storage/app/public
   # Transfer ke local atau cloud storage
   ```

   **PERHATIAN:** Volumes hanya di-backup sekali sehari. Untuk data penting, pertimbangkan backup manual atau migrasi ke object storage.

## Security Best Practices

1. ✅ `APP_DEBUG=false` di production (sudah di-set di fly.toml)
2. ✅ `SESSION_SECURE_COOKIE=true` (sudah di-set di fly.toml)
3. ✅ Force HTTPS (sudah di-set di fly.toml)
4. ⚠️ Set `VIDEO_AUTO_APPROVE=false` untuk production
5. ⚠️ Review rate limiting di routes/api.php
6. ⚠️ Setup monitoring untuk suspicious activities
7. ⚠️ Regular security updates: `composer update`

## Cost Estimation

**Free Tier (Hobby Plan):**
- 3 shared-cpu-1x VMs with 256MB RAM (free)
- 3GB persistent volume storage (free)
- 160GB outbound data transfer (free)

**Estimated Production Costs:**
- VM (shared-cpu-1x, 512MB): ~$5/month
- Postgres database: ~$0-5/month (tergantung usage)
- Volume 10GB: ~$1.50/month ($0.15/GB)
- Bandwidth: Included in free tier untuk moderate usage

Total: ~$6-11/month untuk small-medium traffic

**CATATAN:**
- Biaya volume bertambah sesuai size (10GB = $1.50, 20GB = $3, dst)
- Untuk traffic tinggi dengan banyak video, pertimbangkan object storage (S3/R2) yang lebih murah per GB

## Useful Commands

```bash
# Restart aplikasi
fly apps restart backend-covre

# Open aplikasi di browser
fly open --app backend-covre

# Connect to database
fly postgres connect --app backend-covre-db

# Run artisan commands
fly ssh console --app backend-covre -C "php artisan [command]"

# Download remote database
fly postgres db dump --app backend-covre-db > backup.sql

# Check app secrets
fly secrets list --app backend-covre

# Volume management
fly volumes list --app backend-covre
fly volumes show storage_data --app backend-covre
fly volumes extend storage_data --size 20 --app backend-covre

# Check storage usage
fly ssh console --app backend-covre -C "df -h /var/www/html/storage/app"
fly ssh console --app backend-covre -C "du -sh /var/www/html/storage/app/public/*"
```

## Support

Jika mengalami masalah:
1. Check logs: `fly logs --app backend-covre`
2. Check status: `fly status --app backend-covre`
3. Fly.io community: https://community.fly.io
4. Laravel documentation: https://laravel.com/docs
