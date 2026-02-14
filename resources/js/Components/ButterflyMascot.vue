<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const isVisible = ref(false);
const isStartled = ref(false);
const positionClass = ref('top-24 right-10');
const motionClass = ref('motion-drift');

const positions = [
    'top-24 right-10',
    'top-24 left-72',
    'top-1/2 right-8 -translate-y-1/2',
    'bottom-24 right-16',
    'bottom-20 left-72',
    'top-32 left-1/2 -translate-x-1/2',
];

const motions = ['motion-drift', 'motion-spiral', 'motion-sway', 'motion-glide'];

const colorThemes = {
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

const normalizedTheme = computed(() =>
    String(page?.props?.settings?.color_theme ?? 'default').toLowerCase(),
);

const butterflyEnabled = computed(() => {
    const raw = String(page?.props?.settings?.butterfly_enabled ?? '0').toLowerCase();
    return ['1', 'true', 'yes', 'on'].includes(raw);
});

const butterflyColor = computed(() => {
    const selected = String(page?.props?.settings?.butterfly_color ?? 'rose').toLowerCase();
    return colorThemes[selected] ? selected : 'rose';
});

const intervalSeconds = computed(() => {
    const raw = Number(page?.props?.settings?.butterfly_interval_seconds ?? 30);
    return Math.min(120, Math.max(10, Number.isNaN(raw) ? 30 : raw));
});

const mascotStyles = computed(() => {
    const palette = colorThemes[butterflyColor.value] ?? colorThemes.rose;

    return {
        '--butterfly-fill': palette.fill,
        '--butterfly-stroke': palette.stroke,
        '--butterfly-body': palette.body,
        '--butterfly-shadow': palette.shadow,
    };
});

let nextAppearanceTimeout = null;
let hideTimeout = null;
let bootTimeout = null;
let startledTimeout = null;

const clearTimers = () => {
    if (nextAppearanceTimeout) clearTimeout(nextAppearanceTimeout);
    if (hideTimeout) clearTimeout(hideTimeout);
    if (bootTimeout) clearTimeout(bootTimeout);
    if (startledTimeout) clearTimeout(startledTimeout);

    nextAppearanceTimeout = null;
    hideTimeout = null;
    bootTimeout = null;
    startledTimeout = null;
};

const scheduleNextAppearance = () => {
    if (!(normalizedTheme.value === 'pinky' && butterflyEnabled.value)) {
        return;
    }

    const baseDelay = intervalSeconds.value * 1000;
    const jitterMultiplier = 0.6 + Math.random() * 0.8;
    const delay = Math.floor(baseDelay * jitterMultiplier);

    nextAppearanceTimeout = setTimeout(() => {
        showButterfly();
    }, delay);
};

const showButterfly = () => {
    const randomPos = positions[Math.floor(Math.random() * positions.length)];
    const randomMotion = motions[Math.floor(Math.random() * motions.length)];

    positionClass.value = randomPos;
    motionClass.value = randomMotion;
    isStartled.value = false;
    isVisible.value = true;

    hideTimeout = setTimeout(() => {
        isVisible.value = false;
        scheduleNextAppearance();
    }, 4200);
};

const startleAndFlyAway = () => {
    if (!isVisible.value || isStartled.value) {
        return;
    }

    isStartled.value = true;
    if (hideTimeout) clearTimeout(hideTimeout);

    startledTimeout = setTimeout(() => {
        isVisible.value = false;
        isStartled.value = false;
        scheduleNextAppearance();
    }, 650);
};

const startCycle = () => {
    clearTimers();

    if (!(normalizedTheme.value === 'pinky' && butterflyEnabled.value)) {
        isVisible.value = false;
        return;
    }

    bootTimeout = setTimeout(() => {
        scheduleNextAppearance();
    }, 3500);
};

watch(
    () => [normalizedTheme.value, butterflyEnabled.value, butterflyColor.value, intervalSeconds.value],
    () => {
        startCycle();
    },
);

onMounted(() => {
    startCycle();
});

onUnmounted(() => {
    clearTimers();
});
</script>

<template>
    <Transition name="fly">
        <div
            v-if="isVisible"
            class="fixed z-50 pointer-events-auto cursor-pointer"
            :class="[positionClass, motionClass, { startled: isStartled }]"
            :style="mascotStyles"
            aria-hidden="true"
            @click="startleAndFlyAway"
        >
            <svg
                width="62"
                height="62"
                viewBox="0 0 24 24"
                fill="none"
                xmlns="http://www.w3.org/2000/svg"
                class="butterfly-float"
            >
                <g class="wings">
                    <path
                        d="M12 12C12 12 8 4 4 6C0 8 2 14 6 14C4 16 2 20 6 20C10 20 12 15 12 15"
                        fill="var(--butterfly-fill)"
                        fill-opacity="0.62"
                        stroke="var(--butterfly-stroke)"
                        stroke-width="0.55"
                    />
                    <path
                        d="M12 12C12 12 16 4 20 6C24 8 22 14 18 14C20 16 22 20 18 20C14 20 12 15 12 15"
                        fill="var(--butterfly-fill)"
                        fill-opacity="0.62"
                        stroke="var(--butterfly-stroke)"
                        stroke-width="0.55"
                    />
                    <path d="M12 8V18" :stroke="'var(--butterfly-body)'" stroke-width="1" stroke-linecap="round" />
                    <path d="M12 8L10 5" :stroke="'var(--butterfly-body)'" stroke-width="0.6" stroke-linecap="round" />
                    <path d="M12 8L14 5" :stroke="'var(--butterfly-body)'" stroke-width="0.6" stroke-linecap="round" />
                </g>
            </svg>
        </div>
    </Transition>
</template>

<style scoped>
.fly-enter-active,
.fly-leave-active {
  transition: opacity 0.8s ease, transform 0.8s cubic-bezier(0.22, 1, 0.36, 1);
}

.fly-enter-from,
.fly-leave-to {
  opacity: 0;
  transform: translateY(22px) scale(0.85);
}

.butterfly-float {
  animation:
    float 3.4s ease-in-out infinite,
    tilt 2.6s ease-in-out infinite;
  filter: drop-shadow(0 8px 12px var(--butterfly-shadow));
}

.wings {
  transform-origin: center;
  animation: flap 0.22s ease-in-out infinite alternate;
}

.motion-drift {
  animation: drift 4.1s ease-in-out;
}

.motion-spiral {
  animation: spiral 4.1s ease-in-out;
}

.motion-sway {
  animation: sway 4.1s ease-in-out;
}

.motion-glide {
  animation: glide 4.1s ease-in-out;
}

.startled {
  animation: dart-away 0.65s cubic-bezier(0.12, 0.85, 0.24, 1) forwards !important;
}

@keyframes flap {
  0% { transform: scaleX(1); }
  100% { transform: scaleX(0.72); }
}

@keyframes float {
  0%, 100% { transform: translateY(0px); }
  50% { transform: translateY(-10px); }
}

@keyframes tilt {
  0%, 100% { rotate: 4deg; }
  50% { rotate: -4deg; }
}

@keyframes drift {
  0% { transform: translateX(0) translateY(0) scale(0.94); }
  50% { transform: translateX(10px) translateY(-14px) scale(1); }
  100% { transform: translateX(2px) translateY(-2px) scale(0.96); }
}

@keyframes spiral {
  0% { transform: translate(0, 0) rotate(0deg) scale(0.95); }
  50% { transform: translate(10px, -10px) rotate(5deg) scale(1); }
  100% { transform: translate(-4px, -2px) rotate(-3deg) scale(0.96); }
}

@keyframes sway {
  0% { transform: translateX(0) translateY(0); }
  25% { transform: translateX(-8px) translateY(-6px); }
  50% { transform: translateX(8px) translateY(-12px); }
  75% { transform: translateX(-4px) translateY(-8px); }
  100% { transform: translateX(2px) translateY(-2px); }
}

@keyframes glide {
  0% { transform: translateX(0) translateY(0) scale(0.94); }
  35% { transform: translateX(14px) translateY(-8px) scale(1); }
  70% { transform: translateX(-6px) translateY(-14px) scale(0.98); }
  100% { transform: translateX(2px) translateY(-4px) scale(0.96); }
}

@keyframes dart-away {
  0% { transform: translate(0, 0) scale(1); opacity: 1; }
  20% { transform: translate(10px, -14px) scale(1.08) rotate(6deg); opacity: 1; }
  100% { transform: translate(110px, -190px) scale(0.72) rotate(18deg); opacity: 0; }
}
</style>
