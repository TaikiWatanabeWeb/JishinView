<script setup>
import {ref} from 'vue';
import {Head} from '@inertiajs/vue3';
import EarthquakeView from "@/Components/EarthquakeView.vue";
import TrainView from "@/Components/TrainView.vue";

const viewMode = ref('earthquake'); // 'earthquake' か 'train' を保持

// モード切り替え関数
const toggleMode = (mode) => {
    viewMode.value = mode;
};
</script>

<template>
    <Head title="Earthquake Monitor"/>
    <div class="monitor-root">

        <div class="mode-switcher">
            <button
                :class="{ active: viewMode === 'earthquake' }"
                @click="toggleMode('earthquake')"
            >
                地震情報
            </button>
            <button
                :class="{ active: viewMode === 'train' }"
                @click="toggleMode('train')"
            >
                鉄道運行
            </button>
        </div>

        <div v-show="viewMode === 'earthquake'" class="view-container">
            <EarthquakeView :active="viewMode === 'earthquake'"/>
        </div>

        <div v-if="viewMode === 'train'" class="view-container">
            <TrainView :active="viewMode === 'train'"/>
        </div>
    </div>
</template>

<style scoped>
.monitor-root {
    position: relative;
    width: 100%;
    height: 100vh;
    background: #1a1c1e;
    overflow: hidden;
}

/* 切り替えボタンのスタイル */
.mode-switcher {
    position: absolute;
    top: 20px;
    right: 20px;
    z-index: 10000; /* 全ての要素より最前面に */
    display: flex;
    gap: 5px;
    background: rgba(255, 255, 255, 0.8);
    padding: 5px;
    border-radius: 50px;
    border: 1px solid rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(10px);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.mode-switcher button {
    padding: 8px 20px;
    border-radius: 40px;
    border: none;
    background: transparent;
    color: #666;
    font-size: 13px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
}

.mode-switcher button.active {
    background: #00c3ff;
    color: white;
    box-shadow: 0 2px 8px rgba(0, 195, 255, 0.4);
}

.view-container {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
}
</style>
