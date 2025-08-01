<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Not Found | ERP System</title>
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #2980b9;
            --light-color: #ecf0f1;
            --dark-color: #34495e;
            --error-color: #e74c3c;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f9f9f9;
            color: var(--dark-color);
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .header {
            background-color: #000;
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .container {
            max-width: 800px;
            margin: 3rem auto;
            padding: 2rem;
            text-align: center;
            flex: 1;
        }
        
        .error-code {
            font-size: 6rem;
            font-weight: 700;
            color: var(--error-color);
            margin-bottom: 1rem;
        }
        
        .error-title {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }
        
        .error-message {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            line-height: 1.6;
        }
        
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn {
            padding: 0.8rem 1.5rem;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--accent-color);
        }
        
        .btn-secondary {
            border: 1px solid var(--secondary-color);
            color: var(--secondary-color);
            background-color: transparent;
        }
        
        .btn-secondary:hover {
            background-color: var(--light-color);
        }
        
        .footer {
            background-color: var(--primary-color);
            color: var(--light-color);
            text-align: center;
            padding: 1rem;
            font-size: 0.9rem;
        }
        
        .error-illustration {
            max-width: 300px;
            margin: 0 auto 2rem;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
                margin: 1rem auto;
            }
            
            .error-code {
                font-size: 4rem;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 250px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">DFDataCenter</div>
    </header>
    
    <main class="container">
        <div class="error-code">404</div>
        <div class="error-title">Page Not Found</div>
        <div class="error-message">
            The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.
            <br>Please check the URL or navigate using the menu.
        </div>
        
        <svg class="error-illustration" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#e74c3c" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
            <line x1="12" y1="9" x2="12" y2="13"></line>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
        </svg>
    </main>
    
    <footer class="footer">
        &copy; 2024 DFDataCenter. All rights reserved.
    </footer>
</body>
</html>