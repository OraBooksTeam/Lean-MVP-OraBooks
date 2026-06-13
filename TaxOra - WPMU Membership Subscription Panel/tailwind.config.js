module.exports = {
  content: [
    './**/*.php',
    './**/*.js',
    './assets/**/*.{js,css}'
  ],
  theme: {
    extend: {
      // Keep Orabooks colors but no gradients
      colors: {
        orabooks: {
          primary: '#43a62d',
          secondary: '#2d7a1d'
        }
      }
    }
  },
  plugins: [
    require('daisyui')
  ],
  daisyui: {
    styled: true,
    themes: [
      {
        orabooks: {
          "primary": "#43a62d",
          "secondary": "#2d7a1d",
          "accent": "#f59e0b",
          "neutral": "#ffffff",
          "base-100": "#ffffff",
          "info": "#3abff8",
          "success": "#2d7a1d",
          "warning": "#fbbd23",
          "error": "#f87272"
        }
      }
    ],
    base: true,
    utils: true,
    logs: false,
    rtl: false
  }
}



