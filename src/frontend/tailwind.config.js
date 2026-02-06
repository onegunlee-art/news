/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        // 키 컬러 1: 오렌지 (메인 브랜드)
        primary: {
          50: '#fef3ef',
          100: '#fde4dc',
          200: '#fbc9b8',
          300: '#f8a58a',
          400: '#f36b42',
          500: '#f05123',
          600: '#e03a19',
          700: '#ba2a16',
          800: '#94261a',
          900: '#792319',
        },
        // 키 컬러 2: 퍼플
        keyPurple: {
          DEFAULT: '#590054',
          50: '#fdf2fc',
          100: '#fae6f9',
          200: '#f5ccf3',
          300: '#eda3e9',
          400: '#e26ddb',
          500: '#590054',
          600: '#4a0046',
          700: '#3d0039',
          800: '#2f002c',
          900: '#240022',
        },
        // 키 컬러 3: 그린
        keyGreen: {
          DEFAULT: '#125607',
          50: '#f2f9f1',
          100: '#e2f2e0',
          200: '#c6e5c3',
          300: '#9ad396',
          400: '#67b862',
          500: '#125607',
          600: '#0f4806',
          700: '#0d3a05',
          800: '#0a2d04',
          900: '#082403',
        },
        // 그레이 스케일
        gray: {
          50: '#fafafa',
          100: '#f5f5f5',
          200: '#e5e5e5',
          300: '#d4d4d4',
          400: '#a3a3a3',
          500: '#737373',
          600: '#525252',
          700: '#404040',
          800: '#262626',
          900: '#171717',
          950: '#0a0a0a',
        },
      },
      fontFamily: {
        sans: ['Petrov Sans', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'sans-serif'],
        serif: ['Petrov Sans', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'sans-serif'],
        display: ['Petrov Sans', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'sans-serif'],
      },
      fontSize: {
        'hero': ['3.5rem', { lineHeight: '1.1', letterSpacing: '-0.02em' }],
        'headline': ['2.25rem', { lineHeight: '1.2', letterSpacing: '-0.01em' }],
        'subheadline': ['1.5rem', { lineHeight: '1.3' }],
      },
      animation: {
        'fade-in': 'fadeIn 0.5s ease-out',
        'slide-up': 'slideUp 0.5s ease-out',
      },
      keyframes: {
        fadeIn: {
          '0%': { opacity: '0' },
          '100%': { opacity: '1' },
        },
        slideUp: {
          '0%': { opacity: '0', transform: 'translateY(20px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
      },
    },
  },
  plugins: [],
}
