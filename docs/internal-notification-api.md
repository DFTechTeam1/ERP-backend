# Internal Notification API

A secured service-to-service endpoint for sending notifications from Express.js or Python into the Laravel ERP notification system.

## Endpoint

```
POST /api/internal/notifications/send
```

## Authentication

Every request must be signed with **HMAC-SHA256** using a shared secret (`INTERNAL_SERVICE_SECRET`).

### Required Headers

| Header | Description |
|---|---|
| `X-Service-Name` | Identifier of the calling service (e.g. `express-v2`, `python-reporting`) |
| `X-Timestamp` | Unix timestamp (seconds) of the request |
| `X-Signature` | HMAC-SHA256 hex digest of the signing string |

### Signing String Format

```
{METHOD}\n{PATH}\n{RAW_BODY}\n{TIMESTAMP}
```

**Example:**

```
POST
api/internal/notifications/send
{"recipient_email":"user@example.com","action":"invoice_sent","channels":["email"]}
1713000000
```

The signature is `HMAC-SHA256(signing_string, INTERNAL_SERVICE_SECRET)` as a hex string.

> **Clock sync:** Timestamp tolerance is ±30 seconds. Ensure all servers use NTP.

---

## Request Body

| Field | Type | Required | Description |
|---|---|---|---|
| `recipient_email` | string | Yes | Email of the target user (must exist in `users` table) |
| `action` | string | Yes | Must match an `action` in the `notification_settings` table |
| `channels` | array | Yes | One or more of: `email`, `database`, `slack`, `telegram` |
| `data` | object | No | Template parameters (e.g. `parameter1`, `parameter2`) |
| `options` | object | No | Extra options passed to the notification (e.g. attachments) |

---

## Response

**Success `201`**
```json
{
  "error": false,
  "message": "Notification queued successfully.",
  "data": {
    "recipient": "user@example.com",
    "action": "invoice_sent",
    "channels": ["email", "database"]
  },
  "code": 201
}
```

**Unauthorized `401`** — missing headers, expired timestamp, or invalid signature.

**Not Found `404`** — recipient email does not exist in the system.

**Validation Error `422`** — invalid request body.

---

## Express.js Example

```javascript
const crypto = require('crypto');
const axios = require('axios');

const INTERNAL_SECRET = process.env.INTERNAL_SERVICE_SECRET;
const ERP_BASE_URL = process.env.ERP_BASE_URL; // e.g. http://laravel-erp.internal

function buildHmacHeaders(method, path, body) {
  const timestamp = Math.floor(Date.now() / 1000).toString();
  const rawBody = JSON.stringify(body);
  const signingString = [method.toUpperCase(), path, rawBody, timestamp].join('\n');
  const signature = crypto
    .createHmac('sha256', INTERNAL_SECRET)
    .update(signingString)
    .digest('hex');

  return {
    'Content-Type': 'application/json',
    'X-Service-Name': 'express-v2',
    'X-Timestamp': timestamp,
    'X-Signature': signature,
  };
}

async function sendErpNotification({ recipientEmail, action, channels, data = {}, options = {} }) {
  const path = 'api/internal/notifications/send';
  const body = {
    recipient_email: recipientEmail,
    action,
    channels,
    data,
    options,
  };

  const headers = buildHmacHeaders('POST', path, body);
  const response = await axios.post(`${ERP_BASE_URL}/${path}`, body, { headers });
  return response.data;
}

/**
 * Fetch data from any upstream service, then forward a notification to the ERP.
 *
 * @param {object} params
 * @param {string} params.serviceUrl        - Full URL of the upstream service endpoint
 * @param {string} [params.serviceMethod]   - HTTP method to use when calling the service (default: 'GET')
 * @param {object} [params.servicePayload]  - Request body for POST/PUT calls to the service
 * @param {object} [params.serviceHeaders]  - Extra headers needed by the upstream service
 * @param {function} params.mapToNotification - Maps the service response to notification params:
 *                                             ({ recipientEmail, action, channels, data, options })
 */
async function fetchAndNotify({
  serviceUrl,
  serviceMethod = 'GET',
  servicePayload = null,
  serviceHeaders = {},
  mapToNotification,
}) {
  const serviceResponse = await axios({
    method: serviceMethod,
    url: serviceUrl,
    data: servicePayload,
    headers: serviceHeaders,
  });

  const notificationParams = mapToNotification(serviceResponse.data);
  return sendErpNotification(notificationParams);
}

// --- Usage examples ---

// 1. Fetch an invoice from an invoice service and notify the recipient
fetchAndNotify({
  serviceUrl: 'https://invoice-service.internal/invoices/INV-001',
  mapToNotification: (invoice) => ({
    recipientEmail: invoice.customer_email,
    action: 'invoice_sent',
    channels: ['email', 'database'],
    data: {
      parameter1: invoice.invoice_number,
      parameter2: invoice.total_amount,
    },
  }),
});

// 2. POST to a report service, then notify when the report is ready
fetchAndNotify({
  serviceUrl: 'https://reporting-service.internal/reports/generate',
  serviceMethod: 'POST',
  servicePayload: { type: 'monthly_sales', month: '2026-04' },
  serviceHeaders: { Authorization: `Bearer ${process.env.REPORTING_SERVICE_TOKEN}` },
  mapToNotification: (report) => ({
    recipientEmail: report.requested_by_email,
    action: 'report_generated',
    channels: ['email'],
    data: {
      parameter1: report.title,
      parameter2: report.period,
    },
    options: {
      attachment_url: report.download_url,
    },
  }),
});
```

