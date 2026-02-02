import { usePage } from '@inertiajs/vue3';
import { watchEffect } from 'vue';

export function useTheme() {
    const page = usePage();

    watchEffect(() => {
        const theme = page.props.settings?.system_theme || 'default';
        const root = document.documentElement;

        // List of all supported themes (excluding default which is base)
        const themes = ['rosa'];

        // Remove all supported theme classes
        themes.forEach(t => root.classList.remove(`theme-${t}`));

        // Add class if it's a non-default theme
        if (theme !== 'default' && themes.includes(theme)) {
            root.classList.add(`theme-${theme}`);
        }
    });
}
