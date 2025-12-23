# 🛡️ ADMIN SYSTEM GUIDE

## Overview

The Covre app now includes an admin system that allows designated administrators to manage and delete videos. The pending approval system has been removed - all uploaded videos are now automatically visible to all users.

---

## 🎯 Changes Made

### What Was Removed:
- ❌ **Pending approval system** - Videos no longer need approval before being visible
- ❌ **Status badges** (pending, approved, rejected) - All videos are now automatically "approved"
- ❌ **Approval workflow** in MyVideosScreen

### What Was Added:
- ✅ **Admin role system** - Users can be designated as admins
- ✅ **Admin panel** - Dedicated screen for video management
- ✅ **Delete video functionality** - Admins can delete any video
- ✅ **Admin badge** - Visible on admin profiles
- ✅ **Admin statistics** - Dashboard showing total videos, users, views

---

## 🔑 Backend Requirements

### 1. Admin Role Detection

The mobile app checks for admin status in two ways:

```javascript
// In AuthContext.js
setIsAdmin(userData.role === 'admin' || userData.is_admin === true);
```

**Your backend should return ONE of these in the user object:**

#### Option A: Using `role` field
```json
{
  "user": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@example.com",
    "role": "admin"  // ← This makes them admin
  },
  "token": "..."
}
```

#### Option B: Using `is_admin` field
```json
{
  "user": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@example.com",
    "is_admin": true  // ← This makes them admin
  },
  "token": "..."
}
```

### 2. Required API Endpoints

#### GET `/api/videos`
Returns all videos (paginated)
- **Used by:** HomeScreen, AdminScreen
- **No approval filter needed anymore**

```json
{
  "data": [
    {
      "id": 1,
      "user_id": 2,
      "s3_url": "...",
      "thumbnail_url": "...",
      "menu_data": {...},
      "likes_count": 10,
      "views_count": 100,
      "created_at": "2025-01-15T10:00:00Z",
      "user": {
        "id": 2,
        "name": "Creator Name"
      }
    }
  ],
  "next_page_url": "..."
}
```

#### DELETE `/api/videos/{id}`
Deletes a video (admin only)
- **Used by:** AdminScreen
- **Authorization:** Requires admin role
- **Response on success:** 200/204 with success message
- **Response on unauthorized:** 403 with error message

**Example Success Response:**
```json
{
  "message": "Video berhasil dihapus"
}
```

**Example Error Response (Non-admin):**
```json
{
  "message": "Anda tidak memiliki izin untuk menghapus video ini"
}
```

### 3. Database Migration (Example)

If you're using Laravel, you can add the admin field like this:

```php
// Create migration
php artisan make:migration add_is_admin_to_users_table

// Migration file
public function up()
{
    Schema::table('users', function (Blueprint $table) {
        $table->boolean('is_admin')->default(false);
        // OR
        $table->string('role')->default('user'); // 'user' or 'admin'
    });
}

// Seed an admin user
DB::table('users')->where('email', 'admin@example.com')->update(['is_admin' => true]);
// OR
DB::table('users')->where('email', 'admin@example.com')->update(['role' => 'admin']);
```

### 4. Backend Controller Example (Laravel)

```php
// VideoController.php

public function destroy($id)
{
    // Check if user is admin
    $user = auth()->user();
    if (!$user->is_admin && $user->role !== 'admin') {
        return response()->json([
            'message' => 'Anda tidak memiliki izin untuk menghapus video ini'
        ], 403);
    }

    $video = Video::findOrFail($id);

    // Delete video file from storage
    Storage::delete($video->s3_url);
    Storage::delete($video->thumbnail_url);

    // Delete from database
    $video->delete();

    return response()->json([
        'message' => 'Video berhasil dihapus'
    ], 200);
}
```

---

## 📱 Mobile App Admin Features

### Admin Panel Access

Admins can access the admin panel from:
1. **Profile Screen** → Shield icon button (top right)
2. **Navigation** → Profile → Admin (in navigation stack)

### Admin Panel Features

1. **Statistics Dashboard**
   - Total Videos
   - Total Creators
   - Total Views

2. **Video Management Grid**
   - View all videos in the system
   - See video thumbnails
   - See likes and views count
   - Delete any video with confirmation

3. **Delete Functionality**
   - Confirmation dialog before deletion
   - Shows video title in confirmation
   - Removes video from local state immediately
   - Shows success/error messages
   - Handles 403 errors for non-admin users

### Admin Badge

Admin users will see:
- Gold "Admin" badge on their profile
- Gold shield icon button to access admin panel
- Special admin badge in the admin panel header

---

## 🔒 Security Best Practices

### Backend Security

1. **Always verify admin status on the backend**
   ```php
   // BAD - Don't trust the client
   if ($request->is_admin) { ... }

   // GOOD - Check authenticated user
   if (auth()->user()->is_admin) { ... }
   ```

2. **Use middleware for admin routes**
   ```php
   Route::middleware(['auth', 'admin'])->group(function () {
       Route::delete('/videos/{id}', [VideoController::class, 'destroy']);
   });
   ```

3. **Log admin actions**
   ```php
   Log::info('Admin deleted video', [
       'admin_id' => auth()->id(),
       'video_id' => $id,
   ]);
   ```

4. **Validate authorization in controller**
   ```php
   $this->authorize('delete', $video); // Using Laravel policies
   ```

