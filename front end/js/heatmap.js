
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

// Function to display selected data in table
function displaySelectedData(item) {
    const tableContainer = document.getElementById('table-container');
    tableContainer.innerHTML = `
        <table id="selected-data-table">
            <thead>
                <tr>
                    <th>Property</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Area</strong></td>
                    <td>${item.name}</td>
                </tr>
                <tr>
                    <td><strong>IP Group</strong></td>
                    <td>${item.ip_group}</td>
                </tr>
                <tr>
                    <td><strong>Population</strong></td>
                    <td>${item.population.toLocaleString()}</td>
                </tr>
                <tr>
                    <td><strong>Latitude</strong></td>
                    <td>${item.lat.toFixed(6)}</td>
                </tr>
                <tr>
                    <td><strong>Longitude</strong></td>
                    <td>${item.lng.toFixed(6)}</td>
                </tr>
            </tbody>
        </table>
    `;
}

// Inject CSS for animation and panels
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
.custom-tooltip {
    background-color: rgba(255, 255, 255, 0.95);
    color: #333;
    font-weight: bold;
    border-radius: 6px;
    padding: 6px 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    font-size: 14px;
    pointer-events: none;
}
#ip-summary-panel {
    position: absolute;
    right: 10px;
    top: 10px;
    background: rgba(255, 255, 255, 0.95);
    padding: 12px;
    border-radius: 8px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    max-height: calc(70vh - 40px);
    overflow-y: auto;
    font-size: 12px;
    z-index: 999;
    width: 220px;
}
#ip-summary-panel h4 {
    margin-top: 0;
    margin-bottom: 10px;
    font-size: 14px;
    font-weight: bold;
}
.ip-summary-item {
    margin-bottom: 8px;
    font-size: 11px;
}
.ip-summary-total {
    margin-top: 10px;
    border-top: 1px solid #ccc;
    padding-top: 8px;
    font-weight: bold;
    font-size: 11px;
}
.leaflet-control.legend {
    background: white;
    padding: 10px;
    line-height: 18px;
    color: #333;
    font-size: 13px;
    border-radius: 6px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
}
#info-panel {
    display: none;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    z-index: 1000;
    max-width: 400px;
}
`;
document.head.appendChild(style);

// Create IP summary panel
const mapContainer = document.getElementById('map');
const ipPanel = document.createElement('div');
ipPanel.id = 'ip-summary-panel';
ipPanel.innerHTML = `<h4>IP Group Summary</h4><div id="ip-summary-content">Loading...</div>`;
mapContainer.appendChild(ipPanel);

// Fetch and display population data
fetch('./get_population_data.php')
    .then(response => response.json())
    .then(data => {
        const ipGroups = {};
        let allTotalPop = 0;

        data.forEach(item => {
            let pop = item.population;
            let color;

            // Assign color based on population range
            if (pop <= 100) {
                color = '#0014FF'; // Deep Blue
            } else if (pop <= 300) {
                let green = hexToRgb('#00FF5B');
                let cyan = hexToRgb('#00FFFF');
                let factor = (pop - 100) / (300 - 100);
                factor = Math.pow(factor, 0.7);
                color = rgbToHex(interpolateColor(green, cyan, factor));
            } else if (pop <= 600) {
                let orange = hexToRgb('#FFA500');
                let lightRed = hexToRgb('#FF6666');
                let factor = (pop - 300) / (600 - 300);
                factor = Math.pow(factor, 0.7);
                color = rgbToHex(interpolateColor(orange, lightRed, factor));
            } else if (pop <= 1000) {
                let warmYellow = hexToRgb('#FFC300');
                let deepRed = hexToRgb('#C70039');
                let factor = (pop - 600) / (1000 - 600);
                factor = Math.pow(factor, 0.7);
                color = rgbToHex(interpolateColor(warmYellow, deepRed, factor));
            } else {
                color = '#888888'; // Default for over 1,000
            }

            // Set icon radius based on population scale
            let radius = 10;
            if (pop > 600) radius = 30;
            else if (pop > 300) radius = 22;
            else if (pop > 100) radius = 16;

            // Create animated marker
            const icon = L.divIcon({
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

            L.marker([item.lat, item.lng], { icon: icon })
                .addTo(map)
                .bindTooltip(
                    `<strong>Area:</strong> ${item.name}<br>
                     <strong>IP Group:</strong> ${item.ip_group}<br>
                     <strong>Population:</strong> ${item.population.toLocaleString()}`,
                    {
                        permanent: false,
                        direction: 'top',
                        offset: [0, -radius],
                        className: 'custom-tooltip'
                    }
                )
                .on('click', () => {
                    // Display data in table below map
                    displaySelectedData(item);
                    
                    // Scroll to the data section
                    document.getElementById('data-display-section').scrollIntoView({ 
                        behavior: 'smooth',
                        block: 'start'
                    });
                });

            // Update IP group stats
            if (!ipGroups[item.ip_group]) {
                ipGroups[item.ip_group] = { count: 0, totalPop: 0 };
            }
            ipGroups[item.ip_group].count++;
            ipGroups[item.ip_group].totalPop += item.population;
        });

        // Alphabetically sorted summary
        const summaryHtml = Object.entries(ipGroups)
            .sort((a, b) => a[0].localeCompare(b[0]))
            .map(([group, stats]) => {
                allTotalPop += stats.totalPop;
                return `
                    <div class="ip-summary-item">
                        <strong>${group}</strong><br>
                        Areas: ${stats.count}<br>
                        Population: ${stats.totalPop.toLocaleString()}
                    </div>
                `;
            }).join('');

        // Update summary panel
        document.getElementById('ip-summary-content').innerHTML = `
            ${summaryHtml}
            <div class="ip-summary-total">
                Total Population: ${allTotalPop.toLocaleString()}
            </div>
        `;
    });

// Legend Control
function addLegend(map) {
    const legend = L.control({ position: 'bottomright' });

    legend.onAdd = function () {
        const div = L.DomUtil.create('div', 'leaflet-control legend');
        const ranges = [
            { label: '≤ 100', color: '#0014FF' },
            { label: '101 – 300', color: 'linear-gradient(to right, #00FF5B, #00FFFF)' },
            { label: '301 – 600', color: 'linear-gradient(to right, #FFA500, #FF6666)' },
            { label: '601 – 1000', color: 'linear-gradient(to right, #FFC300, #C70039)' },
            { label: '> 1000', color: '#888888' }
        ];

        div.innerHTML = '<strong>Population Legend</strong><br>';
        ranges.forEach(r => {
            div.innerHTML += `
                <div style="margin-bottom: 6px;">
                    <div style="
                        display: inline-block;
                        width: 80px;
                        height: 12px;
                        background: ${r.color};
                        margin-right: 6px;
                        border: 1px solid #999;
                        border-radius: 2px;
                    "></div>${r.label}
                </div>
            `;
        });

        return div;
    };

    legend.addTo(map);
}

// Close info panel on button click
document.getElementById('close-btn').addEventListener('click', () => {
    document.getElementById('info-panel').style.display = 'none';
});

// Close info panel on ESC key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.getElementById('info-panel').style.display = 'none';
    }
});

addLegend(map);