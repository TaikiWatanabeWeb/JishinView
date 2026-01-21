<script setup>
import {computed} from 'vue';

const props = defineProps({
    text: {type: String, default: ''},
    delay: {type: Number, default: 0},
    as: {type: String, default: 'span'}
});

const characters = computed(() => {
    if (!props.text) return [];
    return String(props.text).split('').map((char, index) => ({
        char: char === ' ' ? '\u00A0' : char,
        index
    }));
});
</script>

<template>
    <component :is="as" class="animated-text-wrapper">
    <span
        v-for="item in characters"
        :key="item.index"
        class="char"
        :style="{ animationDelay: `${props.delay + item.index * 30}ms` }"
    >{{ item.char }}</span>
    </component>
</template>

<style scoped>
.animated-text-wrapper {
    display: inline-block;
    white-space: nowrap;
}

.char {
    display: inline-block;
    opacity: 0;
    animation: fade-in-char 0.4s forwards cubic-bezier(0.25, 0.46, 0.45, 0.94);
    min-width: 0.2em; /* 空白などが潰れないように */
}

@keyframes fade-in-char {
    0% {
        opacity: 0;
        transform: translateY(5px) scale(0.9);
        filter: blur(2px);
    }
    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
        filter: blur(0);
    }
}
</style>
