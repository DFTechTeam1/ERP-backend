<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
    
    <style>
        .wrapper {
            display: flex;
            width: 100%;
        }

        .left {
            width: 20%;
            position: relative;
        }

        .left .footer-logo {
            position: absolute;
            bottom: 0;
        }

        .right {
            width: 80%;
        }

        .header-logo {
            width: 100%;
        }

        .header-logo img,
        .footer-logo img {
            width: 40px;
            height: auto;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        {{-- divide into 2 columns --}}
        <div class="left">
            <div class="header-logo">
                <img src="{{ public_path() . '/dfactory.png' }}" alt="logo">
            </div>

            <div class="footer-logo">
                <img src="{{ $image }}" alt="logo">
            </div>
        </div>
        <div class="right"></div>
    </div>
</body>
</html>