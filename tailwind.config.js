import defaultTheme from 'tailwindcss/defaultTheme';
import preset from './vendor/filament/support/tailwind.config.preset';

/** @type {import('tailwindcss').Config} */
export default {
    presets: [preset],
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
        './app/Filament/**/*.php',
        './resources/views/filament/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                'covre-green': {
                    DEFAULT: '#06402B',
                    50: '#e6f5ec',
                    100: '#c0e6d4',
                    200: '#99d7bb',
                    300: '#73c8a3',
                    400: '#4cb98a',
                    500: '#26aa72',
                    600: '#1d8a5b',
                    700: '#146a44',
                    800: '#0a5a3e',
                    900: '#06402B',
                    950: '#042819',
                },
            },
        },
    },
    plugins: [],
};
