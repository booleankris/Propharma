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
    },
  },
  plugins: [
    require('flowbite/plugin'), 
  ],
  
};