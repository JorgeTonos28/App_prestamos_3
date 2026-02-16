<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    anchorSelector: {
        type: String,
        default: '#login-card',
    },
});

const page = usePage();
const viewport = ref({ width: 1280, height: 720 });

const paletteByColor = {
    rose: {
        fill: '#ec4899',
        stroke: '#db2777',
        body: '#831843',
        shadow: 'rgba(236, 72, 153, 0.35)',
    },
    violet: {
        fill: '#a78bfa',
        stroke: '#7c3aed',
        body: '#4c1d95',
        shadow: 'rgba(124, 58, 237, 0.35)',
    },
    sunset: {
        fill: '#fb7185',
        stroke: '#f97316',
        body: '#9f1239',
        shadow: 'rgba(249, 115, 22, 0.32)',
    },
};

const theme = computed(() => {
    const raw = String(page?.props?.settings?.color_theme ?? 'default').toLowerCase();
    return raw === 'carolina' || raw === 'pinky' ? 'carolina' : 'default';
});

const butterflyColor = computed(() => {
    const selected = String(page?.props?.settings?.butterfly_color ?? 'rose').toLowerCase();
    return paletteByColor[selected] ? selected : 'rose';
});

const palette = computed(() => paletteByColor[butterflyColor.value] ?? paletteByColor.rose);

const directions = ['startled-up-left', 'startled-up-right', 'startled-down-left', 'startled-down-right'];

const butterflies = ref(
    Array.from({ length: 3 }).map((_, index) => ({
        id: index + 1,
        x: 140 + (index * 100),
        y: 180 + (index * 80),
        visible: true,
        perched: false,
        isStartled: false,
        startledClass: directions[index % directions.length],
        keyframe: ['flutter-a', 'flutter-b', 'flutter-c'][index % 3],
        hiddenTimeout: null,
        travelTimeout: null,
        startledTimeout: null,
    })),
);

let motionInterval = null;
let resizeHandler = null;

const updateViewport = () => {
    viewport.value = {
        width: window.innerWidth,
        height: window.innerHeight,
    };
};

const randomPointInViewport = () => ({
    x: 30 + Math.random() * Math.max(180, viewport.value.width - 110),
    y: 30 + Math.random() * Math.max(180, viewport.value.height - 110),
});

const randomPointOnCardEdge = () => {
    const card = document.querySelector(props.anchorSelector);
    if (!card) {
        return randomPointInViewport();
    }

    const rect = card.getBoundingClientRect();
    const edge = Math.floor(Math.random() * 4);

    if (edge === 0) {
        return { x: rect.left + Math.random() * rect.width, y: rect.top - 8 };
    }

    if (edge === 1) {
        return { x: rect.right - 8, y: rect.top + Math.random() * rect.height };
    }

    if (edge === 2) {
        return { x: rect.left + Math.random() * rect.width, y: rect.bottom - 8 };
    }

    return { x: rect.left - 8, y: rect.top + Math.random() * rect.height };
};

const moveButterfly = (butterfly) => {
    if (!butterfly.visible || butterfly.isStartled) return;

    const perchChance = Math.random() < 0.38;
    const point = perchChance ? randomPointOnCardEdge() : randomPointInViewport();

    butterfly.x = point.x;
    butterfly.y = point.y;
    butterfly.perched = perchChance;

    if (butterfly.travelTimeout) {
        clearTimeout(butterfly.travelTimeout);
    }

    butterfly.travelTimeout = setTimeout(() => {
        butterfly.perched = false;
    }, 2200 + Math.floor(Math.random() * 1100));
};

const reappearButterfly = (butterfly) => {
    butterfly.visible = true;
    butterfly.perched = false;
    butterfly.isStartled = false;
    butterfly.startledClass = directions[Math.floor(Math.random() * directions.length)];

    const point = randomPointInViewport();
    butterfly.x = point.x;
    butterfly.y = point.y;
};

