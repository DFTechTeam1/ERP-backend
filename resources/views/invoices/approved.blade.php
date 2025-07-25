<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Approved</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f5f7fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        
        .notification-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            padding: 40px;
            text-align: center;
            max-width: 400px;
            width: 90%;
        }
        
        .checkmark-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #4CAF50;
            margin: 0 auto 25px;
            position: relative;
            animation: scaleIn 0.5s ease-out;
        }
        
        .checkmark {
            display: inline-block;
            transform: rotate(45deg);
            height: 60px;
            width: 30px;
            border-bottom: 5px solid white;
            border-right: 5px solid white;
            position: absolute;
            top: 50%;
            left: 50%;
            margin-top: -35px;
            margin-left: -15px;
            animation: drawCheck 0.8s ease-out;
        }
        
        h1 {
            color: #2E7D32;
            margin-bottom: 15px;
        }
        
        p {
            color: #555;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        
        .btn {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: #3E8E41;
            transform: translateY(-2px);
        }
        
        @keyframes scaleIn {
            0% { transform: scale(0); opacity: 0; }
            80% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }
        
        @keyframes drawCheck {
            0% { height: 0; width: 0; opacity: 0; }
            50% { height: 60px; width: 0; opacity: 1; }
            100% { width: 30px; }
        }
    </style>
</head>
<body>
    <div class="notification-card">
        <div class="checkmark-circle">
            <div class="checkmark"></div>
        </div>
        <h1>Invoice Approved!</h1>
        <p>Your invoice changes have been successfully approved. The updates are now live in the system.</p>
    </div>
</body>
</html>