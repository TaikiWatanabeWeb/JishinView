<script setup>
defineProps({
    show: Boolean,
    title: String,
    message: String,
    isConfirm: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['close', 'confirm']);
</script>

<template>
    <Transition name="fade">
        <div v-if="show" class="dialog-overlay">
            <div class="dialog-content">
                <h3 v-if="title" class="dialog-title">{{ title }}</h3>
                <p class="dialog-message">{{ message }}</p>
                <div class="dialog-actions">
                    <button v-if="isConfirm" @click="$emit('close')" class="cancel-btn">キャンセル</button>
                    <button @click="$emit('confirm')" class="confirm-btn">OK</button>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.dialog-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 10000;
    backdrop-filter: blur(2px);
}

.dialog-content {
    background: #2c3e50;
    padding: 20px;
    border-radius: 8px;
    width: 90%;
    max-width: 400px;
    color: white;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.dialog-title {
    margin-top: 0;
    margin-bottom: 10px;
    font-size: 1.1rem;
    color: #00c3ff;
}

.dialog-message {
    margin-bottom: 20px;
    line-height: 1.5;
    color: #ddd;
}

.dialog-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

button {
    padding: 8px 16px;
    border-radius: 4px;
    border: none;
    cursor: pointer;
    font-weight: bold;
    transition: background 0.2s;
}

.confirm-btn {
    background: #00c3ff;
    color: white;
}

.confirm-btn:hover {
    background: #00a0d1;
}

.cancel-btn {
    background: transparent;
    border: 1px solid #5f7387;
    color: #ccc;
}

.cancel-btn:hover {
    background: rgba(255, 255, 255, 0.1);
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
