<script setup>
import {markRaw, nextTick, onMounted, onUnmounted, watch} from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import {lines} from '@/Data/train_data.js';

const props = defineProps({
    active: Boolean
});

let map = null;
let trainMarkers = {};
let animationIntervalId = null;
let currentBounds = null;

const calculateLineTrains = (lineKey, lineData, currentMinutes) => {
    const trains = [];
    const stations = lineData.stations;

    const TOTAL_MINUTES = 60;
    const ONE_WAY_MINUTES = stations.length * 2.5;
    const STATION_INTERVAL = 2.5;

    // 越後線などの特殊な運行間隔に対応するため、intervalを調整
    // train_data.js で interval が定義されているが、越後線は区間によって本数が違う
    // ここでは簡易的に、lineData.interval を基本とする

    if (lineData.loop) {
        // --- 環状線 (山手線) ---
        const LOOP_MINUTES = 60;
        const LOOP_STATION_INTERVAL = LOOP_MINUTES / stations.length;

        // 内回り (順方向: index増)
        let trainCount = 0;
        for (let startMin = 0; startMin < 24 * 60; startMin += lineData.interval) {
            trainCount++;
            let elapsed = currentMinutes - startMin;
            if (startMin < 4 * 60 + 30 || startMin > 25 * 60) continue;

            let dest = lineData.destinations.inner[0];
            let isLoop = true;
            if (lineData.destinations.inner.length > 1 && trainCount % 5 === 0) {
                dest = lineData.destinations.inner[1];
                isLoop = false;
            }

            if (!isLoop && elapsed > LOOP_MINUTES) continue;

            let positionInLoop = elapsed % LOOP_MINUTES;
            if (positionInLoop < 0) positionInLoop += LOOP_MINUTES;

            const stationIndexFloat = positionInLoop / LOOP_STATION_INTERVAL;
            const idx1 = Math.floor(stationIndexFloat) % stations.length;
            const idx2 = (idx1 + 1) % stations.length;
            const ratio = stationIndexFloat - Math.floor(stationIndexFloat);

            if (!isLoop) {
                const destIndex = stations.findIndex(s => s.name === dest);
                // 簡易判定: 始発(0)から進んで終点indexを超えたら消す
                // 山手線内回りは 東京(0) -> 品川(24) -> 東京
                // 大崎(23)止まりの場合、idx1が23以上なら消す
                if (destIndex !== -1 && idx1 >= destIndex) continue;
            }

            trains.push({
                id: `${lineKey}-inner-${startMin}`,
                line: lineKey,
                label: dest,
                lat: stations[idx1].lat + (stations[idx2].lat - stations[idx1].lat) * ratio,
                lng: stations[idx1].lng + (stations[idx2].lng - stations[idx1].lng) * ratio
            });
        }

        // 外回り (逆方向: index減)
        trainCount = 0;
        for (let startMin = 0; startMin < 24 * 60; startMin += lineData.interval) {
            trainCount++;
            let elapsed = currentMinutes - startMin;
            if (startMin < 4 * 60 + 30 || startMin > 25 * 60) continue;

            let dest = lineData.destinations.outer[0];
            let isLoop = true;
            if (lineData.destinations.outer.length > 1 && trainCount % 5 === 0) {
                dest = lineData.destinations.outer[1];
                isLoop = false;
            }

            if (!isLoop && elapsed > LOOP_MINUTES) continue;

            let positionInLoop = elapsed % LOOP_MINUTES;
            if (positionInLoop < 0) positionInLoop += LOOP_MINUTES;

            const stationIndexFloat = positionInLoop / LOOP_STATION_INTERVAL;
            // 外回りは逆順
            const idx1 = (stations.length - 1 - Math.floor(stationIndexFloat)) % stations.length;
            let idx2 = idx1 - 1;
            if (idx2 < 0) idx2 = stations.length - 1;

            const ratio = stationIndexFloat - Math.floor(stationIndexFloat);

            if (!isLoop) {
                const destIndex = stations.findIndex(s => s.name === dest);
                // 池袋(12)止まりの場合、東京(0)から逆順で進み、池袋に到達したら消す
                // 外回りの進行: 0(東京) -> 29(有楽町) -> ... -> 12(池袋)
                // idx1がdestIndexになったら終了
                if (destIndex !== -1 && idx1 === destIndex) continue;
            }

            trains.push({
                id: `${lineKey}-outer-${startMin}`,
                line: lineKey,
                label: dest,
                lat: stations[idx1].lat + (stations[idx2].lat - stations[idx1].lat) * ratio,
                lng: stations[idx1].lng + (stations[idx2].lng - stations[idx1].lng) * ratio
            });
        }

    } else {
        // --- 通常路線 (往復) ---

        // 下り (Down): 配列順 (0 -> length-1)
        // 例: 京浜東北線 南行 (大宮 -> 大船)
        // 例: 越後線 (新潟 -> 柏崎) ※train_data.jsのdestinations.downは「新潟」になっているが、
        //     配列順(0->end)は新潟->柏崎なので、これは「上り」または「下り」の定義による。
        //     JRの定義では新潟方面が「下り」だが、配列は新潟(0)から始まっている。
        //     ここでは「配列のインデックスが増える方向」を Down方向ロジック で処理し、
        //     行き先ラベルは lineData.destinations.up/down のどちらを使うか注意が必要。

        // train_data.js の定義を確認:
        // echigo: stations=[新潟, ..., 柏崎], destinations={down:["新潟"], up:["内野", "柏崎"]}
        // 一般的に新潟方面が下り。つまり 柏崎(end) -> 新潟(0) が下り。
        // 配列順 (0 -> end) は 新潟 -> 柏崎 なので「上り」。
        // よって、配列順のループ処理には destinations.up (柏崎方面) を適用すべき。

        // 汎用化のため、destinationsのキー名ではなく、配列の方向で考える。
        // Direction 1: Index 0 -> End
        let trainCount = 0;
        for (let startMin = 0; startMin < 24 * 60; startMin += lineData.interval) {
            trainCount++;
            let elapsed = currentMinutes - startMin;
            if (startMin < 4 * 60 + 30 || startMin > 25 * 60) continue;

            // 越後線特有の処理: 新潟(0)-内野(7)間は本数多いが、それ以南は少ない
            // 簡易的に、3本に2本は内野止まりとする
            let isShort = false;
            let dest = "";

            // 行き先ラベルの決定ロジック
            if (lineKey === 'echigo') {
                // 越後線の場合、配列順(0->End)は「上り(柏崎方面)」
                // 3本に2本は内野止まり
                if (trainCount % 3 !== 0) {
                    dest = "内野";
                    isShort = true;
                } else {
                    dest = "柏崎";
                }
            } else {
                // その他の路線（京浜東北線など）
                // 配列順は destinations.down (南行/西行など) を使うことが多いが、
                // 京浜東北線は 大宮(0) -> 大船(end) なので南行。 destinations.down=["大船", "磯子"]
                dest = lineData.destinations.down[0];
                if (lineData.destinations.down.length > 1 && trainCount % 3 === 0) {
                    dest = lineData.destinations.down[1];
                    isShort = true;
                }
            }

            // 運行時間チェック
            // 短縮運転の場合は所要時間も短い
            let maxIndex = stations.length - 1;
            if (isShort) {
                const destIndex = stations.findIndex(s => s.name === dest);
                if (destIndex !== -1) maxIndex = destIndex;
            }

            // 所要時間 = 駅数 * 2.5 (簡易)
            // 越後線は駅間長いので調整
            let intervalFactor = 2.5;
            if (lineKey === 'echigo') intervalFactor = 4.0;

            const currentMaxMinutes = maxIndex * intervalFactor;

            if (elapsed < 0 || elapsed > currentMaxMinutes) continue;

            const stationIndexFloat = elapsed / intervalFactor;
            const idx1 = Math.floor(stationIndexFloat);
            const idx2 = idx1 + 1;

            if (idx2 >= stations.length) continue;

            // 終点を超えていたら表示しない
            if (idx1 >= maxIndex) continue;

            const ratio = stationIndexFloat - Math.floor(stationIndexFloat);

            trains.push({
                id: `${lineKey}-dir1-${startMin}`,
                line: lineKey,
                label: dest,
                lat: stations[idx1].lat + (stations[idx2].lat - stations[idx1].lat) * ratio,
                lng: stations[idx1].lng + (stations[idx2].lng - stations[idx1].lng) * ratio
            });
        }

        // 下り (Up): 配列逆順 (length-1 -> 0)
        // Direction 2: Index End -> 0
        trainCount = 0;
        for (let startMin = 0; startMin < 24 * 60; startMin += lineData.interval) {
            trainCount++;
            let elapsed = currentMinutes - startMin;
            if (startMin < 4 * 60 + 30 || startMin > 25 * 60) continue;

            let isShort = false;
            let dest = "";
            let startIndex = stations.length - 1;

            if (lineKey === 'echigo') {
                // 越後線の場合、配列逆順(End->0)は「下り(新潟方面)」
                // destinations.down=["新潟"]
                dest = "新潟";
                // 内野始発(短縮)の判定
                // 3本に2本は内野始発とする
                if (trainCount % 3 !== 0) {
                    const uchinoIndex = stations.findIndex(s => s.name === "内野");
                    startIndex = uchinoIndex;
                    isShort = true;
                }
            } else {
                // その他の路線
                // 京浜東北線: 大船(end) -> 大宮(0) なので北行。 destinations.up=["大宮", "南浦和"]
                dest = lineData.destinations.up[0];
                if (lineData.destinations.up.length > 1 && trainCount % 3 === 0) {
                    dest = lineData.destinations.up[1];
                    // 途中止まり（南浦和行き）
                    // 始発は大船だが、終点が南浦和
                    // ここでは isShort フラグを「途中止まり」の意味で使う
                    isShort = true;
                }
            }

            let intervalFactor = 2.5;
            if (lineKey === 'echigo') intervalFactor = 4.0;

            // 経過時間から現在位置を計算
            // 始発駅からの距離(駅数) = elapsed / intervalFactor
            const traveledStations = elapsed / intervalFactor;

            // 現在のインデックス = 始発インデックス - 進んだ駅数
            const currentFloatIndex = startIndex - traveledStations;

            // 終点判定
            // 越後線以外で途中止まりの場合の終点インデックス
            let minIndex = 0;
            if (lineKey !== 'echigo' && isShort) {
                const destIndex = stations.findIndex(s => s.name === dest);
                if (destIndex !== -1) minIndex = destIndex;
            }

            if (currentFloatIndex < minIndex) continue; // 終点到着済み
            if (currentFloatIndex > startIndex) continue; // まだ出発していない(計算上ありえないが念のため)

            const idx1 = Math.ceil(currentFloatIndex); // 手前の駅（インデックスは大きい方）
            const idx2 = idx1 - 1; // 次の駅（インデックスは小さい方）

            if (idx2 < 0 && minIndex > 0) continue; // 終点より先
            if (idx2 < -1) continue; // 0より先

            // idx1とidx2の間の補間
            // currentFloatIndex は idx1 と idx2 の間にある
            // 例: 7.5 なら idx1=8, idx2=7. ratioは 8 - 7.5 = 0.5
            const ratio = idx1 - currentFloatIndex;

            // idx1が範囲外の場合のガード
            if (idx1 >= stations.length) continue;
            // idx2が範囲外(-1)の場合は0へ向かう途中
            const nextStation = (idx2 < 0) ? stations[0] : stations[idx2];
            const prevStation = stations[idx1];

            trains.push({
                id: `${lineKey}-dir2-${startMin}`,
                line: lineKey,
                label: dest,
                lat: prevStation.lat + (nextStation.lat - prevStation.lat) * ratio,
                lng: prevStation.lng + (nextStation.lng - prevStation.lng) * ratio
            });
        }
    }

    return trains;
};

