/** @type {import('tailwindcss').Config} */

// BounceBox ramps. Coral carries primary actions, teal supports and confirms,
// sunshine yellow is reserved for rewards and celebration.
const coral = {
  50: "#FFF5F5",
  100: "#FFE4E4",
  200: "#FFCCCC",
  300: "#FFA8A8",
  400: "#FF8A8A",
  500: "#FF6B6B",
  600: "#E85D5D",
  700: "#D14A4A",
  800: "#A63A3A",
  900: "#7A2B2B",
};

const teal = {
  50: "#EDFBFA",
  100: "#D3F5F2",
  200: "#A8EBE6",
  300: "#7CDFD8",
  400: "#5ED3CA",
  500: "#4ECDC4",
  600: "#3DBEB5",
  700: "#2F9B94",
  800: "#277A75",
  900: "#215F5C",
};

const sunny = {
  50: "#FFFDF2",
  100: "#FFF9CC",
  200: "#FFF3A3",
  300: "#FFEC79",
  400: "#FFE66D",
  500: "#F5D44E",
  600: "#D9B62F",
  700: "#B08D1E",
  800: "#8A6C1C",
  900: "#6B531A",
};

module.exports = {
  content: ["./src/**/*.{html,ts}"],
  theme: {
    extend: {
      colors: {
        primary: coral,
        secondary: teal,
        tertiary: sunny,
        // Named aliases so templates can reach for the BounceBox colours directly.
        coral,
        teal,
        sunny,
        accent: coral[600],
      },
      fontFamily: {
        // Titan One carries game-like headlines, Poppins everything else.
        display: ["Titan One", "Poppins", "system-ui", "sans-serif"],
        sans: ["Poppins", "system-ui", "sans-serif"],
        mono: ["Roboto Mono", "ui-monospace", "monospace"],
      },
      borderRadius: {
        // Everything is very rounded: 16px inputs, 24px cards, pill controls.
        sm: "16px",
        md: "16px",
        lg: "24px",
        xl: "24px",
        pill: "9999px",
      },
      boxShadow: {
        sm: "0 2px 4px rgba(0, 0, 0, 0.08)",
        md: "0 4px 10px rgba(0, 0, 0, 0.12)",
        lg: "0 8px 20px rgba(0, 0, 0, 0.15)",
        coral: "0 4px 14px rgba(255, 107, 107, 0.35)",
        teal: "0 4px 14px rgba(78, 205, 196, 0.35)",
        sunny: "0 4px 14px rgba(255, 230, 109, 0.40)",
        // Historic aliases used across component stylesheets.
        card: "0 2px 4px rgba(0, 0, 0, 0.08)",
        raised: "0 4px 10px rgba(0, 0, 0, 0.12)",
      },
      spacing: {
        xs: "4px",
        sm: "8px",
        md: "16px",
        lg: "24px",
        xl: "32px",
        "2xl": "48px",
        "3xl": "64px",
      },
      fontSize: {
        // Nothing renders below 14px anywhere in the interface.
        xs: ["14px", { lineHeight: "1.4", fontWeight: "600" }],
        sm: ["16px", { lineHeight: "1.5" }],
        base: ["18px", { lineHeight: "1.5" }],
        lg: ["22px", { lineHeight: "1.3" }],
        xl: ["28px", { lineHeight: "1.25" }],
        "2xl": ["36px", { lineHeight: "1.2" }],
        "3xl": ["48px", { lineHeight: "1.2" }],
      },
      minHeight: {
        // Minimum 44px touch target for small fingers.
        touch: "44px",
      },
    },
  },
  plugins: [],
};