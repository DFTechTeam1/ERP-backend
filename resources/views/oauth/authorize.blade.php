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
            --black: #0a0a0a;
            --dark: #111111;
            --dark-2: #1a1a1a;
            --gray: #6b7280;
            --gray-light: #d1d5db;
            --gray-subtle: #f5f5f5;
            --white: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            background: var(--gray-subtle);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .login-card {
            background: var(--white);
            border-radius: 4px;
            box-shadow: 0 2px 40px rgba(0, 0, 0, 0.12), 0 0 0 1px rgba(0,0,0,0.04);
            overflow: hidden;
            max-width: 980px;
            width: 100%;
            display: flex;
            flex-direction: row;
        }

        /* ── Left panel ── */
        .login-left {
            flex: 1;
            background: var(--black);
            padding: 60px 48px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: var(--white);
            position: relative;
        }

        .left-top {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .brand-logo {
            width: 64px;
            height: 64px;
            object-fit: contain;
            margin-bottom: 40px;
            filter: brightness(0) invert(1);
        }

        .login-left h2 {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
            line-height: 1.2;
        }

        .login-left > .left-top > p {
            font-size: 14px;
            color: rgba(255,255,255,0.55);
            line-height: 1.7;
            margin-bottom: 0;
        }

        .scope-list {
            margin-top: 44px;
        }

        .scope-list h6 {
            font-size: 10px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.35);
            margin-bottom: 14px;
        }

        .scope-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 4px;
            padding: 9px 14px;
            font-size: 13px;
            color: rgba(255,255,255,0.8);
        }

        .scope-item svg {
            margin-right: 10px;
            flex-shrink: 0;
            color: rgba(255,255,255,0.5);
        }

        .left-footer {
            font-size: 11px;
            color: rgba(255,255,255,0.2);
            letter-spacing: 0.5px;
        }

        /* ── Right panel ── */
        .login-right {
            flex: 1.1;
            padding: 60px 56px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: var(--white);
        }

        .login-header {
            margin-bottom: 36px;
            padding-bottom: 28px;
            border-bottom: 1px solid var(--gray-subtle);
        }

        .login-header h1 {
            font-size: 22px;
            font-weight: 700;
            color: var(--black);
            margin-bottom: 6px;
            letter-spacing: -0.3px;
        }

        .login-header p {
            color: var(--gray);
            font-size: 14px;
            margin-bottom: 0;
        }

        .client-badge {
            display: inline-flex;
            align-items: center;
            background: var(--gray-subtle);
            border: 1px solid var(--gray-light);
            border-radius: 4px;
            padding: 5px 12px;
            font-size: 12px;
            color: var(--dark);
            margin-bottom: 28px;
            gap: 7px;
            font-weight: 500;
        }

        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 7px;
            font-size: 13px;
            letter-spacing: 0.1px;
        }

        .form-control {
            border: 1.5px solid var(--gray-light);
            border-radius: 4px;
            padding: 11px 15px;
            font-size: 14px;
            color: var(--dark);
            background: var(--white);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-control:focus {
            border-color: var(--black);
            box-shadow: 0 0 0 3px rgba(10,10,10,0.06);
            outline: none;
        }

        .form-control::placeholder {
            color: var(--gray-light);
        }

        .input-group {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-light);
            z-index: 10;
        }

        .form-control.with-icon {
            padding-left: 44px;
        }

        /* ── Expiry grid ── */
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
            border: 1.5px solid var(--gray-light);
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 700;
            color: var(--gray);
            text-align: center;
            transition: all 0.15s ease;
            line-height: 1.3;
            user-select: none;
        }

        .expiry-option label span.unit {
            font-size: 10px;
            font-weight: 400;
            color: var(--gray-light);
            margin-top: 2px;
            letter-spacing: 0.3px;
        }

        .expiry-option input[type="radio"]:checked + label {
            border-color: var(--black);
            background: var(--black);
            color: var(--white);
        }

        .expiry-option input[type="radio"]:checked + label span.unit {
            color: rgba(255,255,255,0.5);
        }

        .expiry-option label:hover {
            border-color: var(--dark-2);
            color: var(--dark);
        }

        /* ── Button ── */
        .btn-login {
            background: var(--black);
            border: none;
            border-radius: 4px;
            padding: 13px;
            font-size: 14px;
            font-weight: 600;
            color: var(--white);
            transition: background 0.2s ease, transform 0.1s ease;
            letter-spacing: 0.3px;
            width: 100%;
        }

        .btn-login:hover {
            background: var(--dark-2);
            color: var(--white);
        }

        .btn-login:active {
            transform: scale(0.99);
        }

        /* ── Alerts ── */
        .alert {
            border-radius: 4px;
            border: none;
            padding: 12px 16px;
        }

        .alert-danger {
            background-color: #fef2f2;
            color: #991b1b;
            border-left: 3px solid #ef4444;
        }

        .form-text {
            font-size: 12px;
        }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .login-card {
                flex-direction: column;
            }

            .login-left {
                padding: 40px 32px;
            }

            .login-right {
                padding: 40px 32px;
            }

            .login-left h2 {
                font-size: 20px;
            }

            .scope-list {
                display: none;
            }

            .left-footer {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <!-- Left Side -->
            <div class="login-left">
                <div class="left-top">
                    <img src="/images/dfactory.webp" alt="DFactory Logo" class="brand-logo">
                    <h2>MCP Server<br>Access</h2>
                    <p>Sign in to authorize the MCP client to access this server on your behalf.</p>

                    @if(!empty($scope))
                        <div class="scope-list">
                            <h6>Permissions Requested</h6>
                            @foreach(explode(' ', $scope) as $s)
                                <div class="scope-item">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" viewBox="0 0 16 16">
                                        <path d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425z"/>
                                    </svg>
                                    {{ $s }}
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="left-footer">MCP SERVER &mdash; SECURE AUTHORIZATION</div>
            </div>

            <!-- Right Side -->
            <div class="login-right">
                <div class="login-header">
                    <h1>Sign In to Authorize</h1>
                    <p>Enter your credentials to grant access to the MCP client.</p>
                </div>

                @if(!empty($client_id))
                    <div class="client-badge">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16">
                            <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
                            <path d="M7 11.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 0 1h-1a.5.5 0 0 1-.5-.5zm-2-3a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5zm-1-3a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5z"/>
                        </svg>
                        {{ $client_id }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger mb-4" role="alert">
                        <strong>Authentication failed.</strong>
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
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
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
                            <div class="form-text text-danger mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
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
                            <div class="form-text text-danger mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-5">
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

                    <div class="d-grid mb-4">
                        <button type="submit" class="btn btn-login">
                            Authorize Access
                        </button>
                    </div>

                    <div class="text-center">
                        <small style="font-size: 12px; color: #9ca3af;">
                            By signing in you grant the MCP client the permissions listed.
                        </small>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
