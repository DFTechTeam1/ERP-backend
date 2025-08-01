<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Rejected</title>
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
        
        .cross-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #F44336;
            margin: 0 auto 25px;
            position: relative;
            animation: scaleIn 0.5s ease-out;
        }
        
        .cross {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 60px;
            height: 60px;
            transform: translate(-50%, -50%);
        }
        
        .cross-line {
            position: absolute;
            height: 5px;
            width: 100%;
            background-color: white;
            border-radius: 2px;
        }
        
        .cross-line:first-child {
            transform: rotate(45deg);
            animation: drawCross1 0.6s ease-out;
        }
        
        .cross-line:last-child {
            transform: rotate(-45deg);
            animation: drawCross2 0.6s ease-out;
        }
        
        h1 {
            color: #C62828;
            margin-bottom: 15px;
        }
        
        p {
            color: #555;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        
        .btn {
            background: #F44336;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: #D32F2F;
            transform: translateY(-2px);
        }
        
        @keyframes scaleIn {
            0% { transform: scale(0); opacity: 0; }
            80% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }
        
        @keyframes drawCross1 {
            0% { width: 0; transform: rotate(45deg) translateX(-30px); }
            100% { width: 100%; transform: rotate(45deg); }
        }
        
        @keyframes drawCross2 {
            0% { width: 0; transform: rotate(-45deg) translateX(30px); }
            100% { width: 100%; transform: rotate(-45deg); }
        }
    </style>
</head>
<body>
    <div class="notification-card">
        <div class="cross-circle">
            <div class="cross">
                <div class="cross-line"></div>
                <div class="cross-line"></div>
            </div>
        </div>
        <h1>Invoice Rejected</h1>
        <p>Invoice successfully changed. Change request rejected.</p>
    </div>
</body>
</html>