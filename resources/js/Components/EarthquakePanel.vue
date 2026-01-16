<script setup>
import {computed} from 'vue';

const props = defineProps({
    earthquakes: {type: Array, required: true},
    currentIndex: {type: Number, required: true},
    formatScale: {type: Function, required: true},
    getShindoColor: {type: Function, required: true},
    lastUpdateDisplay: {type: String, default: ""}
});

const emit = defineEmits(['update:currentIndex']);

// 現在選択されている地震データ
const currentEq = computed(() => props.earthquakes[props.currentIndex] || null);

// 時刻の整形: 「2026年01月16日 08:23ごろ」
const formatFullTime = (timeStr) => {
    if (!timeStr) return "";
    const [date, time] = timeStr.split(' ');
    const [y, m, d] = date.split('/');
    return `${y}年${m}月${d}日 ${time.substring(0, 5)}ごろ`;
};

// 深さの整形: 0や-1を「ごく浅い」にする
const formatDepth = (depth) => {
    if (depth === '0' || depth === 0 || depth === '-1' || !depth || depth === '-') {
        return 'ごく浅い';
    }
    return `${depth}km`;
};

const selectHistory = (index) => {
    emit('update:currentIndex', index);
};
</script>

<template>
    <div v-if="currentEq" class="panel-container">
        <div id="side-panel">
            <div class="panel-header">各地の震度情報</div>

            <div class="panel-body">
                <div class="shindo-main-card">
                    <div class="shindo-large-badge"
                         :style="{ background: getShindoColor(currentEq.earthquake.maxScale) }">
                        <div class="shindo-label">最大震度</div>
                        <div class="shindo-number">{{ formatScale(currentEq.earthquake.maxScale) }}</div>
                    </div>

                    <div class="area-info">
                        <div class="info-time-top">{{ formatFullTime(currentEq.earthquake.time) }}</div>
                        <div class="area-name">{{ currentEq.earthquake.hypocenter.name || "調査中" }}</div>

                        <div class="eq-spec-row">
                            <div class="spec-item">
                                <span class="spec-label">マグニチュード</span>
                                <span class="spec-value m-value">{{
                                        currentEq.earthquake.hypocenter.magnitude || '-'
                                    }}</span>
                            </div>
                            <div class="spec-item">
                                <span class="spec-label">深さ</span>
                                <span class="spec-value d-value">{{
                                        formatDepth(currentEq.earthquake.hypocenter.depth)
                                    }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="history-list">
                    <div class="history-label">地震履歴</div>
                    <div v-for="(eq, i) in earthquakes.slice(0, 15)"
                         :key="eq.id"
                         class="history-item"
                         :class="{ 'active-eq': currentIndex === i }"
                         @click="selectHistory(i)">
                    <span class="h-scale" :style="{ background: getShindoColor(eq.earthquake.maxScale) }">
                        {{ formatScale(eq.earthquake.maxScale) }}
                    </span>
                        <span class="h-time">{{ eq.earthquake.time.split(' ')[1].substring(0, 5) }}</span>
                        <span class="h-name">{{ eq.earthquake.hypocenter.name }}</span>
                        <span class="h-m">M{{ eq.earthquake.hypocenter.magnitude }}</span>
                    </div>
                </div>
            </div>

            <div class="system-status">
                <div class="live-indicator">
                    <div class="live-dot"></div>
                    <span class="live-text">LIVE</span>
                </div>
                <div class="update-time-text">{{ lastUpdateDisplay }}</div>
            </div>
        </div>
    </div>
</template>

<style scoped>
#side-panel {
    position: absolute;
    top: 20px;
    left: 20px;
    width: 340px;
    background: rgba(30, 35, 45, 0.95);
    color: white;
    z-index: 1000;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    padding: 0 !important;
    overflow: hidden;
}

.panel-header {
    background: #2c3e50;
    padding: 10px 15px;
    font-weight: bold;
    font-size: 0.85rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.panel-body {
    padding: 15px;
}

.shindo-main-card {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}

.shindo-large-badge {
    width: 80px;
    border-radius: 6px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #fff;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(0, 0, 0, 0.2);
}

.shindo-label {
    font-size: 0.9rem;
    font-weight: bold;
    margin-bottom: -5px;
    opacity: 0.9;
    text-shadow: 1px 1px 0 #000, -1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000;
}

.shindo-number {
    font-size: 2.8rem;
    font-weight: 900;
    text-shadow: 1px 1px 0 #000, -1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000;
}

.info-time-top {
    font-size: 1rem;
    color: #aaa;
    margin-bottom: 2px;
}

.area-name {
    font-size: 1.3rem;
    font-weight: bold;
    margin-bottom: 10px;
    line-height: 1.2;
}

.eq-spec-row {
    display: flex;
    gap: 10px;
}

.spec-item {
    background: rgba(255, 255, 255, 0.08);
    padding: 4px 12px;
    border-radius: 4px;
    display: flex;
    flex-direction: column;
    flex: 1;
    width: 6.5rem;
}

.spec-label {
    font-size: 0.7rem;
    color: #888;
    margin-bottom: 2px;
}

.spec-value {
    font-size: 1.1rem;
    font-weight: bold;
}

.m-value {
    color: #00c3ff;
}

.d-value {
    color: #ffa726;
}

#history-list {
    max-height: 380px;
    overflow-y: auto;
    margin-top: 10px;
}

.history-label {
    font-size: 0.7rem;
    color: #888;
    margin-bottom: 8px;
    border-bottom: 1px solid #444;
    padding-bottom: 4px;
}

.history-item {
    display: flex;
    align-items: center;
    padding: 8px 5px;
    cursor: pointer;
    gap: 10px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.active-eq {
    background: rgba(255, 255, 255, 0.15);
    border-left: 4px solid #00c3ff;
}

.h-scale {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    font-weight: bold;
    color: #fff;
    font-size: 0.8rem;
    flex-shrink: 0;
    text-shadow: 1px 1px 0 #000, -1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000;
    border: 1px solid rgba(0, 0, 0, 0.2);
}

.h-time, .h-m {
    font-size: 0.8rem;
}

.h-name {
    flex: 1;
    font-size: 0.9rem;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
