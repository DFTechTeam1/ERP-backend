<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>403 Forbidden - Session Expired</title>
  <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: #f9fafb;
      color: #333;
      display: flex;
      align-items: center;
      justify-content: center;
      height: 100vh;
    }

    .container {
      text-align: center;
      max-width: 500px;
      padding: 2rem;
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 12px;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
    }

    .error-code {
      font-size: 96px;
      font-weight: bold;
      color: #000000;
      margin: 0;
    }

    .message {
      font-size: 20px;
      margin-top: 1rem;
      color: #555;
    }

    .description {
      font-size: 16px;
      color: #777;
      margin-top: 0.5rem;
      margin-bottom: 2rem;
    }

    .btn {
      display: inline-block;
      background-color: #1d4ed8;
      color: white;
      padding: 0.75rem 1.5rem;
      text-decoration: none;
      font-size: 16px;
      border-radius: 6px;
      transition: background 0.3s ease;
    }

    .btn:hover {
      background-color: #2563eb;
    }

    @media (max-width: 500px) {
      .error-code {
        font-size: 64px;
      }

      .message {
        font-size: 18px;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <h1 class="error-code">403</h1>
    <div class="message">Access Denied - Session Expired</div>
    <div class="description">
      Your session may have expired or you don't have permission to access this page.<br>
      Please try logging in again.
    </div>
  </div>
</body>
</html>
