/**
 * SECURITY.JS - Protección silenciosa del Frontend
 * Bloquea consola, click derecho, guardado y selección.
 */
(function() {
    'use strict';

    // 1. DESHABILITAR CLICK DERECHO (Context Menu)
    document.addEventListener('contextmenu', function(e) {
        e.preventDefault();
    }, false);

    // 2. DESHABILITAR ATAJOS DE TECLADO (F12, Ctrl+S, Ctrl+U, etc.)
    document.addEventListener('keydown', function(e) {
        // Detectar si es Mac (usa Command/Meta) o Windows (usa Ctrl)
        const isCtrl = e.ctrlKey || e.metaKey;
        const key = e.key.toLowerCase();

        // Lista de bloqueos
        if (
            // F12 (Herramientas de desarrollador)
            e.key === 'F12' || 
            
            // Ctrl + S (Guardar página)
            (isCtrl && key === 's') || 
            
            // Ctrl + U (Ver código fuente)
            (isCtrl && key === 'u') || 
            
            // Ctrl + Shift + I (Inspeccionar)
            (isCtrl && e.shiftKey && key === 'i') || 
            
            // Ctrl + Shift + J (Consola)
            (isCtrl && e.shiftKey && key === 'j') || 
            
            // Ctrl + Shift + C (Inspeccionar elemento)
            (isCtrl && e.shiftKey && key === 'c') || 
            
            // Ctrl + P (Imprimir)
            (isCtrl && key === 'p') ||

            // Ctrl + H (Historial)
            (isCtrl && key === 'h')
        ) {
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
    }, false);

    // 3. DESHABILITAR SELECCIÓN DE TEXTO E IMÁGENES
    function disableSelection(e) {
        // Permitir interacción con inputs y textareas para que el usuario pueda escribir
        const target = e.target;
        const isInput = target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable;
        
        if (!isInput) {
            e.preventDefault();
        }
    }

    // Eventos para bloquear selección y arrastre
    document.addEventListener('selectstart', disableSelection);
    document.addEventListener('dragstart', disableSelection);

    // 4. DETECCIÓN DE CONSOLA ABIERTA (Trampa silenciosa)
    // Si logran abrir la consola, esto la inunda o pausa la ejecución
    setInterval(function() {
        const start = new Date();
        debugger; // Esto pausa el script si la consola está abierta
        const end = new Date();
        if (end - start > 100) {
            // Si la diferencia de tiempo es alta, la consola pausó el script
            // Redireccionar o limpiar pantalla (Opcional, aquí está comentado para ser silencioso)
            // document.body.innerHTML = "";
        }
    }, 1000);

})();