<script setup>
defineProps({
    isLoading: Boolean
});
</script>

<template>
    <Transition name="fade">
        <div v-if="isLoading" class="loading-overlay">
            <div class="loading-content">
                <div class="spinner"></div>
                <p>データを読み込んでいます...</p>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.loading-content {
    text-align: center;
    color: #00c3ff;
}

.spinner {
    width: 50px;
    height: 50px;
    border: 3px solid rgba(0, 195, 255, 0.1);
    border-top-color: #00c3ff;
    border-radius: 50%;
    animation: spin 1s infinite;
    margin: 0 auto 15px;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

.fade-leave-active {
    transition: opacity 0.8s ease;
}

.fade-leave-to {
    opacity: 0;
}
</style>
