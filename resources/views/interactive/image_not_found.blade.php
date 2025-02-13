<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Image Not Found</title>
  <style>
    body {
      margin: 0;
      padding: 0;
      font-family: 'Arial', sans-serif;
      background: linear-gradient(to bottom, #f44336, #e57373);
      color: white;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      background-image: url('https://www.transparenttextures.com/patterns/brick-wall.png');
      background-size: cover;
      overflow: hidden;
    }
    .container {
      text-align: center;
    }
    .message {
      font-size: 2rem;
      margin: 0;
      color: #165B33;
      animation: pulse 2s infinite;
    }
    .icon {
      font-size: 5rem;
      margin-bottom: 1rem;
    }
    @keyframes pulse {
      0%, 100% {
        transform: scale(1);
      }
      50% {
        transform: scale(1.1);
      }
    }
    .snowflakes {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      pointer-events: none;
      z-index: 10;
    }
    .snowflake {
      position: absolute;
      top: -10%;
      font-size: 1.5rem;
      color: white;
      opacity: 0.8;
      animation: snowfall 10s linear infinite;
    }
    @keyframes snowfall {
      to {
        transform: translateY(110vh) rotate(360deg);
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <div class="icon">🎄</div>
    <p class="message">Image Not Found</p>
    <p style="font-size: 1rem; margin: 10px; color: #146B3A;">But don't worry, Santa is on the way! 🎅</p>
  </div>
  <div class="snowflakes">
    <div class="snowflake">❄</div>
    <div class="snowflake">❅</div>
    <div class="snowflake">❆</div>
  </div>
  <script>
    // Add multiple snowflakes dynamically
    const snowflakes = document.querySelector('.snowflakes');
    for (let i = 0; i < 50; i++) {
      const snowflake = document.createElement('div');
      snowflake.classList.add('snowflake');
      snowflake.style.left = `${Math.random() * 100}%`;
      snowflake.style.animationDuration = `${Math.random() * 5 + 5}s`;
      snowflake.style.fontSize = `${Math.random() * 1.5 + 1}rem`;
      snowflakes.appendChild(snowflake);
    }
  </script>
</body>
</html>
