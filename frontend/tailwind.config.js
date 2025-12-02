/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        brand: {
          DEFAULT: '#4f46e5',
          50: '#eef2ff',
          100: '#e0e7ff',
          500: '#6366f1',
          600: '#4f46e5',
          700: '#4338ca',
        },
      },
      fontFamily: {
        sans: ['Inter', 'InterVariable', 'system-ui', 'sans-serif'],
      },
      boxShadow: {
        card: '0 20px 35px rgba(15, 23, 42, 0.08)',
      },
    },
  },
  plugins: [],
}

