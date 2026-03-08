# Android: WhatsApp-Level Incoming Call & Message Notifications (FCM)

You are a senior Android Kotlin realtime communication engineer.

Modify the existing Android app to implement WhatsApp-level incoming call and message notification using **Firebase Cloud Messaging (FCM)**. The backend uses **FCM HTTP v1** (service account); no server key is required on the server. The app only needs to receive FCM messages and save the FCM token.

**Do NOT break existing chat, Agora call, or party room system.**

---

## GOAL

- Receive **incoming call** FCM and launch `IncomingCallActivity`
- Receive **incoming message** FCM and show a notification (tap opens chat)
- Save FCM token to backend so push works when app is **closed** or **background**
- WhatsApp-level reliability: high-priority delivery, no polling

---

## STEP 1: FIREBASE MESSAGING SERVICE

**Create file:** `firebase/MyFirebaseMessagingService.kt` (or equivalent package path)

```kotlin
class MyFirebaseMessagingService : FirebaseMessagingService() {

    override fun onNewToken(token: String) {
        saveTokenToServer(token)
    }

    override fun onMessageReceived(remoteMessage: RemoteMessage) {
        val data = remoteMessage.data ?: return
        when (data["type"]) {
            "incoming_call" -> openIncomingCall(data)
            "new_message" -> showMessageNotification(data)
        }
    }

    private fun saveTokenToServer(token: String) {
        // Use your existing ApiClient with Bearer token (user must be logged in)
        ApiClient.apiService.updateFcmToken(mapOf("fcm_token" to token))
            .enqueue(/* handle response/error */)
    }

    private fun openIncomingCall(data: Map<String, String>) {
        // Backend sends: channel_name, token (Agora), caller_name, call_id, call_type, caller_id, conversation_id, uid
        val channel = data["channel_name"] ?: data["channel"] ?: return
        val token = data["token"] ?: return
        val intent = Intent(this, IncomingCallActivity::class.java).apply {
            putExtra("channel_name", channel)
            putExtra("channel", channel)   // if activity expects "channel"
            putExtra("token", token)
            putExtra("caller_name", data["caller_name"] ?: "")
            putExtra("call_id", data["call_id"] ?: "")
            putExtra("call_type", data["call_type"] ?: "audio")
            putExtra("caller_id", data["caller_id"])
            putExtra("uid", data["uid"])
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP)
        }
        startActivity(intent)
    }

    private fun showMessageNotification(data: Map<String, String>) {
        val intent = Intent(this, ChatConversationActivity::class.java).apply {
            putExtra("conversation_id", data["conversation_id"] ?: "")
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_SINGLE_TOP)
        }
        val pendingIntent = PendingIntent.getActivity(
            this, 0, intent,
            PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT
        )
        val notification = NotificationCompat.Builder(this, CHAT_CHANNEL_ID)
            .setContentTitle(data["sender_name"] ?: "New message")
            .setContentText(data["message"] ?: "")
            .setSmallIcon(R.drawable.ic_notification)
            .setContentIntent(pendingIntent)
            .setAutoCancel(true)
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .build()
        NotificationManagerCompat.from(this).notify(NOTIFICATION_ID_MESSAGE, notification)
    }

    companion object {
        private const val CHAT_CHANNEL_ID = "chat_channel"
        private const val NOTIFICATION_ID_MESSAGE = 1001
    }
}
```

- Use **data payload** only (backend sends high-priority data; no notification payload in payload when app must handle it).
- For **incoming_call**, backend sends `channel_name` (and Agora `token`, `uid`). Use `channel_name`; support `channel` if your activity expects it.
- For **new_message**, backend sends `conversation_id`, `sender_id`, `sender_name`, `message`, and optionally `message_id`, `msg_type` (FCM reserves `message_type`; type is sent as `msg_type`).

---

## STEP 2: REGISTER SERVICE IN MANIFEST

**AndroidManifest.xml** (inside `<application>`):

```xml
<service
    android:name=".firebase.MyFirebaseMessagingService"
    android:exported="false">
    <intent-filter>
        <action android:name="com.google.firebase.MESSAGING_EVENT" />
    </intent-filter>
</service>
```

Adjust `android:name` to your actual package path (e.g. `.firebase.MyFirebaseMessagingService`).

---

## STEP 3: SAVE FCM TOKEN ON LOGIN

After successful login (and whenever the token might change), get the FCM token and send it to the backend:

```kotlin
FirebaseMessaging.getInstance().token
    .addOnSuccessListener { token ->
        ApiClient.apiService.updateFcmToken(mapOf("fcm_token" to token))
            .enqueue(/* handle success/error */)
    }
```

**Backend endpoint:** `POST /api/v1/update-fcm-token`  
**Body:** `{ "fcm_token": "<token>" }`  
**Headers:** `Authorization: Bearer <access_token>` (same as other API calls).

Optionally also call **POST /api/v1/device/register** with `fcm_token` and `platform`/`device_type` for multi-device support; the backend uses both `users.fcm_token` and `user_devices` for delivery.

---

## STEP 4: CREATE NOTIFICATION CHANNEL

Create the channel at app startup (e.g. in your `Application` class or before first notification):

```kotlin
val channel = NotificationChannel(
    "chat_channel",
    "Chat Notifications",
    NotificationManager.IMPORTANCE_HIGH
).apply {
    description = "Incoming messages"
    enableVibration(true)
}
(getSystemService(NotificationManager::class.java)).createNotificationChannel(channel)
```

Use the same channel ID as in `MyFirebaseMessagingService` (`chat_channel`).

---

## STEP 5: BACKEND API FOR FCM TOKEN

Ensure the app has an API method for:

- **POST** `/api/v1/update-fcm-token`  
  Body: `{ "fcm_token": "string" }`  
  Auth: Bearer token required.

Add to your Retrofit API interface if missing:

```kotlin
@POST("update-fcm-token")
fun updateFcmToken(@Body body: Map<String, String>): Call<ResponseBody>
// or use your generic API response type
```

---

## EXPECTED RESULT

- **Incoming call:** FCM with `type == "incoming_call"` opens `IncomingCallActivity` with channel, Agora token, caller name, call_id. Works when app is closed or in background.
- **Incoming message:** FCM with `type == "new_message"` shows a high-priority notification; tap opens the chat conversation. Works when app is closed or in background.
- FCM token is saved to the backend on login (and optionally on token refresh in `onNewToken`).
- Existing Agora call connection logic, chat UI, and party room flow are unchanged; only FCM reception and token registration are added.

---

## CONSTRAINTS

- Do **not** modify Agora token request or call connection logic.
- Do **not** change existing chat or party room behavior; only add FCM handling and notification display.
- Handle **data-only** FCM messages (backend sends high-priority data payload for call and message).