const dismissButterfly = (butterfly) => {
    if (!butterfly.visible || butterfly.isStartled) return;

    butterfly.isStartled = true;
    butterfly.perched = false;

    butterfly.startledClass = directions[Math.floor(Math.random() * directions.length)];

    if (butterfly.travelTimeout) {
        clearTimeout(butterfly.travelTimeout);
    }

    if (butterfly.startledTimeout) {
        clearTimeout(butterfly.startledTimeout);
    }

    butterfly.startledTimeout = setTimeout(() => {
        butterfly.visible = false;
        butterfly.isStartled = false;
    }, 1800);

    if (butterfly.hiddenTimeout) {
        clearTimeout(butterfly.hiddenTimeout);
    }

    butterfly.hiddenTimeout = setTimeout(() => {
        reappearButterfly(butterfly);
    }, 5000);
};

const startMotion = () => {
    stopMotion();

    butterflies.value.forEach((butterfly) => {
        reappearButterfly(butterfly);
    });

    motionInterval = setInterval(() => {
        butterflies.value.forEach((butterfly) => {
            moveButterfly(butterfly);
        });
    }, 2100);
};

const stopMotion = () => {
    if (motionInterval) {
        clearInterval(motionInterval);
        motionInterval = null;
    }

    butterflies.value.forEach((butterfly) => {
        if (butterfly.hiddenTimeout) clearTimeout(butterfly.hiddenTimeout);
        if (butterfly.travelTimeout) clearTimeout(butterfly.travelTimeout);
        if (butterfly.startledTimeout) clearTimeout(butterfly.startledTimeout);

        butterfly.hiddenTimeout = null;
        butterfly.travelTimeout = null;
        butterfly.startledTimeout = null;
    });
};

watch(theme, (value) => {
    if (value === 'carolina') {
        startMotion();
        return;
    }

    stopMotion();
}, { immediate: false });

onMounted(() => {
    updateViewport();
    resizeHandler = () => updateViewport();
    window.addEventListener('resize', resizeHandler);

    if (theme.value === 'carolina') {
        startMotion();
    }
});

onUnmounted(() => {
    stopMotion();

    if (resizeHandler) {
        window.removeEventListener('resize', resizeHandler);
    }
});
</script>

<template>
    <Teleport to="body">
        <div v-if="theme === 'carolina'" class="pointer-events-none fixed inset-0 z-[70]">
            <button
                v-for="butterfly in butterflies"
                :key="butterfly.id"
                type="button"
                class="login-butterfly pointer-events-auto"
                :class="[
                    butterfly.keyframe,
                    { perched: butterfly.perched, hidden: !butterfly.visible, startled: butterfly.isStartled },
                    butterfly.startledClass,
                ]"
                :style="{
                    transform: `translate(${butterfly.x}px, ${butterfly.y}px)`,
                    '--butterfly-fill': palette.fill,
                    '--butterfly-stroke': palette.stroke,
                    '--butterfly-body': palette.body,
                    '--butterfly-shadow': palette.shadow,
                }"
                @click="dismissButterfly(butterfly)"
            >
                <svg width="54" height="54" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <g :class="['wings', { 'wings-startled': butterfly.isStartled }]">
                        <path d="M12 12C12 12 8 4 4 6C0 8 2 14 6 14C4 16 2 20 6 20C10 20 12 15 12 15" fill="var(--butterfly-fill)" fill-opacity="0.62" stroke="var(--butterfly-stroke)" stroke-width="0.55" />
                        <path d="M12 12C12 12 16 4 20 6C24 8 22 14 18 14C20 16 22 20 18 20C14 20 12 15 12 15" fill="var(--butterfly-fill)" fill-opacity="0.62" stroke="var(--butterfly-stroke)" stroke-width="0.55" />
                        <path d="M12 8V18" stroke="var(--butterfly-body)" stroke-width="1" stroke-linecap="round" />
                        <path d="M12 8L10 5" stroke="var(--butterfly-body)" stroke-width="0.6" stroke-linecap="round" />
                        <path d="M12 8L14 5" stroke="var(--butterfly-body)" stroke-width="0.6" stroke-linecap="round" />
                    </g>
                </svg>
            </button>
        </div>
    </Teleport>
