<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>400 - Bad Request</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .error-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 100%;
            padding: 60px 40px;
            text-align: center;
            animation: fadeInUp 0.8s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .error-icon {
            width: 120px;
            height: 120px;
            margin: 0 auto 30px;
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .error-icon svg {
            width: 60px;
            height: 60px;
            fill: white;
        }

        .error-code {
            font-size: 3.5rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 20px;
            letter-spacing: -2px;
        }

        .error-title {
            font-size: 2rem;
            font-weight: 600;
            color: #34495e;
            margin-bottom: 20px;
        }

        .error-message {
            font-size: 1.1rem;
            color: #7f8c8d;
            line-height: 1.6;
            margin-bottom: 40px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        .error-details {
            background: #f8f9fa;
            border-left: 4px solid #ff6b6b;
            padding: 20px;
            margin: 30px 0;
            border-radius: 8px;
            text-align: left;
        }

        .error-details h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }

        .error-details ul {
            color: #5d6d7e;
            padding-left: 20px;
        }

        .error-details li {
            margin-bottom: 8px;
            line-height: 1.5;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 40px;
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: transparent;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-secondary:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }

        .support-info {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid #e0e0e0;
        }

        .support-info p {
            color: #95a5a6;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }

        .support-contact {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .support-contact:hover {
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            .error-container {
                padding: 40px 25px;
                margin: 10px;
            }

            .error-code {
                font-size: 2.5rem;
            }

            .error-title {
                font-size: 1.5rem;
            }

            .action-buttons {
                flex-direction: column;
                align-items: center;
            }

            .btn {
                width: 100%;
                max-width: 280px;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">
            <svg viewBox="0 0 24 24">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                <path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z" fill="white"/>
            </svg>
        </div>

        <div class="error-code">400</div>
        <h1 class="error-title">Bad Request</h1>
        
        <p class="error-message">
            We're sorry, but your request couldn't be processed due to invalid or malformed data. 
            Please check your input and try again.
        </p>

        {{-- <div class="error-details">
            <h3>Common causes include:</h3>
            <ul>
                <li>Missing required fields in the form</li>
                <li>Invalid data format or values</li>
                <li>Malformed request parameters</li>
                <li>File upload size exceeds the limit</li>
                <li>Invalid characters in the input</li>
            </ul>
        </div> --}}

        <div class="action-buttons">
            <a href="{{ config('app.frontend_url') }}" class="btn btn-secondary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/>
                </svg>
                Home Page
            </a>
        </div>

        <div class="support-info">
            <p>If you continue to experience problems, please contact our support team.</p>
            <a href="mailto:admin@dfactory.pro" class="support-contact">admin@dfactory.pro</a>
        </div>
    </div>
</body>
</html>