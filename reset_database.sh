#!/bin/bash

echo "========================================"
echo "DATABASE RESET - LOCAL STORAGE ONLY"
echo "========================================"
echo ""
echo "This will:"
echo "1. Drop all tables and recreate fresh"
echo "2. Create storage symlink"
echo "3. Clear all caches"
echo ""
echo "WARNING: This will DELETE ALL DATA!"
echo ""
read -p "Press Enter to continue or Ctrl+C to cancel..."

echo ""
echo "[1/5] Dropping all tables and recreating..."
php artisan migrate:fresh
if [ $? -ne 0 ]; then
    echo "ERROR: Migration failed!"
    exit 1
fi
echo "✅ Database reset complete!"

echo ""
echo "[2/5] Creating storage symlink..."
php artisan storage:link
if [ $? -ne 0 ]; then
    echo "⚠️  Storage link may already exist (this is OK)"
else
    echo "✅ Storage symlink created!"
fi

echo ""
echo "[3/5] Clearing config cache..."
php artisan config:clear
echo "✅ Config cache cleared!"

echo ""
echo "[4/5] Clearing application cache..."
php artisan cache:clear
echo "✅ Application cache cleared!"

echo ""
echo "[5/5] Clearing route cache..."
php artisan route:clear
echo "✅ Route cache cleared!"

echo ""
echo "========================================"
echo "✅ DATABASE RESET COMPLETE!"
echo "========================================"
echo ""
echo "Your database is now EMPTY and ready for:"
echo "- Manual video uploads via mobile app"
echo "- Real user registrations"
echo "- Local storage only (no S3/fake URLs)"
echo ""
echo "Storage structure:"
echo "- Videos: backend/storage/app/public/videos/"
echo "- Thumbnails: backend/storage/app/public/thumbnails/"
echo ""
echo "Public access via:"
echo "- http://192.168.1.7:8000/storage/videos/..."
echo "- http://192.168.1.7:8000/storage/thumbnails/..."
echo ""
echo "========================================"
echo "NEXT STEPS:"
echo "========================================"
echo "1. Start backend server:"
echo "   cd backend"
echo "   php artisan serve --host=0.0.0.0 --port=8000"
echo ""
echo "2. Start mobile app:"
echo "   cd mobile"
echo "   npm start"
echo ""
echo "3. Upload your first video!"
echo "========================================"
echo ""
