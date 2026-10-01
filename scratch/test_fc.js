const https = require('https');

https.get('https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js', (res) => {
    let data = '';
    res.on('data', chunk => data += chunk);
    res.on('end', () => {
        // Buscar function di
        const idx = data.indexOf('function di(');
        console.log('di:', data.substring(idx, idx + 400));
    });
});
