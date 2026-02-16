<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
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

const butterflies = ref(
    Array.from({ length: 3 }).map((_, index) => ({
        id: index + 1,
        x: 140 + (index * 100),
        y: 180 + (index * 80),
        visible: true,
        perched: false,
        keyframe: ['flutter-a', 'flutter-b', 'flutter-c'][index % 3],
        hiddenTimeout: null,
        travelTimeout: null,
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
    x: 40 + Math.random() * Math.max(160, viewport.value.width - 120),
    y: 40 + Math.random() * Math.max(160, viewport.value.height - 120),
});

const randomPointNearCard = () => {
    const card = document.querySelector(props.anchorSelector);
    if (!card) {
        return randomPointInViewport();
    }

    const rect = card.getBoundingClientRect();
    return {
        x: rect.left + (Math.random() * rect.width),
        y: rect.top + (Math.random() * rect.height),
    };
};

const moveButterfly = (butterfly) => {
    if (!butterfly.visible) return;

    const perchChance = Math.random() < 0.32;
    const point = perchChance ? randomPointNearCard() : randomPointInViewport();

    butterfly.x = point.x;
    butterfly.y = point.y;
    butterfly.perched = perchChance;

    if (butterfly.travelTimeout) {
        clearTimeout(butterfly.travelTimeout);
    }

    butterfly.travelTimeout = setTimeout(() => {
        butterfly.perched = false;
    }, 2800 + Math.floor(Math.random() * 1200));
};

const dismissButterfly = (butterfly) => {
    if (!butterfly.visible) return;

    butterfly.visible = false;
    butterfly.perched = false;

    if (butterfly.hiddenTimeout) {
        clearTimeout(butterfly.hiddenTimeout);
    }

    butterfly.hiddenTimeout = setTimeout(() => {
        butterfly.visible = true;
        const point = randomPointInViewport();
        butterfly.x = point.x;
        butterfly.y = point.y;
    }, 5000);
};

const startMotion = () => {
    stopMotion();

    butterflies.value.forEach((butterfly) => {
        const point = randomPointInViewport();
        butterfly.x = point.x;
        butterfly.y = point.y;
        butterfly.visible = true;
    });

    motionInterval = setInterval(() => {
        butterflies.value.forEach((butterfly) => {
            moveButterfly(butterfly);
        });
    }, 2400);
};

const stopMotion = () => {
    if (motionInterval) {
        clearInterval(motionInterval);
        motionInterval = null;
    }

    butterflies.value.forEach((butterfly) => {
        if (butterfly.hiddenTimeout) clearTimeout(butterfly.hiddenTimeout);
        if (butterfly.travelTimeout) clearTimeout(butterfly.travelTimeout);
        butterfly.hiddenTimeout = null;
        butterfly.travelTimeout = null;
    });
};

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
    <div v-if="theme === 'carolina'" class="pointer-events-none fixed inset-0 z-40">
        <button
            v-for="butterfly in butterflies"
            :key="butterfly.id"
            type="button"
            class="login-butterfly pointer-events-auto"
            :class="[butterfly.keyframe, { perched: butterfly.perched, hidden: !butterfly.visible }]"
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
                <g class="wings">
                    <path d="M12 12C12 12 8 4 4 6C0 8 2 14 6 14C4 16 2 20 6 20C10 20 12 15 12 15" fill="var(--butterfly-fill)" fill-opacity="0.62" stroke="var(--butterfly-stroke)" stroke-width="0.55" />
                    <path d="M12 12C12 12 16 4 20 6C24 8 22 14 18 14C20 16 22 20 18 20C14 20 12 15 12 15" fill="var(--butterfly-fill)" fill-opacity="0.62" stroke="var(--butterfly-stroke)" stroke-width="0.55" />
                    <path d="M12 8V18" stroke="var(--butterfly-body)" stroke-width="1" stroke-linecap="round" />
                    <path d="M12 8L10 5" stroke="var(--butterfly-body)" stroke-width="0.6" stroke-linecap="round" />
                    <path d="M12 8L14 5" stroke="var(--butterfly-body)" stroke-width="0.6" stroke-linecap="round" />
                </g>
            </svg>
        </button>
    </div>
</template>

<style scoped>
.login-butterfly {
    position: absolute;
    border: 0;
    background: transparent;
    cursor: pointer;
    transition: transform 2.1s ease-in-out, opacity 0.6s ease;
    filter: drop-shadow(0 8px 12px var(--butterfly-shadow));
}

.login-butterfly.hidden {
    opacity: 0;
    pointer-events: none;
}

.login-butterfly.perched {
    transition-duration: 1.7s;
    transform-origin: center;
}

.wings {
    transform-origin: center;
    animation: flap 0.23s ease-in-out infinite alternate;
}

.flutter-a { animation: bob-a 2.6s ease-in-out infinite; }
.flutter-b { animation: bob-b 2.9s ease-in-out infinite; }
.flutter-c { animation: bob-c 2.4s ease-in-out infinite; }

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
</style>