---

## Python Example

```python
import hmac
import hashlib
import time
import json
import requests
import os

INTERNAL_SECRET = os.environ['INTERNAL_SERVICE_SECRET']
ERP_BASE_URL = os.environ['ERP_BASE_URL']  # e.g. http://laravel-erp.internal


def build_hmac_headers(method: str, path: str, body: dict) -> dict:
    timestamp = str(int(time.time()))
    raw_body = json.dumps(body, separators=(',', ':'))
    signing_string = '\n'.join([method.upper(), path, raw_body, timestamp])
    signature = hmac.new(
        INTERNAL_SECRET.encode(),
        signing_string.encode(),
        hashlib.sha256
    ).hexdigest()

    return {
        'Content-Type': 'application/json',
        'X-Service-Name': 'python-reporting',
        'X-Timestamp': timestamp,
        'X-Signature': signature,
    }


def send_erp_notification(recipient_email: str, action: str, channels: list,
                           data: dict = {}, options: dict = {}) -> dict:
    path = 'api/internal/notifications/send'
    body = {
        'recipient_email': recipient_email,
        'action': action,
        'channels': channels,
        'data': data,
        'options': options,
    }

    headers = build_hmac_headers('POST', path, body)

    # IMPORTANT: pass body as `json=` so requests serializes it the same way
    # as json.dumps above — use the same separators to guarantee byte-identical output
    response = requests.post(
        f'{ERP_BASE_URL}/{path}',
        data=raw_body := json.dumps(body, separators=(',', ':')),
        headers=headers,
    )
    response.raise_for_status()
    return response.json()


# Usage
send_erp_notification(
    recipient_email='user@example.com',
    action='report_generated',
    channels=['email'],
    data={'parameter1': 'Monthly Sales Report', 'parameter2': 'April 2026'},
)
```

> **Important for Python:** Use `json.dumps(body, separators=(',', ':'))` and pass it as raw `data=` (not `json=`) to `requests.post`. This ensures the body string used for signing is byte-identical to the body sent over the wire.

---

## Getting the Secret

Copy `INTERNAL_SERVICE_SECRET` from the Laravel `.env` file into each service's environment variables.

```bash
# Laravel .env
INTERNAL_SERVICE_SECRET=9cb3ca6f2c24c14b88e84dcbf2c631...

# Express (.env or environment)
INTERNAL_SERVICE_SECRET=9cb3ca6f2c24c14b88e84dcbf2c631...

# Python (environment)
INTERNAL_SERVICE_SECRET=9cb3ca6f2c24c14b88e84dcbf2c631...
```