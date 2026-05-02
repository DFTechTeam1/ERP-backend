# WhatsApp Notification Microservice

An Express.js microservice dedicated to sending WhatsApp notifications. Any internal service — Laravel, Express.js, or Python — can trigger it using HMAC-SHA256 signed requests.

## Architecture

```
Laravel ERP  ──┐
Express.js   ──┼──► POST /api/whatsapp/send  (Express WA Service)  ──► WhatsApp API
Python       ──┘         (verifies HMAC)
```

---

## Endpoint

```
POST /api/whatsapp/send
```

## Authentication

Every caller must sign the request with **HMAC-SHA256** using a shared secret (`INTERNAL_SERVICE_SECRET`).

### Required Headers

| Header | Description |
|---|---|
| `X-Service-Name` | Identifier of the calling service (e.g. `laravel-erp`, `express-v2`, `python-reporting`) |
| `X-Timestamp` | Unix timestamp (seconds) of the request |
| `X-Signature` | HMAC-SHA256 hex digest of the signing string |

### Signing String Format

```
{METHOD}\n{PATH}\n{RAW_BODY}\n{TIMESTAMP}
```

**Example:**

```
POST
api/whatsapp/send
{"phone":"628123456789","message":"Your invoice INV-001 is ready."}
1713000000
```

The signature is `HMAC-SHA256(signing_string, INTERNAL_SERVICE_SECRET)` as a hex string.

> **Clock sync:** Timestamp tolerance is ±30 seconds. Ensure all servers use NTP.

---

## Request Body

| Field | Type | Required | Description |
|---|---|---|---|
| `phone` | string | Yes | Destination phone number in international format (e.g. `628123456789`) |
| `message` | string | Yes | Plain text or template message body |
| `data` | object | No | Template variables if using a message template |

---

## Response

**Success `200`**
```json
{
  "error": false,
  "message": "WhatsApp notification sent.",
  "data": {
    "phone": "628123456789"
  }
}
```

**Unauthorized `401`** — missing headers, expired timestamp, or invalid signature.

**Validation Error `422`** — invalid request body.

---

## Express.js WhatsApp Service (Receiver)

The microservice itself — verifies the HMAC signature before processing the request.

```javascript
// server.js
const express = require('express');
const crypto = require('crypto');

const app = express();
const INTERNAL_SECRET = process.env.INTERNAL_SERVICE_SECRET;
const TIMESTAMP_TOLERANCE = 30; // seconds

app.use(express.json());

// HMAC verification middleware
function verifyHmac(req, res, next) {
  const serviceName = req.headers['x-service-name'];
  const timestamp   = req.headers['x-timestamp'];
  const signature   = req.headers['x-signature'];

  if (!serviceName || !timestamp || !signature) {
    return res.status(401).json({ error: true, message: 'Missing authentication headers.' });
  }

  const now = Math.floor(Date.now() / 1000);
  if (Math.abs(now - parseInt(timestamp, 10)) > TIMESTAMP_TOLERANCE) {
    return res.status(401).json({ error: true, message: 'Request timestamp expired.' });
  }

  // Reconstruct the path without leading slash to match the signing convention
  const path = req.originalUrl.replace(/^\//, '');
  const rawBody = JSON.stringify(req.body);
  const signingString = [req.method.toUpperCase(), path, rawBody, timestamp].join('\n');

  const expected = crypto
    .createHmac('sha256', INTERNAL_SECRET)
    .update(signingString)
    .digest('hex');

  if (!crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(expected))) {
    return res.status(401).json({ error: true, message: 'Invalid signature.' });
  }

  next();
}

app.post('/api/whatsapp/send', verifyHmac, async (req, res) => {
  const { phone, message, data = {} } = req.body;

  if (!phone || !message) {
    return res.status(422).json({ error: true, message: 'phone and message are required.' });
  }

  // TODO: replace with your actual WhatsApp API call
  await sendWhatsApp(phone, message, data);

  return res.status(200).json({
    error: false,
    message: 'WhatsApp notification sent.',
    data: { phone },
  });
});

app.listen(3000, () => console.log('WA service listening on :3000'));
```

---

## Caller Examples

### Laravel (PHP)

