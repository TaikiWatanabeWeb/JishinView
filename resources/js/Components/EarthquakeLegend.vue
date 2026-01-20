<script setup>
import {formatScaleJP, getShindoColor} from "@/Utils/earthquakeUtils";
</script>

<template>
    <div id="legend">
        <div v-for="s in [70, 60, 55, 50, 45, 40, 30, 20, 10]" :key="s" class="legend-item">
            <span :style="{ background: getShindoColor(s) }"></span>震度 {{ formatScaleJP(s) }}
        </div>
    </div>
</template>

<style scoped>
#legend {
    position: absolute;
    bottom: 30px;
    right: 20px;
    background: rgba(255, 255, 255, 0.8); /* ライトテーマ凡例 */
    padding: 12px;
    border-radius: 8px;
    color: #333;
    z-index: 1000;
    font-size: 11px;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
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
    border: 1px solid rgba(0, 0, 0, 0.1);
}
</style>
