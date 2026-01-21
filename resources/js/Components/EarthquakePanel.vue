<script setup>
import {computed} from 'vue';
import {formatScale, getShindoColor, formatFullTime, formatDepth} from "@/Utils/earthquakeUtils";

const props = defineProps({
    earthquakes: {type: Array, required: true},
    savedEarthquakes: {type: Array, default: () => []},
    isShowingSaved: {type: Boolean, default: false},
    currentIndex: {type: Number, required: true},
    lastUpdateDisplay: {type: String, default: ""},
    isEEW: {type: Boolean, default: false}
});

const emit = defineEmits(['update:currentIndex', 'save', 'delete', 'show-saved', 'show-latest']);

const displayList = computed(() => props.isShowingSaved ? props.savedEarthquakes : props.earthquakes);
const currentEq = computed(() => displayList.value[props.currentIndex] || null);

const selectHistory = (index) => {
    emit('update:currentIndex', index);
};

const handleSave = (index, event) => {
    event.stopPropagation();
    emit('save', index);
};

const handleDelete = (id, event) => {
    event.stopPropagation();
    emit('delete', id);
};
</script>

<template>
    <div v-if="currentEq" class="panel-container">
        <div id="side-panel">
            <div class="panel-header">
                <span>各地の震度情報</span>
                <div class="header-buttons">
                    <button @click="emit('show-latest')" :class="{active: !isShowingSaved}">最新</button>
                    <button @click="emit('show-saved')" :class="{active: isShowingSaved}">保存済</button>
                </div>
            </div>

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
                    <div class="history-label">{{ isShowingSaved ? '保存済み地震' : '地震履歴' }}</div>
                    <div v-for="(eq, i) in displayList.slice(0, 15)"
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
                        <button v-if="!isShowingSaved" class="action-button save" @click="handleSave(i, $event)">保存</button>
                        <button v-else class="action-button delete" @click="handleDelete(eq.id, $event)">削除</button>
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
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #2c3e50;
    padding: 10px 15px;
    font-weight: bold;
    font-size: 0.85rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.header-buttons button {
    background: transparent;
    border: 1px solid #5f7387;
    color: #ccc;
    padding: 4px 8px;
    margin-left: 5px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.75rem;
}

.header-buttons button.active {
    background: #00c3ff;
    border-color: #00c3ff;
    color: white;
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

.action-button {
    background: #4a5568;
    color: white;
    border: none;
    padding: 4px 8px;
    font-size: 0.75rem;
    border-radius: 4px;
    cursor: pointer;
}
.action-button.delete {
    background: #c53030;
}

.system-status {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 15px;
    background: rgba(0, 0, 0, 0.2);
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    font-size: 0.75rem;
}

.live-indicator {
    display: flex;
    align-items: center;
    gap: 6px;
}

.live-dot {
    width: 8px;
    height: 8px;
    background-color: #ff4444;
    border-radius: 50%;
    box-shadow: 0 0 8px #ff4444;
    animation: pulse 1.5s infinite;
}

.live-text {
    font-weight: bold;
    color: #ff4444;
    letter-spacing: 1px;
}

.update-time-text {
    color: #aaa;
}

@keyframes pulse {
    0% {
        opacity: 1;
        transform: scale(1);
    }
    50% {
        opacity: 0.5;
        transform: scale(1.2);
    }
    100% {
        opacity: 1;
        transform: scale(1);
    }
}
</style>