const updateTrains = () => {
    if (!map) return;

    currentBounds = map.getBounds();
    const now = new Date();
    const currentMinutes = now.getHours() * 60 + now.getMinutes() + now.getSeconds() / 60;

    let allTrains = [];

    Object.keys(lines).forEach(key => {
        const lineTrains = calculateLineTrains(key, lines[key], currentMinutes);
        allTrains = allTrains.concat(lineTrains);
    });

    const activeIds = new Set();

    allTrains.forEach(train => {
        if (currentBounds && !currentBounds.contains([train.lat, train.lng])) {
            return;
        }

        activeIds.add(train.id);

        if (trainMarkers[train.id]) {
            trainMarkers[train.id].setLatLng([train.lat, train.lng]);

            const currentLabel = trainMarkers[train.id].options.trainLabel;
            if (currentLabel !== train.label) {
                map.removeLayer(trainMarkers[train.id]);
                delete trainMarkers[train.id];
                createMarker(train);
            }
        } else {
            createMarker(train);
        }
    });

    Object.keys(trainMarkers).forEach(id => {
        if (!activeIds.has(id)) {
            map.removeLayer(trainMarkers[id]);
            delete trainMarkers[id];
        }
    });
};

const createMarker = (train) => {
    const lineData = lines[train.line];
    const bgColor = lineData ? lineData.color : '#80c342';

    const iconHtml = `
        <div class="dest-sign" style="background-color: ${bgColor};">
            <span class="dest-text">${train.label}</span>
        </div>
    `;

    const marker = L.marker([train.lat, train.lng], {
        icon: L.divIcon({
            className: 'train-icon-wrapper',
            html: iconHtml,
            iconSize: [28, 28],
            iconAnchor: [14, 14]
        }),
        interactive: false,
        zIndexOffset: 1000,
        trainLabel: train.label
    }).addTo(map);

    trainMarkers[train.id] = marker;
};