```php
use Illuminate\Support\Facades\Http;

function buildHmacHeaders(string $method, string $path, array $body): array
{
    $timestamp     = (string) time();
    $rawBody       = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $signingString = implode("\n", [strtoupper($method), $path, $rawBody, $timestamp]);
    $signature     = hash_hmac('sha256', $signingString, config('services.internal.secret'));

    return [
        'X-Service-Name' => 'laravel-erp',
        'X-Timestamp'    => $timestamp,
        'X-Signature'    => $signature,
    ];
}

function sendWhatsAppNotification(string $phone, string $message, array $data = []): array
{
    $path = 'api/whatsapp/send';
    $body = ['phone' => $phone, 'message' => $message, 'data' => $data];

    $response = Http::withHeaders(buildHmacHeaders('POST', $path, $body))
        ->post(config('services.whatsapp.base_url') . '/' . $path, $body);

    $response->throw();

    return $response->json();
}

// Usage
sendWhatsAppNotification(
    phone: '628123456789',
    message: 'Your invoice INV-001 is ready. Total: Rp 5.000.000',
);
```

Add to `config/services.php`:
```php
'internal' => [
    'secret' => env('INTERNAL_SERVICE_SECRET'),
],
'whatsapp' => [
    'base_url' => env('WHATSAPP_SERVICE_URL'), // e.g. http://wa-service.internal
],
```

---

### Express.js (another service)

```javascript
const crypto = require('crypto');
const axios = require('axios');

const INTERNAL_SECRET    = process.env.INTERNAL_SERVICE_SECRET;
const WHATSAPP_BASE_URL  = process.env.WHATSAPP_SERVICE_URL; // e.g. http://wa-service.internal

function buildHmacHeaders(method, path, body) {
  const timestamp    = Math.floor(Date.now() / 1000).toString();
  const rawBody      = JSON.stringify(body);
  const signingString = [method.toUpperCase(), path, rawBody, timestamp].join('\n');
  const signature    = crypto
    .createHmac('sha256', INTERNAL_SECRET)
    .update(signingString)
    .digest('hex');

  return {
    'Content-Type':  'application/json',
    'X-Service-Name': 'express-v2',
    'X-Timestamp':   timestamp,
    'X-Signature':   signature,
  };
}

async function sendWhatsAppNotification({ phone, message, data = {} }) {
  const path = 'api/whatsapp/send';
  const body = { phone, message, data };

  const headers  = buildHmacHeaders('POST', path, body);
  const response = await axios.post(`${WHATSAPP_BASE_URL}/${path}`, body, { headers });
  return response.data;
}

// Usage
sendWhatsAppNotification({
  phone:   '628123456789',
  message: 'Your report is ready: Monthly Sales April 2026',
});
```

---

### Python

```python
import hmac
import hashlib
import time
import json
import requests
import os

INTERNAL_SECRET   = os.environ['INTERNAL_SERVICE_SECRET']
WHATSAPP_BASE_URL = os.environ['WHATSAPP_SERVICE_URL']  # e.g. http://wa-service.internal


def build_hmac_headers(method: str, path: str, body: dict) -> dict:
    timestamp      = str(int(time.time()))
    raw_body       = json.dumps(body, separators=(',', ':'))
    signing_string = '\n'.join([method.upper(), path, raw_body, timestamp])
    signature      = hmac.new(
        INTERNAL_SECRET.encode(),
        signing_string.encode(),
        hashlib.sha256,
    ).hexdigest()

    return {
        'Content-Type':  'application/json',
        'X-Service-Name': 'python-reporting',
        'X-Timestamp':   timestamp,
        'X-Signature':   signature,
    }


def send_whatsapp_notification(phone: str, message: str, data: dict = {}) -> dict:
    path    = 'api/whatsapp/send'
    body    = {'phone': phone, 'message': message, 'data': data}
    raw_body = json.dumps(body, separators=(',', ':'))
    headers = build_hmac_headers('POST', path, body)

    # Pass as raw data= (not json=) so the body bytes are identical to what was signed
    response = requests.post(
        f'{WHATSAPP_BASE_URL}/{path}',
        data=raw_body,
        headers=headers,
    )
    response.raise_for_status()
    return response.json()


# Usage
send_whatsapp_notification(
    phone='628123456789',
    message='Your invoice INV-001 is ready. Total: Rp 5.000.000',
)
```

---

## Shared Secret

All services must share the same `INTERNAL_SERVICE_SECRET`.

```bash
# Express WA service (.env)
INTERNAL_SERVICE_SECRET=9cb3ca6f2c24c14b88e84dcbf2c631...

# Laravel (.env)
INTERNAL_SERVICE_SECRET=9cb3ca6f2c24c14b88e84dcbf2c631...
WHATSAPP_SERVICE_URL=http://wa-service.internal

# Other Express services (.env)
INTERNAL_SERVICE_SECRET=9cb3ca6f2c24c14b88e84dcbf2c631...
WHATSAPP_SERVICE_URL=http://wa-service.internal

# Python (.env or environment)
INTERNAL_SERVICE_SECRET=9cb3ca6f2c24c14b88e84dcbf2c631...
WHATSAPP_SERVICE_URL=http://wa-service.internal
```