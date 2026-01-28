/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#e6faff',
          100: '#b3f0ff',
          200: '#80e6ff',
          300: '#4ddbff',
          400: '#1ad1ff',
          500: '#00d9ff',
          600: '#00a8cc',
          700: '#007a99',
          800: '#004d66',
          900: '#002633',
        },
        dark: {
          50: '#e6e6ea',
          100: '#b3b3bd',
          200: '#80808f',
          300: '#4d4d62',
          400: '#1a1a35',
          500: '#0a0a0f',
          600: '#12121a',
          700: '#16213e',
          800: '#1a1a2e',
          900: '#0f0f14',
        },
        accent: {
          purple: '#667eea',
          pink: '#f093fb',
          orange: '#ff6b6b',
          green: '#00d26a',
          blue: '#4facfe',
        }
      },
      fontFamily: {
        sans: ['Noto Sans KR', '-apple-system', 'BlinkMacSystemFont', 'sans-serif'],
        display: ['Space Grotesk', 'Noto Sans KR', 'sans-serif'],
        mono: ['JetBrains Mono', 'Consolas', 'monospace'],
      },
      animation: {
        'fade-in': 'fadeIn 0.5s ease-out',
        'slide-up': 'slideUp 0.5s ease-out',
        'slide-down': 'slideDown 0.3s ease-out',
        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
        'spin-slow': 'spin 3s linear infinite',
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
        slideDown: {
          '0%': { opacity: '0', transform: 'translateY(-10px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
      },
      backgroundImage: {
        'gradient-radial': 'radial-gradient(var(--tw-gradient-stops))',
        'gradient-conic': 'conic-gradient(from 180deg at 50% 50%, var(--tw-gradient-stops))',
      },
    },
  },
  plugins: [],
}