</template>

<style scoped>
.login-butterfly {
    position: absolute;
    border: 0;
    background: transparent;
    cursor: pointer;
    transition: transform 2.1s ease-in-out, opacity 0.5s ease;
    filter: drop-shadow(0 8px 12px var(--butterfly-shadow));
}

.login-butterfly.hidden {
    opacity: 0;
    pointer-events: none;
}

.login-butterfly.perched {
    transition-duration: 1.6s;
}

.wings {
    transform-origin: center;
    animation: flap 0.23s ease-in-out infinite alternate;
}

.wings-startled {
    animation-duration: 0.14s;
}

.flutter-a { animation: bob-a 2.6s ease-in-out infinite; }
.flutter-b { animation: bob-b 2.9s ease-in-out infinite; }
.flutter-c { animation: bob-c 2.4s ease-in-out infinite; }

.startled {
    animation-duration: 1.8s !important;
    animation-timing-function: cubic-bezier(0.2, 0.7, 0.2, 1) !important;
    animation-fill-mode: forwards !important;
}

.startled.startled-up-left { animation-name: dart-away-up-left !important; }
.startled.startled-up-right { animation-name: dart-away-up-right !important; }
.startled.startled-down-left { animation-name: dart-away-down-left !important; }
.startled.startled-down-right { animation-name: dart-away-down-right !important; }

@keyframes flap {
    0% { transform: scaleX(1); }
    100% { transform: scaleX(0.7); }
}

@keyframes bob-a {
    0%, 100% { rotate: 1deg; }
    50% { rotate: -5deg; }
}

@keyframes bob-b {
    0%, 100% { rotate: -2deg; }
    50% { rotate: 4deg; }
}

@keyframes bob-c {
    0%, 100% { rotate: 2deg; }
    50% { rotate: -4deg; }
}

@keyframes dart-away-up-right {
    0% { transform: translate(0, 0) scale(1); opacity: 1; }
    25% { transform: translate(20px, -20px) scale(1.08) rotate(8deg); opacity: 1; }
    60% { transform: translate(95px, -90px) scale(0.95) rotate(14deg); opacity: 0.86; }
    100% { transform: translate(170px, -180px) scale(0.8) rotate(20deg); opacity: 0; }
}

@keyframes dart-away-up-left {
    0% { transform: translate(0, 0) scale(1); opacity: 1; }
    25% { transform: translate(-20px, -20px) scale(1.08) rotate(-8deg); opacity: 1; }
    60% { transform: translate(-95px, -90px) scale(0.95) rotate(-14deg); opacity: 0.86; }
    100% { transform: translate(-170px, -180px) scale(0.8) rotate(-20deg); opacity: 0; }
}

@keyframes dart-away-down-right {
    0% { transform: translate(0, 0) scale(1); opacity: 1; }
    25% { transform: translate(20px, 10px) scale(1.08) rotate(9deg); opacity: 1; }
    60% { transform: translate(100px, 60px) scale(0.95) rotate(14deg); opacity: 0.86; }
    100% { transform: translate(180px, 120px) scale(0.8) rotate(20deg); opacity: 0; }
}

@keyframes dart-away-down-left {
    0% { transform: translate(0, 0) scale(1); opacity: 1; }
    25% { transform: translate(-20px, 10px) scale(1.08) rotate(-9deg); opacity: 1; }
    60% { transform: translate(-100px, 60px) scale(0.95) rotate(-14deg); opacity: 0.86; }
    100% { transform: translate(-180px, 120px) scale(0.8) rotate(-20deg); opacity: 0; }
}
</style>
