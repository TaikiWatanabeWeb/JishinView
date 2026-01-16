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
const lastUpdateDisplay = ref(""); // 表示用の文字列

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

const getShindoColor = (scale) => {
    const s = parseInt(scale);
    if (s >= 70) return '#c850c8';
    if (s >= 60) return '#ff6b6b';
    if (s >= 55) return '#ff8e53';
    if (s >= 50) return '#ffad5a';
    if (s >= 45) return '#ffcf77';
    if (s >= 40) return '#fff27d';
    if (s >= 30) return '#98ee99';
    if (s >= 20) return '#81d4fa';
    if (s >= 10) return '#bbdefb';
    return 'transparent';
};

// --- 地図描画 ---
const updateMapDisplay = (shouldFly = false) => {
    if (!map || !geoJsonLayer || earthquakes.value.length === 0) return;

    const target = earthquakes.value[currentIndex.value];
    if (!target) return;

    const points = target.points || [];
    const eqInfo = target.earthquake || {};
    const hypo = eqInfo.hypocenter || {};

    // 1. 震度データと揺れた都道府県のリストを作成
    const cityShindoMap = {};
    const shakingPrefs = new Set();
    points.forEach(p => {
        if (p.pref) shakingPrefs.add(p.pref);
        // 都道府県名＋市区町村名でマップを作成（例: "長野県池田町"）
        cityShindoMap[p.pref + p.addr] = p.scale;
        // 市区町村名単体でも保持（後方互換用）
        cityShindoMap[p.addr] = p.scale;
    });

    iconLayerGroup.clearLayers();
    const placedCities = new Set();

    geoJsonLayer.eachLayer(layer => {
        const props = layer.feature.properties;
        const pref = props.N03_001 || ""; // 都道府県名
        const city = props.N03_004 || ""; // 市区町村名
        const ward = props.N03_005 || ""; // 区名
        const cityName = city + ward;
        const fullName = pref + cityName;

        let scale = 0;

        // 2. 判定ロジック：その都道府県が揺れたリストにある場合のみ詳細チェック
        if (shakingPrefs.has(pref)) {
            // まずは「都道府県+市区町村」で完全一致を狙う
            scale = cityShindoMap[fullName] || cityShindoMap[cityName] || 0;

            // 部分一致（API側の名称が短い場合などの補完）
            if (scale === 0) {
                for (let [addr, s] of Object.entries(cityShindoMap)) {
                    if (cityName && addr.includes(cityName) && addr.includes(pref)) {
                        scale = s;
                        break;
                    }
                }
            }
        }

        // 3. スタイル適用
        layer.setStyle({
            fillColor: scale > 0 ? getShindoColor(scale) : 'transparent',
            fillOpacity: scale > 0 ? 0.6 : 0, // 揺れていない場所は完全に透明に
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
            // 1. ズームレベルを計算するロジック
            // 基本は 8。震度が 30(震度3) 未満ならズームを 9 に、50(震度5弱) 以上なら広域を見せるために 7 にする
            let dynamicZoom = 8;
            const scale = parseInt(target.earthquake.maxScale);

            if (scale >= 50) {
                dynamicZoom = 7; // 広域表示
            } else if (scale < 30) {
                dynamicZoom = 10 // より詳細にズーム
            }

            // 2. マグニチュードが非常に大きい場合（M7以上など）の考慮も加える場合
            const mag = parseFloat(hypo.magnitude);
            if (mag >= 7.0) {
                dynamicZoom = 6; // 超巨大地震は日本全体が見えるくらい引く
            }

            // 計算したズームレベルで移動
            map.flyTo([hypo.latitude, hypo.longitude], dynamicZoom, {
                animate: true,
                duration: 1.5
            });

            isInitialLoad.value = false;
        }
    }
};

const fetchHistory = async () => {
    try {
        const res = await fetch('/api/earthquake/history');
        const data = await res.json();
        const dataArray = Array.isArray(data) ? data : [data];

        // フィルタリング
        const filtered = dataArray.filter((eq, i) => {
            const info = eq.earthquake || {};
            if (i === 0) return true;
            const hasScale = info.maxScale && info.maxScale !== -1;
            const hasHypo = info.hypocenter && info.hypocenter.name;
            return hasScale && hasHypo;
        });

        if (filtered.length > 0) {
            const latestId = filtered[0].id;

            // 【重要】最新の地震IDが前回取得時と違う場合のみ処理を行う
            if (latestId !== lastTopId.value) {
                // 初回読み込み（lastTopIdがnull）ではない場合は「新着あり」とみなす
                const isNewArrival = lastTopId.value !== null;

                if (isNewArrival) {
                    currentIndex.value = 0; // 新着があれば強制的に最新へ
                }

                earthquakes.value = filtered;
                lastTopId.value = latestId;

                // 描画更新（新着時のみ flyTo するように isNewArrival を渡す）
                nextTick(() => updateMapDisplay(isNewArrival));
            }
        }

        const now = new Date();
        const h = String(now.getHours()).padStart(2, '0');
        const m = String(now.getMinutes()).padStart(2, '0');
        const s = String(now.getSeconds()).padStart(2, '0');
        const y = now.getFullYear();
        const mon = now.getMonth() + 1;
        const d = now.getDate();

        lastUpdateDisplay.value = `${y}年${mon}月${d}日 ${h}時${m}分${s}秒更新`;
    } catch (e) {
        console.error("Fetch error:", e);
    }
};

watch(currentIndex, () => updateMapDisplay(true));

onMounted(async () => {
    // 1. 日本付近の表示制限範囲を設定
    const southWest = L.latLng(20, 118); // 南西端（沖縄の南西）
    const northEast = L.latLng(50, 155); // 北東端（択捉島の北東）
    const bounds = L.latLngBounds(southWest, northEast);

    // 2. 地図の初期化オプションを追加
    map = markRaw(L.map('map', {
        center: [36.5, 137.0],
        zoom: 5,
        minZoom: 5,           // これ以上ズームアウトさせない
        maxZoom: 12,          // 必要に応じて最大ズームも制限
        maxBounds: bounds,    // 画面の移動可能範囲を日本付近に限定
        maxBoundsViscosity: 1.0, // 範囲外にドラッグしようとした時の跳ね返り強度(1.0で完全固定)
        zoomControl: false
    }));

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        noWrap: true,         // 世界地図の横ループを無効化
        bounds: bounds        // タイルの読み込み範囲も制限
    }).addTo(map);

    iconLayerGroup = markRaw(L.layerGroup().addTo(map));

    try {
        const geoRes = await fetch('/data/japan.json');
        const geoData = await geoRes.json();
        geoJsonLayer = markRaw(L.geoJson(geoData, {
            style: {
                fillColor: 'transparent',
                weight: 0.3,
                color: '#555',
                fillOpacity: 0.05
            },
            onEachFeature: (feature, layer) => {
                // 市区町村名（+区名）を取得
                const cityName = (feature.properties.N03_004 || "") + (feature.properties.N03_005 || "");

                if (cityName) {
                    // ツールチップをバインド
                    layer.bindTooltip(cityName, {
                        sticky: true,        // これでマウスカーソルに追随します
                        direction: 'top',    // マウスの少し上に表示
                        offset: [0, -10],    // 位置の微調整
                        className: 'city-tooltip' // CSSで見た目をいじるためのクラス名
                    });
                }

                // ホバー時のスタイル変更（任意：分かりやすくなります）
                layer.on({
                    mouseover: (e) => {
                        const l = e.target;
                        l.setStyle({
                            weight: 2,
                            color: '#00c3ff',
                            fillOpacity: 0.2
                        });
                    },
                    mouseout: (e) => {
                        const l = e.target;
                        // scaleの状態に合わせてスタイルを戻す
                        const props = l.feature.properties;
                        const isShaking = l.options.fillColor !== 'transparent';
                        l.setStyle({
                            weight: isShaking ? 1.5 : 0.3,
                            color: isShaking ? '#ffffff' : '#555',
                            fillOpacity: isShaking ? 0.6 : 0.05
                        });
                    }
                });
            }
        }).addTo(map));
        await fetchHistory();
    } catch (e) {
        console.error(e);
    }

    // 初回実行
    await fetchHistory();
    setInterval(() => {
        fetchHistory();
        const now = new Date();
        secondsSinceUpdate.value = Math.floor((now - lastUpdateTime.value) / 1000);
    }, 1000);
});
</script>

