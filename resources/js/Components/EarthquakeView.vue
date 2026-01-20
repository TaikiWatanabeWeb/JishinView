<script setup>
import {markRaw, nextTick, onMounted, ref, watch} from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import EarthquakePanel from "@/Components/EarthquakePanel.vue";
import EarthquakeLegend from "@/Components/EarthquakeLegend.vue";
import LoadingOverlay from "@/Components/LoadingOverlay.vue";
import {formatScale, getShindoColor} from "@/Utils/earthquakeUtils";

const props = defineProps({
    active: Boolean
});

const earthquakes = ref([]);
const currentIndex = ref(0);
const lastTopId = ref(null);
const isInitialLoad = ref(true);
const lastUpdateDisplay = ref("");
const isLoading = ref(true);
const currentIsEEW = ref(false);

let map = null;
let geoJsonLayer = null;
let iconLayerGroup = null;
let epicenterMarker = null;
let eewBoundsLayer = null;

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

const handleEEW = (data) => {
    if (!map) return;
    if (eewBoundsLayer) map.removeLayer(eewBoundsLayer);

    const hypo = data.earthquake?.hypocenter || {};
    if (hypo.area) {
        const bounds = [
            [hypo.area.s, hypo.area.w],
            [hypo.area.n, hypo.area.e]
        ];

        eewBoundsLayer = L.rectangle(bounds, {
            className: 'eew-rect-animated',
            color: "#ff0000",
            weight: 5,
            fillColor: "#ff0000",
            fillOpacity: 0.3,
            dashArray: '12, 12',
            interactive: false
        }).addTo(map);

        map.fitBounds(bounds, {padding: [100, 100], animate: true, duration: 1.5});
    }
};

const connectWS = () => {
    const socket = new WebSocket('wss://api.p2pquake.net/v2/ws');
    socket.onmessage = (event) => {
        const data = JSON.parse(event.data);
        if (data.code === 554) {
            currentIsEEW.value = true;
            handleEEW(data);
        }
        if ([551, 552, 556, 561].includes(data.code)) {
            currentIsEEW.value = false;
            if (eewBoundsLayer) {
                map.removeLayer(eewBoundsLayer);
                eewBoundsLayer = null;
            }
            fetchHistory();
        }
    };
    socket.onclose = () => setTimeout(connectWS, 5000);
};

const updateMapDisplay = (shouldFly = false) => {
    if (!map || !geoJsonLayer || earthquakes.value.length === 0) return;
    const target = earthquakes.value[currentIndex.value];
    if (!target) return;

    const points = target.points || [];
    const eqInfo = target.earthquake || {};
    const hypo = eqInfo.hypocenter || {};

    const cityShindoMap = {};
    const shakingPrefs = new Set();
    points.forEach(p => {
        if (p.pref) shakingPrefs.add(p.pref);
        cityShindoMap[p.pref + p.addr] = p.scale;
    });

    iconLayerGroup.clearLayers();
    const placedCities = new Set();

    geoJsonLayer.eachLayer(layer => {
        const props = layer.feature.properties;
        const pref = props.N03_001 || "";
        const cityName = (props.N03_004 || "") + (props.N03_005 || "");
        const fullName = pref + cityName;

        let scale = 0;
        if (shakingPrefs.has(pref)) {
            scale = cityShindoMap[fullName] || 0;
            if (scale === 0) {
                for (let [key, s] of Object.entries(cityShindoMap)) {
                    if (key.startsWith(pref) && key.includes(cityName)) {
                        scale = s;
                        break;
                    }
                }
            }
        }

        layer.setStyle({
            fillColor: scale > 0 ? getShindoColor(scale) : 'transparent',
            fillOpacity: scale > 0 ? 0.6 : 0,
            color: scale > 0 ? '#ffffff' : '#777575', // 境界線を薄いグレーに
            weight: scale > 0 ? 1.5 : 0.3 // 通常時の線を細く
        });

        if (scale > 0 && !placedCities.has(fullName)) {
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
        }
    });

    if (epicenterMarker) map.removeLayer(epicenterMarker);
    if (hypo.latitude && hypo.latitude !== -1) {
        epicenterMarker = L.marker([hypo.latitude, hypo.longitude], {
            icon: L.divIcon({
                className: 'epicenter-wrapper',
                html: '<div class="ripple"></div><div class="ripple delay"></div><div class="epicenter-mark">×</div>',
                iconSize: [40, 40],
                iconAnchor: [20, 20]
            }),
            interactive: false // マウスイベントを無効化
        }).addTo(map);

        if (isInitialLoad.value || shouldFly) {
            let dynamicZoom = 8;
            const maxS = parseInt(eqInfo.maxScale);

            if (maxS >= 50) {
                dynamicZoom = 7;
            } else if (maxS >= 40) {
                dynamicZoom = 8;
            } else if (maxS < 30) {
                dynamicZoom = 10;
            }

            map.flyTo([hypo.latitude, hypo.longitude], dynamicZoom, {animate: true, duration: 1.5});
            isInitialLoad.value = false;
        }
    }
};

