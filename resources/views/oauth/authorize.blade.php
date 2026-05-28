<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-gH2yIJqKdNHPEq0n4Mqa/HGKIhSkIHeL5AyhkYV8i59U5AR6csBvApHHNl/vI1Bx" crossorigin="anonymous">
    <title>Authorize - MCP Server</title>
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-dark: #4338ca;
            --primary-light: #6366f1;
            --gradient-start: #667eea;
            --gradient-end: #764ba2;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 1000px;
            width: 100%;
            display: flex;
            flex-direction: row;
        }

        .login-left {
            flex: 1;
            background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .login-left::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -100px;
            right: -100px;
        }

        .login-left::after {
            content: '';
            position: absolute;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            bottom: -50px;
            left: -50px;
        }

        .login-left-content {
            position: relative;
            z-index: 1;
            text-align: center;
        }

        .logo-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            backdrop-filter: blur(10px);
        }

        .logo-icon svg {
            width: 50px;
            height: 50px;
        }

        .login-left h2 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .login-left p {
            font-size: 15px;
            opacity: 0.9;
            line-height: 1.6;
        }

        .scope-list {
            margin-top: 30px;
            text-align: left;
            width: 100%;
        }

        .scope-list h6 {
            font-size: 12px;
            letter-spacing: 1px;
            text-transform: uppercase;
            opacity: 0.7;
            margin-bottom: 12px;
        }

        .scope-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
        }

        .scope-item svg {
            margin-right: 10px;
            flex-shrink: 0;
            opacity: 0.9;
        }

        .login-right {
            flex: 1;
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-header {
            margin-bottom: 32px;
        }

        .login-header h1 {
            font-size: 26px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 6px;
        }

        .login-header p {
            color: #6b7280;
            font-size: 14px;
        }

        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-control {
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
        }

        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            z-index: 10;
        }

        .form-control.with-icon {
            padding-left: 45px;
        }

        .btn-login {
            background: linear-gradient(135deg, var(--gradient-start) 0%, var(--gradient-end) 100%);
            border: none;
            border-radius: 10px;
            padding: 14px;
            font-size: 16px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(79, 70, 229, 0.4);
            color: white;
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .alert {
            border-radius: 10px;
            border: none;
            padding: 12px 16px;
        }

        .alert-danger {
            background-color: #fef2f2;
            color: #991b1b;
        }

        .form-text {
            font-size: 13px;
        }

        .client-badge {
            display: inline-flex;
            align-items: center;
            background: #f3f4f6;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 13px;
            color: #374151;
            margin-bottom: 24px;
            gap: 6px;
        }

        .client-badge svg {
            color: var(--primary-color);
        }

        .expiry-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
        }

        .expiry-option input[type="radio"] {
            display: none;
        }

        .expiry-option label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 10px 6px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            text-align: center;
            transition: all 0.2s ease;
            line-height: 1.3;
        }

        .expiry-option label span.unit {
            font-size: 11px;
            font-weight: 400;
            color: #9ca3af;
            margin-top: 2px;
        }

        .expiry-option input[type="radio"]:checked + label {
            border-color: var(--primary-color);
            background: rgba(79, 70, 229, 0.06);
            color: var(--primary-color);
        }

        .expiry-option input[type="radio"]:checked + label span.unit {
            color: var(--primary-light);
        }

        .expiry-option label:hover {
            border-color: #a5b4fc;
        }

        @media (max-width: 768px) {
            .login-card {
                flex-direction: column;
            }

            .login-left {
                padding: 40px 30px;
            }

            .login-right {
                padding: 40px 30px;
            }

            .login-left h2 {
                font-size: 22px;
            }

            .scope-list {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <!-- Left Side - Branding -->
            <div class="login-left">
                <div class="login-left-content">
                    <div class="logo-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="white" viewBox="0 0 24 24">
                            <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 2.18l7 3.12v5.7c0 4.15-2.77 8.03-7 9.37-4.23-1.34-7-5.22-7-9.37V6.3l7-3.12z"/>
                            <path d="M12 7l-1.5 3H8l2.5 1.8-1 3 3-1.8 3 1.8-1-3L17 10h-2.5L12 7z"/>
                        </svg>
                    </div>
                    <h2>MCP Server Access</h2>
                    <p>Sign in to authorize the MCP client to access this server on your behalf.</p>

                    @if(!empty($scope))
                        <div class="scope-list">
                            <h6>Permissions Requested</h6>
                            @foreach(explode(' ', $scope) as $s)
                                <div class="scope-item">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425z"/>
                                    </svg>
                                    {{ $s }}
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- Right Side - Login Form -->
            <div class="login-right">
                <div class="login-header">
                    <h1>Sign In to Authorize</h1>
                    <p>Enter your credentials to grant access to the MCP client.</p>
                </div>

                @if(!empty($client_id))
                    <div class="client-badge">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
                            <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/>
                        </svg>
                        Client: {{ $client_id }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger mb-4" role="alert">
                        <strong>Error!</strong>
                        <ul class="mb-0 mt-2">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="/oauth/authorize" method="POST">
                    @csrf

                    <input type="hidden" name="redirect_uri" value="{{ $redirect_uri }}">
                    <input type="hidden" name="scope" value="{{ $scope }}">
                    <input type="hidden" name="state" value="{{ $state }}">
                    <input type="hidden" name="client_id" value="{{ $client_id }}">
                    <input type="hidden" name="code_challenge" value="{{ $code_challenge }}">
                    <input type="hidden" name="code_challenge_method" value="{{ $code_challenge_method }}">

                    <div class="mb-4">
                        <label for="email" class="form-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4zm2-1a1 1 0 0 0-1 1v.217l7 4.2 7-4.2V4a1 1 0 0 0-1-1H2zm13 2.383-4.758 2.855L15 11.114v-5.73zm-.034 6.876-5.64-3.471L8 9.583l-1.326-.795-5.64 3.47A1 1 0 0 0 2 13h12a1 1 0 0 0 .966-.741zM1 11.114l4.758-2.876L1 5.383v5.73z"/>
                                </svg>
                            </span>
                            <input
                                type="email"
                                class="form-control with-icon @error('email') is-invalid @enderror"
                                id="email"
                                name="email"
                                value="{{ old('email') }}"
                                placeholder="you@example.com"
                                required
                                autofocus
                            >
                        </div>
                        @error('email')
                            <div class="form-text text-danger">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16">
                                    <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
                                </svg>
                            </span>
                            <input
                                type="password"
                                class="form-control with-icon @error('password') is-invalid @enderror"
                                id="password"
                                name="password"
                                placeholder="Enter your password"
                                required
                            >
                        </div>
                        @error('password')
                            <div class="form-text text-danger">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Token Expiration</label>
                        <div class="expiry-grid">
                            <div class="expiry-option">
                                <input type="radio" name="token_expiry" id="expiry_5h" value="5h">
                                <label for="expiry_5h">5<span class="unit">hours</span></label>
                            </div>
                            <div class="expiry-option">
                                <input type="radio" name="token_expiry" id="expiry_1d" value="1d">
                                <label for="expiry_1d">1<span class="unit">day</span></label>
                            </div>
                            <div class="expiry-option">
                                <input type="radio" name="token_expiry" id="expiry_1w" value="1w">
                                <label for="expiry_1w">1<span class="unit">week</span></label>
                            </div>
                            <div class="expiry-option">
                                <input type="radio" name="token_expiry" id="expiry_2w" value="2w">
                                <label for="expiry_2w">2<span class="unit">weeks</span></label>
                            </div>
                            <div class="expiry-option">
                                <input type="radio" name="token_expiry" id="expiry_1m" value="1m" checked>
                                <label for="expiry_1m">1<span class="unit">month</span></label>
                            </div>
                            <div class="expiry-option">
                                <input type="radio" name="token_expiry" id="expiry_2m" value="2m">
                                <label for="expiry_2m">2<span class="unit">months</span></label>
                            </div>
                        </div>
                        @error('token_expiry')
                            <div class="form-text text-danger mt-2">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-grid mb-3">
                        <button type="submit" class="btn btn-login">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="me-2" viewBox="0 0 16 16" style="display: inline-block; vertical-align: middle;">
                                <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2zm3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"/>
                            </svg>
                            Authorize Access
                        </button>
                    </div>

                    <div class="text-center">
                        <small class="text-muted">
                            By signing in you grant the MCP client the permissions listed above.
                        </small>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