watch(() => props.active, (newVal) => {
    if (newVal) {
        nextTick(() => {
            if (map) map.invalidateSize();
            updateTrains();
            if (!animationIntervalId) {
                animationIntervalId = setInterval(updateTrains, 1000);
            }
        });
    } else {
        if (animationIntervalId) {
            clearInterval(animationIntervalId);
            animationIntervalId = null;
        }
    }
});

onMounted(() => {
    nextTick(() => {
        map = markRaw(L.map('train-map', {
            center: [35.69, 139.75],
            zoom: 11,
            minZoom: 8,
            zoomControl: false
        }));

        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_nolabels/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; OpenStreetMap &copy; CARTO',
            maxZoom: 20
        }).addTo(map);

        Object.values(lines).forEach(line => {
            const latlngs = line.stations.map(s => [s.lat, s.lng]);
            if (line.loop) latlngs.push([line.stations[0].lat, line.stations[0].lng]);

            L.polyline(latlngs, {
                color: line.color,
                weight: 3,
                opacity: 0.8
            }).addTo(map);

            line.stations.forEach(s => {
                L.circleMarker([s.lat, s.lng], {
                    radius: 4,
                    fillColor: '#fff',
                    color: '#333',
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 1
                }).addTo(map).bindTooltip(s.name, {
                    permanent: true,
                    direction: 'right',
                    className: 'station-label',
                    offset: [8, 0]
                });
            });
        });

        if (props.active) {
            updateTrains();
            animationIntervalId = setInterval(updateTrains, 1000);
        }
    });
});