<template>
    <Head title="Earthquake Monitor"/>
    <div class="monitor-root">
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
/* 地図自体の高さ確保 */
#map {
    height: 100vh;
    width: 100%;
    background: #1a1c1e;
}

/* 1. 地図上の震度アイコン (app.cssにはない Leaflet専用スタイル) */
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
    /* 白文字・黒縁取りの袋文字設定 */
    text-shadow: 1px 1px 0 #000, -1px -1px 0 #000,
    1px -1px 0 #000, -1px 1px 0 #000,
    1px 0px 0 #000, -1px 0px 0 #000,
    0px 1px 0 #000, 0px -1px 0 #000;
}

/* 2. 震源地マーカー (×印) */
:deep(.epicenter-mark) {
    color: #ff0000 !important;
    font-size: 40px !important;
    font-weight: bold !important;
    text-shadow: 0 0 5px #fff, 0 0 10px #fff;
    line-height: 40px;
    text-align: center;
}

/* 3. サイドパネルの微調整 (app.cssで足りない Vue特有の挙動) */
#side-panel {
    z-index: 1000; /* 地図より上に来るように強制 */
    /* app.css で定義されたスタイルがここに乗ります */
}

/* 履歴リストのアクティブ項目 */
.active-eq {
    background: rgba(255, 255, 255, 0.15) !important;
    border-left: 3px solid #00c3ff !important;
}

/* 凡例 (app.cssにない場合のみ) */
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
