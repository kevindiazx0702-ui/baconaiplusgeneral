<?php
// Frontend/blocked.php - Redirección inmediata a Google
?>
<!DOCTYPE html>
<html>
<head>
    <title>Redireccionando...</title>
    <meta http-equiv="refresh" content="0; url=https://www.google.com">
    <script>
        // Redirección inmediata
        window.location.href = 'https://www.google.com';
        
        // Prevenir cualquier acción
        document.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            window.location.href = 'https://www.google.com';
        });
        
        document.addEventListener('keydown', function(e) {
            e.preventDefault();
            e.stopPropagation();
            window.location.href = 'https://www.google.com';
        });
        
        // Forzar redirección cada segundo por si acaso
        setInterval(function() {
            window.location.href = 'https://www.google.com';
        }, 1000);
    </script>
</head>
<body>
    <!-- Contenido vacío -->
</body>
</html>