onUnmounted(() => {
    if (animationIntervalId) clearInterval(animationIntervalId);
});
</script>

<template>
    <div class="view-container">
        <div id="train-map"></div>

        <div class="train-legend">
            <div class="legend-title">運行状況 (シミュレーション)</div>
            <div class="legend-grid">
                <div v-for="(line, key) in lines" :key="key" class="legend-item">
                    <span class="train-dot" :style="{ background: line.color }"></span> {{ line.name }}
                </div>
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

#train-map {
    height: 100vh;
    width: 100%;
    background: #f4f7f6;
}

.train-legend {
    position: absolute;
    bottom: 30px;
    right: 20px;
    background: rgba(255, 255, 255, 0.9);
    padding: 12px;
    border-radius: 8px;
    color: #333;
    z-index: 1000;
    font-size: 11px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    max-width: 200px;
}

.legend-title {
    font-weight: bold;
    margin-bottom: 8px;
}

.legend-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 5px;
}

.legend-item {
    display: flex;
    align-items: center;
}

.train-dot {
    width: 10px;
    height: 10px;
    margin-right: 6px;
    border-radius: 2px;
    flex-shrink: 0;
}

:deep(.train-icon-wrapper) {
    background: transparent;
    border: none;
}

:deep(.dest-sign) {
    width: 28px;
    height: 28px;
    border-radius: 4px;
    border: 2px solid #fff;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
    display: flex;
    justify-content: center;
    align-items: center;
}

:deep(.dest-text) {
    color: #333;
    font-size: 10px;
    font-weight: bold;
    line-height: 1;
    white-space: nowrap;
    transform: scale(0.85);
}

:deep(.station-label) {
    background-color: rgba(255, 255, 255, 0.85);
    border: 1px solid #aaa;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    font-size: 11px;
    font-weight: bold;
    color: #333;
    padding: 2px 5px;
}
</style>
