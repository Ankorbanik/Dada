(function() {
    let loadTime = 0;
    window.addEventListener('load', function() {
        if(performance.timing) {
            loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
        }
        document.body.addEventListener('click', function(e) {
            let data = {
                click_x: e.clientX,
                click_y: e.clientY,
                viewport_w: window.innerWidth,
                viewport_h: window.innerHeight,
                load_time: loadTime
            };
            fetch('update_click_details.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            }).catch(console.error);
        }, { once: true });
    });
})();