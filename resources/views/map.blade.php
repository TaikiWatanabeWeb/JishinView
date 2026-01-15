<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Earthquake Monitor</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        body, html { margin: 0; padding: 0; height: 100%; background: #1a1c1e; }
        #map { height: 100vh; width: 100%; background: #1a1c1e; }
        path.leaflet-interactive { transition: fill 0.4s ease; outline: none; }

        /* 震度アイコンのデザイン */
        .shindo-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid #444;
            border-radius: 4px;
            font-weight: bold;
            font-size: 12px;
            color: #333;
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        #legend {
            position: absolute; bottom: 30px; right: 20px;
            background: rgba(0, 0, 0, 0.7); padding: 12px;
            border-radius: 8px; color: white; z-index: 1000;
            font-size: 11px; border: 1px solid rgba(255,255,255,0.1);
            backdrop-filter: blur(4px);
        }
        .legend-item { display: flex; align-items: center; margin-bottom: 3px; }
        .legend-item span { width: 14px; height: 14px; margin-right: 8px; border-radius: 2px; }
    </style>
</head>
<body>

@include("components.panel")

<div id="map"></div>

<div id="legend">
    <div class="legend-item"><span style="background:#c850c8"></span>震度 7</div>
    <div class="legend-item"><span style="background:#ff6b6b"></span>震度 6強</div>
    <div class="legend-item"><span style="background:#ff8e53"></span>震度 6弱</div>
    <div class="legend-item"><span style="background:#ffad5a"></span>震度 5強</div>
    <div class="legend-item"><span style="background:#ffcf77"></span>震度 5弱</div>
    <div class="legend-item"><span style="background:#fff27d"></span>震度 4</div>
    <div class="legend-item"><span style="background:#98ee99"></span>震度 3</div>
    <div class="legend-item"><span style="background:#81d4fa"></span>震度 2</div>
    <div class="legend-item"><span style="background:#bbdefb"></span>震度 1</div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    // 1. 地図初期化
    const map = L.map('map', {
        center: [36.5, 137.0],
        zoom: 5,
        minZoom: 5,
        maxZoom: 12,
        maxBounds: [[20.0, 122.0], [46.0, 150.0]],
        maxBoundsViscosity: 1.0
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(map);

    let geoJsonLayer = null;
    let iconLayerGroup = L.layerGroup().addTo(map); // 震度数字用のレイヤー
    let epicenterMarker = null; // 震源地マーカー用
    let cityShindoMap = {};

    function formatScale(scale) {
        if (scale >= 70) return "7";
        if (scale >= 60) return "6強";
        if (scale >= 55) return "6弱";
        if (scale >= 50) return "5強";
        if (scale >= 45) return "5弱";
        if (scale >= 40) return "4";
        if (scale >= 30) return "3";
        if (scale >= 20) return "2";
        if (scale >= 10) return "1";
        return "-";
    }

    function getShindoColor(scale) {
        if (scale >= 70) return '#c850c8';
        if (scale >= 60) return '#ff6b6b';
        if (scale >= 55) return '#ff8e53';
        if (scale >= 50) return '#ffad5a';
        if (scale >= 45) return '#ffcf77';
        if (scale >= 40) return '#fff27d';
        if (scale >= 30) return '#98ee99';
        if (scale >= 20) return '#81d4fa';
        if (scale >= 10) return '#bbdefb';
        return 'transparent';
    }

    let isInitialLoad = true; // 初回判定用フラグ
    // 2. 最新地震取得・反映処理
    // updateEarthquake 関数を以下のように書き換えます
    let currentFullData = []; // 取得した全データを保持する変数
    let currentViewId = null; // 現在地図に表示している地震のID
    let lastTopId = null;     // 前回取得した最新地震のID
    let currentIndex = 0;    // ★追加：現在表示中のインデックスを保持

    async function updateEarthquake(index = 0, isAutoUpdate = false) {
        try {
            const response = await fetch('/api/earthquake/history');
            let rawData = await response.json();

            // 配列化
            let dataArray = Array.isArray(rawData) ? rawData : [rawData];

            // --- 異常データのフィルタリングロジック ---
            const now = new Date();
            const filteredData = dataArray.filter((eq, i) => {
                // 最新の1件目(index 0)は、速報の可能性があるので異常値でも表示する
                if (i === 0) return true;

                const eqInfo = eq.earthquake || {};
                const maxScale = eqInfo.maxScale;
                const hypoName = eqInfo.hypocenter?.name;

                // 異常判定フラグ
                const isInvalidScale = (maxScale === null || maxScale === undefined || maxScale === -1);
                const isUnknownHypo = (!hypoName || hypoName === "" || hypoName === "調査中");

                // 発生時刻からの経過時間（分）を計算
                const eqTime = new Date(eqInfo.time);
                const diffMinutes = (now - eqTime) / (1000 * 60);

                // 【除外条件】: 震度または震源が不明で、かつ発生から10分以上経過しているもの
                return !((isInvalidScale || isUnknownHypo) && diffMinutes > 10);

            });

            // フィルタリング後のデータをグローバル変数に格納
            currentFullData = filteredData;
            if (currentFullData.length === 0) return;

            const latestId = currentFullData[0].id;
            let isNewQuake = false;

            // 2. 自動更新時の制御（過去ログ閲覧中の戻り防止）
            if (isAutoUpdate) {
                if (lastTopId !== null && latestId !== lastTopId) {
                    index = 0;
                    currentIndex = 0; // 新着があれば最新へ
                    isNewQuake = true;
                } else if (currentIndex !== 0) {
                    lastTopId = latestId;
                    updateInfoPanelHTML(currentFullData[currentIndex], currentIndex);
                    return; // 過去ログ閲覧中かつ新着なしなら描画スキップ
                } else {
                    index = 0;
                }
            } else {
                currentIndex = index;
            }

            lastTopId = latestId


            const targetData = currentFullData[index];
            const points = targetData.points || [];
            const eqInfo = targetData.earthquake || {};
            const hypo = eqInfo.hypocenter || {};

            // --- 1. 震度データの事前整理 ---
            const cityShindoMap = {};
            const shakingPrefs = new Set(); // 揺れた都道府県リスト

            points.forEach(point => {
                if (point.pref) shakingPrefs.add(point.pref);

                // 地点名・都道府県+地点名の両方を登録
                cityShindoMap[point.addr] = point.scale;
                if (point.pref) cityShindoMap[point.pref + point.addr] = point.scale;
            });

            // --- 2. 地図の塗り分け ---
            const placedCities = new Set();
            iconLayerGroup.clearLayers();

            geoJsonLayer.eachLayer(layer => {
                const p = layer.feature.properties;
                const pref = p.N03_001 || ""; // 石川県
                const region = p.N03_003 || ""; // 能登 など
                const city = p.N03_004 || ""; // 七尾市
                const ward = p.N03_005 || ""; // 区

                const fullName = pref + city + ward;
                const cityWard = city + ward;

                let scale = 0;

                // マッチングの優先順位（厳格な順に）
                // 1. フルネーム (石川県七尾市)
                // 2. 市区町村+区 (七尾市)
                // 3. 郡・支庁名 (能登)
                // 4. 都道府県名 (石川県)
                if (cityShindoMap[fullName]) {
                    scale = cityShindoMap[fullName];
                } else if (cityShindoMap[cityWard]) {
                    scale = cityShindoMap[cityWard];
                } else if (cityShindoMap[region]) {
                    scale = cityShindoMap[region];
                } else if (cityShindoMap[pref]) {
                    scale = cityShindoMap[pref];
                } else {
                    // 部分一致による最終チェック（addrの中に市区町村名が含まれるか）
                    for (let [addr, s] of Object.entries(cityShindoMap)) {
                        if (addr.includes(city) && (addr.includes(pref) || shakingPrefs.has(pref))) {
                            scale = s;
                            break;
                        }
                    }
                }

                // 最終防衛線：その都道府県全体に全く震度情報がない場合は、同名回避のため塗らない
                if (scale > 0 && !shakingPrefs.has(pref)) {
                    // ただし、APIの地点名自体に都道府県名が入っている場合は許可
                    const hasDirectPrefInfo = points.some(pt => pt.addr.includes(pref));
                    if (!hasDirectPrefInfo) scale = 0;
                }

                layer.setStyle({
                    fillColor: getShindoColor(scale),
                    fillOpacity: scale > 0 ? 0.6 : 0.05,
                    color: scale > 0 ? '#ffffff' : '#555',
                    weight: scale > 0 ? 1 : 0.3
                });

                // アイコン配置 (1市区町村1つ)
                if (scale > 0 && !placedCities.has(fullName)) {
                    const center = layer.getBounds().getCenter();
                    const icon = L.divIcon({
                        className: 'shindo-icon',
                        html: `<div style="background-color: ${getShindoColor(scale)};">${formatScale(scale)}</div>`,
                        iconSize: [22, 22],
                        iconAnchor: [11, 11],
                    });
                    L.marker(center, { icon: icon, interactive: false }).addTo(iconLayerGroup);
                    placedCities.add(fullName);
                }
            });

            // --- 3. 震源地マーカーと移動 ---
            if (epicenterMarker) map.removeLayer(epicenterMarker);
            const hasValidCoords = hypo.latitude && hypo.latitude !== -1;
            if (hasValidCoords) {
                epicenterMarker = L.marker([hypo.latitude, hypo.longitude], {
                    icon: L.divIcon({className: 'epicenter-mark', html: '×', iconSize: [40, 40], iconAnchor: [20, 20]})
                }).addTo(map);

                if (isInitialLoad || !isAutoUpdate || isNewQuake) {
                    map.flyTo([hypo.latitude, hypo.longitude], 8);
                    isInitialLoad = false;
                }
            }

            // --- 4. パネル表示 ---
            const maxScale = eqInfo.maxScale || (points.length > 0 ? Math.max(...points.map(p => p.scale)) : 0);
            const areaName = hypo.name || "（震源地調査中）";
            const magInfo = (hypo.magnitude && hypo.magnitude !== -1) ? `M ${hypo.magnitude}` : "調査中";
            const depthInfo = (hypo.depth && hypo.depth !== -1) ? `深さ ${hypo.depth}km` : "調査中";

            let historyHtml = '';
            currentFullData.slice(0, 12).forEach((eq, i) => {
                const hHypo = eq.earthquake.hypocenter || {};
                const hTimeFull = eq.earthquake.time || "";
                const hTime = hTimeFull.includes(' ') ? hTimeFull.split(' ')[1].substring(0, 5) : "速報";
                const hMaxScale = eq.earthquake.maxScale || 0;
                const activeClass = (i === currentIndex) ? 'active-eq' : '';

                historyHtml += `
                <div class="history-item ${activeClass}" onclick="updateEarthquake(${i}, false)">
                    <span class="h-scale" style="background:${getShindoColor(hMaxScale)}">${formatScale(hMaxScale)}</span>
                    <span class="h-time">${hTime}</span>
                    <span class="h-name">${hHypo.name || "調査中"}</span>
                    <span class="h-m">${hHypo.magnitude && hHypo.magnitude !== -1 ? 'M'+hHypo.magnitude : '-'}</span>
                </div>
            `;
            });

            document.getElementById('info-content').innerHTML = `
            <div class="shindo-box">
                <div class="shindo-value" style="color:${getShindoColor(maxScale)}">${formatScale(maxScale)}</div>
                <div>
                    <div class="area-name">${areaName}</div>
                    <div style="font-size: 0.8rem; color: #aaa;">${eqInfo.time || ""}</div>
                    <div style="font-size: 0.8rem; color: #aaa;">${magInfo} / ${depthInfo}</div>
                </div>
            </div>
            <div id="history-list">
                <div style="font-size: 0.75rem; color: #888; margin: 10px 0 5px; border-bottom: 1px solid #444;">
                    地震履歴（クリックで詳細表示）
                </div>
                ${historyHtml}
            </div>
        `;
        } catch (e) { console.error("Update error:", e); }
    }

    // 3. 市区町村GeoJSONの読み込み
    fetch('/data/japan.json')
        .then(res => res.json())
        .then(geoData => {
            geoJsonLayer = L.geoJson(geoData, {
                style: { fillColor: 'transparent', weight: 0.3, color: '#555', fillOpacity: 0.05 },
                onEachFeature: (feature, layer) => {
                    const cityName = (feature.properties.N03_004 || "") + (feature.properties.N03_005 || "");
                    layer.bindTooltip(cityName, { sticky: true });
                }
            }).addTo(map);

            updateEarthquake(); // 初回実行
        });

    // 定期更新（第2引数に true を渡す）
    setInterval(() => {
        // 現在の表示インデックスを判定して渡す（簡易的に currentViewId 等から逆算も可能ですが、
        // ここではグローバルに現在の index を保持するか、単に latestId のチェックのみを行います）
        updateEarthquake(0, true);
    }, 6000);

</script>
</body>
</html>
