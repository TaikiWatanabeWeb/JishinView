<script setup>
import {markRaw, nextTick, onMounted, ref, watch} from 'vue';
import {Head} from '@inertiajs/vue3';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import EarthquakePanel from "@/Components/EarthquakePanel.vue";

const earthquakes = ref([]);
const currentIndex = ref(0);
const lastTopId = ref(null);
const isInitialLoad = ref(true);
const lastUpdateDisplay = ref("");
const isLoading = ref(true); // 初期値は true

let map = null;
let geoJsonLayer = null;
let iconLayerGroup = null;
let epicenterMarker = null;

// --- 震度変換ヘルパー ---
const formatScale = (scale) => {
    const s = parseInt(scale);
    if (isNaN(s) || s < 10) return "-";
    if (s >= 70) return "7";
    if (s >= 60) return "6強";
    if (s >= 55) return "6弱";
    if (s >= 50) return "5強";
    if (s >= 45) return "5弱";
    if (s >= 40) return "4";
    if (s >= 30) return "3";
    if (s >= 20) return "2";
    if (s >= 10) return "1";
    return "-";
};

// 【元の色味を完全復元】
const getShindoColor = (scale) => {
    const s = parseInt(scale);
    if (s >= 70) return '#c850c8'; // 7
    if (s >= 60) return '#ff6b6b'; // 6強
    if (s >= 55) return '#ff8e53'; // 6弱
    if (s >= 50) return '#ffad5a'; // 5強
    if (s >= 45) return '#ffcf77'; // 5弱
    if (s >= 40) return '#fff27d'; // 4
    if (s >= 30) return '#98ee99'; // 3
    if (s >= 20) return '#81d4fa'; // 2
    if (s >= 10) return '#bbdefb'; // 1
    return 'transparent';
};

const updateClock = () => {
    const now = new Date();
    const h = String(now.getHours()).padStart(2, '0');
    const m = String(now.getMinutes()).padStart(2, '0');
    const s = String(now.getSeconds()).padStart(2, '0');
    lastUpdateDisplay.value = `${now.getFullYear()}年${now.getMonth() + 1}月${now.getDate()}日 ${h}時${m}分${s}秒更新`;
};

const fetchHistory = async () => {
    try {
        const res = await fetch('/api/earthquake/history');
        const data = await res.json();
        const dataArray = Array.isArray(data) ? data : [data];
        const filtered = dataArray.filter((eq, i) => {
            const info = eq.earthquake || {};
            if (i === 0) return true;
            return info.maxScale && info.maxScale !== -1 && info.hypocenter && info.hypocenter.name;
        });
        if (filtered.length > 0) {
            const latestId = filtered[0].id;
            if (latestId !== lastTopId.value) {
                const isNewArrival = lastTopId.value !== null;
                if (isNewArrival) currentIndex.value = 0;
                earthquakes.value = filtered;
                lastTopId.value = latestId;
                nextTick(() => updateMapDisplay(isNewArrival));
            }
        }
    } catch (e) {
        console.error(e);
    }
};

const connectWS = () => {
    const socket = new WebSocket('wss://api.p2pquake.net/v2/ws');

    socket.onmessage = (event) => {
        const data = JSON.parse(event.data);
        if ([551, 552, 554, 561].includes(data.code)) fetchHistory();
    };
    socket.onclose = () => setTimeout(connectWS, 5000);
};

// --- 地図描画（塗りつぶしロジック復元） ---
const updateMapDisplay = (shouldFly = false) => {
    if (!map || !geoJsonLayer || earthquakes.value.length === 0) return;
    const target = earthquakes.value[currentIndex.value];
    if (!target) return;

    const points = target.points || [];
    const eqInfo = target.earthquake || {};
    const hypo = eqInfo.hypocenter || {};

    // 1. 震度データと揺れた都道府県のリストを再構築
    const cityShindoMap = {};
    const shakingPrefs = new Set();
    points.forEach(p => {
        if (p.pref) shakingPrefs.add(p.pref);
        cityShindoMap[p.pref + p.addr] = p.scale;
        cityShindoMap[p.addr] = p.scale;
    });

    iconLayerGroup.clearLayers();
    const placedCities = new Set();

    geoJsonLayer.eachLayer(layer => {
        const props = layer.feature.properties;
        const pref = props.N03_001 || "";
        const city = props.N03_004 || "";
        const ward = props.N03_005 || "";
        const cityName = city + ward;
        const fullName = pref + cityName;

        let scale = 0;
        // 2. 判定ロジック：都道府県が一致する場合のみ詳細チェック
        if (shakingPrefs.has(pref)) {
            scale = cityShindoMap[fullName] || cityShindoMap[cityName] || 0;
            if (scale === 0) {
                for (let [addr, s] of Object.entries(cityShindoMap)) {
                    if (cityName && addr.includes(cityName) && addr.includes(pref)) {
                        scale = s;
                        break;
                    }
                }
            }
        }

        // 3. スタイル適用（塗りつぶしを有効化）
        layer.setStyle({
            fillColor: scale > 0 ? getShindoColor(scale) : 'transparent',
            fillOpacity: scale > 0 ? 0.6 : 0,
            color: scale > 0 ? '#ffffff' : '#444',
            weight: scale > 0 ? 1.5 : 0.3
        });

        // 4. 地図上の震度アイコン表示
        if (scale > 0 && !placedCities.has(fullName)) {
            try {
                const center = layer.getBounds().getCenter();
                L.marker(center, {
                    icon: L.divIcon({
                        className: 'shindo-icon-container',
                        html: `<div class="shindo-icon-inner" style="background-color: ${getShindoColor(scale)};">${formatScale(scale)}</div>`,
                        iconSize: [24, 24],
                        iconAnchor: [12, 12]
                    }),
                    interactive: false
                }).addTo(iconLayerGroup);
                placedCities.add(fullName);
            } catch (e) {
            }
        }
    });

    if (epicenterMarker) map.removeLayer(epicenterMarker);
    if (hypo.latitude && hypo.latitude !== -1) {
        epicenterMarker = L.marker([hypo.latitude, hypo.longitude], {
            icon: L.divIcon({className: 'epicenter-mark', html: '×', iconSize: [40, 40], iconAnchor: [20, 20]})
        }).addTo(map);

        if (isInitialLoad.value || shouldFly) {
            let dynamicZoom = 8;
            const maxS = parseInt(target.earthquake.maxScale);
            if (maxS >= 50) dynamicZoom = 7;
            else if (maxS < 30) dynamicZoom = 10;
            map.flyTo([hypo.latitude, hypo.longitude], dynamicZoom, {animate: true, duration: 1.5});
            isInitialLoad.value = false;
        }
    }
};

