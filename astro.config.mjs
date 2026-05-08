import { defineConfig } from 'astro/config';
import tailwind from '@astrojs/tailwind';

export default defineConfig({
  integrations: [tailwind()],
  i18n: {
    defaultLocale: 'es',
    locales: ['es', 'eu', 'en', 'fr'],
    routing: {
      prefixDefaultLocale: false,
    },
  },
  redirects: {
    '/fr/restaurant-vegetarien': '/fr/restaurant',
  },
});
