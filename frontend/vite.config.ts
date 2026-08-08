import { defineConfig, loadEnv } from "vite";
import react from "@vitejs/plugin-react";
import svgr from "vite-plugin-svgr";

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, ".", "");
  const apiProxyTarget =
    env.VITE_INTERNAL_API_PROXY_TARGET || "http://laravel.test";
  const proxyOptions = () => ({
    target: apiProxyTarget,
    changeOrigin: true,
    configure: (proxy: {
      on: (
        event: "proxyRes",
        listener: (response: { headers: Record<string, unknown> }) => void,
      ) => void;
    }) => {
      proxy.on("proxyRes", (response) => {
        delete response.headers.host;
      });
    },
  });

  return {
    // Only explicitly public variables may be exposed through import.meta.env.
    // VITE_INTERNAL_API_PROXY_TARGET is consumed by this Node-side config only.
    envPrefix: "VITE_PUBLIC_",
    plugins: [
      react(),
      svgr({
        svgrOptions: {
          icon: true,
          // This will transform your SVG to a React component
          exportType: "named",
          namedExport: "ReactComponent",
        },
      }),
    ],
    server: {
      port: 5173,
      allowedHosts: ["frontend"],
      proxy: {
        "/api": proxyOptions(),
        "/sanctum": proxyOptions(),
      },
    },
  };
});
