# 🔑 Firebase Authentication Setup Information

## SHA-1 Fingerprint (Debug)

```
3C:FF:24:8C:FA:E5:E7:34:82:04:52:42:80:66:B0:07:82:AF:71:92
```

## Setup Steps

1. **Firebase Console Setup:**

   - Go to: https://console.firebase.google.com/project/waves-of-food-9af5f
   - Navigate to: Authentication > Sign-in method
   - Click on Google provider and enable it
   - Add the SHA-1 fingerprint above to Project Settings > Your apps

2. **Get Web Client ID:**

   - After enabling Google Sign-in, copy the Web Client ID
   - Replace in `app/src/main/res/values/strings.xml`:

   ```xml
   <string name="default_web_client_id">YOUR_WEB_CLIENT_ID_HERE</string>
   ```

3. **Download Updated google-services.json:**
   - Download the new google-services.json from Firebase Console
   - Replace the existing file in `app/google-services.json`

## Current Status

- ✅ SHA-1 Fingerprint generated
- ⚠️ Need to configure Google Sign-In in Firebase Console
- ⚠️ Need to update Web Client ID
- ⚠️ Need to update google-services.json

## Next Steps

1. Configure Google Sign-In in Firebase Console
2. Add SHA-1 fingerprint to Firebase
3. Update Web Client ID in strings.xml
4. Test authentication
