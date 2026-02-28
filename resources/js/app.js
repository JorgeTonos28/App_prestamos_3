import '../css/app.css';
import './bootstrap';

import { createInertiaApp, usePage } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h, watch } from 'vue';
import { ZiggyVue } from 'ziggy-js';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const applyTheme = (theme) => {
    const normalizedTheme = theme === 'carolina' || theme === 'pinky' ? 'carolina' : 'default';

    document.documentElement.dataset.theme = normalizedTheme;

    if (document.body) {
        document.body.dataset.theme = normalizedTheme;
    }
};

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const Root = {
            setup() {
                const page = usePage();

                watch(
                    () => page.props?.settings?.color_theme,
                    (theme) => applyTheme(theme),
                    { immediate: true },
                );

                return () => h(App, props);
            },
        };

        return createApp(Root)
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