### Frontend Security

The mobile app already:
- ✅ Hides admin button from non-admin users
- ✅ Checks `isAdmin` from AuthContext before rendering admin features
- ✅ Shows error alerts if unauthorized delete attempt
- ✅ Handles 403 responses gracefully

---

## 🧪 Testing the Admin System

### 1. Create an Admin User

Using Laravel Tinker:
```bash
php artisan tinker
```

```php
$user = User::where('email', 'your-email@example.com')->first();
$user->is_admin = true; // or $user->role = 'admin'
$user->save();
```

Or directly in database:
```sql
UPDATE users SET is_admin = 1 WHERE email = 'your-email@example.com';
-- OR
UPDATE users SET role = 'admin' WHERE email = 'your-email@example.com';
```

### 2. Test Login

1. Login to the mobile app with the admin user
2. Check that the admin badge appears on profile
3. Check that the shield button appears in action buttons

### 3. Test Admin Panel

1. Tap the shield icon button
2. Verify admin panel opens
3. Check that statistics load correctly
4. Verify videos display in grid

### 4. Test Delete Functionality

1. Tap delete button on a video
2. Verify confirmation dialog appears
3. Confirm deletion
4. Check video is removed from list
5. Verify success message appears

### 5. Test Non-Admin User

1. Login with regular user
2. Verify NO admin badge appears
3. Verify NO shield button appears
4. Try navigating to Admin screen manually (should show error)

---

## 📊 Admin Panel UI

### Statistics Cards:
```
┌──────────────┬──────────────┬──────────────┐
│   📹         │   👥         │   👁         │
│   25         │   10         │   1,234      │
│ Total Videos │  Creators    │ Total Views  │
└──────────────┴──────────────┴──────────────┘
```

### Video Grid:
```
┌────────┬────────┐
│ Video1 │ Video2 │
│ [❤ 10] │ [❤ 5]  │
│ [👁 50]│ [👁 30] │
│ Creator│ Creator│
│ [🗑 Del]│ [🗑 Del]│
├────────┼────────┤
│ Video3 │ Video4 │
│   ...  │   ...  │
└────────┴────────┘
```

---

## 🔄 Migration from Old System

### For Existing Videos

All existing videos in your database should work automatically. If you had a `status` field:

**Option 1: Keep the field (recommended)**
- Leave the `status` field in database
- Set all existing videos to 'approved'
- New uploads automatically get 'approved'

```sql
UPDATE videos SET status = 'approved';
```

**Option 2: Remove the field**
- Drop the status column if not needed
- Update video model if necessary

```php
// Migration
Schema::table('videos', function (Blueprint $table) {
    $table->dropColumn('status');
});
```

### For Video Upload

Your backend should automatically make videos visible:

```php
// VideoController@store
public function store(Request $request)
{
    $video = Video::create([
        'user_id' => auth()->id(),
        's3_url' => $s3Url,
        'thumbnail_url' => $thumbnailUrl,
        'menu_data' => $menuData,
        // No status field needed, or:
        'status' => 'approved', // if keeping the field
    ]);

    return response()->json($video, 201);
}
```

---

## 🐛 Troubleshooting

### Admin button not showing?

**Check:**
1. User object has `role: "admin"` or `is_admin: true`
2. Login response includes correct user data
3. AuthContext properly sets `isAdmin` state
4. Check console logs for auth data

### Delete not working?

**Check:**
1. DELETE `/api/videos/{id}` endpoint exists
2. Backend verifies admin role
3. Authorization header is sent
4. Check backend logs for errors
5. Check network tab for 403/404 errors

### Admin panel shows error?

**Check:**
1. User is logged in
2. User has admin role
3. GET `/api/videos` endpoint works
4. Check console for API errors

---

## 📝 API Endpoint Summary

| Method | Endpoint | Description | Auth | Admin Only |
|--------|----------|-------------|------|------------|
| GET | `/api/videos` | Get all videos (paginated) | Yes | No |
| GET | `/api/videos/my` | Get user's own videos | Yes | No |
| POST | `/api/videos` | Upload new video | Yes | No |
| DELETE | `/api/videos/{id}` | Delete a video | Yes | **Yes** |

---

## ✅ Checklist for Implementation

- [ ] Add `is_admin` or `role` field to users table
- [ ] Update login/register to return admin status
- [ ] Create DELETE `/api/videos/{id}` endpoint
- [ ] Add admin middleware/authorization
- [ ] Test with admin user account
- [ ] Test with regular user account
- [ ] Verify videos are auto-visible after upload
- [ ] Test delete functionality
- [ ] Check error handling for non-admin delete attempts

---

## 🎉 Benefits of New System

### For Users:
- ✅ Videos are immediately visible after upload
- ✅ No waiting for approval
- ✅ Better user experience
- ✅ Faster content delivery

### For Admins:
- ✅ Direct control over content
- ✅ Quick video removal if needed
- ✅ Statistics dashboard
- ✅ Easy video management

### For Developers:
- ✅ Simpler codebase
- ✅ Less complex workflow
- ✅ Easier to maintain
- ✅ Clear separation of roles

---

**Last Updated:** January 2025
**Mobile App Version:** Compatible with current version
**Backend Requirements:** Laravel 8+ (or equivalent)
