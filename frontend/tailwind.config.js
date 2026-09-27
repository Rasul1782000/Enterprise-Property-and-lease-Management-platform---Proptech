/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./src/**/*.{html,ts}"],
  theme: {
    extend: {
      colors: {
        // Single brand accent for the whole portal. Everything else is neutral.
        primary: {
          DEFAULT: "#ea8a0c",
          50: "#fff8ed",
          100: "#ffefd4",
          200: "#fedcab",
          300: "#fdc179",
          400: "#fba338",
          500: "#f88b0c",
          600: "#ea8a0c",
          700: "#c26a06",
          800: "#9a510d",
          900: "#7c410f",
        },
        accent: "#c26a06",
      },
      fontFamily: {
        sans: ["Inter", "DM Sans", "Avenir Next", "system-ui", "sans-serif"],
      },
      boxShadow: {
        card: "0 1px 2px rgba(24, 24, 27, 0.04), 0 1px 3px rgba(24, 24, 27, 0.03)",
        raised: "0 6px 16px -6px rgba(24, 24, 27, 0.12), 0 2px 6px -2px rgba(24, 24, 27, 0.06)",
      },
    },
  },
  plugins: [],
};
