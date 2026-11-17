/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],
    theme: {
        extend: {
            colors: {
                'f94-navy': '#0B1F3A',
                'f94-teal': '#0F766E',
                'f94-cyan': '#06B6D4',
                'f94-sidebar': '#0A1830',
            },
        },
    },
    plugins: [require('@tailwindcss/forms')],
};
