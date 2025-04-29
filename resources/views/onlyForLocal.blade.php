<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>We're Coming Soon</title>

    <style>
        /* styles.css */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: #f3f4f6;
            color: #333;
        }

        .container {
            text-align: center;
            padding: 20px;
        }

        h1 {
            font-size: 2.5rem;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        p {
            font-size: 1.2rem;
            color: #7f8c8d;
            margin-bottom: 20px;
        }

        .email-signup {
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        .email-signup input[type="email"] {
            padding: 10px;
            border: 2px solid #bdc3c7;
            border-radius: 4px;
            outline: none;
            font-size: 1rem;
            flex: 1;
        }

        .email-signup button {
            padding: 10px 20px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color;
        }
    </style>
    <script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
    <script>
      window.OneSignalDeferred = window.OneSignalDeferred || [];
      OneSignalDeferred.push(async function(OneSignal) {
        await OneSignal.init({
          appId: "0acae159-4c1b-4c96-b9d5-a9ae5752e1fa",
        });
      });
    </script>
</head>
<body>
<div class="container">
    <h1>This is not for you!</h1>
    <p>What brings you here???</p>
</div>
</body>
</html>
