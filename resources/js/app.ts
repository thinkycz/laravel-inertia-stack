import './bootstrap';
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import type { DefineComponent } from 'vue';
import { createAppI18n, isSupportedLocale } from './i18n';
import type { SharedProps } from './types';

createInertiaApp({
    title: (title) =>
        title ? `${title} - Laravel Inertia Stack` : 'Laravel Inertia Stack',
    resolve: (name) => {
        const pages = import.meta.glob<DefineComponent>('./pages/**/*.vue');
        const page = pages[`./pages/${name}.vue`];

        if (page === undefined) {
            throw new Error(`Page not found: ${name}`);
        }

        return page();
    },
    setup({ el, App, props, plugin }) {
        const initial = props.initialPage.props as unknown as SharedProps;
        const userLocale = initial.auth?.user?.locale;
        const appLocale = initial.app?.locale;
        const requested = userLocale ?? appLocale ?? 'en';
        const locale = isSupportedLocale(requested) ? requested : 'en';

        const i18n = createAppI18n(locale);

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18n)
            .mount(el);
    },
    progress: {
        color: '#2563eb',
    },
});
