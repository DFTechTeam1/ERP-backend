<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User</title>
    <style>
        /* General reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        /* Center content vertically and horizontally */
        body, html {
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f4f4f6;
            color: #333;
        }

        /* Main container */
        .error-container {
            text-align: center;
            max-width: 500px;
            width: 100%;
            padding: 20px;
        }

        .username {
            font-size: 1rem;
            color: #555;
            font-weight: bold;
        }

        /* Error code and message */
        .error-code {
            font-size: 3rem;
            font-weight: bold;
            color: #f44336;
            margin-bottom: 10px;
        }

        .error-message {
            font-size: .8rem;
            margin-bottom: 20px;
            color: #555;
        }

        /* Back button styling */
        .back-button {
            padding: 10px 20px;
            background-color: #007ad9;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        .back-button:hover {
            background-color: #005bb5;
        }

        /* Container for input */
        .input-container {
            display: flex;
            flex-direction: column;
            width: 300px;
        }

        /* Label styling */
        .input-label {
            font-size: 14px;
            color: #333;
            margin-bottom: 8px;
            font-weight: bold;
        }

        /* Input styling */
        .input-field {
            padding: 12px 15px;
            font-size: 16px;
            border: 2px solid #e0e3eb;
            border-radius: 8px;
            outline: none;
            transition: border-color 0.3s, box-shadow 0.3s;
            background-color: #fff;
        }

        /* Focus effect */
        .input-field:focus {
            border-color: #007ad9;
            box-shadow: 0 4px 8px rgba(0, 122, 217, 0.2);
        }

        /* Small helper text */
        .helper-text {
            font-size: 12px;
            color: #6c757d;
            margin-top: 6px;
        }

        /* Button styling */
        .styled-button {
            padding: 12px 24px;
            font-size: 16px;
            font-weight: bold;
            color: #333;
            background-color: #fff;
            border: 2px solid #ccc;
            border-radius: 8px;
            cursor: pointer;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 20px;
        }

        /* Hover effect */
        .styled-button:hover {
            background-color: #333;
            color: #fff;
            border-color: #333;
            box-shadow: 0 6px 10px rgba(0, 0, 0, 0.15);
        }

        /* Active effect */
        .styled-button:active {
            transform: scale(0.98);
        }
    </style>
    <script src="{{ asset('js/jquery.js') }}"></script>
</head>
<body>

<div class="error-container">
    <div class="error-code">{{ $errorMessage }}</div>
    <div class="error-message">{{ $errorDescription }}</div>
</div>

</body>
</html>
