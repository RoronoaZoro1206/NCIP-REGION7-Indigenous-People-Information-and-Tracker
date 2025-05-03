// Initialize map
var map = L.map('map', {
    maxBounds: [
        [5, 115],
        [20, 130]
    ],
    maxBoundsViscosity: 1.0,
    minZoom: 5,
    maxZoom: 18
}).setView([10.3157, 123.8854], 11);

// Add tile layer
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
}).addTo(map);

// Helper functions
function hexToRgb(hex) {
    hex = hex.replace('#', '');
    return [
        parseInt(hex.substring(0, 2), 16),
        parseInt(hex.substring(2, 4), 16),
        parseInt(hex.substring(4, 6), 16)
    ];
}

function rgbToHex(rgb) {
    return `#${rgb.map(val => val.toString(16).padStart(2, '0')).join('')}`;
}

function interpolateColor(color1, color2, factor) {
    let result = [];
    for (let i = 0; i < 3; i++) {
        result.push(Math.round(color1[i] + factor * (color2[i] - color1[i])));
    }
    return result;
}

// Inject keyframes animation into page
const style = document.createElement('style');
style.innerHTML = `
@keyframes pulse {
    0% {
        transform: translate(-50%, -50%) scale(1);
        opacity: 0.95;
        box-shadow: 0 0 12px 6px rgba(255, 255, 255, 0.7);
    }
    50% {
        transform: translate(-50%, -50%) scale(1.2);
        opacity: 0.7;
        box-shadow: 0 0 20px 12px rgba(255, 255, 255, 0.9);
    }
    100% {
        transform: translate(-50%, -50%) scale(1);
        opacity: 0.95;
        box-shadow: 0 0 12px 6px rgba(255, 255, 255, 0.7);
    }
}
`;
document.head.appendChild(style);

// Fetch population data
fetch('./get_population_data.php')
    .then(response => response.json())
    .then(data => {
        data.forEach(item => {
            let pop = item.population;
            let color;

            if (pop <= 50000) {
                color = '#0014FF'; // Solid Deep Blue
            } else if (pop > 50000 && pop <= 300000) {
                let green = hexToRgb('#00FF5B');
                let cyan = hexToRgb('#00FFFF');
                let factor = (pop - 50000) / (300000 - 50000);
                factor = Math.pow(factor, 0.7);
                color = rgbToHex(interpolateColor(green, cyan, factor));
            } else if (pop > 300000 && pop <= 600000) {
                let orange = hexToRgb('#FFE53B');
                let lightRed = hexToRgb('#FF2525');
                let factor = (pop - 300000) / (600000 - 300000);
                factor = Math.pow(factor, 0.7);
                color = rgbToHex(interpolateColor(orange, lightRed, factor));
            } else if (pop > 600000 && pop <= 1000000) {
                let warmYellow = hexToRgb('#FFC300');
                let deepRed = hexToRgb('#C70039');
                let factor = (pop - 600000) / (1000000 - 600000);
                factor = Math.pow(factor, 0.7);
                color = rgbToHex(interpolateColor(warmYellow, deepRed, factor));
            } else {
                color = '#888888'; // fallback
            }

            // Adjust radius based on population
            let radius = 15;
            if (pop > 600000) radius = 45;
            else if (pop > 300000) radius = 35;
            else if (pop > 50000) radius = 25;

            // Create a custom div icon with brighter radial gradient and pulse animation
            var icon = L.divIcon({
                className: '',
                html: `<div style="
                    width: ${radius * 2}px;
                    height: ${radius * 2}px;
                    background: radial-gradient(circle at center, ${color} 0%, ${color} 40%, rgba(255,255,255,0) 75%);
                    border-radius: 50%;
                    transform: translate(-50%, -50%);
                    opacity: 0.95;
                    box-shadow: 0 0 12px 6px white;
                    animation: pulse 2s infinite;
                    "></div>`
            });

            // Add marker with divIcon
            L.marker([item.lat, item.lng], { icon: icon })
                .addTo(map)
                .bindPopup(`<b>Area:</b> ${item.name}<br><b>Population:</b> ${item.population.toLocaleString()}`);
        });
    });