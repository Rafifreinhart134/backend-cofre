# 🧪 TEST AI FOOD SCANNER DENGAN FOTO REAL

## Foto Test: Mie/Bihun dengan Telur dan Sayuran

### Method 1: Via Mobile App
```bash
1. Save foto ke ponsel
2. Open Covre app
3. Tap "+" button
4. Select foto dari gallery
5. Tap "✨ Scan Food with AI"
6. Wait 5-10 seconds
7. See results!
```

### Method 2: Via cURL (Terminal)

**Prerequisites**:
- Save image as `test_food.jpg` in backend folder
- Get auth token

**Step 1: Login dan get token**
```bash
curl -X POST http://192.168.1.101:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"marco@foodie.com","password":"password"}' \
  | python -m json.tool
```

**Step 2: Upload image untuk AI scan**
```bash
# Replace YOUR_TOKEN with actual token from step 1
curl -X POST http://192.168.1.101:8000/api/ai/scan \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "image=@test_food.jpg" \
  | python -m json.tool
```

### Expected Response:

```json
{
  "success": true,
  "data": {
    "is_food": true,
    "items": [
      {
        "name": "Bihun/Mie Putih",
        "calories": 350,
        "description": "White rice noodles with vegetables"
      },
      {
        "name": "Telur Dadar",
        "calories": 90,
        "description": "Sliced omelette topping"
      },
      {
        "name": "Sayuran Mix",
        "calories": 45,
        "description": "Green beans, carrots, spring onions"
      }
    ],
    "total_calories": 485,
    "price": 18000,
    "ingredients": "bihun, telur, buncis, wortel, bawang daun, bumbu maggi",
    "description": "Bihun goreng dengan telur dadar dan sayuran segar, dengan bumbu Maggi"
  }
}
```

### What AI Should Detect in Your Image:

✅ **Main Items**:
1. Bihun/Mie putih (white noodles)
2. Telur dadar iris (sliced omelette) - orange strips on top
3. Buncis/kacang panjang (green beans)
4. Wortel (carrots) - orange pieces
5. Bawang daun (spring onions) - green garnish

✅ **Additional Context**:
- Maggi brand visible (kemasan kuning di foto)
- Presentation: Bowl with chopsticks
- Style: Asian noodle dish

✅ **Estimated Values**:
- Calories: 450-500 kcal
- Price: Rp 15.000-20.000
- Serving: 1 portion

### Troubleshooting:

**If "Network Error"**:
- Check mobile app IP config: 192.168.1.101
- Restart mobile app
- Check backend running

**If "Failed to analyze"**:
- Image too large (>10MB) - compress first
- Image too dark/blurry - use clearer photo
- Network timeout - check internet connection

**If "Bukan Makanan"**:
- AI detected non-food item
- Try different angle/lighting
- Make sure food is clearly visible
