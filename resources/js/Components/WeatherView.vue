<script setup>
import {markRaw, nextTick, onMounted, watch} from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import axios from 'axios';

const props = defineProps({
    active: Boolean
});

let map = null;
let windLayerGroup = null;

// スリープ関数
const sleep = (ms) => new Promise(resolve => setTimeout(resolve, ms));

// 日本周辺のグリッドポイントを生成
const generateGridPoints = () => {
    const points = [];
    for (let lat = 24; lat <= 48; lat += 0.8) {
        for (let lng = 123; lng <= 150; lng += 0.8) {
            points.push({lat, lng});
        }
    }
    return points;
};

// Open-Meteo APIから風データを取得
const fetchWindData = async () => {
    const points = generateGridPoints();

    // POSTリクエストになったので、ある程度まとめて送っても大丈夫
    // ただしOpen-Meteo側の処理負荷も考慮し、適度なサイズにする
    const chunkSize = 200;
    const chunks = [];

    for (let i = 0; i < points.length; i += chunkSize) {
        chunks.push(points.slice(i, i + chunkSize));
    }

    try {
        windLayerGroup.clearLayers();

        for (const chunk of chunks) {
            const lats = chunk.map(p => p.lat).join(',');
            const lngs = chunk.map(p => p.lng).join(',');

            try {
                const response = await axios.post('/api/weather/wind', {
                    latitude: lats,
                    longitude: lngs
                });

                const data = response.data;

                let results = [];
                if (Array.isArray(data)) {
                    results = data;
                } else {
                    results = [data];
                }

                addWindMarkers(results, chunk);

                // 連続リクエストによる429エラーを防ぐため、少し待機する
                await sleep(300);

            } catch (err) {
                // 特定のチャンクが失敗しても、他のチャンクは処理を続ける
                console.error("Chunk fetch failed:", err);
                // エラー時も少し待つ
                await sleep(1000);
            }
        }
    } catch (e) {
        console.error("Failed to fetch wind data process:", e);
    }
};

const addWindMarkers = (weatherDataList, points) => {
    if (!map || !windLayerGroup) return;

    weatherDataList.forEach((data, index) => {
        // エラーレスポンスが含まれている場合のガード
        if (!data || data.error) return;

        const point = points[index];
        const current = data.current_weather;

        if (!current) return;

        const windSpeed = current.windspeed;
        const windDir = current.winddirection;

        let particleColor = '#42a5f5';

        if (windSpeed >= 15) {
            particleColor = '#d32f2f';
        } else if (windSpeed >= 10) {
            particleColor = '#fbc02d';
        } else if (windSpeed >= 5) {
            particleColor = '#66bb6a';
        }

        const duration = 1.5 + Math.random() * 1.0;
        const delay = Math.random() * 2.0;
        const rotateDeg = windDir + 180;

        const iconHtml = `
            <div class="wind-particle-container" style="transform: rotate(${rotateDeg}deg);">
                <div class="wind-particle-line"
                     style="
                        background: linear-gradient(to bottom, transparent, ${particleColor}, transparent);
                        animation-duration: ${duration}s;
                        animation-delay: -${delay}s;
                     ">
                </div>
            </div>
        `;

        L.marker([point.lat, point.lng], {
            icon: L.divIcon({
                className: 'wind-icon-wrapper',
                html: iconHtml,
                iconSize: [60, 60],
                iconAnchor: [30, 30]
            }),
            interactive: false
        }).addTo(windLayerGroup);
    });
};

watch(() => props.active, (newVal) => {
    if (newVal && map) {
        nextTick(() => {
            map.invalidateSize();
            if (windLayerGroup.getLayers().length === 0) {
                fetchWindData();
            }
        });
    }
});

onMounted(() => {
    nextTick(() => {
        map = markRaw(L.map('weather-map', {
            center: [36.5, 137.0],
            zoom: 5,
            minZoom: 4,
            zoomControl: false
        }));

        L.tileLayer('https://cyberjapandata.gsi.go.jp/xyz/pale/{z}/{x}/{y}.png', {
            attribution: "<a href='https://maps.gsi.go.jp/development/ichiran.html' target='_blank'>国土地理院</a> | <a href='https://open-meteo.com/'>Open-Meteo</a>",
            maxZoom: 18,
            opacity: 1.0
        }).addTo(map);

        windLayerGroup = markRaw(L.layerGroup().addTo(map));

        fetchWindData();

        setTimeout(() => {
            map.invalidateSize();
        }, 200);

        setInterval(fetchWindData, 60 * 60 * 1000);
    });
});
</script>

<template>
    <div class="view-container">
        <div id="weather-map"></div>

        <div class="wind-legend">
            <div class="legend-title">風向・風速</div>
            <div class="legend-scale">
                <span class="scale-label">弱</span>
                <div class="color-bar"></div>
                <span class="scale-label">強</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
.view-container {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
}

#weather-map {
    height: 100vh;
    width: 100%;
    background: #f0f0f0;
}

.wind-legend {
    position: absolute;
    bottom: 30px;
    right: 20px;
    background: rgba(255, 255, 255, 0.9);
    padding: 10px 15px;
    border-radius: 8px;
    color: #333;
    z-index: 1000;
    font-size: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.legend-title {
    font-weight: bold;
    margin-bottom: 8px;
    text-align: center;
}

.legend-scale {
    display: flex;
    align-items: center;
    gap: 8px;
}

.color-bar {
    width: 120px;
    height: 8px;
    background: linear-gradient(to right, #42a5f5, #66bb6a, #fbc02d, #d32f2f);
    border-radius: 4px;
}

.scale-label {
    font-size: 11px;
    color: #555;
}

:deep(.wind-icon-wrapper) {
    background: transparent;
    border: none;
}

:deep(.wind-particle-container) {
    width: 100%;
    height: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    transform-origin: center center;
}

:deep(.wind-particle-line) {
    width: 2px;
    height: 25px;
    border-radius: 2px;
    opacity: 0;
    animation-name: particle-flow;
    animation-timing-function: linear;
    animation-iteration-count: infinite;
}

@keyframes particle-flow {
    0% {
        transform: translateY(-15px);
        opacity: 0;
    }
    20% {
        opacity: 0.8;
    }
    80% {
        opacity: 0.8;
    }
    100% {
        transform: translateY(15px);
        opacity: 0;
    }
}
</style>