watch(currentIndex, () => updateMapDisplay(true));

watch(() => props.active, (newVal) => {
    if (newVal && map) {
        nextTick(() => {
            map.invalidateSize();
        });
    }
});

onMounted(async () => {
    const bounds = L.latLngBounds(L.latLng(20, 118), L.latLng(50, 155));
    map = markRaw(L.map('map', {
        center: [36.5, 137.0], zoom: 5, minZoom: 5, maxBounds: bounds, maxBoundsViscosity: 1.0, zoomControl: false, attributionControl: false
    }));

    // シンプルな地図（ラベルなし、建物なし）
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
        subdomains: 'abcd',
        maxZoom: 20,
        bounds: bounds
    }).addTo(map);

    iconLayerGroup = markRaw(L.layerGroup().addTo(map));

    try {
        const geoRes = await fetch('/data/japan.json');
        const geoData = await geoRes.json();
        geoJsonLayer = markRaw(L.geoJson(geoData, {
            style: {
                fillColor: 'transparent',
                weight: 0.3, // 境界線を細く
                color: '#ccc', // 境界線を薄いグレーに
                fillOpacity: 0 // 塗りつぶしなし
            },
            onEachFeature: (feature, layer) => {
                const props = feature.properties;
                const cityName = (props.N03_004 || "") + (props.N03_005 || "");

                if (cityName) {
                    layer.bindTooltip(cityName, {
                        sticky: true,
                        direction: 'top',
                        offset: [0, -10],
                        className: 'city-tooltip',
                        pane: 'tooltipPane'
                    });
                }

                layer.on({
                    mouseover: (e) => {
                        const l = e.target;
                        l._originalStyle = {
                            weight: l.options.weight,
                            color: l.options.color,
                            fillOpacity: l.options.fillOpacity,
                            fillColor: l.options.fillColor
                        };

                        l.setStyle({
                            weight: 3,
                            color: '#00c3ff',
                            fillOpacity: 0.4
                        });

                        if (!L.Browser.ie && !L.Browser.opera && !L.Browser.edge) {
                            l.bringToFront();
                        }
                    },
                    mouseout: (e) => {
                        const l = e.target;
                        if (l._originalStyle) {
                            l.setStyle(l._originalStyle);
                        }
                    }
                });
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
    <div class="view-container">
        <LoadingOverlay :isLoading="isLoading"/>

        <div id="map"></div>

        <EarthquakePanel
            v-if="!isLoading && earthquakes.length > 0"
            :earthquakes="earthquakes"
            v-model:currentIndex="currentIndex"
            :lastUpdateDisplay="lastUpdateDisplay"
            :isEEW="currentIsEEW"
        />

        <EarthquakeLegend/>
    </div>
</template>

<style scoped>
#map {
    height: 100vh;
    width: 100%;
}

:deep(.eew-rect-animated) {
    animation: eew-dash-rotate 2s linear infinite, eew-fade-pulse 1.5s ease-in-out infinite alternate;
}

@keyframes eew-dash-rotate {
    from {
        stroke-dashoffset: 24;
    }
    to {
        stroke-dashoffset: 0;
    }
}

@keyframes eew-fade-pulse {
    from {
        opacity: 0.4;
        stroke-width: 4;
    }
    to {
        opacity: 1;
        stroke-width: 7;
    }
}

:deep(.shindo-icon-inner) {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(0, 0, 0, 0.3);
    border-radius: 4px;
    color: #ffffff !important;
    font-weight: 900;
    font-size: 14px;
    text-shadow: 1px 1px 0 #000, -1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000;
}

:deep(.epicenter-wrapper) {
    background: transparent;
    border: none;
    pointer-events: none; /* マウスイベントを無効化 */
}

:deep(.epicenter-mark) {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: #ff0000 !important;
    font-size: 80px !important;
    text-shadow: 0 0 5px #fff, 0 0 10px #fff;
    line-height: 40px;
    text-align: center;
    font-weight: lighter;
    z-index: 10;
}

:deep(.ripple) {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 2px solid #ff0000;
    opacity: 0;
    box-sizing: border-box;
    animation: ripple-anim 2s infinite ease-out;
    pointer-events: none; /* マウスイベントを無効化 */
}

:deep(.ripple.delay) {
    animation-delay: 1s;
}

@keyframes ripple-anim {
    0% {
        width: 20px;
        height: 20px;
        opacity: 1;
        border-width: 3px;
    }
    100% {
        width: 150px;
        height: 150px;
        opacity: 0;
        border-width: 1px;
    }
}

:deep(.city-tooltip) {
    background-color: #fff !important;
    border: 1px solid rgba(0, 0, 0, 0.2) !important;
    color: #000 !important;
    font-size: 12px !important;
    padding: 4px 8px !important;
    border-radius: 4px !important;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2) !important;
}

.view-container {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
}
</style>
