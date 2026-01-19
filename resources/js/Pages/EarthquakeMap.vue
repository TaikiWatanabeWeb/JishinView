<script setup>
import {Head} from '@inertiajs/vue3';
import EarthquakeView from "@/Components/EarthquakeView.vue";
</script>

<template>
    <Head title="Earthquake Monitor"/>
    <div class="monitor-root">
        <div class="view-container">
            <EarthquakeView :active="true"/>
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

.view-container {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
}
</style>
