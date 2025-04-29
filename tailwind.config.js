import defaultTheme from "tailwindcss/defaultTheme";
import typography from "@tailwindcss/typography";
import preset from './vendor/filament/support/tailwind.config.preset'
export default {
    presets: [preset],
    darkMode: "class",
    content: [
        "./app/Filament/**/*.php",
        "./resources/views/filament/**/*.blade.php",
        "./vendor/filament/**/*.blade.php",
        "./vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php",
        "./storage/framework/views/*.php",
        "./resources/views/**/*.blade.php",
    ],
    theme: {
        extend: {
            colors: {
                headerBg: "#232323",
                primary: {
                    50: "rgb(255, 247, 237)",
                    100: "rgb(255, 237, 213)",
                    200: "rgb(254, 215, 170)",
                    300: "rgb(253, 186, 116)",
                    400: "rgb(251, 146, 60)",
                    500: "rgb(249, 115, 22)",
                    600: "rgb(234, 88, 12)",
                    700: "rgb(194, 65, 12)",
                    800: "rgb(154, 52, 18)",
                    900: "rgb(124, 45, 18)",
                    950: "rgb(67, 20, 7)",
                },
            },
            fontFamily: {
                sans: ["Inter", ...defaultTheme.fontFamily.sans],
            },
        },
    },
    plugins: [
        typography,
        require("@tailwindcss/forms"),
    ],
};