watch(currentIndex, () => updateMapDisplay(true));

onMounted(async () => {
    const bounds = L.latLngBounds(L.latLng(20, 118), L.latLng(50, 155));
    map = markRaw(L.map('map', {
        center: [36.5, 137.0], zoom: 5, minZoom: 5, maxBounds: bounds, maxBoundsViscosity: 1.0, zoomControl: false
    }));

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {noWrap: true, bounds: bounds}).addTo(map);
    iconLayerGroup = markRaw(L.layerGroup().addTo(map));

    try {
        const geoRes = await fetch('/data/japan.json');
        const geoData = await geoRes.json();
        geoJsonLayer = markRaw(L.geoJson(geoData, {
            style: {fillColor: 'transparent', weight: 0.3, color: '#555', fillOpacity: 0.05},
            onEachFeature: (feature, layer) => {
                const cityName = (feature.properties.N03_004 || "") + (feature.properties.N03_005 || "");
                if (cityName) {
                    layer.bindTooltip(cityName, {
                        sticky: true,
                        direction: 'top',
                        offset: [0, -10],
                        className: 'city-tooltip'
                    });
                }
            }
        }).addTo(map));

        await fetchHistory();

        isLoading.value = false;

        connectWS();
        setInterval(updateClock, 1000);
        updateClock();
    } catch (e) {
        console.error(e);
        isLoading.value = false;
    }
});
</script>

<template>
    <Head title="Earthquake Monitor"/>
    <div class="monitor-root">
        <Transition name="fade">
            <div v-if="isLoading" class="loading-overlay">
                <div class="loading-content">
                    <div class="spinner"></div>
                    <p>データを読み込んでいます...</p>
                </div>
            </div>
        </Transition>

        <div id="map"></div>
        <EarthquakePanel
            :earthquakes="earthquakes"
            v-model:currentIndex="currentIndex"
            :formatScale="formatScale"
            :getShindoColor="getShindoColor"
            :last-update-display="lastUpdateDisplay"
        />
        <div id="legend">
            <div v-for="s in [70, 60, 55, 50, 45, 40, 30, 20, 10]" :key="s" class="legend-item">
                <span :style="{ background: getShindoColor(s) }"></span>震度 {{ formatScale(s) }}
            </div>
        </div>
    </div>
</template>

<style scoped>
/* スタイルは変更なし（前回提供いただいたものに準拠） */
#map {
    height: 100vh;
    width: 100%;
    background: #1a1c1e;
}

:deep(.shindo-icon-container) {
    background: transparent !important;
    border: none !important;
}

:deep(.shindo-icon-inner) {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(0, 0, 0, 0.3);
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.5);
    color: #ffffff !important;
    font-weight: 900;
    font-size: 14px;
    line-height: 1;
    text-shadow: 1px 1px 0 #000, -1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000, 1px 0px 0 #000, -1px 0px 0 #000, 0px 1px 0 #000, 0px -1px 0 #000;
}

:deep(.epicenter-mark) {
    color: #ff0000 !important;
    font-size: 40px !important;
    font-weight: bold !important;
    text-shadow: 0 0 5px #fff, 0 0 10px #fff;
    line-height: 40px;
    text-align: center;
}

#legend {
    position: absolute;
    bottom: 30px;
    right: 20px;
    background: rgba(0, 0, 0, 0.7);
    padding: 12px;
    border-radius: 8px;
    color: white;
    z-index: 1000;
    font-size: 11px;
}

.legend-item {
    display: flex;
    align-items: center;
    margin-bottom: 4px;
}

.legend-item span {
    width: 15px;
    height: 15px;
    margin-right: 10px;
    border-radius: 2px;
    border: 1px solid rgba(255, 255, 255, 0.2);
}
</style>
