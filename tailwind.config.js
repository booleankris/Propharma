module.exports = {
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.{js,jsx,ts,tsx}',
    './resources/**/*.vue',
    './app/**/*.php',
    './node_modules/flowbite/**/*.js', 
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', 'Inter', 'system-ui', '-apple-system', 'sans-serif'],
        montserrat: ['Montserrat', 'sans-serif'],
        nunito: ['Nunito Sans', 'sans-serif'],
        quicksand: ['Quicksand'],
      },
      keyframes: {
        drawerSlideIn: {
          '0%': { transform: 'translateX(100%)' },
          '100%': { transform: 'translateX(0)' },
        },
        drawerSlideOut: {
          '0%': { transform: 'translateX(0)' },
          '100%': { transform: 'translateX(100%)' },
        },
        modalFadeIn: {
          '0%': { opacity: '0', transform: 'scale(0.96) translateY(4px)' },
          '100%': { opacity: '1', transform: 'scale(1) translateY(0)' },
        },
      },
      animation: {
        'drawer-in': 'drawerSlideIn 0.32s cubic-bezier(0.16, 1, 0.3, 1) forwards',
        'drawer-out': 'drawerSlideOut 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards',
        'modal-in': 'modalFadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards',
      },
    },
  },
  plugins: [
    require('flowbite/plugin'), 
  ],
};